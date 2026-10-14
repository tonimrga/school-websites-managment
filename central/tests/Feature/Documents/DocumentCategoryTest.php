<?php

use App\Exceptions\CategoryHasDocuments;
use App\Filament\Resources\DocumentCategories\Pages\ManageDocumentCategories;
use App\Filament\Resources\DocumentCategories\Pages\ReorderDocumentCategories;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Editors can only see and reorder their own school's categories. Only the
| super admin creates, renames and deletes them, and a category that still
| has documents cannot be deleted. Isolation between schools holds for both.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
    $this->editorA = User::factory()->editor($this->schoolA)->create();

    $this->categoryA = createDocumentCategory($this->schoolA, ['name' => 'Kategorija škole A', 'slug' => 'kategorija-a']);
    $this->categoryB = createDocumentCategory($this->schoolB, ['name' => 'Kategorija škole B', 'slug' => 'kategorija-b']);
});

function categoriesOf(School $school): array
{
    return DocumentCategory::withoutGlobalScope(SchoolScope::class)
        ->where('school_id', $school->id)
        ->orderBy('sort_order')
        ->pluck('id')
        ->all();
}

describe('editor', function () {
    beforeEach(fn () => $this->actingAs($this->editorA));

    it('sees only its own school\'s categories', function () {
        $this->get('/admin/document-categories')
            ->assertOk()
            ->assertSee('Kategorija škole A')
            ->assertDontSee('Kategorija škole B');

        Livewire::test(ManageDocumentCategories::class)
            ->assertCanSeeTableRecords([$this->categoryA])
            ->assertCanNotSeeTableRecords([$this->categoryB]);
    });

    it('sees no create, rename or delete buttons', function () {
        Livewire::test(ManageDocumentCategories::class)
            ->assertActionHidden(CreateAction::class)
            ->assertActionHidden(TestAction::make(EditAction::class)->table($this->categoryA))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($this->categoryA))
            ->assertDontSee('Nova kategorija')
            ->assertDontSee('Preimenuj');
    });

    it('cannot create, rename or delete categories with crafted Livewire calls', function () {
        $count = DocumentCategory::count();
        $record = ['table' => true, 'recordKey' => (string) $this->categoryA->id];

        Livewire::test(ManageDocumentCategories::class)
            ->call('mountAction', 'create')
            ->set('mountedActions.0.data', ['name' => 'Podmetnuta', 'slug' => 'podmetnuta'])
            ->call('callMountedAction')
            ->call('mountAction', 'edit', [], $record)
            ->set('mountedActions.0.data', ['name' => 'Preimenovana', 'slug' => 'preimenovana'])
            ->call('callMountedAction')
            ->call('mountAction', 'delete', [], $record)
            ->call('callMountedAction');

        expect(DocumentCategory::count())->toBe($count)
            ->and($this->categoryA->fresh()->name)->toBe('Kategorija škole A');
    });

    it('is denied create, rename and delete by the policy', function () {
        expect($this->editorA->can('create', DocumentCategory::class))->toBeFalse()
            ->and($this->editorA->can('update', $this->categoryA))->toBeFalse()
            ->and($this->editorA->can('delete', $this->categoryA))->toBeFalse()
            ->and($this->editorA->can('deleteAny', DocumentCategory::class))->toBeFalse()
            ->and($this->editorA->can('viewAny', DocumentCategory::class))->toBeTrue()
            ->and($this->editorA->can('reorder', DocumentCategory::class))->toBeTrue();
    });

    it('sees the reorder button and opens the reorder page', function () {
        Livewire::test(ManageDocumentCategories::class)
            ->assertActionVisible('reorder');

        $this->get('/admin/document-categories/reorder')
            ->assertOk()
            ->assertSee('Kategorija škole A')
            ->assertDontSee('Kategorija škole B');
    });

    it('can reorder its own categories', function () {
        $reversed = array_reverse(categoriesOf($this->schoolA));

        Livewire::test(ReorderDocumentCategories::class)
            ->call('reorder', array_map('strval', $reversed));

        expect(categoriesOf($this->schoolA))->toBe($reversed);
    });

    it('keeps unlisted categories after the listed ones', function () {
        $current = categoriesOf($this->schoolA);

        Livewire::test(ReorderDocumentCategories::class)
            ->call('reorder', [$this->categoryA->id]);

        expect(categoriesOf($this->schoolA))
            ->toBe([$this->categoryA->id, ...array_values(array_diff($current, [$this->categoryA->id]))]);
    });

    it('cannot reorder another school\'s categories', function () {
        $before = categoriesOf($this->schoolB);

        Livewire::test(ReorderDocumentCategories::class)
            ->call('reorder', array_map('strval', [...array_reverse($before), $this->categoryA->id]));

        expect(categoriesOf($this->schoolB))->toBe($before);
    });

    it('cannot open the reorder page without a school', function () {
        $this->actingAs(User::factory()->editor($this->schoolA)->create(['is_active' => false]));

        Livewire::test(ReorderDocumentCategories::class)->assertForbidden();
    });
});

