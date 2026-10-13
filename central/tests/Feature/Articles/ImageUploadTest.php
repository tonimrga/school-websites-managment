<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| Uploaded images are resized to at most 2000 px and stripped of metadata
| before they are stored; thumb and large WebP conversions are generated on
| save. Only real JPG, PNG and WebP files are accepted.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->editor = User::factory()->editor($this->school)->create();
    $this->article = createArticle($this->school);

    $this->actingAs($this->editor);
});

function storedPath(Media $media, string $conversion = ''): string
{
    return Storage::disk('public')->path($media->getPathRelativeToRoot($conversion));
}

/**
 * @return array{int, int}
 */
function imageSize(string $path): array
{
    [$width, $height] = getimagesize($path);

    return [$width, $height];
}

it('scales large images down to 2000 px on the longest side', function () {
    $media = $this->article->addMedia(UploadedFile::fake()->image('velika.jpg', 3000, 1500))
        ->toMediaCollection('cover');

    expect(imageSize(storedPath($media)))->toBe([2000, 1000]);
});

it('does not upscale small images', function () {
    $media = $this->article->addMedia(UploadedFile::fake()->image('mala.png', 640, 480))
        ->toMediaCollection('cover');

    expect(imageSize(storedPath($media)))->toBe([640, 480]);
});

it('strips EXIF data including the GPS location', function () {
    $photo = jpegWithExif(800, 600);
    expect(exif_read_data($photo))->toHaveKey('GPSLatitude');

    $media = $this->article->addMedia($photo)->toMediaCollection('cover');

    $exif = @exif_read_data(storedPath($media)) ?: [];

    expect($exif)->not->toHaveKey('GPSLatitude')
        ->and($exif)->not->toHaveKey('Orientation');
});

it('applies the EXIF orientation before stripping it', function () {
    // Orientation 6: the camera was turned, the photo must be rotated 90°.
    $media = $this->article->addMedia(jpegWithExif(300, 200, orientation: 6))
        ->toMediaCollection('cover');

    expect(imageSize(storedPath($media)))->toBe([200, 300]);
});

it('stores files under random names and keeps the original name as metadata', function () {
    Livewire::test(EditArticle::class, ['record' => $this->article->getRouteKey()])
        ->fillForm(['cover' => UploadedFile::fake()->image('Izlet u Zagreb.jpg', 800, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    $media = $this->article->fresh()->getFirstMedia('cover');

    expect($media->name)->toBe('Izlet u Zagreb')
        ->and($media->file_name)->not->toContain('Izlet')
        ->and($media->file_name)->toEndWith('.jpg');
});

it('generates thumb and large WebP conversions on save', function () {
    $media = $this->article->addMedia(UploadedFile::fake()->image('slika.jpg', 2400, 1800))
        ->toMediaCollection('gallery');

    $thumb = storedPath($media, 'thumb');
    $large = storedPath($media, 'large');

    expect(imageSize($thumb))->toBe([400, 300])
        ->and(mime_content_type($thumb))->toBe('image/webp')
        ->and(imageSize($large))->toBe([1600, 1200])
        ->and(mime_content_type($large))->toBe('image/webp');
});

it('keeps a single cover when the cover is replaced', function () {
    $this->article->addMedia(UploadedFile::fake()->image('prva.jpg', 800, 600))->toMediaCollection('cover');
    $this->article->addMedia(UploadedFile::fake()->image('druga.jpg', 800, 600))->toMediaCollection('cover');

    expect($this->article->fresh()->getMedia('cover'))->toHaveCount(1)
        ->and($this->article->fresh()->getFirstMedia('cover')->name)->toBe('druga');
});

it('rejects files that are not JPG, PNG or WebP images in the form', function (UploadedFile $file) {
    Livewire::test(EditArticle::class, ['record' => $this->article->getRouteKey()])
        ->fillForm(['cover' => $file])
        ->call('save')
        ->assertHasFormErrors(['cover']);

    expect($this->article->fresh()->getMedia('cover'))->toBeEmpty();
})->with([
    'SVG image' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'HTML renamed to .jpg' => fn () => UploadedFile::fake()->createWithContent('slika.jpg', '<html><body><script>alert(1)</script></body></html>'),
    'PHP renamed to .png' => fn () => UploadedFile::fake()->createWithContent('slika.png', '<?php echo "hacked"; ?>'),
    'real JPEG with a .php extension' => fn () => UploadedFile::fake()->image('slika.php', 100, 100),
    'GIF image' => fn () => UploadedFile::fake()->image('slika.gif', 100, 100),
]);

it('rejects images over 10 MB in the form', function () {
    Livewire::test(EditArticle::class, ['record' => $this->article->getRouteKey()])
        ->fillForm(['cover' => UploadedFile::fake()->image('ogromna.jpg', 100, 100)->size(10 * 1024 + 1)])
        ->call('save')
        ->assertHasFormErrors(['cover']);
});

it('rejects non-image files at the model level too', function () {
    $file = UploadedFile::fake()->createWithContent('dokument.jpg', '<html>nije slika</html>');

    expect(fn () => $this->article->addMedia($file)->toMediaCollection('cover'))
        ->toThrow(FileUnacceptableForCollection::class);
});

it('limits the gallery to 30 images in the form', function () {
    $images = collect(range(1, 31))
        ->map(fn (int $number) => UploadedFile::fake()->image("slika-{$number}.jpg", 10, 10))
        ->all();

    Livewire::test(EditArticle::class, ['record' => $this->article->getRouteKey()])
        ->fillForm(['gallery' => $images])
        ->call('save')
        ->assertHasFormErrors(['gallery']);

    expect($this->article->fresh()->getMedia('gallery'))->toBeEmpty();
});

it('enforces the gallery limit at the model level', function () {
    config(['cms.articles.gallery_max' => 2]);

    $this->article->addMedia(UploadedFile::fake()->image('1.jpg', 10, 10))->toMediaCollection('gallery');
    $this->article->addMedia(UploadedFile::fake()->image('2.jpg', 10, 10))->toMediaCollection('gallery');

    expect(fn () => $this->article->addMedia(UploadedFile::fake()->image('3.jpg', 10, 10))->toMediaCollection('gallery'))
        ->toThrow(FileUnacceptableForCollection::class);

    expect(Article::find($this->article->id)->getMedia('gallery'))->toHaveCount(2);
});
