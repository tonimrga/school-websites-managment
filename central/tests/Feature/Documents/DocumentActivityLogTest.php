<?php

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\DocumentCategories\Pages\ManageDocumentCategories;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Activity;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Creating, changing, deleting and restoring a document, and renaming a
| category, is logged with the user and school, and labelled in the log.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->category = DocumentCategory::withoutGlobalScopes()->where('school_id', $this->school->id)->orderBy('sort_order')->first();
});

it('logs the life cycle of a document with user and school', function () {
    $editor = User::factory()->editor($this->school)->create();
    $this->actingAs($editor);

    Livewire::test(CreateDocument::class)
        ->fillForm(['title' => 'Pravilnik', 'document_category_id' => $this->category->id, 'file' => documentUpload('pravilnik.pdf', 'pdf')])
        ->call('create')
        ->assertHasNoFormErrors();
    $document = Document::sole();

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm(['title' => 'Pravilnik o radu'])
        ->call('save');

    Livewire::test(ListDocuments::class)->callAction(TestAction::make(DeleteAction::class)->table($document));
    Livewire::test(ListDocuments::class)
        ->filterTable(TrashedFilter::class, true)
        ->callAction(TestAction::make(RestoreAction::class)->table($document));

    $activities = Activity::query()->where('subject_type', $document->getMorphClass())->orderBy('id')->get();

    expect($activities->pluck('event')->all())->toBe(['created', 'updated', 'deleted', 'restored'])
        ->and($activities->pluck('school_id')->unique()->all())->toBe([$this->school->id])
        ->and($activities->pluck('causer_id')->unique()->all())->toBe([$editor->id])
        ->and($activities[1]->properties['old']['title'])->toBe('Pravilnik')
        ->and($activities[1]->properties['attributes']['title'])->toBe('Pravilnik o radu');
});

it('logs renaming a category', function () {
    $admin = actingAsSuperAdmin($this->school);

    Livewire::test(ManageDocumentCategories::class)
        ->callAction(TestAction::make(EditAction::class)->table($this->category), data: ['name' => 'Natječaji i oglasi', 'slug' => 'natjecaji'])
        ->assertHasNoFormErrors();

    $activity = Activity::query()->latest('id')->firstOrFail();

    expect($activity->event)->toBe('updated')
        ->and($activity->subject_type)->toBe($this->category->getMorphClass())
        ->and($activity->school_id)->toBe($this->school->id)
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['attributes']['name'])->toBe('Natječaji i oglasi');
});

it('labels documents and categories in the activity log', function () {
    expect(ActivityResource::SUBJECT_LABELS)
        ->toHaveKey(Document::class, 'Dokument')
        ->toHaveKey(DocumentCategory::class, 'Kategorija dokumenata');
});
