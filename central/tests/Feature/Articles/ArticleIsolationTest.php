<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Pages\PreviewArticle;
use App\Models\Article;
use App\Models\School;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Filters\TrashedFilter;
use Livewire\Livewire;

/*
| An editor of school A must not be able to reach any article of school B,
| neither through the UI nor by changing IDs in URLs or Livewire requests.
| Same cases as the placeholder suite in tests/Feature/Tenancy, plus soft
| deletes, publishing and preview.
*/

beforeEach(function () {
    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();

    $this->editorA = User::factory()->editor($this->schoolA)->create();

    $this->articleA = createArticle($this->schoolA, ['title' => 'Vijest škole A']);
    $this->articleB = createArticle($this->schoolB, ['title' => 'Vijest škole B']);
    $this->trashedB = createArticle($this->schoolB, ['title' => 'Obrisana vijest škole B']);
    $this->trashedB->delete();

    $this->actingAs($this->editorA);
});

it('does not list articles of another school', function () {
    $this->get('/admin/articles')
        ->assertOk()
        ->assertSee('Vijest škole A')
        ->assertDontSee('Vijest škole B');

    Livewire::test(ListArticles::class)
        ->assertCanSeeTableRecords([$this->articleA])
        ->assertCanNotSeeTableRecords([$this->articleB]);
});

it('does not list another school\'s trashed articles', function () {
    $trashedA = createArticle($this->schoolA, ['title' => 'Obrisana vijest škole A']);
    $trashedA->delete();

    Livewire::test(ListArticles::class)
        ->filterTable(TrashedFilter::class, true)
        ->assertCanSeeTableRecords([$trashedA])
        ->assertCanNotSeeTableRecords([$this->trashedB]);
});

it('does not find another school\'s articles through table search', function () {
    Livewire::test(ListArticles::class)
        ->searchTable('škole B')
        ->assertCanNotSeeTableRecords([$this->articleB, $this->trashedB]);
});

it('returns 404 when opening another school\'s article by URL', function (string $page) {
    $this->get("/admin/articles/{$this->articleA->id}/{$page}")->assertOk();
    $this->get("/admin/articles/{$this->articleB->id}/{$page}")->assertNotFound();
    $this->get("/admin/articles/{$this->trashedB->id}/{$page}")->assertNotFound();
})->with(['edit', 'preview']);

it('cannot mount the edit or preview page for another school\'s article', function (string $page) {
    Livewire::test($page, ['record' => $this->articleB->getRouteKey()])->assertNotFound();
})->with([EditArticle::class, PreviewArticle::class]);

it('cannot delete another school\'s article from the table', function () {
    expect(fn () => Livewire::test(ListArticles::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($this->articleB))
    )->toThrow(ActionNotResolvableException::class, 'no longer exists');

    expect(Article::withoutGlobalScopes()->find($this->articleB->id)->trashed())->toBeFalse();
});

it('cannot bulk delete another school\'s articles', function () {
    Livewire::test(ListArticles::class)
        ->selectTableRecords([$this->articleA->id, $this->articleB->id])
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    expect(Article::withoutGlobalScopes()->find($this->articleA->id)->trashed())->toBeTrue()
        ->and(Article::withoutGlobalScopes()->find($this->articleB->id)->trashed())->toBeFalse();
});

it('cannot restore another school\'s trashed article', function () {
    expect(fn () => Livewire::test(ListArticles::class)
        ->filterTable(TrashedFilter::class, true)
        ->callAction(TestAction::make(RestoreAction::class)->table($this->trashedB))
    )->toThrow(ActionNotResolvableException::class, 'no longer exists');

    expect(Article::withoutGlobalScopes()->find($this->trashedB->id)->trashed())->toBeTrue();
});

it('cannot bulk restore another school\'s trashed articles', function () {
    $trashedA = createArticle($this->schoolA);
    $trashedA->delete();

    Livewire::test(ListArticles::class)
        ->filterTable(TrashedFilter::class, true)
        ->selectTableRecords([$trashedA->id, $this->trashedB->id])
        ->callAction(TestAction::make(RestoreBulkAction::class)->table()->bulk());

    expect(Article::withoutGlobalScopes()->find($trashedA->id)->trashed())->toBeFalse()
        ->and(Article::withoutGlobalScopes()->find($this->trashedB->id)->trashed())->toBeTrue();
});

it('cannot publish or unpublish another school\'s article from the table', function (string $action) {
    expect(fn () => Livewire::test(ListArticles::class)
        ->callAction(TestAction::make($action)->table($this->articleB))
    )->toThrow(ActionNotResolvableException::class);
})->with(['publish', 'unpublish']);

it('stores new articles in the editor\'s school even when another school_id is injected', function () {
    Livewire::test(CreateArticle::class)
        ->set('data.school_id', $this->schoolB->id)
        ->fillForm(['title' => 'Podmetnuta vijest', 'slug' => 'podmetnuta-vijest', 'school_id' => $this->schoolB->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::withoutGlobalScopes()->where('title', 'Podmetnuta vijest')->sole();

    expect($article->school_id)->toBe($this->schoolA->id);
});

it('denies policy checks on another school\'s article', function () {
    // Load B's article without the scope, as if it had leaked past it.
    $articleB = Article::withoutGlobalScopes()->find($this->articleB->id);

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($this->editorA->can($ability, $articleB))->toBeFalse("{$ability} should be denied")
            ->and($this->editorA->can($ability, $this->articleA))->toBeTrue("{$ability} should be allowed");
    }
});

it('isolates the super admin to the school picked in the switcher', function () {
    actingAsSuperAdmin($this->schoolB);

    Livewire::test(ListArticles::class)
        ->assertCanSeeTableRecords([$this->articleB])
        ->assertCanNotSeeTableRecords([$this->articleA]);

    $this->get("/admin/articles/{$this->articleA->id}/edit")->assertNotFound();
    $this->get("/admin/articles/{$this->articleB->id}/edit")->assertOk();
});
