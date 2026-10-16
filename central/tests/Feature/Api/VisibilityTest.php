<?php

use App\Models\School;
use App\Models\User;
use Database\Factories\ArticleFactory;
use Database\Factories\DocumentFactory;
use Illuminate\Support\Facades\Storage;

/*
| The API returns only public content: live articles (published, publish
| date reached, not in the trash) and published documents not in the trash,
| of an active school, and only the school named in the URL. Responses never
| contain user data or internal fields.
*/

dataset('api endpoints', ['', '/home', '/articles', '/documents', '/sitemap']);
dataset('all api endpoints', ['', '/home', '/articles', '/documents', '/sitemap', '/articles/objavljeno']);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = School::factory()->create();
    $this->other = School::factory()->create();

    $this->live = createArticle($this->school, ['slug' => 'objavljeno', 'is_featured' => true], fn (ArticleFactory $f) => $f->published()->withCover());
    $this->draft = createArticle($this->school, ['slug' => 'nacrt', 'is_featured' => true]);
    $this->scheduled = createArticle($this->school, ['slug' => 'zakazano', 'is_featured' => true], fn (ArticleFactory $f) => $f->scheduled());
    $this->trashed = createArticle($this->school, ['slug' => 'obrisano', 'is_featured' => true], fn (ArticleFactory $f) => $f->published());
    $this->trashed->delete();
    $this->foreign = createArticle($this->other, ['slug' => 'tude', 'is_featured' => true], fn (ArticleFactory $f) => $f->published());

    $this->document = createDocument($this->school, ['title' => 'Javni dokument'], fn (DocumentFactory $f) => $f->published()->withFile());
    createDocument($this->school, ['title' => 'Neobjavljeni dokument'], fn (DocumentFactory $f) => $f->withFile());
    $trashedDocument = createDocument($this->school, ['title' => 'Obrisani dokument'], fn (DocumentFactory $f) => $f->published()->withFile());
    $trashedDocument->delete();
    createDocument($this->other, ['title' => 'Tuđi dokument'], fn (DocumentFactory $f) => $f->published()->withFile());
});

/**
 * Every article slug and document title in an API response.
 *
 * @return list<string>
 */
function visibleContent(array $json): array
{
    $found = [];

    array_walk_recursive($json, function (mixed $value, string|int $key) use (&$found): void {
        if (in_array($key, ['slug', 'title'], true) && is_string($value)) {
            $found[] = $value;
        }
    });

    return $found;
}

it('lists only live articles of this school', function (string $path) {
    $content = visibleContent($this->getJson(schoolApi($this->school, $path))->assertOk()->json());

    expect($content)->toContain('objavljeno')
        ->not->toContain('nacrt')
        ->not->toContain('zakazano')
        ->not->toContain('obrisano')
        ->not->toContain('tude');
})->with(['/home', '/articles', '/sitemap']);

it('returns 404 for an article that is not live or belongs to another school', function (string $slug) {
    $this->getJson(schoolApi($this->school, '/articles/'.$slug))
        ->assertNotFound()
        ->assertExactJson(['error' => 'not_found']);
})->with(['nacrt', 'zakazano', 'obrisano', 'tude']);

it('shows a scheduled article once its publish date is reached', function () {
    $this->travelTo($this->scheduled->published_at->addMinute());

    $this->getJson(schoolApi($this->school, '/articles/zakazano'))->assertOk();
});

it('never shows articles that are not live as recent articles', function () {
    $response = $this->getJson(schoolApi($this->school, '/articles/objavljeno'))->assertOk();

    expect($response->json('recent'))->toBe([]);
});

it('lists only published documents of this school that are not in the trash', function () {
    $content = visibleContent($this->getJson(schoolApi($this->school, '/documents'))->assertOk()->json());

    expect($content)->toContain('Javni dokument')
        ->not->toContain('Neobjavljeni dokument')
        ->not->toContain('Obrisani dokument')
        ->not->toContain('Tuđi dokument');
});

it('returns 404 on every endpoint of an inactive school', function (string $path) {
    $this->school->update(['is_active' => false]);

    $this->getJson(schoolApi($this->school, $path))
        ->assertNotFound()
        ->assertExactJson(['error' => 'not_found']);
})->with('all api endpoints');

it('returns 404 on every endpoint of an unknown school', function (string $path) {
    $this->getJson('/api/v1/schools/ne-postoji'.$path)
        ->assertNotFound()
        ->assertExactJson(['error' => 'not_found']);
})->with('all api endpoints');

it('ignores a logged-in dashboard user', function (string $path) {
    // Neither the tenant scope nor the school switcher applies to the API.
    $this->actingAs(User::factory()->editor($this->other)->create());

    $content = visibleContent($this->getJson(schoolApi($this->school, $path))->assertOk()->json());

    expect($content)->not->toContain('tude')->not->toContain('Tuđi dokument')->not->toContain('nacrt');

    if ($path !== '' && $path !== '/documents') {
        expect($content)->toContain('objavljeno');
    }
})->with('api endpoints');

it('never returns user data, internal ids or stored images', function (string $path) {
    $editor = User::factory()->editor($this->school)->create(['name' => 'Ivana Urednica', 'email' => 'ivana@dom.example.com']);
    $this->live->forceFill(['created_by' => $editor->id, 'updated_by' => $editor->id])->saveQuietly();

    $json = $this->getJson(schoolApi($this->school, $path))->assertOk()->json();
    $keys = [];
    array_walk_recursive($json, function (mixed $value, string|int $key) use (&$keys): void {
        $keys[] = $key;
    });
    $body = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $cover = $this->live->getFirstMedia('cover');

    expect($keys)->not->toContain('id')
        ->not->toContain('school_id')
        ->not->toContain('created_by')
        ->not->toContain('updated_by')
        ->not->toContain('is_active')
        ->not->toContain('status')
        ->not->toContain('deleted_at')
        ->not->toContain('uuid')
        ->and($body)->not->toContain('Ivana Urednica')
        ->not->toContain('ivana@dom.example.com')
        ->not->toContain($cover->getUrl())
        ->not->toContain($this->document->file()->getPathRelativeToRoot());
})->with('all api endpoints');
