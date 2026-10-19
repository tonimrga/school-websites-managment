<?php

use App\Filament\Resources\Schools\Pages\CreateSchool;
use App\Models\Activity;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Database\Seeders\DemoSchoolsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Every new school gets the default document categories, in order, and the
| demo seeder adds any that are missing to existing schools.
*/

function categoryNamesOf(School $school): array
{
    return DocumentCategory::withoutGlobalScope(SchoolScope::class)
        ->where('school_id', $school->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();
}

it('creates the default categories with every new school', function () {
    $school = School::factory()->create();

    expect(categoryNamesOf($school))->toBe(DocumentCategory::DEFAULTS);
});

it('creates them in the new school even when the super admin works on another one', function () {
    $other = School::factory()->create(['name' => 'AAA prvi dom']);
    actingAsSuperAdmin($other);

    Livewire::test(CreateSchool::class)
        ->fillForm([
            'name' => 'Novi učenički dom',
            'slug' => 'novi-ucenicki-dom',
            'site_url' => 'https://novi-dom.example',
            'theme' => 'green',
            'address' => 'Nova 1',
            'postal_code' => '10000',
            'city' => 'Zagreb',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $new = School::where('slug', 'novi-ucenicki-dom')->sole();

    expect(categoryNamesOf($new))->toBe(DocumentCategory::DEFAULTS)
        ->and(categoryNamesOf($other))->toBe(DocumentCategory::DEFAULTS);
});

it('does not log the default categories as user activity', function () {
    School::factory()->create();

    expect(Activity::where('subject_type', (new DocumentCategory)->getMorphClass())->count())->toBe(0);
});

it('adds missing defaults without touching existing categories', function () {
    $school = School::factory()->create();
    DocumentCategory::withoutGlobalScope(SchoolScope::class)
        ->where('school_id', $school->id)
        ->where('slug', 'upisi')
        ->delete();
    $renamed = DocumentCategory::withoutGlobalScope(SchoolScope::class)
        ->where('school_id', $school->id)
        ->where('slug', 'natjecaji')
        ->sole();
    $renamed->update(['name' => 'Natječaji za posao']);

    DocumentCategory::createDefaultsFor($school);
    DocumentCategory::createDefaultsFor($school);

    $names = categoryNamesOf($school);

    expect($names)->toHaveCount(count(DocumentCategory::DEFAULTS))
        ->and($names)->toContain('Natječaji za posao', 'Upisi')
        ->and(end($names))->toBe('Upisi');
});

it('gives the demo schools their default categories, idempotently', function () {
    Storage::fake('public');

    $this->seed(DemoSchoolsSeeder::class);
    $this->seed(DemoSchoolsSeeder::class);

    foreach (School::all() as $school) {
        expect(categoryNamesOf($school))->toBe(DocumentCategory::DEFAULTS);
    }
});