describe('super admin', function () {
    beforeEach(fn () => actingAsSuperAdmin($this->schoolA));

    it('creates, renames and deletes categories', function () {
        Livewire::test(ManageDocumentCategories::class)
            ->assertActionVisible(CreateAction::class)
            ->callAction(CreateAction::class, data: ['name' => 'Odluke upravnog vijeća', 'slug' => 'odluke-upravnog-vijeca'])
            ->assertHasNoFormErrors();

        $category = DocumentCategory::where('slug', 'odluke-upravnog-vijeca')->sole();

        expect($category->school_id)->toBe($this->schoolA->id)
            ->and($category->sort_order)->toBe(DocumentCategory::max('sort_order'));

        Livewire::test(ManageDocumentCategories::class)
            ->callAction(TestAction::make(EditAction::class)->table($category), data: ['name' => 'Odluke', 'slug' => 'odluke'])
            ->assertHasNoFormErrors();

        expect($category->fresh()->name)->toBe('Odluke');

        Livewire::test(ManageDocumentCategories::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($category));

        expect(DocumentCategory::find($category->id))->toBeNull();
    });

    it('is allowed to manage the selected school\'s categories only', function () {
        $admin = auth()->user();
        $categoryB = DocumentCategory::withoutGlobalScopes()->find($this->categoryB->id);

        foreach (['view', 'update', 'delete'] as $ability) {
            expect($admin->can($ability, $this->categoryA))->toBeTrue("{$ability} should be allowed")
                ->and($admin->can($ability, $categoryB))->toBeFalse("{$ability} should be denied");
        }

        expect($admin->can('create', DocumentCategory::class))->toBeTrue()
            ->and($admin->can('reorder', DocumentCategory::class))->toBeTrue();
    });

    it('stores new categories in the selected school even when another school_id is injected', function () {
        Livewire::test(ManageDocumentCategories::class)
            ->callAction(CreateAction::class, data: ['name' => 'Podmetnuta', 'slug' => 'podmetnuta', 'school_id' => $this->schoolB->id])
            ->assertHasNoFormErrors();

        expect(DocumentCategory::withoutGlobalScopes()->where('slug', 'podmetnuta')->sole()->school_id)->toBe($this->schoolA->id);
    });

    it('requires a unique slug within the school only', function () {
        Livewire::test(ManageDocumentCategories::class)
            ->callAction(CreateAction::class, data: ['name' => 'Isto', 'slug' => 'kategorija-a'])
            ->assertHasFormErrors(['slug' => 'unique']);

        // The slug of another school's category is free.
        Livewire::test(ManageDocumentCategories::class)
            ->callAction(CreateAction::class, data: ['name' => 'Isto', 'slug' => 'kategorija-b'])
            ->assertHasNoFormErrors();
    });

    it('cannot rename or delete another school\'s category', function (string $action) {
        expect(fn () => Livewire::test(ManageDocumentCategories::class)
            ->callAction(TestAction::make($action)->table($this->categoryB), data: ['name' => 'Preimenovana'])
        )->toThrow(ActionNotResolvableException::class);

        expect(DocumentCategory::withoutGlobalScopes()->find($this->categoryB->id)->name)->toBe('Kategorija škole B');
    })->with([EditAction::class, DeleteAction::class]);

    it('cannot delete a category that still has documents', function () {
        $document = createDocument($this->schoolA, ['document_category_id' => $this->categoryA->id]);

        Livewire::test(ManageDocumentCategories::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($this->categoryA))
            ->assertNotified('Kategorija nije obrisana.');

        expect(DocumentCategory::find($this->categoryA->id))->not->toBeNull()
            ->and(Document::find($document->id))->not->toBeNull();
    });

    it('refuses to delete a category with documents at the model level', function () {
        createDocument($this->schoolA, ['document_category_id' => $this->categoryA->id]);

        expect(fn () => $this->categoryA->delete())->toThrow(CategoryHasDocuments::class);

        expect(DocumentCategory::find($this->categoryA->id))->not->toBeNull();
    });

    it('permanently deletes trashed documents and their files together with the category', function () {
        $trashed = createDocument($this->schoolA, ['document_category_id' => $this->categoryA->id], fn (DocumentFactory $f) => $f->withFile());
        $path = $trashed->file()->getPathRelativeToRoot();
        $trashed->delete();

        Livewire::test(ManageDocumentCategories::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($this->categoryA));

        expect(DocumentCategory::find($this->categoryA->id))->toBeNull()
            ->and(Document::withTrashed()->find($trashed->id))->toBeNull();

        Storage::disk('local')->assertMissing($path);
    });
});
