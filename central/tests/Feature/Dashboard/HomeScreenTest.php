<?php

use App\Filament\Widgets\LatestArticles;
use App\Filament\Widgets\LatestDocuments;
use App\Filament\Widgets\QuickLinks;
use App\Models\Article;
use App\Models\Document;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Database\Factories\ArticleFactory;
use Livewire\Livewire;

/*
| The home screen shows shortcuts, the latest articles and documents and a
| link to the public site, always of the school the user is working on.
*/

beforeEach(function () {
    $this->schoolA = School::factory()->create(['name' => 'Dom A', 'site_url' => 'https://dom-a.example']);
    $this->schoolB = School::factory()->create(['name' => 'Dom B', 'site_url' => 'https://dom-b.example']);
});

/**
 * Articles of a school, changed one after another (the last one most recently).
 *
 * @return list<Article>
 */
function articlesChangedInOrder(School $school, int $count, string $prefix): array
{
    return collect(range(1, $count))
        ->map(fn (int $number): Article => createArticle($school, [
            'title' => "{$prefix} {$number}",
            'updated_at' => now()->subMinutes(100 - $number),
        ]))
        ->all();
}

/**
 * @return list<Document>
 */
function documentsChangedInOrder(School $school, int $count, string $prefix): array
{
    return collect(range(1, $count))
        ->map(fn (int $number): Document => createDocument($school, [
            'title' => "{$prefix} {$number}",
            'updated_at' => now()->subMinutes(100 - $number),
        ]))
        ->all();
}

describe('editor', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->editor($this->schoolA)->create());
    });

    it('shows the shortcuts and the link to the school\'s own public site', function () {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Nova vijest')
            ->assertSee('Novi dokument')
            ->assertSee('/admin/articles/create')
            ->assertSee('/admin/documents/create')
            ->assertSee('https://dom-a.example')
            ->assertDontSee('https://dom-b.example');
    });

    it('lists the five most recently changed articles of their own school', function () {
        $own = articlesChangedInOrder($this->schoolA, 7, 'Vijest A');
        $other = articlesChangedInOrder($this->schoolB, 2, 'Vijest B');

        $widget = Livewire::test(LatestArticles::class)
            ->assertCanSeeTableRecords(array_reverse(array_slice($own, 2)), inOrder: true)
            ->assertCanNotSeeTableRecords([...array_slice($own, 0, 2), ...$other]);

        expect($widget->instance()->getTableRecords())->toHaveCount(5);
    });

    it('shows each article\'s status', function () {
        $draft = createArticle($this->schoolA, ['title' => 'Prva']);
        $published = createArticle($this->schoolA, ['title' => 'Druga'], fn (ArticleFactory $f) => $f->published());

        Livewire::test(LatestArticles::class)
            ->assertTableColumnFormattedStateSet('status', 'Skica', $draft)
            ->assertTableColumnFormattedStateSet('status', 'Objavljeno', $published);
    });

    it('leaves out articles in the trash', function () {
        $trashed = createArticle($this->schoolA);
        withoutUser(fn () => $trashed->delete());

        Livewire::test(LatestArticles::class)->assertCanNotSeeTableRecords([$trashed]);
    });

    it('lists the five most recently changed documents of their own school', function () {
        $own = documentsChangedInOrder($this->schoolA, 6, 'Dokument A');
        $other = documentsChangedInOrder($this->schoolB, 2, 'Dokument B');

        $widget = Livewire::test(LatestDocuments::class)
            ->assertCanSeeTableRecords(array_reverse(array_slice($own, 1)), inOrder: true)
            ->assertCanNotSeeTableRecords([$own[0], ...$other]);

        expect($widget->instance()->getTableRecords())->toHaveCount(5);
    });

    it('shows whether each document is published', function () {
        $published = createDocument($this->schoolA, ['is_published' => true]);
        $hidden = createDocument($this->schoolA, ['is_published' => false]);

        Livewire::test(LatestDocuments::class)
            ->assertTableColumnFormattedStateSet('is_published', 'Objavljeno', $published)
            ->assertTableColumnFormattedStateSet('is_published', 'Nije objavljeno', $hidden);
    });

    it('places the latest articles and documents on the home screen', function () {
        $this->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(LatestArticles::class)
            ->assertSeeLivewire(LatestDocuments::class);
    });
});

describe('super admin', function () {
    it('shows the content and public site of the school selected in the switcher', function () {
        $articleA = createArticle($this->schoolA, ['title' => 'Vijest doma A']);
        $articleB = createArticle($this->schoolB, ['title' => 'Vijest doma B']);
        $documentA = createDocument($this->schoolA);
        $documentB = createDocument($this->schoolB);

        actingAsSuperAdmin($this->schoolB);

        Livewire::test(QuickLinks::class)
            ->assertSee('https://dom-b.example')
            ->assertDontSee('https://dom-a.example');

        Livewire::test(LatestArticles::class)
            ->assertCanSeeTableRecords([$articleB])
            ->assertCanNotSeeTableRecords([$articleA]);

        Livewire::test(LatestDocuments::class)
            ->assertCanSeeTableRecords([$documentB])
            ->assertCanNotSeeTableRecords([$documentA]);

        app(CurrentSchool::class)->select($this->schoolA);

        Livewire::test(QuickLinks::class)->assertSee('https://dom-a.example');

        Livewire::test(LatestArticles::class)
            ->assertCanSeeTableRecords([$articleA])
            ->assertCanNotSeeTableRecords([$articleB]);

        Livewire::test(LatestDocuments::class)
            ->assertCanSeeTableRecords([$documentA])
            ->assertCanNotSeeTableRecords([$documentB]);
    });
});
