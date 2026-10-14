<?php

use App\Models\Article;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Database\Factories\ArticleFactory;
use Database\Factories\DocumentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Models\Note;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a placeholder content record for a school, bypassing the tenant
 * hooks so it can be created whoever is logged in.
 */
function createNote(School $school, array $attributes = []): Note
{
    $note = new Note(['title' => 'Bilješka', ...$attributes]);
    $note->school_id = $school->id;

    Note::withoutEvents(fn () => $note->save());

    return $note;
}

/**
 * Create an article for a school, whoever is logged in. The factory can be
 * configured further, e.g. fn (ArticleFactory $f) => $f->published()->withCover().
 *
 * @param  (Closure(ArticleFactory): ArticleFactory)|null  $configure
 */
function createArticle(School $school, array $attributes = [], ?Closure $configure = null): Article
{
    $factory = Article::factory()->for($school);

    if ($configure !== null) {
        $factory = $configure($factory);
    }

    return withoutUser(fn (): Article => $factory->create($attributes));
}

/**
 * Create a document category for a school, whoever is logged in.
 */
function createDocumentCategory(School $school, array $attributes = []): DocumentCategory
{
    return withoutUser(fn (): DocumentCategory => DocumentCategory::factory()->for($school)->create($attributes));
}

/**
 * Create a document for a school, whoever is logged in. Without a category
 * it goes into the school's first (default) category. The factory can be
 * configured further, e.g. fn (DocumentFactory $f) => $f->published()->withFile().
 *
 * @param  (Closure(DocumentFactory): DocumentFactory)|null  $configure
 */
function createDocument(School $school, array $attributes = [], ?Closure $configure = null): Document
{
    $attributes['document_category_id'] ??= DocumentCategory::withoutGlobalScopes()
        ->where('school_id', $school->id)
        ->orderBy('sort_order')
        ->value('id');

    $factory = Document::factory()->for($school);

    if ($configure !== null) {
        $factory = $configure($factory);
    }

    return withoutUser(fn (): Document => $factory->create($attributes));
}

/**
 * The contents of a small generated file of the given kind, built in PHP:
 * pdf, doc, xls, docx, xlsx, odt, ods (real documents), docx-unordered and
 * xlsx-unordered (valid, but in an entry order fileinfo only sees as zip),
 * and ole, zip, exe, html, svg, php, jpeg (not documents).
 */
function documentFixture(string $kind): string
{
    $contentTypes = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>';
    $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>';

    return match ($kind) {
        'pdf' => DocumentFactory::pdf(),
        'doc' => oleFixture('WordDocument'),
        'xls' => oleFixture('Workbook'),
        'ole' => oleFixture(),
        'docx' => zipFixture(['[Content_Types].xml' => $contentTypes, '_rels/.rels' => $rels, 'word/document.xml' => '<w:document/>']),
        'xlsx' => zipFixture(['[Content_Types].xml' => $contentTypes, '_rels/.rels' => $rels, 'xl/workbook.xml' => '<workbook/>']),
        'docx-unordered' => zipFixture(['docProps/app.xml' => '<Properties/>', '[Content_Types].xml' => $contentTypes, 'word/document.xml' => '<w:document/>', '_rels/.rels' => $rels]),
        'xlsx-unordered' => zipFixture(['docProps/app.xml' => '<Properties/>', '[Content_Types].xml' => $contentTypes, 'xl/workbook.xml' => '<workbook/>', '_rels/.rels' => $rels]),
        'odt' => zipFixture(['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<office:document-content/>']),
        'ods' => zipFixture(['mimetype' => 'application/vnd.oasis.opendocument.spreadsheet', 'content.xml' => '<office:document-content/>']),
        'zip' => zipFixture(['readme.txt' => 'Nije dokument.']),
        'exe' => "MZ\x90\0\3\0\0\0".str_repeat("\0", 56)."PE\0\0",
        'html' => '<html><body><script>alert(1)</script></body></html>',
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        'php' => '<?php echo "hacked"; ?>',
        'jpeg' => jpegFixture(),
    };
}

function jpegFixture(): string
{
    // Keep the fake file referenced: its temporary file is removed with it.
    $image = UploadedFile::fake()->image('slika.jpg', 10, 10);

    return (string) file_get_contents($image->getPathname());
}

/**
 * An uploadable file with the given name and contents of documentFixture($kind).
 */
function documentUpload(string $name, string $kind): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, documentFixture($kind));
}

/**
 * A zip archive with the given entries, in the given order.
 *
 * @param  array<string, string>  $entries
 */
