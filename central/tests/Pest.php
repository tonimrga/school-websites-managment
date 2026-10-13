<?php

use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
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
