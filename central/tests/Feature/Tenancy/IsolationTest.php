<?php

use App\Models\School;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Fixtures\Filament\Resources\Notes\Pages\CreateNote;
use Tests\Fixtures\Filament\Resources\Notes\Pages\EditNote;
use Tests\Fixtures\Filament\Resources\Notes\Pages\ListNotes;
use Tests\Fixtures\Models\Note;

/*
| An editor of school A must not be able to reach any record of school B,
| neither through the UI nor by changing IDs in URLs or Livewire requests.
| These tests use the placeholder Note resource; every content resource is
| built from the same trait, policy base and Filament pages.
*/

beforeEach(function () {
    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();

    $this->editorA = User::factory()->editor($this->schoolA)->create();

    $this->noteA = createNote($this->schoolA, ['title' => 'Bilješka škole A', 'sort_order' => 1]);
    $this->noteB = createNote($this->schoolB, ['title' => 'Bilješka škole B', 'sort_order' => 1]);
    $this->otherNoteB = createNote($this->schoolB, ['title' => 'Druga bilješka škole B', 'sort_order' => 2]);

    $this->actingAs($this->editorA);
});

it('does not list records of another school', function () {
    $this->get('/admin/notes')
        ->assertOk()
        ->assertSee('Bilješka škole A')
        ->assertDontSee('Bilješka škole B');

    Livewire::test(ListNotes::class)
        ->assertCanSeeTableRecords([$this->noteA])
        ->assertCanNotSeeTableRecords([$this->noteB, $this->otherNoteB]);
});

it('does not find another school\'s records through table search', function () {
    Livewire::test(ListNotes::class)
        ->searchTable('škole B')
        ->assertCanNotSeeTableRecords([$this->noteB, $this->otherNoteB]);
});

it('returns 404 when opening another school\'s record by URL', function () {
    $this->get("/admin/notes/{$this->noteA->id}/edit")->assertOk();
    $this->get("/admin/notes/{$this->noteB->id}/edit")->assertNotFound();
});

it('cannot mount the edit page for another school\'s record', function () {
    Livewire::test(EditNote::class, ['record' => $this->noteB->getRouteKey()])->assertNotFound();
});

it('cannot delete another school\'s record from the table', function () {
    expect(fn () => Livewire::test(ListNotes::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($this->noteB))
    )->toThrow(ActionNotResolvableException::class, 'no longer exists');

    expect(Note::withoutGlobalScopes()->whereKey($this->noteB->id)->exists())->toBeTrue();
});

it('cannot bulk delete another school\'s records', function () {
    Livewire::test(ListNotes::class)
        ->selectTableRecords([$this->noteA->id, $this->noteB->id])
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    expect(Note::withoutGlobalScopes()->whereKey($this->noteA->id)->exists())->toBeFalse()
        ->and(Note::withoutGlobalScopes()->whereKey($this->noteB->id)->exists())->toBeTrue();
});

it('cannot reorder another school\'s records', function () {
    Livewire::test(ListNotes::class)
        ->call('reorderTable', [(string) $this->otherNoteB->id, (string) $this->noteB->id, (string) $this->noteA->id]);

    expect($this->noteB->fresh()->sort_order)->toBe(1)
        ->and($this->otherNoteB->fresh()->sort_order)->toBe(2);
});

it('can still reorder its own records', function () {
    $secondA = createNote($this->schoolA, ['title' => 'Druga bilješka škole A', 'sort_order' => 2]);

    Livewire::test(ListNotes::class)
        ->call('reorderTable', [(string) $secondA->id, (string) $this->noteA->id]);

    expect($secondA->fresh()->sort_order)->toBe(1)
        ->and($this->noteA->fresh()->sort_order)->toBe(2);
});

it('stores new records in the editor\'s school even when another school_id is injected', function () {
    Livewire::test(CreateNote::class)
        ->set('data.school_id', $this->schoolB->id)
        ->fillForm(['title' => 'Podmetnuta bilješka', 'school_id' => $this->schoolB->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $note = Note::withoutGlobalScopes()->where('title', 'Podmetnuta bilješka')->sole();

    expect($note->school_id)->toBe($this->schoolA->id);
});

it('denies policy checks on another school\'s record', function () {
    // Load B's record without the scope, as if it had leaked past it.
    $noteB = Note::withoutGlobalScopes()->find($this->noteB->id);

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($this->editorA->can($ability, $noteB))->toBeFalse("{$ability} should be denied");
        expect($this->editorA->can($ability, $this->noteA))->toBeTrue("{$ability} should be allowed");
    }
});

it('isolates the super admin to the school picked in the switcher', function () {
    actingAsSuperAdmin($this->schoolB);

    Livewire::test(ListNotes::class)
        ->assertCanSeeTableRecords([$this->noteB, $this->otherNoteB])
        ->assertCanNotSeeTableRecords([$this->noteA]);

    $this->get("/admin/notes/{$this->noteA->id}/edit")->assertNotFound();
    $this->get("/admin/notes/{$this->noteB->id}/edit")->assertOk();
});
