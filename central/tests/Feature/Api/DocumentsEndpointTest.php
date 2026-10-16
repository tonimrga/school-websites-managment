<?php

use App\Models\DocumentCategory;
use App\Models\School;
use Database\Factories\DocumentFactory;
use Illuminate\Support\Facades\Storage;

/*
| GET /documents lists the categories that have published documents, in
| their manual order, each with its documents in order. ?category= returns
| only that category's documents while still listing the others.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    // A school without the default categories, so only these exist.
    $this->school = School::withoutEvents(fn () => School::factory()->create());

    $this->natjecaji = createDocumentCategory($this->school, ['name' => 'Natječaji', 'slug' => 'natjecaji']);
    $this->prazna = createDocumentCategory($this->school, ['name' => 'Prazna', 'slug' => 'prazna']);
    $this->upisi = createDocumentCategory($this->school, ['name' => 'Upisi', 'slug' => 'upisi']);

    $published = fn (string $name) => fn (DocumentFactory $f) => $f->published()->withFile($name);

    $this->odluka = createDocument($this->school, [
        'document_category_id' => $this->natjecaji->id,
        'title' => 'Odluka o izboru',
        'description' => 'Odluka ravnatelja.',
        'document_date' => '2026-09-01',
    ], $published('Odluka o izboru.pdf'));
    $this->natjecaj = createDocument($this->school, ['document_category_id' => $this->natjecaji->id, 'title' => 'Natječaj za kuhara'], $published('natjecaj.pdf'));
    $this->upis = createDocument($this->school, ['document_category_id' => $this->upisi->id, 'title' => 'Upisni list'], $published('upisni-list.pdf'));

    // Not shown: unpublished, or published without a file.
    createDocument($this->school, ['document_category_id' => $this->prazna->id], fn (DocumentFactory $f) => $f->withFile());
    createDocument($this->school, ['document_category_id' => $this->prazna->id], fn (DocumentFactory $f) => $f->published());
});

/**
 * @return array<string, list<string>>
 */
function documentTitlesByCategory(array $categories): array
{
    return collect($categories)
        ->mapWithKeys(fn (array $category): array => [$category['slug'] => array_column($category['documents'], 'title')])
        ->all();
}

it('lists categories with published documents in order, each with its documents in order', function () {
    // Manual order: upisi first, documents of natjecaji reversed.
    DocumentCategory::withoutGlobalScopes()->whereKey($this->upisi->id)->update(['sort_order' => 0]);
    $this->natjecaj->forceFill(['sort_order' => 0])->saveQuietly();

    $response = $this->getJson(schoolApi($this->school, '/documents'))->assertOk();

    expect(array_keys($response->json()))->toBe(['school', 'categories'])
        ->and($response->json('school.slug'))->toBe($this->school->slug)
        ->and(documentTitlesByCategory($response->json('categories')))->toBe([
            'upisi' => ['Upisni list'],
            'natjecaji' => ['Natječaj za kuhara', 'Odluka o izboru'],
        ]);
});

it('returns categories and documents in the documented shape', function () {
    $media = $this->odluka->file();

    $category = collect($this->getJson(schoolApi($this->school, '/documents'))->json('categories'))->firstWhere('slug', 'natjecaji');

    expect(array_keys($category))->toBe(['name', 'slug', 'documents'])
        ->and($category['name'])->toBe('Natječaji')
        ->and($category['documents'][0])->toBe([
            'title' => 'Odluka o izboru',
            'description' => 'Odluka ravnatelja.',
            'document_date' => '2026-09-01',
            'file' => [
                'url' => $this->odluka->downloadUrl(),
                'name' => 'Odluka o izboru.pdf',
                'extension' => 'pdf',
                'size_bytes' => (int) $media->size,
            ],
        ])
        ->and($category['documents'][1]['description'])->toBeNull()
        ->and($category['documents'][1]['document_date'])->toBeNull();
});

it('returns only the selected category\'s documents but still lists the others', function () {
    $response = $this->getJson(schoolApi($this->school, '/documents?category=upisi'))->assertOk();

    expect(documentTitlesByCategory($response->json('categories')))->toBe([
        'natjecaji' => [],
        'upisi' => ['Upisni list'],
    ]);
});

it('lists a selected category even when it has no published documents', function () {
    $response = $this->getJson(schoolApi($this->school, '/documents?category=prazna'))->assertOk();

    expect(documentTitlesByCategory($response->json('categories')))->toBe([
        'natjecaji' => [],
        'prazna' => [],
        'upisi' => [],
    ]);
});

it('treats an empty category filter as no filter', function () {
    $response = $this->getJson(schoolApi($this->school, '/documents?category='))->assertOk();

    expect(documentTitlesByCategory($response->json('categories')))->toHaveKeys(['natjecaji', 'upisi'])
        ->and(documentTitlesByCategory($response->json('categories'))['upisi'])->toBe(['Upisni list']);
});

it('returns 404 for an unknown category or another school\'s category', function (string $query) {
    $other = School::factory()->create();
    createDocumentCategory($other, ['name' => 'Tuđa', 'slug' => 'tuda']);

    $this->getJson(schoolApi($this->school, '/documents?'.$query))
        ->assertNotFound()
        ->assertExactJson(['error' => 'not_found']);
})->with(['category=ne-postoji', 'category=tuda', 'category[]=upisi']);

it('returns an empty list for a school without published documents', function () {
    $school = School::factory()->create();

    $this->getJson(schoolApi($school, '/documents'))
        ->assertOk()
        ->assertJsonPath('categories', []);
});
