<?php

use App\Models\Article;
use App\Models\Document;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoImage;
use Database\Seeders\DemoSchoolsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/*
| The demo content seeder fills both demo schools with live, featured,
| draft, scheduled and trashed articles and with documents, and never runs
| twice for the same school. Seeding is slow, so each test seeds as little
| as possible.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    // Small pictures and galleries keep the test fast; the pipeline is the same.
    DemoImage::$width = 320;
    DemoContentSeeder::$galleryLimit = 1;

    $this->seed(DemoSchoolsSeeder::class);
    $this->school = School::where('slug', 'os-ivana-gundulica')->sole();
    $this->other = School::where('slug', 'os-druga-skola')->sole();
});

afterEach(function () {
    DemoImage::$width = 1600;
    DemoContentSeeder::$galleryLimit = null;
});

/**
 * @return Builder<Article>
 */
function demoArticles(School $school): Builder
{
    return Article::withoutGlobalScope(SchoolScope::class)->where('school_id', $school->id);
}

it('fills both schools with articles and documents of every kind, once', function () {
    $this->seed(DemoContentSeeder::class);

    $live = demoArticles($this->school)->live()->with('media')->get();

    expect($live)->toHaveCount(14)
        ->and($live->where('is_featured', true))->toHaveCount(3)
        ->and($live->every(fn (Article $article) => $article->getFirstMedia('cover') !== null))->toBeTrue()
        ->and($live->filter(fn (Article $article) => $article->getMedia('gallery')->isNotEmpty()))->not->toBeEmpty()
        ->and($live->every(fn (Article $article) => $article->created_by !== null))->toBeTrue()
        ->and(demoArticles($this->school)->drafts()->count())->toBe(2)
        ->and(demoArticles($this->school)->scheduled()->count())->toBe(1)
        ->and(demoArticles($this->school)->onlyTrashed()->count())->toBe(1)
        ->and(demoArticles($this->other)->live()->count())->toBe(10);

    $documents = Document::withoutGlobalScope(SchoolScope::class)->where('school_id', $this->school->id)->with('media')->get();

    expect($documents)->toHaveCount(20)
        ->and($documents->where('is_published', false))->toHaveCount(2)
        ->and($documents->every(fn (Document $document) => $document->file()?->mime_type === 'application/pdf'))->toBeTrue()
        ->and($documents->first()->file()->file_name)->not->toContain('Natje');

    // More than one page of news, and only categories with published documents.
    $this->getJson(schoolApi($this->school, '/articles'))
        ->assertOk()
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 14);

    $categories = collect($this->getJson(schoolApi($this->school, '/documents'))->json('categories'))->pluck('slug');

    expect($categories)->toContain('natjecaji')
        ->and($categories)->not->toContain('ostalo')
        ->and($categories)->not->toContain('javna-nabava');

    // Running it again adds nothing.
    $articleCount = Article::withoutGlobalScopes()->count();
    $documentCount = Document::withoutGlobalScopes()->count();

    $this->seed(DemoContentSeeder::class);

    expect(Article::withoutGlobalScopes()->count())->toBe($articleCount)
        ->and(Document::withoutGlobalScopes()->count())->toBe($documentCount);
});

it('skips a school that already has content', function () {
    createArticle($this->school, ['title' => 'Postojeća vijest']);
    createDocument($this->other, ['title' => 'Postojeći dokument']);

    $this->seed(DemoContentSeeder::class);

    expect(Article::withoutGlobalScopes()->count())->toBe(1)
        ->and(Document::withoutGlobalScopes()->count())->toBe(1);
});
