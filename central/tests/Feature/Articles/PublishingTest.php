<?php

use App\Enums\ArticleStatus;
use App\Exceptions\ArticleNotPublishable;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Pages\PreviewArticle;
use App\Filament\Resources\Articles\Schemas\ArticleForm;
use App\Models\Article;
use App\Models\School;
use App\Models\User;
use Database\Factories\ArticleFactory;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Articles are drafts until published. Publishing requires a title, body and
| cover; a future publish date schedules the article.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->editor = User::factory()->editor($this->school)->create();

    $this->actingAs($this->editor);
});

describe('model rules', function () {
    it('publishes now when no date is set', function () {
        $this->freezeSecond();
        $article = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->withCover());

        $article->publish();

        expect($article->fresh())
            ->status->toBe(ArticleStatus::Published)
            ->published_at->toEqual(now())
            ->isScheduled()->toBeFalse();
    });

    it('schedules when the publish date is in the future', function () {
        $article = createArticle($this->school, ['published_at' => now()->addDays(3)], fn (ArticleFactory $f) => $f->withCover());

        $article->publish();

        expect($article->fresh())
            ->isPublished()->toBeTrue()
            ->isScheduled()->toBeTrue()
            ->statusLabel()->toBe('Zakazano');
    });

    it('refuses to publish without a cover, body or title', function (array $attributes, bool $withCover, string $missing) {
        $article = createArticle($this->school, $attributes, $withCover ? fn (ArticleFactory $f) => $f->withCover() : null);

        expect(fn () => $article->publish())->toThrow(ArticleNotPublishable::class, $missing);
        expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
    })->with([
        'no cover' => [[], false, 'naslovna fotografija'],
        'empty body' => [['body' => '<p> </p>'], true, 'tekst'],
        'body without text' => [['body' => '<p><br></p>'], true, 'tekst'],
        'no title' => [['title' => ''], true, 'naslov'],
    ]);

    it('unpublishes back to a draft and keeps the publish date', function () {
        $article = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->published()->withCover());
        $publishedAt = $article->published_at;

        $article->unpublish();

        expect($article->fresh())
            ->status->toBe(ArticleStatus::Draft)
            ->published_at->toEqual($publishedAt);
    });

    it('fills an empty excerpt from the body', function () {
        $article = createArticle($this->school, [
            'excerpt' => null,
            'body' => '<h2>Dan škole</h2><p>Učenici su  pripremili&nbsp;priredbu.</p>'.str_repeat('<p>Dugačak tekst vijesti.</p>', 30),
        ]);

        expect($article->excerpt)->toStartWith('Dan škole Učenici su pripremili priredbu.')
            ->and(mb_strlen($article->excerpt))->toBeLessThanOrEqual(Article::EXCERPT_LENGTH);
    });

    it('keeps an excerpt written by hand', function () {
        $article = createArticle($this->school, ['excerpt' => 'Kratki sažetak.']);

        expect($article->excerpt)->toBe('Kratki sažetak.');
    });

    it('does not accept status through mass assignment', function () {
        $article = new Article(['title' => 'Naslov', 'status' => 'published']);

        expect($article->status)->toBe(ArticleStatus::Draft);
    });
});

