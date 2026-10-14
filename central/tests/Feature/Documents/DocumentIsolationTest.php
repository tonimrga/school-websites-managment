<?php

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ReorderDocuments;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| An editor of school A must not be able to reach any document of school B,
| neither through the UI nor by changing IDs in URLs or Livewire requests.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();

    $this->editorA = User::factory()->editor($this->schoolA)->create();

    $this->documentA = createDocument($this->schoolA, ['title' => 'Dokument škole A']);
    $this->documentB = createDocument($this->schoolB, ['title' => 'Dokument škole B']);
    $this->otherDocumentB = createDocument($this->schoolB, ['title' => 'Drugi dokument škole B']);
    $this->trashedB = createDocument($this->schoolB, ['title' => 'Obrisani dokument škole B']);
    $this->trashedB->delete();

    $this->actingAs($this->editorA);
});

function documentCategoryOf(School $school): DocumentCategory
{
    return DocumentCategory::withoutGlobalScopes()->where('school_id', $school->id)->orderBy('sort_order')->firstOrFail();
}

it('does not list documents of another school', function () {
    $this->get('/admin/documents')
        ->assertOk()
        ->assertSee('Dokument škole A')
        ->assertDontSee('Dokument škole B');

    Livewire::test(ListDocuments::class)
        ->assertCanSeeTableRecords([$this->documentA])
        ->assertCanNotSeeTableRecords([$this->documentB, $this->otherDocumentB]);
});

it('does not list another school\'s trashed documents', function () {
    $trashedA = createDocument($this->schoolA, ['title' => 'Obrisani dokument škole A']);
    $trashedA->delete();

    Livewire::test(ListDocuments::class)
        ->filterTable(TrashedFilter::class, true)
        ->assertCanSeeTableRecords([$trashedA])
        ->assertCanNotSeeTableRecords([$this->trashedB]);
});

it('does not find another school\'s documents through table search', function () {
    Livewire::test(ListDocuments::class)
        ->searchTable('škole B')
        ->assertCanNotSeeTableRecords([$this->documentB, $this->otherDocumentB, $this->trashedB]);
});

it('does not offer another school\'s categories in the category filter', function () {
    Livewire::test(ListDocuments::class)
        ->filterTable('document_category_id', documentCategoryOf($this->schoolB)->id)
        ->assertCanNotSeeTableRecords([$this->documentB, $this->otherDocumentB]);
});

it('returns 404 when opening another school\'s document by URL', function () {
    $this->get("/admin/documents/{$this->documentA->id}/edit")->assertOk();
    $this->get("/admin/documents/{$this->documentB->id}/edit")->assertNotFound();
    $this->get("/admin/documents/{$this->trashedB->id}/edit")->assertNotFound();
});

it('cannot mount the edit page for another school\'s document', function () {
    Livewire::test(EditDocument::class, ['record' => $this->documentB->getRouteKey()])->assertNotFound();
});

it('cannot delete another school\'s document from the table', function () {
    expect(fn () => Livewire::test(ListDocuments::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($this->documentB))
    )->toThrow(ActionNotResolvableException::class, 'no longer exists');

    expect(Document::withoutGlobalScopes()->find($this->documentB->id)->trashed())->toBeFalse();
});

it('cannot bulk delete another school\'s documents', function () {
    Livewire::test(ListDocuments::class)
        ->selectTableRecords([$this->documentA->id, $this->documentB->id])
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    expect(Document::withoutGlobalScopes()->find($this->documentA->id)->trashed())->toBeTrue()
        ->and(Document::withoutGlobalScopes()->find($this->documentB->id)->trashed())->toBeFalse();
});

it('cannot restore another school\'s trashed document', function () {
    expect(fn () => Livewire::test(ListDocuments::class)
        ->filterTable(TrashedFilter::class, true)
        ->callAction(TestAction::make(RestoreAction::class)->table($this->trashedB))
    )->toThrow(ActionNotResolvableException::class, 'no longer exists');

    expect(Document::withoutGlobalScopes()->find($this->trashedB->id)->trashed())->toBeTrue();
});

it('cannot bulk restore another school\'s trashed documents', function () {
    $trashedA = createDocument($this->schoolA);
    $trashedA->delete();

    Livewire::test(ListDocuments::class)
        ->filterTable(TrashedFilter::class, true)
        ->selectTableRecords([$trashedA->id, $this->trashedB->id])
        ->callAction(TestAction::make(RestoreBulkAction::class)->table()->bulk());

    expect(Document::withoutGlobalScopes()->find($trashedA->id)->trashed())->toBeFalse()
        ->and(Document::withoutGlobalScopes()->find($this->trashedB->id)->trashed())->toBeTrue();
});

it('shows only its own school\'s categories and documents on the reorder page', function () {
    $this->get('/admin/documents/reorder')
        ->assertOk()
        ->assertSee('Dokument škole A')
        ->assertDontSee('Dokument škole B');

    Livewire::test(ReorderDocuments::class)
        ->assertSee(documentCategoryOf($this->schoolA)->name)
        ->assertDontSee('Drugi dokument škole B');
});

