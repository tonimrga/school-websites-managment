<?php

use App\Models\School;
use App\Support\ConversionSize;
use Database\Factories\ArticleFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| The API reports image sizes without reading files: the stored image's size
| is recorded on upload, and conversion sizes are derived from it. Images
| uploaded before that are filled in by media:record-dimensions.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->article = createArticle($this->school, configure: fn (ArticleFactory $f) => $f->published());
});

/**
 * The real size of a generated conversion file, read from the disk.
 *
 * @return array{width: int, height: int}
 */
function generatedSize(Media $media, string $conversion): array
{
    $path = $media->getPathRelativeToRoot($conversion);

    return ConversionSize::measure((string) Storage::disk($media->conversions_disk ?: $media->disk)->get($path));
}

it('records the size of the stored image, after scaling, on upload', function () {
    $media = $this->article->addMedia(UploadedFile::fake()->image('slika.jpg', 3000, 1500))->toMediaCollection('gallery');

    expect($media->getCustomProperty('width'))->toBe(2000)
        ->and($media->getCustomProperty('height'))->toBe(1000)
        ->and($media->refresh()->getCustomProperty('width'))->toBe(2000);
});

it('records the size of images in the original orientation of the photo', function () {
    // EXIF orientation 6: stored rotated, so 400×300 becomes 300×400.
    $media = $this->article->addMedia(jpegWithExif(400, 300, 6))->toMediaCollection('gallery');

    expect([$media->getCustomProperty('width'), $media->getCustomProperty('height')])->toBe([300, 400]);
});

it('keeps captions and other custom properties set when adding', function () {
    $media = $this->article->addMedia(UploadedFile::fake()->image('slika.jpg', 100, 50))
        ->withCustomProperties(['caption' => 'Natpis'])
        ->toMediaCollection('gallery');

    expect($media->custom_properties)->toBe(['caption' => 'Natpis', 'width' => 100, 'height' => 50]);
});

it('records no size for documents', function () {
    $document = createDocument($this->school, configure: fn ($f) => $f->withFile());

    expect($document->file()->custom_properties)->toBe([]);
});

it('derives article conversion sizes that match the generated files', function (int $width, int $height) {
    $media = $this->article->addMedia(UploadedFile::fake()->image('slika.jpg', $width, $height))->toMediaCollection('gallery');

    foreach (['thumb', 'large'] as $conversion) {
        expect(ConversionSize::for($media, $conversion))->toBe(generatedSize($media, $conversion));
    }
})->with([
    'landscape' => [2500, 1406],
    'portrait' => [1200, 1900],
    'small' => [320, 200],
    'odd ratio' => [1999, 1333],
]);

it('derives logo conversion sizes that match the generated files', function (int $width, int $height) {
    $media = $this->school->addMedia(UploadedFile::fake()->image('logo.png', $width, $height))->toMediaCollection('logo');

    foreach (['small', 'large'] as $conversion) {
        expect(ConversionSize::for($media, $conversion))->toBe(generatedSize($media, $conversion));
    }
})->with([
    'wide' => [1800, 500],
    'tall' => [333, 1001],
    'small' => [120, 80],
]);

it('gives no size when none was recorded', function () {
    $media = $this->article->addMedia(UploadedFile::fake()->image('slika.jpg', 800, 600))->toMediaCollection('cover');
    $media->forgetCustomProperty('width')->forgetCustomProperty('height')->save();

    expect(ConversionSize::for($media, 'large'))->toBeNull();

    $this->getJson(schoolApi($this->school, '/articles/'.$this->article->slug))
        ->assertJsonPath('article.cover.width', null)
        ->assertJsonPath('article.cover.height', null);
});

describe('media:record-dimensions', function () {
    it('fills in missing sizes of stored images and skips the rest', function () {
        $old = $this->article->addMedia(UploadedFile::fake()->image('stara.jpg', 640, 480))->toMediaCollection('gallery');
        $old->forgetCustomProperty('width')->forgetCustomProperty('height')->setCustomProperty('caption', 'Stara')->save();

        $logo = $this->school->addMedia(UploadedFile::fake()->image('logo.png', 300, 200))->toMediaCollection('logo');
        $logo->forgetCustomProperty('width')->forgetCustomProperty('height')->save();

        // Already recorded: left untouched, even if the value were different.
        $recorded = $this->article->addMedia(UploadedFile::fake()->image('nova.jpg', 100, 100))->toMediaCollection('gallery');
        $recorded->setCustomProperty('width', 99)->save();

        $document = createDocument($this->school, configure: fn ($f) => $f->withFile());

        $this->artisan('media:record-dimensions')
            ->expectsOutputToContain('2 images recorded, 0 could not be read.')
            ->assertSuccessful();

        expect($old->refresh()->custom_properties)->toBe(['caption' => 'Stara', 'width' => 640, 'height' => 480])
            ->and([$logo->refresh()->getCustomProperty('width'), $logo->getCustomProperty('height')])->toBe([300, 200])
            ->and($recorded->refresh()->getCustomProperty('width'))->toBe(99)
            ->and($document->file()->refresh()->custom_properties)->toBe([]);

        // Running it again changes nothing.
        $this->artisan('media:record-dimensions')
            ->expectsOutputToContain('0 images recorded, 0 could not be read.')
            ->assertSuccessful();
    });

    it('reports images whose file is missing', function () {
        $media = $this->article->addMedia(UploadedFile::fake()->image('slika.jpg', 640, 480))->toMediaCollection('cover');
        $media->forgetCustomProperty('width')->forgetCustomProperty('height')->save();
        Storage::disk($media->disk)->delete($media->getPathRelativeToRoot());

        $this->artisan('media:record-dimensions')
            ->expectsOutputToContain('0 images recorded, 1 could not be read.')
            ->assertFailed();

        expect($media->refresh()->hasCustomProperty('width'))->toBeFalse();
    });
});