describe('dashboard', function () {
    it('creates an article as a draft with the editor as author', function () {
        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => 'Dan otvorenih vrata', 'body' => '<p>Pozivamo roditelje.</p>'])
            ->assertSchemaStateSet(['slug' => 'dan-otvorenih-vrata'])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::sole();

        expect($article)
            ->status->toBe(ArticleStatus::Draft)
            ->slug->toBe('dan-otvorenih-vrata')
            ->excerpt->toBe('Pozivamo roditelje.')
            ->created_by->toBe($this->editor->id)
            ->updated_by->toBe($this->editor->id);
    });

    it('creates and publishes an article with a cover and gallery in one step', function () {
        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Dan otvorenih vrata',
                'body' => '<p>Pozivamo roditelje.</p>',
                'is_featured' => true,
                'cover' => UploadedFile::fake()->image('naslovna.jpg', 1200, 800),
                'gallery' => [
                    UploadedFile::fake()->image('prva.jpg', 800, 600),
                    UploadedFile::fake()->image('druga.jpg', 800, 600),
                ],
            ])
            ->call('createAndPublish')
            ->assertHasNoFormErrors();

        $article = Article::sole();

        expect($article->isPublished())->toBeTrue()
            ->and($article->is_featured)->toBeTrue()
            ->and($article->getMedia('gallery')->pluck('name')->all())->toBe(['prva', 'druga']);
    });

    it('keeps "Objavi" disabled on create until title, text and cover are filled in', function () {
        $page = Livewire::test(CreateArticle::class)
            ->assertActionDisabled('createAndPublish')
            ->assertActionHasColor('createAndPublish', 'gray')
            ->assertSee('Za objavu nedostaje: naslov, tekst, naslovna fotografija.')
            ->fillForm(['title' => 'Dan otvorenih vrata', 'body' => '<p>Tekst.</p>'])
            ->assertActionDisabled('createAndPublish')
            ->assertSee('Za objavu nedostaje: naslovna fotografija.')
            ->fillForm(['cover' => UploadedFile::fake()->image('naslovna.jpg', 800, 600)])
            ->assertActionEnabled('createAndPublish')
            ->assertActionHasColor('createAndPublish', 'success')
            ->assertDontSee('Za objavu nedostaje');

        // Saving as a draft needs only the title.
        $page->assertActionEnabled('create');
    });

    it('marks the text and cover as needed for publishing', function () {
        Livewire::test(CreateArticle::class)
            ->assertSee(ArticleForm::PUBLISH_HINT);
    });

    it('creates nothing when publishing is forced with fields missing', function () {
        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => 'Bez fotografije', 'body' => '<p>Tekst.</p>'])
            ->call('createAndPublish')
            ->assertNotified('Za objavu nedostaje: naslovna fotografija.')
            ->assertNoRedirect();

        expect(Article::count())->toBe(0);
    });

    it('keeps "Objavi" disabled on the edit page while something is missing', function () {
        $withoutCover = createArticle($this->school);

        Livewire::test(EditArticle::class, ['record' => $withoutCover->getRouteKey()])
            ->assertActionDisabled('publish')
            ->assertSee('Za objavu nedostaje: naslovna fotografija.')
            ->fillForm(['cover' => [UploadedFile::fake()->image('naslovna.jpg', 800, 600)]])
            ->assertActionEnabled('publish');

        $complete = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->withCover());

        Livewire::test(EditArticle::class, ['record' => $complete->getRouteKey()])
            ->assertActionEnabled('publish')
            ->fillForm(['title' => ''])
            ->assertActionDisabled('publish');
    });

    it('reads the editor\'s TipTap document when checking the text', function () {
        $doc = fn (string $text): array => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]]],
        ]];

        expect(ArticleForm::missingForPublishing(['title' => 'Naslov', 'body' => $doc('Tekst'), 'cover' => ['uuid' => 'uuid']]))->toBe([])
            ->and(ArticleForm::missingForPublishing(['title' => 'Naslov', 'body' => $doc(''), 'cover' => ['uuid' => 'uuid']]))->toBe(['tekst'])
            ->and(ArticleForm::missingForPublishing(['title' => 'Naslov', 'body' => $doc('   '), 'cover' => []]))->toBe(['tekst', 'naslovna fotografija']);
    });

    it('saves pending changes and publishes from the edit page', function () {
        $article = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->withCover());

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['title' => 'Novi naslov'])
            ->callAction('publish')
            ->assertNotified('Vijest je objavljena.');

        expect($article->fresh())
            ->title->toBe('Novi naslov')
            ->isPublished()->toBeTrue();
    });

    it('tells the editor what is missing instead of publishing', function () {
        $article = createArticle($this->school);

        Livewire::test(ListArticles::class)
            ->callAction(TestAction::make('publish')->table($article))
            ->assertNotified('Za objavu nedostaje: naslovna fotografija.');

        expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
    });

    it('unpublishes from the table', function () {
        $article = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->published()->withCover());

        Livewire::test(ListArticles::class)
            ->callAction(TestAction::make('unpublish')->table($article));

        expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
    });

    it('requires a slug that is unique within the school, including the trash', function () {
        $trashed = createArticle($this->school, ['slug' => 'izlet']);
        $trashed->delete();

        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => 'Izlet', 'slug' => 'izlet'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    });

    it('allows the same slug in another school', function () {
        createArticle(School::factory()->create(), ['slug' => 'izlet']);

        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => 'Izlet', 'slug' => 'izlet'])
            ->call('create')
            ->assertHasNoFormErrors();
    });

    it('validates title, slug and excerpt lengths and the slug format', function (array $data, array $errors) {
        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => 'Naslov', 'slug' => 'naslov', ...$data])
            ->call('create')
            ->assertHasFormErrors($errors);
    })->with([
        'missing title' => [['title' => ''], ['title' => 'required']],
        'long title' => [['title' => str_repeat('a', 201)], ['title' => 'max']],
        'bad slug' => [['slug' => 'Dan škole!'], ['slug' => 'regex']],
        'long excerpt' => [['excerpt' => str_repeat('a', 301)], ['excerpt' => 'max']],
    ]);

    it('filters the list by status and featured flag', function () {
        $draft = createArticle($this->school);
        $published = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->published()->featured());
        $scheduled = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->scheduled());

        Livewire::test(ListArticles::class)
            ->filterTable('status', 'scheduled')
            ->assertCanSeeTableRecords([$scheduled])
            ->assertCanNotSeeTableRecords([$draft, $published])
            ->resetTableFilters()
            ->filterTable('status', 'published')
            ->assertCanSeeTableRecords([$published])
            ->assertCanNotSeeTableRecords([$draft, $scheduled])
            ->resetTableFilters()
            ->filterTable('is_featured', true)
            ->assertCanSeeTableRecords([$published])
            ->assertCanNotSeeTableRecords([$draft, $scheduled]);
    });

    it('also shows the save buttons at the top of the form', function () {
        $article = createArticle($this->school);

        // Header buttons submit the page form by its id.
        expect(substr_count($this->get('/admin/articles/create')->assertOk()->getContent(), 'form="form"'))
            ->toBeGreaterThanOrEqual(1)
            ->and(substr_count($this->get("/admin/articles/{$article->id}/edit")->assertOk()->getContent(), 'form="form"'))
            ->toBeGreaterThanOrEqual(1);
    });

    it('shows the GDPR reminder in the article form', function () {
        $this->get('/admin/articles/create')
            ->assertOk()
            ->assertSee(ArticleForm::GDPR_REMINDER);
    });

    it('shows a read-only preview with body, cover and captioned gallery', function () {
        $article = createArticle($this->school, [
            'title' => 'Posjet muzeju',
            'body' => '<p>Učenici su posjetili muzej.</p>',
        ], fn (ArticleFactory $f) => $f->withCover()->withGallery(1));
        $article->getFirstMedia('gallery')->setCustomProperty('caption', 'Ispred muzeja')->save();

        $this->get("/admin/articles/{$article->id}/preview")
            ->assertOk()
            ->assertSee('Posjet muzeju')
            ->assertSee('<p>Učenici su posjetili muzej.</p>', escape: false)
            ->assertSee('Ispred muzeja')
            ->assertSee($article->getFirstMediaUrl('cover', 'large'));

        Livewire::test(PreviewArticle::class, ['record' => $article->getRouteKey()])
            ->assertOk()
            ->assertSee('Skica');
    });

    it('saves gallery captions', function () {
        $article = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->withGallery(2));
        [$first, $second] = $article->getMedia('gallery')->all();
        $second->setCustomProperty('caption', 'Stari opis')->save();

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->callAction('captions', data: ['captions' => [$first->id => '  Posjet knjižnici ', $second->id => '']])
            ->assertHasNoFormErrors();

        expect($first->fresh()->getCustomProperty('caption'))->toBe('Posjet knjižnici')
            ->and($second->fresh()->hasCustomProperty('caption'))->toBeFalse();
    });
});
