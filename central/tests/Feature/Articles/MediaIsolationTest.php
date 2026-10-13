<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\School;
use App\Models\User;
use Database\Factories\ArticleFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| An editor of school A cannot upload, reorder, remove or caption the cover
| or gallery images of a school B article: not through B's pages, and not by
| injecting B's media UUIDs or IDs into requests for A's own article.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
    $this->editorA = User::factory()->editor($this->schoolA)->create();

    $withMedia = fn (ArticleFactory $factory) => $factory->withCover()->withGallery(2);

    $this->articleA = createArticle($this->schoolA, configure: $withMedia);
    $this->articleB = createArticle($this->schoolB, configure: $withMedia);

    $this->mediaB = Media::query()
        ->where('model_type', $this->articleB->getMorphClass())
        ->where('model_id', $this->articleB->id)
        ->get();

    $this->actingAs($this->editorA);
});

/**
 * School B's media rows, as a comparable snapshot.
 *
 * @return array<int, array<string, mixed>>
 */
function mediaSnapshot(Article $article): array
{
    return Media::query()
        ->where('model_type', $article->getMorphClass())
        ->where('model_id', $article->id)
        ->orderBy('id')
        ->get()
        ->map(fn (Media $media): array => $media->only(['id', 'model_id', 'collection_name', 'order_column', 'custom_properties']))
        ->all();
}

function uuidState(iterable $media): array
{
    return collect($media)->mapWithKeys(fn (Media $media): array => [$media->uuid => $media->uuid])->all();
}

it('cannot open the form of another school\'s article to upload or change its media', function () {
    $this->get("/admin/articles/{$this->articleB->id}/edit")->assertNotFound();

    Livewire::test(EditArticle::class, ['record' => $this->articleB->getRouteKey()])
        ->assertNotFound();
});

it('ignores another school\'s media injected into the gallery state', function () {
    $before = mediaSnapshot($this->articleB);
    $galleryA = $this->articleA->getMedia('gallery');
    $galleryB = $this->articleB->getMedia('gallery');

    // B's images first, as if trying to reorder them or adopt them into A.
    Livewire::test(EditArticle::class, ['record' => $this->articleA->getRouteKey()])
        ->set('data.gallery', uuidState([...$galleryB->reverse(), ...$galleryA]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect(mediaSnapshot($this->articleB))->toBe($before)
        ->and($this->articleA->fresh()->getMedia('gallery')->pluck('id')->all())
        ->toBe($galleryA->pluck('id')->all());
});

it('ignores another school\'s media injected into the cover state', function () {
    $before = mediaSnapshot($this->articleB);
    $coverA = $this->articleA->getFirstMedia('cover');

    Livewire::test(EditArticle::class, ['record' => $this->articleA->getRouteKey()])
        ->set('data.cover', uuidState([$this->articleB->getFirstMedia('cover'), $coverA]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect(mediaSnapshot($this->articleB))->toBe($before)
        ->and($this->articleA->fresh()->getFirstMedia('cover')->id)->toBe($coverA->id);
});

it('does not remove another school\'s media when an image is removed from its own gallery', function () {
    $before = mediaSnapshot($this->articleB);
    $galleryA = $this->articleA->getMedia('gallery');

    Livewire::test(EditArticle::class, ['record' => $this->articleA->getRouteKey()])
        ->set('data.gallery', uuidState([$galleryA->first()]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->articleA->fresh()->getMedia('gallery'))->toHaveCount(1)
        ->and(mediaSnapshot($this->articleB))->toBe($before);

    foreach ($this->mediaB as $media) {
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }
});

it('stores uploads only on the editor\'s own article', function () {
    $before = mediaSnapshot($this->articleB);

    Livewire::test(EditArticle::class, ['record' => $this->articleA->getRouteKey()])
        ->fillForm(['gallery' => [UploadedFile::fake()->image('nova.jpg', 100, 100)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->articleA->fresh()->getMedia('gallery')->pluck('name')->all())->toBe(['nova'])
        ->and(mediaSnapshot($this->articleB))->toBe($before);
});

it('does not caption another school\'s media through the captions action', function () {
    $before = mediaSnapshot($this->articleB);
    $ownImage = $this->articleA->getMedia('gallery')->first();

    Livewire::test(EditArticle::class, ['record' => $this->articleA->getRouteKey()])
        ->callAction('captions', data: [
            'captions' => [
                $ownImage->id => 'Posjet knjižnici',
                $this->articleB->getMedia('gallery')->first()->id => 'Podmetnuti opis',
            ],
        ])
        ->assertHasNoFormErrors();

    expect($ownImage->fresh()->getCustomProperty('caption'))->toBe('Posjet knjižnici')
        ->and(mediaSnapshot($this->articleB))->toBe($before);
});
