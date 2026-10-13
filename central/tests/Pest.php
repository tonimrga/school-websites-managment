<?php

use App\Models\Article;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Database\Factories\ArticleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
