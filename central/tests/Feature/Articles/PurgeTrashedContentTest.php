<?php

use App\Models\Article;
use App\Models\School;
use Database\Factories\ArticleFactory;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| Soft-deleted articles are permanently deleted, with their files, 30 days
| after deletion by a daily scheduled command.
*/

beforeEach(function () {
    Storage::fake('public');
});

function trashedDaysAgo(int $days): Article
{
    $article = createArticle(School::factory()->create(), configure: fn (ArticleFactory $f) => $f->withCover()->withGallery(1));

    test()->travel(-$days)->days();
    $article->delete();
    test()->travelBack();

    return $article;
}

it('permanently deletes articles trashed 30 or more days ago, with their files', function () {
    $old = trashedDaysAgo(31);
    $exactlyThirty = trashedDaysAgo(30);
    $paths = $old->media->map(fn ($media) => $media->getPathRelativeToRoot());
    expect($paths)->toHaveCount(2);

    $this->artisan('content:purge-trashed')->assertSuccessful();

    expect(Article::withTrashed()->find($old->id))->toBeNull()
        ->and(Article::withTrashed()->find($exactlyThirty->id))->toBeNull()
        ->and($old->media()->count())->toBe(0);

    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('keeps recently trashed and live articles', function () {
    $recent = trashedDaysAgo(29);
    $live = createArticle(School::factory()->create(), configure: fn (ArticleFactory $f) => $f->withCover());

    $this->artisan('content:purge-trashed')->assertSuccessful();

    expect(Article::withTrashed()->find($recent->id))->not->toBeNull()
        ->and(Article::find($live->id))->not->toBeNull()
        ->and($recent->media()->count())->toBe(2);

    Storage::disk('public')->assertExists($live->getFirstMedia('cover')->getPathRelativeToRoot());
});

it('purges trashed articles of every school', function () {
    $first = trashedDaysAgo(40);
    $second = trashedDaysAgo(40);

    actingAsSuperAdmin($first->school);

    $this->artisan('content:purge-trashed')->assertSuccessful();

    expect(Article::withoutGlobalScopes()->whereKey([$first->id, $second->id])->count())->toBe(0);
});

it('is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'content:purge-trashed'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});
