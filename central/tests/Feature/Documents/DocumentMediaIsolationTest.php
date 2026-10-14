<?php

use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Models\Document;
use App\Models\School;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| An editor of school A cannot upload or replace the file of a school B
| document: not through B's pages, and not by injecting B's media UUIDs into
| requests for A's own document.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
    $this->editorA = User::factory()->editor($this->schoolA)->create();

    $withFile = fn (DocumentFactory $factory) => $factory->withFile();

    $this->documentA = createDocument($this->schoolA, configure: $withFile);
    $this->documentB = createDocument($this->schoolB, configure: $withFile);
    $this->mediaB = $this->documentB->file();

    $this->actingAs($this->editorA);
});

/**
 * @return array<string, mixed>
 */
function documentMediaSnapshot(Document $document): array
{
    return Media::query()
        ->where('model_type', $document->getMorphClass())
        ->where('model_id', $document->id)
        ->orderBy('id')
        ->get()
        ->map(fn (Media $media): array => $media->only(['id', 'uuid', 'model_id', 'collection_name', 'file_name', 'name']))
        ->all();
}

it('cannot open another school\'s document form to upload or replace its file', function () {
    $this->get("/admin/documents/{$this->documentB->id}/edit")->assertNotFound();

    Livewire::test(EditDocument::class, ['record' => $this->documentB->getRouteKey()])
        ->assertNotFound();
});

it('ignores another school\'s file injected into the file state', function () {
    $before = documentMediaSnapshot($this->documentB);
    $mediaA = $this->documentA->file();

    Livewire::test(EditDocument::class, ['record' => $this->documentA->getRouteKey()])
        ->set('data.file', [$this->mediaB->uuid => $this->mediaB->uuid])
        ->call('save');

    expect(documentMediaSnapshot($this->documentB))->toBe($before)
        ->and(Media::find($this->mediaB->id)->model_id)->toBe($this->documentB->id)
        ->and($this->documentA->fresh()->getMedia('file')->pluck('id')->all())->not->toContain($this->mediaB->id);

    Storage::disk('local')->assertExists($this->mediaB->getPathRelativeToRoot());
});

it('replaces only its own file and leaves the other school\'s file alone', function () {
    $before = documentMediaSnapshot($this->documentB);
    $oldA = $this->documentA->file();

    Livewire::test(EditDocument::class, ['record' => $this->documentA->getRouteKey()])
        ->fillForm(['file' => documentUpload('zamjena.pdf', 'pdf')])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->documentA->fresh()->file()->name)->toBe('zamjena')
        ->and(Media::find($oldA->id))->toBeNull()
        ->and(documentMediaSnapshot($this->documentB))->toBe($before);

    Storage::disk('local')->assertExists($this->mediaB->getPathRelativeToRoot());
});
