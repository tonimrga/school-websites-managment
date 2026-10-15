<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use Database\Factories\ArticleFactory;
use Database\Factories\DocumentFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Creating or saving an article or document returns to its list.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->actingAs(User::factory()->editor($this->school)->create());
});

it('returns to the articles list after creating an article', function (string $method) {
    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title' => 'Dan otvorenih vrata',
            'body' => '<p>Pozivamo roditelje.</p>',
            'cover' => UploadedFile::fake()->image('naslovna.jpg', 800, 600),
        ])
        ->call($method)
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/articles');
})->with(['create', 'createAndPublish']);

it('returns to the articles list after saving an article', function () {
    $article = createArticle($this->school);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['title' => 'Novi naslov'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/articles');
});

it('stays on the article when publishing from its form', function () {
    $article = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->withCover());

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->callAction('publish')
        ->assertNoRedirect();

    expect($article->fresh()->isPublished())->toBeTrue();
});

it('returns to the documents list after creating a document', function () {
    Livewire::test(CreateDocument::class)
        ->fillForm([
            'title' => 'Odluka o upisu',
            'document_category_id' => DocumentCategory::query()->value('id'),
            'file' => documentUpload('odluka.pdf', 'pdf'),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/documents');
});

it('returns to the documents list after saving a document', function () {
    $document = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->withFile());

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm(['title' => 'Novi naslov'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/documents');
});