it('cannot reorder another school\'s documents', function () {
    $sortOrders = fn () => Document::withoutGlobalScopes()->where('school_id', $this->schoolB->id)->pluck('sort_order', 'id')->all();
    $before = $sortOrders();

    // Through B's category: not found in A's scope.
    Livewire::test(ReorderDocuments::class)
        ->call('reorderCategory', documentCategoryOf($this->schoolB)->id, [$this->otherDocumentB->id, $this->documentB->id])
        ->assertNotFound();

    // B's documents injected into A's own category are ignored.
    Livewire::test(ReorderDocuments::class)
        ->call('reorderCategory', documentCategoryOf($this->schoolA)->id, [$this->otherDocumentB->id, $this->documentB->id, $this->documentA->id]);

    expect($sortOrders())->toBe($before)
        ->and($this->documentA->fresh()->sort_order)->toBe(1);
});

it('reorders its own documents within a category only', function () {
    $secondA = createDocument($this->schoolA, ['title' => 'Drugi dokument škole A']);
    $thirdA = createDocument($this->schoolA, ['title' => 'Treći dokument škole A']);
    $elsewhere = createDocument($this->schoolA, [
        'title' => 'Dokument druge kategorije',
        'document_category_id' => DocumentCategory::where('slug', 'upisi')->value('id'),
    ]);

    Livewire::test(ReorderDocuments::class)
        ->call('reorderCategory', documentCategoryOf($this->schoolA)->id, [$thirdA->id, $elsewhere->id, $this->documentA->id]);

    // Listed documents first, the unlisted one keeps its place after them.
    expect($thirdA->fresh()->sort_order)->toBe(1)
        ->and($this->documentA->fresh()->sort_order)->toBe(2)
        ->and($secondA->fresh()->sort_order)->toBe(3)
        ->and($elsewhere->fresh()->sort_order)->toBe(1)
        ->and($elsewhere->fresh()->document_category_id)->toBe(DocumentCategory::where('slug', 'upisi')->value('id'));
});

it('does not let a user without a school open the reorder page', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create(['is_active' => false]));

    Livewire::test(ReorderDocuments::class)->assertForbidden();
});

it('stores new documents in the editor\'s school even when another school_id is injected', function () {
    Livewire::test(CreateDocument::class)
        ->set('data.school_id', $this->schoolB->id)
        ->fillForm([
            'title' => 'Podmetnuti dokument',
            'document_category_id' => documentCategoryOf($this->schoolA)->id,
            'file' => documentUpload('podmetnuti.pdf', 'pdf'),
            'school_id' => $this->schoolB->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $document = Document::withoutGlobalScopes()->where('title', 'Podmetnuti dokument')->sole();

    expect($document->school_id)->toBe($this->schoolA->id);
});

it('rejects another school\'s category on create and on edit', function () {
    $categoryB = documentCategoryOf($this->schoolB);

    Livewire::test(CreateDocument::class)
        ->fillForm([
            'title' => 'Dokument u tuđoj kategoriji',
            'document_category_id' => $categoryB->id,
            'file' => documentUpload('tudji.pdf', 'pdf'),
        ])
        ->call('create')
        ->assertHasFormErrors(['document_category_id']);

    Livewire::test(EditDocument::class, ['record' => $this->documentA->getRouteKey()])
        ->fillForm(['document_category_id' => $categoryB->id])
        ->call('save')
        ->assertHasFormErrors(['document_category_id']);

    expect(Document::withoutGlobalScopes()->where('title', 'Dokument u tuđoj kategoriji')->exists())->toBeFalse()
        ->and($this->documentA->fresh()->document_category_id)->toBe(documentCategoryOf($this->schoolA)->id)
        ->and($categoryB->documents()->withoutGlobalScope(SchoolScope::class)->count())->toBe(2);
});

it('refuses at the model level to put a document into another school\'s category', function () {
    $this->documentA->document_category_id = documentCategoryOf($this->schoolB)->id;

    expect(fn () => $this->documentA->save())->toThrow(LogicException::class);
});

it('denies policy checks on another school\'s document', function () {
    $documentB = Document::withoutGlobalScopes()->find($this->documentB->id);

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($this->editorA->can($ability, $documentB))->toBeFalse("{$ability} should be denied")
            ->and($this->editorA->can($ability, $this->documentA))->toBeTrue("{$ability} should be allowed");
    }
});

it('isolates the super admin to the school picked in the switcher', function () {
    actingAsSuperAdmin($this->schoolB);

    Livewire::test(ListDocuments::class)
        ->assertCanSeeTableRecords([$this->documentB])
        ->assertCanNotSeeTableRecords([$this->documentA]);

    $this->get("/admin/documents/{$this->documentA->id}/edit")->assertNotFound();
    $this->get("/admin/documents/{$this->documentB->id}/edit")->assertOk();
});
