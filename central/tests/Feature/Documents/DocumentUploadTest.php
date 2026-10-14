<?php

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use App\Support\DocumentFileType;
use Database\Factories\DocumentFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| Only real PDF, DOC/DOCX, XLS/XLSX and ODT/ODS files of at most 20 MB are
| accepted, checked by contents and extension together. SVG, HTML and
| executables are rejected even when renamed. Files are stored under random
| names on the non-public documents disk, keeping the original name.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->editor = User::factory()->editor($this->school)->create();
    $this->category = DocumentCategory::withoutGlobalScopes()->where('school_id', $this->school->id)->orderBy('sort_order')->first();

    $this->actingAs($this->editor);
});

function createDocumentWithFile(UploadedFile $file, string $title = 'Novi dokument'): Testable
{
    return Livewire::test(CreateDocument::class)
        ->fillForm([
            'title' => $title,
            'document_category_id' => test()->category->id,
            'file' => $file,
        ])
        ->call('create');
}

it('accepts every allowed document format', function (string $name, string $kind, string $mimeType) {
    createDocumentWithFile(documentUpload($name, $kind))->assertHasNoFormErrors();

    $media = Document::sole()->file();

    expect($media)->not->toBeNull()
        ->and($media->mime_type)->toBe($mimeType)
        ->and($media->disk)->toBe('local');

    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
})->with([
    'PDF' => ['odluka.pdf', 'pdf', 'application/pdf'],
    'DOC' => ['zapisnik.doc', 'doc', 'application/msword'],
    'DOCX' => ['zapisnik.docx', 'docx', DocumentFileType::MIME_TYPES['docx']],
    'DOCX in another entry order' => ['zapisnik.docx', 'docx-unordered', DocumentFileType::MIME_TYPES['docx']],
    'XLS' => ['proracun.xls', 'xls', 'application/vnd.ms-excel'],
    'XLSX' => ['proracun.xlsx', 'xlsx', DocumentFileType::MIME_TYPES['xlsx']],
    'XLSX in another entry order' => ['proracun.xlsx', 'xlsx-unordered', DocumentFileType::MIME_TYPES['xlsx']],
    'ODT' => ['plan.odt', 'odt', DocumentFileType::MIME_TYPES['odt']],
    'ODS' => ['plan.ods', 'ods', DocumentFileType::MIME_TYPES['ods']],
    'upper-case extension' => ['ODLUKA.PDF', 'pdf', 'application/pdf'],
]);

it('rejects files that are not allowed documents, even when renamed', function (string $name, string $kind) {
    createDocumentWithFile(documentUpload($name, $kind))->assertHasFormErrors(['file']);

    expect(Document::count())->toBe(0)
        ->and(Media::count())->toBe(0);
})->with([
    'SVG' => ['crtez.svg', 'svg'],
    'SVG renamed to .pdf' => ['crtez.pdf', 'svg'],
    'HTML' => ['stranica.html', 'html'],
    'HTML renamed to .pdf' => ['stranica.pdf', 'html'],
    'HTML renamed to .docx' => ['stranica.docx', 'html'],
    'PHP renamed to .xlsx' => ['skripta.xlsx', 'php'],
    'executable' => ['program.exe', 'exe'],
    'executable renamed to .pdf' => ['program.pdf', 'exe'],
    'executable renamed to .doc' => ['program.doc', 'exe'],
    'plain zip renamed to .docx' => ['arhiva.docx', 'zip'],
    'OLE file without a document renamed to .doc' => ['paket.doc', 'ole'],
    'DOCX renamed to .pdf' => ['zapisnik.pdf', 'docx'],
    'XLSX renamed to .docx' => ['proracun.docx', 'xlsx'],
    'PDF with a .php extension' => ['odluka.php', 'pdf'],
    'JPEG renamed to .pdf' => ['slika.pdf', 'jpeg'],
    'JPEG' => ['slika.jpg', 'jpeg'],
]);

it('rejects documents over 20 MB', function () {
    createDocumentWithFile(documentUpload('velik.pdf', 'pdf')->size(20 * 1024 + 1))
        ->assertHasFormErrors(['file']);

    expect(Document::count())->toBe(0);
});

it('accepts documents of exactly 20 MB', function () {
    createDocumentWithFile(documentUpload('velik.pdf', 'pdf')->size(20 * 1024))
        ->assertHasNoFormErrors();

    expect(Document::count())->toBe(1);
});

it('requires a file, a title and a category', function () {
    Livewire::test(CreateDocument::class)
        ->fillForm(['title' => '', 'document_category_id' => null, 'file' => null])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required', 'document_category_id' => 'required', 'file' => 'required']);
});

it('stores files under random names and keeps the original name for downloads', function () {
    createDocumentWithFile(documentUpload('Odluka o upisu 2026.pdf', 'pdf'))->assertHasNoFormErrors();

    $document = Document::sole();
    $media = $document->file();

    expect($media->name)->toBe('Odluka o upisu 2026')
        ->and($media->file_name)->not->toContain('Odluka')
        ->and($media->file_name)->toEndWith('.pdf')
        ->and($document->downloadName())->toBe('Odluka o upisu 2026.pdf');
});

it('publishes new documents by default and puts them at the end of their category', function () {
    $existing = createDocument($this->school, ['document_category_id' => $this->category->id]);

    createDocumentWithFile(documentUpload('novi.pdf', 'pdf'))->assertHasNoFormErrors();

    $document = Document::where('title', 'Novi dokument')->sole();

    expect($document->is_published)->toBeTrue()
        ->and($document->sort_order)->toBe($existing->sort_order + 1);
});

it('keeps the same document, position and link when the file is replaced', function () {
    $document = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->withFile('stari.pdf'));
    $oldMedia = $document->file();
    $url = $document->downloadUrl();
    $sortOrder = $document->sort_order;

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm(['file' => documentUpload('novi.docx', 'docx')])
        ->call('save')
        ->assertHasNoFormErrors();

    $document->refresh();

    expect(Document::count())->toBe(1)
        ->and($document->getMedia('file'))->toHaveCount(1)
        ->and($document->file()->name)->toBe('novi')
        ->and($document->sort_order)->toBe($sortOrder)
        ->and($document->downloadUrl())->toBe($url)
        ->and(Media::find($oldMedia->id))->toBeNull();

    Storage::disk('local')->assertMissing($oldMedia->getPathRelativeToRoot());
});

it('rejects non-documents at the model level too', function (string $name, string $kind) {
    $document = createDocument($this->school);

    expect(fn () => $document->addMedia(documentUpload($name, $kind))->toMediaCollection('file'))
        ->toThrow(FileUnacceptableForCollection::class);

    expect($document->fresh()->getMedia('file'))->toBeEmpty();
})->with([
    'HTML renamed to .pdf' => ['stranica.pdf', 'html'],
    'plain zip renamed to .docx' => ['arhiva.docx', 'zip'],
    'DOCX renamed to .pdf' => ['zapisnik.pdf', 'docx'],
    'JPEG renamed to .pdf' => ['slika.pdf', 'jpeg'],
]);

it('stores document files on the non-public documents disk', function () {
    $document = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->withFile());

    expect($document->file()->disk)->toBe(config('cms.documents.disk'))
        ->and(config('cms.documents.disk'))->not->toBe('public');

    Storage::disk('public')->assertDirectoryEmpty('/');
});

it('still limits images to 10 MB', function () {
    $article = createArticle($this->school);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['cover' => UploadedFile::fake()->image('velika.jpg', 100, 100)->size(10 * 1024 + 1)])
        ->call('save')
        ->assertHasFormErrors(['cover']);
});
