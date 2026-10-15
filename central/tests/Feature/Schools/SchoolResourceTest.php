<?php

use App\Enums\SchoolTheme;
use App\Filament\Resources\Schools\Pages\CreateSchool;
use App\Filament\Resources\Schools\Pages\EditSchool;
use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Models\School;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function validSchoolData(array $overrides = []): array
{
    return [
        'name' => 'Učenički dom Ivana Gundulića',
        'slug' => 'os-ivana-gundulica',
        'site_url' => 'https://os-igundulica.hr',
        'theme' => SchoolTheme::Green->value,
        'address' => 'Ulica 1',
        'postal_code' => '10000',
        'city' => 'Zagreb',
        'phone' => '01 234 5678',
        'email' => 'ured@os-igundulica.hr',
        'social_links' => ['facebook' => 'https://facebook.com/osig', 'instagram' => null, 'youtube' => null],
        ...$overrides,
    ];
}

describe('super admin', function () {
    beforeEach(fn () => actingAsSuperAdmin());

    it('sees school and user management in the navigation', function () {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('/admin/schools')
            ->assertSee('/admin/users');
    });

    it('lists schools', function () {
        $schools = School::factory()->count(3)->create();

        $this->get('/admin/schools')->assertOk();

        Livewire::test(ListSchools::class)->assertCanSeeTableRecords($schools);
    });

    it('creates a school', function () {
        Livewire::test(CreateSchool::class)
            ->fillForm(validSchoolData())
            ->call('create')
            ->assertHasNoFormErrors();

        $school = School::sole();

        expect($school->slug)->toBe('os-ivana-gundulica')
            ->and($school->theme)->toBe(SchoolTheme::Green)
            ->and($school->is_active)->toBeTrue()
            ->and($school->social_links['facebook'])->toBe('https://facebook.com/osig');
    });

    it('generates the slug from the name', function () {
        Livewire::test(CreateSchool::class)
            ->fillForm(['name' => 'Učenički dom Ivana Gundulića'])
            ->assertSchemaStateSet(['slug' => 'ucenicki-dom-ivana-gundulica']);
    });

    it('requires a unique, URL-safe slug', function () {
        School::factory()->create(['slug' => 'os-ivana-gundulica']);

        Livewire::test(CreateSchool::class)
            ->fillForm(validSchoolData())
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        Livewire::test(CreateSchool::class)
            ->fillForm(validSchoolData(['slug' => 'OŠ Ivana!']))
            ->call('create')
            ->assertHasFormErrors(['slug' => 'regex']);
    });

    it('edits a school', function () {
        $school = School::factory()->create();

        $this->get("/admin/schools/{$school->id}/edit")->assertOk();

        Livewire::test(EditSchool::class, ['record' => $school->getRouteKey()])
            ->fillForm(['name' => 'Novi naziv', 'theme' => SchoolTheme::Red->value])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($school->fresh())
            ->name->toBe('Novi naziv')
            ->theme->toBe(SchoolTheme::Red);
    });

    it('uploads a logo for the edited school', function () {
        Storage::fake('public');
        $school = School::factory()->create();
        $other = School::factory()->create();

        Livewire::test(EditSchool::class, ['record' => $school->getRouteKey()])
            ->fillForm(['logo' => [UploadedFile::fake()->image('logo.png', 300, 300)]])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($school->fresh()->getFirstMedia('logo')?->name)->toBe('logo')
            ->and($other->fresh()->getMedia('logo'))->toHaveCount(0);
    });

    it('deactivates and reactivates a school', function () {
        $school = School::factory()->create();

        Livewire::test(ListSchools::class)
            ->callAction(TestAction::make('deactivate')->table($school));

        expect($school->fresh()->is_active)->toBeFalse();

        Livewire::test(ListSchools::class)
            ->assertActionHidden(TestAction::make('deactivate')->table($school))
            ->callAction(TestAction::make('activate')->table($school));

        expect($school->fresh()->is_active)->toBeTrue();
    });

    it('offers no way to delete a school', function () {
        $school = School::factory()->create();

        Livewire::test(EditSchool::class, ['record' => $school->getRouteKey()])
            ->assertActionDoesNotExist('delete');

        expect(auth()->user()->can('delete', $school))->toBeFalse();
    });
});

describe('editor', function () {
    beforeEach(function () {
        $this->school = School::factory()->create();
        $this->actingAs(User::factory()->editor($this->school)->create());
    });

    it('cannot open school management', function () {
        $this->get('/admin/schools')->assertForbidden();
        $this->get('/admin/schools/create')->assertForbidden();
        $this->get("/admin/schools/{$this->school->id}/edit")->assertForbidden();
    });

    it('does not see school management in the navigation', function () {
        $this->get('/admin')->assertOk()->assertDontSee('/admin/schools');
    });
});
