<?php

use App\Models\Article;
use App\Models\School;
use Database\Factories\ArticleFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| The article endpoints: /home, /articles?page=N, /articles/{slug} and
| /sitemap. Lists are newest first; the news list puts featured articles
| first. Visibility is covered in VisibilityTest.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
});

/**
 * A live article published the given number of days ago.
 */
function liveArticle(School $school, int $daysAgo, array $attributes = []): Article
{
    return createArticle($school, ['published_at' => now()->subDays($daysAgo), ...$attributes], fn (ArticleFactory $f) => $f->published());
}

/**
 * @return list<string>
 */
function slugsOf(array $articles): array
{
    return array_column($articles, 'slug');
}

describe('GET /home', function () {
    it('returns the school, up to 3 newest featured and the 6 newest others', function () {
        $featured = collect(range(1, 4))->map(fn (int $i) => liveArticle($this->school, $i, ['slug' => "istaknuto-{$i}", 'is_featured' => true]));
        $others = collect(range(1, 7))->map(fn (int $i) => liveArticle($this->school, $i + 10, ['slug' => "obicno-{$i}"]));

        $response = $this->getJson(schoolApi($this->school, '/home'))->assertOk();

        expect(array_keys($response->json()))->toBe(['school', 'featured', 'latest'])
            ->and($response->json('school.slug'))->toBe($this->school->slug)
            ->and(slugsOf($response->json('featured')))->toBe(['istaknuto-1', 'istaknuto-2', 'istaknuto-3'])
            // The fourth featured article is not in the highlight area, so it shows in the latest news.
            ->and(slugsOf($response->json('latest')))->toBe(['istaknuto-4', 'obicno-1', 'obicno-2', 'obicno-3', 'obicno-4', 'obicno-5']);
    });

    it('does not fill up featured or repeat articles when few are featured', function () {
        liveArticle($this->school, 1, ['slug' => 'istaknuto', 'is_featured' => true]);
        liveArticle($this->school, 2, ['slug' => 'obicno']);

        $response = $this->getJson(schoolApi($this->school, '/home'))->assertOk();

        expect(slugsOf($response->json('featured')))->toBe(['istaknuto'])
            ->and(slugsOf($response->json('latest')))->toBe(['obicno']);
    });

    it('returns empty lists for a school without articles', function () {
        $this->getJson(schoolApi($this->school, '/home'))
            ->assertOk()
            ->assertJsonPath('featured', [])
            ->assertJsonPath('latest', []);
    });
});

describe('GET /articles', function () {
    beforeEach(function () {
        // 14 articles: two featured (one old), the rest newest first by day.
        liveArticle($this->school, 30, ['slug' => 'staro-istaknuto', 'is_featured' => true]);
        liveArticle($this->school, 5, ['slug' => 'novo-istaknuto', 'is_featured' => true]);

        foreach (range(1, 12) as $i) {
            liveArticle($this->school, $i, ['slug' => "vijest-{$i}"]);
        }
    });

    it('returns 12 per page, featured first, then newest, with pagination meta', function () {
        $response = $this->getJson(schoolApi($this->school, '/articles'))->assertOk();

        expect(array_keys($response->json()))->toBe(['school', 'data', 'meta'])
            ->and(slugsOf($response->json('data')))->toBe([
                'novo-istaknuto', 'staro-istaknuto',
                'vijest-1', 'vijest-2', 'vijest-3', 'vijest-4', 'vijest-5',
                'vijest-6', 'vijest-7', 'vijest-8', 'vijest-9', 'vijest-10',
            ])
            ->and($response->json('meta'))->toBe([
                'current_page' => 1,
                'last_page' => 2,
                'per_page' => 12,
                'total' => 14,
            ]);
    });

    it('returns the second page', function () {
        $response = $this->getJson(schoolApi($this->school, '/articles?page=2'))->assertOk();

        expect(slugsOf($response->json('data')))->toBe(['vijest-11', 'vijest-12'])
            ->and($response->json('meta.current_page'))->toBe(2);
    });

    it('returns 404 for pages that do not exist', function (string $page) {
        $this->getJson(schoolApi($this->school, '/articles?page='.$page))
            ->assertNotFound()
            ->assertExactJson(['error' => 'not_found']);
    })->with(['3', '0', '-1', 'abc', '1.5']);

    it('returns 404 for a page given as an array', function () {
        $this->getJson(schoolApi($this->school, '/articles?page[]=1'))->assertNotFound();
    });
});

it('returns an empty first page for a school without articles', function () {
    $this->getJson(schoolApi($this->school, '/articles'))
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'per_page' => 12, 'total' => 0]);

    $this->getJson(schoolApi($this->school, '/articles?page=2'))->assertNotFound();
});

