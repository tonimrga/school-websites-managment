<?php

use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Models\School;
use Database\Factories\ArticleFactory;
use Database\Factories\DocumentFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| The schools list shows how much space each school's files take: its logo,
| article images and documents (trashed ones too, until they are purged).
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
});

function setMediaSize(Media $media, int $bytes): void
{
    $media->forceFill(['size' => $bytes])->saveQuietly();
}

function storageBytes(School $school): int
{
    return (int) School::query()->withStorageBytes()->whereKey($school->id)->value('storage_bytes');
}

it('sums the logo, article images and documents of each school', function () {
    $article = createArticle($this->schoolA, configure: fn (ArticleFactory $f) => $f->withCover()->withGallery(1));
    $document = createDocument($this->schoolA, configure: fn (DocumentFactory $f) => $f->withFile());
    $logo = $this->schoolA->addMedia(UploadedFile::fake()->image('logo.png', 50, 50))->toMediaCollection('logo');

    setMediaSize($article->getFirstMedia('cover'), 1_000);
    setMediaSize($article->getFirstMedia('gallery'), 2_000);
    setMediaSize($document->getFirstMedia('file'), 30_000);
    setMediaSize($logo, 400);

    $otherDocument = createDocument($this->schoolB, configure: fn (DocumentFactory $f) => $f->withFile());
    setMediaSize($otherDocument->getFirstMedia('file'), 5_000_000);

    expect(storageBytes($this->schoolA))->toBe(33_400)
        ->and(storageBytes($this->schoolB))->toBe(5_000_000);
});

it('counts content in the trash until it is purged', function () {
    $article = createArticle($this->schoolA, configure: fn (ArticleFactory $f) => $f->withCover());
    $document = createDocument($this->schoolA, configure: fn (DocumentFactory $f) => $f->withFile());

    setMediaSize($article->getFirstMedia('cover'), 1_000);
    setMediaSize($document->getFirstMedia('file'), 2_000);

    withoutUser(function () use ($article, $document): void {
        $article->delete();
        $document->delete();
    });

    expect(storageBytes($this->schoolA))->toBe(3_000);

    withoutUser(fn () => $document->forceDelete());

    expect(storageBytes($this->schoolA))->toBe(1_000);
});

it('is zero for a school without files', function () {
    expect(storageBytes($this->schoolA))->toBe(0);
});

it('shows the storage use in the schools list, sortable', function () {
    $document = createDocument($this->schoolB, configure: fn (DocumentFactory $f) => $f->withFile());
    setMediaSize($document->getFirstMedia('file'), 1_572_864);

    actingAsSuperAdmin();

    Livewire::test(ListSchools::class)
        ->assertTableColumnFormattedStateSet('storage_bytes', '1.5 MB', $this->schoolB)
        ->assertTableColumnFormattedStateSet('storage_bytes', '0 B', $this->schoolA)
        ->sortTable('storage_bytes', 'desc')
        ->assertCanSeeTableRecords([$this->schoolB, $this->schoolA], inOrder: true)
        ->sortTable('storage_bytes', 'asc')
        ->assertCanSeeTableRecords([$this->schoolA, $this->schoolB], inOrder: true);
});