function zipFixture(array $entries): string
{
    $path = tempnam(sys_get_temp_dir(), 'zip-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
        // OpenDocument requires the mimetype entry to be stored uncompressed.
        if ($name === 'mimetype') {
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }
    }

    $zip->close();
    $contents = (string) file_get_contents($path);
    @unlink($path);

    return $contents;
}

/**
 * A minimal OLE compound file (the container of DOC and XLS) whose directory
 * holds one stream with the given name, or only the root entry.
 */
function oleFixture(string $streamName = ''): string
{
    $free = 0xFFFFFFFF;
    $endOfChain = 0xFFFFFFFE;

    $header = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 16)
        .pack('vvvvV', 0x3E, 3, 0xFFFE, 9, 6)
        .pack('VVVVVVVVVVV', 0, 0, 1, 1, 0, 4096, $endOfChain, 0, $endOfChain, 0, 0)
        .str_repeat(pack('V', $free), 108);

    // Sector 0: the FAT (itself, then the one-sector directory chain).
    $fat = pack('VV', 0xFFFFFFFD, $endOfChain).str_repeat(pack('V', $free), 126);

    $entry = function (string $name, int $type, int $child) use ($free, $endOfChain): string {
        $utf16 = mb_convert_encoding($name, 'UTF-16LE', 'UTF-8')."\0\0";

        return str_pad($utf16, 64, "\0").pack('v', strlen($utf16)).chr($type).chr(1)
            .pack('VVV', $free, $free, $child).str_repeat("\0", 16).pack('V', 0).str_repeat("\0", 16)
            .pack('VVV', $endOfChain, 0, 0);
    };

    // Sector 1: the directory.
    $directory = $entry('Root Entry', 5, $streamName === '' ? $free : 1);

    if ($streamName !== '') {
        $directory .= $entry($streamName, 2, $free);
    }

    return $header.$fat.str_pad($directory, 512, "\0");
}

/**
 * Run a callback as if nobody were logged in (no tenant scope, no
 * automatic school_id), then restore the logged-in user.
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function withoutUser(Closure $callback): mixed
{
    $guard = auth()->guard();
    $user = $guard->user();

    $guard->forgetUser();

    try {
        return $callback();
    } finally {
        if ($user !== null) {
            $guard->setUser($user);
        }
    }
}

/**
 * A real JPEG with an EXIF block holding an orientation and a GPS position,
 * like a phone photo. Returns the path of a temporary file.
 */
function jpegWithExif(int $width, int $height, int $orientation = 1): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 60, 60));

    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();

    // Little-endian TIFF: IFD0 (Orientation, GPS pointer) at 8, GPS IFD
    // (latitude ref + latitude) at 38, latitude rationals at 68.
    $entry = fn (int $tag, int $type, int $count, string $value): string => pack('vvV', $tag, $type, $count).str_pad($value, 4, "\0");

    $tiff = 'II'.pack('vV', 42, 8)
        .pack('v', 2)
        .$entry(0x0112, 3, 1, pack('v', $orientation))
        .$entry(0x8825, 4, 1, pack('V', 38))
        .pack('V', 0)
        .pack('v', 2)
        .$entry(0x0001, 2, 2, "N\0")
        .$entry(0x0002, 5, 3, pack('V', 68))
        .pack('V', 0)
        .pack('VVVVVV', 45, 1, 48, 1, 0, 1);

    $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;

    $base = tempnam(sys_get_temp_dir(), 'exif-');
    $path = $base.'.jpg';
    rename($base, $path);
    file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

    return $path;
}

/**
 * Log in as the super admin with the given school selected in the switcher.
 */
function actingAsSuperAdmin(?School $school = null): User
{
    $admin = User::factory()->superAdmin()->create();

    test()->actingAs($admin);

    if ($school !== null) {
        app(CurrentSchool::class)->select($school);
    }

    return $admin;
}

/**
 * Fake the breached-password API used by Password::uncompromised(). Any
 * password listed in $breached is reported as leaked.
 */
function fakePwnedPasswords(array $breached = []): void
{
    Http::fake(function (Request $request) use ($breached) {
        $prefix = strtoupper(substr($request->url(), -5));

        $lines = collect($breached)
            ->map(fn (string $password): string => strtoupper(sha1($password)))
            ->filter(fn (string $hash): bool => str_starts_with($hash, $prefix))
            ->map(fn (string $hash): string => substr($hash, 5).':1000');

        return Http::response($lines->implode("\r\n"));
    });
}
