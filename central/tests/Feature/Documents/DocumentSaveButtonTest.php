<?php

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| The document save button stays gray and disabled until title, category and
| file are filled in, and the form says what is still missing.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->actingAs(User::factory()->editor($this->school)->create());
});

it('keeps the create button disabled until title, category and file are filled in', function () {
    Livewire::test(CreateDocument::class)
        ->assertActionDisabled('create')
        ->assertActionHasColor('create', 'gray')
        ->assertSee('Za spremanje nedostaje: naslov, kategorija, datoteka.')
        ->fillForm(['title' => 'Odluka', 'document_category_id' => DocumentCategory::query()->value('id')])
        ->assertActionDisabled('create')
        ->assertSee('Za spremanje nedostaje: datoteka.')
        ->fillForm(['file' => documentUpload('odluka.pdf', 'pdf')])
        ->assertActionEnabled('create')
        ->assertActionHasColor('create', 'primary')
        ->assertDontSee('Za spremanje nedostaje');
});

it('still rejects a forced create with fields missing', function () {
    Livewire::test(CreateDocument::class)
        ->fillForm(['title' => 'Odluka'])
        ->call('create')
        ->assertHasFormErrors(['document_category_id' => 'required', 'file' => 'required']);

    expect(Document::count())->toBe(0);
});

it('disables the save button while a required field is emptied', function () {
    $document = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->withFile());

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->assertActionEnabled('save')
        ->assertDontSee('Za spremanje nedostaje')
        ->fillForm(['title' => ''])
        ->assertActionDisabled('save')
        ->assertActionHasColor('save', 'gray')
        ->assertSee('Za spremanje nedostaje: naslov.');
});
