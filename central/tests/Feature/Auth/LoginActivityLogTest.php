<?php

use App\Filament\Pages\Auth\Login;
use App\Models\Activity;
use App\Models\School;
use App\Models\User;
use Livewire\Livewire;

/*
| Logins and failed logins are written to the activity log, with the user's
| school when there is one. Passwords are never logged.
*/

function loginAs(string $email, string $password): void
{
    try {
        Livewire::test(Login::class)
            ->set('data.email', $email)
            ->set('data.password', $password)
            ->call('authenticate');
    } finally {
        auth()->logout();
    }
}

it('logs a successful login with the user and school', function () {
    $school = School::factory()->create();
    $editor = User::factory()->editor($school)->create(['password' => 'correct-horse-battery']);

    loginAs($editor->email, 'correct-horse-battery');

    $activity = Activity::where('event', 'login')->sole();

    expect($activity->causer->is($editor))->toBeTrue()
        ->and($activity->subject->is($editor))->toBeTrue()
        ->and($activity->school_id)->toBe($school->id);
});

it('logs a wrong password without logging the password', function () {
    $editor = User::factory()->create(['email' => 'urednik@example.com', 'password' => 'correct-horse-battery']);

    loginAs('urednik@example.com', 'pogresna-lozinka');

    $activity = Activity::where('event', 'login_failed')->sole();

    expect($activity->subject->is($editor))->toBeTrue()
        ->and($activity->causer)->toBeNull()
        ->and($activity->properties['attributes']['email'])->toBe('urednik@example.com')
        ->and(json_encode($activity->properties))->not->toContain('pogresna-lozinka')
        ->and(Activity::where('event', 'login')->exists())->toBeFalse();
});

it('logs a failed login for an unknown email and for an inactive user', function () {
    $inactive = User::factory()->inactive()->create(['password' => 'correct-horse-battery']);

    loginAs('nepoznat@example.com', 'bilo-sto');
    loginAs($inactive->email, 'correct-horse-battery');

    $failed = Activity::where('event', 'login_failed')->orderBy('id')->get();

    expect($failed)->toHaveCount(2)
        ->and($failed[0]->subject_id)->toBeNull()
        ->and($failed[0]->properties['attributes']['email'])->toBe('nepoznat@example.com')
        ->and($failed[1]->subject->is($inactive))->toBeTrue()
        ->and(Activity::where('event', 'login')->exists())->toBeFalse();
});