it('returns list items in the documented shape', function () {
    Carbon::setTestNow('2026-10-16 12:00:00');

    $article = createArticle($this->school, [
        'title' => 'Dan otvorenih vrata',
        'slug' => 'dan-otvorenih-vrata',
        'excerpt' => 'Pozivamo roditelje i buduće učenike...',
        'published_at' => Carbon::parse('2026-10-12 09:00:00'),
        'is_featured' => true,
    ], fn (ArticleFactory $f) => $f->published()->withCover());

    $cover = $article->getFirstMedia('cover');

    $this->getJson(schoolApi($this->school, '/articles'))
        ->assertOk()
        ->assertJsonPath('data', [[
            'title' => 'Dan otvorenih vrata',
            'slug' => 'dan-otvorenih-vrata',
            'excerpt' => 'Pozivamo roditelje i buduće učenike...',
            'cover' => [
                'thumb' => $cover->getUrl('thumb'),
                'large' => $cover->getUrl('large'),
                // The 800×600 cover is never upsized.
                'width' => 800,
                'height' => 600,
                'alt' => 'Dan otvorenih vrata',
            ],
            'published_at' => '2026-10-12T09:00:00+02:00',
            'is_featured' => true,
        ]]);
});

describe('GET /articles/{slug}', function () {
    it('returns the full article, its gallery in order and 3 other recent articles', function () {
        Carbon::setTestNow('2026-10-16 12:00:00');

        $article = createArticle($this->school, [
            'title' => 'Dan otvorenih vrata',
            'slug' => 'dan-otvorenih-vrata',
            'excerpt' => 'Pozivamo roditelje...',
            'body' => '<p>Dobro <strong>došli</strong>!</p>',
            'published_at' => Carbon::parse('2026-01-12 09:00:00'),
        ], fn (ArticleFactory $f) => $f->published()->withCover()->withGallery(3));

        [$first, $second, $third] = $article->getMedia('gallery')->all();
        $first->setCustomProperty('caption', 'Posjet knjižnici')->save();
        // Moved to the front of the gallery.
        $third->setCustomProperty('caption', 'Ulaz')->save();
        Media::setNewOrder([$third->id, $first->id, $second->id]);

        Carbon::setTestNow('2026-10-16 12:30:00');
        $article->touch();

        foreach (range(1, 4) as $i) {
            liveArticle($this->school, $i, ['slug' => "druga-{$i}"]);
        }

        $response = $this->getJson(schoolApi($this->school, '/articles/dan-otvorenih-vrata'))->assertOk();
        $cover = $article->getFirstMedia('cover');
        $image = fn ($media, string $alt): array => [
            'thumb' => $media->getUrl('thumb'),
            'large' => $media->getUrl('large'),
            'width' => 800,
            'height' => 600,
            'alt' => $alt,
        ];

        expect(array_keys($response->json()))->toBe(['school', 'article', 'recent'])
            ->and($response->json('article'))->toBe([
                'title' => 'Dan otvorenih vrata',
                'slug' => 'dan-otvorenih-vrata',
                'excerpt' => 'Pozivamo roditelje...',
                'cover' => $image($cover, 'Dan otvorenih vrata'),
                'published_at' => '2026-01-12T09:00:00+01:00',
                'is_featured' => false,
                'body' => '<p>Dobro <strong>došli</strong>!</p>',
                'gallery' => [
                    ['image' => $image($third, 'Ulaz'), 'caption' => 'Ulaz'],
                    ['image' => $image($first, 'Posjet knjižnici'), 'caption' => 'Posjet knjižnici'],
                    // Without a caption the alt text is the article title.
                    ['image' => $image($second, 'Dan otvorenih vrata'), 'caption' => null],
                ],
                'updated_at' => '2026-10-16T12:30:00+02:00',
            ])
            ->and(slugsOf($response->json('recent')))->toBe(['druga-1', 'druga-2', 'druga-3'])
            ->and(array_keys($response->json('recent.0')))->toBe(['title', 'slug', 'excerpt', 'cover', 'published_at', 'is_featured']);
    });

    it('returns null for a missing cover and an empty gallery', function () {
        liveArticle($this->school, 1, ['slug' => 'bez-slika']);

        $this->getJson(schoolApi($this->school, '/articles/bez-slika'))
            ->assertOk()
            ->assertJsonPath('article.cover', null)
            ->assertJsonPath('article.gallery', [])
            ->assertJsonPath('recent', []);
    });

    it('reports a scheduled article as updated when it goes live', function () {
        Carbon::setTestNow('2026-10-01 08:00:00');
        createArticle($this->school, ['slug' => 'zakazano', 'published_at' => Carbon::parse('2026-10-10 07:00:00')], fn (ArticleFactory $f) => $f->published());

        Carbon::setTestNow('2026-10-16 12:00:00');

        $this->getJson(schoolApi($this->school, '/articles/zakazano'))
            ->assertJsonPath('article.updated_at', '2026-10-10T07:00:00+02:00');
    });

    it('returns 404 for an unknown article', function () {
        $this->getJson(schoolApi($this->school, '/articles/ne-postoji'))
            ->assertNotFound()
            ->assertExactJson(['error' => 'not_found']);
    });
});

describe('GET /sitemap', function () {
    it('lists the slugs and last change of live articles only', function () {
        Carbon::setTestNow('2026-10-16 12:00:00');

        liveArticle($this->school, 2, ['slug' => 'starija']);
        liveArticle($this->school, 1, ['slug' => 'novija']);
        createArticle($this->school, ['slug' => 'nacrt']);

        $this->getJson(schoolApi($this->school, '/sitemap'))
            ->assertOk()
            ->assertExactJson([
                'articles' => [
                    ['slug' => 'novija', 'updated_at' => '2026-10-16T12:00:00+02:00'],
                    ['slug' => 'starija', 'updated_at' => '2026-10-16T12:00:00+02:00'],
                ],
            ]);
    });
});
