<?php

use App\Models\School;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Illuminate\Support\Facades\Storage;

/*
| Documents are downloaded through one route per document, as attachments
| under their original name and with nosniff. Visitors get only published
| documents of active schools; dashboard users also get their own school's
| unpublished ones.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->published = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->published()->withFile('Odluka o upisu.pdf'));
    $this->unpublished = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->withFile('Nacrt.pdf'));
});

it('sends a published document as an attachment with its original name', function () {
    $response = $this->get($this->published->downloadUrl());

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('Odluka o upisu.pdf');

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->streamedContent())->toBe(DocumentFactory::pdf());
});

it('keeps non-ASCII names in the UTF-8 file name with an ASCII fallback', function () {
    $document = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->published()->withFile('Godišnji plan 2026.pdf'));

    $disposition = $this->get($document->downloadUrl())->headers->get('Content-Disposition');

    expect($disposition)->toContain('filename="Godisnji plan 2026.pdf"')
        ->and($disposition)->toContain("filename*=utf-8''Godi%C5%A1nji%20plan%202026.pdf");
});

it('sends the content type of the document format', function () {
    $document = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->published());
    $document->addMedia(documentUpload('Proracun.xlsx', 'xlsx-unordered'))->toMediaCollection('file');

    $this->get($document->downloadUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload('Proracun.xlsx');
});

it('does not serve unpublished, trashed or missing documents to visitors', function () {
    $trashed = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->published()->withFile());
    $trashed->delete();
    $withoutFile = createDocument($this->school, configure: fn (DocumentFactory $f) => $f->published());

    $this->get($this->unpublished->downloadUrl())->assertNotFound();
    $this->get($trashed->downloadUrl())->assertNotFound();
    $this->get($withoutFile->downloadUrl())->assertNotFound();
    $this->get('/files/documents/999999')->assertNotFound();
});

it('does not serve documents of an inactive school', function () {
    $this->school->update(['is_active' => false]);

    $this->get($this->published->downloadUrl())->assertNotFound();
});

it('serves an editor their own school\'s unpublished document, without caching', function () {
    $this->actingAs(User::factory()->editor($this->school)->create());

    $this->get($this->unpublished->downloadUrl())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('Nacrt.pdf');

    expect($this->get($this->unpublished->downloadUrl())->headers->get('Cache-Control'))->toContain('no-store');
});

it('does not serve another school\'s unpublished document to an editor', function () {
    $this->actingAs(User::factory()->editor(School::factory()->create())->create());

    $this->get($this->unpublished->downloadUrl())->assertNotFound();
    // Published documents are public, whoever asks.
    $this->get($this->published->downloadUrl())->assertOk();
});

it('keeps the files out of the public disk', function () {
    Storage::disk('public')->assertDirectoryEmpty('/');
    Storage::disk('local')->assertExists($this->published->file()->getPathRelativeToRoot());
});
