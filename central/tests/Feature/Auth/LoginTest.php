<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Attempt a dashboard login through the Filament login page.
 */
function attemptLogin(string $email, string $password): Testable
{
    return Livewire::test(Login::class)
        ->set('data.email', $email)
        ->set('data.password', $password)
        ->call('authenticate');
}

it('shows the login page in Croatian', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Prijavi se');
});

it('redirects guests from the dashboard to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets the super admin log in and open the dashboard', function () {
    $admin = User::factory()->superAdmin()->create(['password' => 'correct-horse-battery']);

    attemptLogin($admin->email, 'correct-horse-battery')
        ->assertHasNoErrors()
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
    $this->get('/admin')->assertOk();
});

it('records the last login time', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    attemptLogin($user->email, 'correct-horse-battery');

    expect($user->fresh()->last_login_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    attemptLogin($user->email, 'wrong-password')->assertHasErrors(['data.email']);

    $this->assertGuest();
});

it('rejects an inactive user with the generic failure message', function () {
    $user = User::factory()->inactive()->create(['password' => 'correct-horse-battery']);

    attemptLogin($user->email, 'correct-horse-battery')
        ->assertHasErrors(['data.email' => __('filament-panels::auth/pages/login.messages.failed')]);

    $this->assertGuest();
});

it('forbids an inactive user who still has a session from opening the dashboard', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('blocks login after 5 failed attempts for the same email and IP', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin($user->email, 'wrong-password')->assertHasErrors(['data.email']);
    }

    attemptLogin($user->email, 'correct-horse-battery')
        ->assertNotified(__('filament-panels::auth/pages/login.notifications.throttled.title', ['seconds' => 60, 'minutes' => 1]));

    $this->assertGuest();
});

it('treats the email case-insensitively when counting failed attempts', function () {
    $user = User::factory()->create(['email' => 'urednik@example.com', 'password' => 'correct-horse-battery']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin('URednik@Example.com', 'wrong-password');
    }

    attemptLogin('urednik@example.com', 'correct-horse-battery');

    $this->assertGuest();
});

it('does not block a different email from the same IP', function () {
    User::factory()->create(['email' => 'prvi@example.com']);
    $other = User::factory()->create(['email' => 'drugi@example.com', 'password' => 'correct-horse-battery']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin('prvi@example.com', 'wrong-password');
    }

    attemptLogin($other->email, 'correct-horse-battery')->assertHasNoErrors();

    $this->assertAuthenticatedAs($other);
});

it('resets the failed attempt counter after a successful login', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    foreach (range(1, 4) as $attempt) {
        attemptLogin($user->email, 'wrong-password');
    }

    attemptLogin($user->email, 'correct-horse-battery')->assertHasNoErrors();
    auth()->logout();

    foreach (range(1, 4) as $attempt) {
        attemptLogin($user->email, 'wrong-password');
    }

    attemptLogin($user->email, 'correct-horse-battery')->assertHasNoErrors();
    $this->assertAuthenticatedAs($user);
});
