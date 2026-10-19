<?php

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Activity;
use App\Models\School;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/*
| Creating and deactivating users is logged by the super admin, without the
| password.
*/

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = actingAsSuperAdmin();
    fakePwnedPasswords([]);
});

it('logs creating an editor without the password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Ana Anić',
            'email' => 'ana@example.com',
            'school_id' => $this->school->id,
            'password' => 'konj-baterija-spajalica',
            'password_confirmation' => 'konj-baterija-spajalica',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'ana@example.com')->sole();
    $activity = Activity::where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)->sole();

    expect($activity->event)->toBe('created')
        ->and($activity->causer->is($this->admin))->toBeTrue()
        ->and($activity->school_id)->toBe($this->school->id)
        ->and($activity->properties['attributes'])->toHaveKeys(['name', 'email', 'role', 'school_id', 'is_active'])
        ->and($activity->properties['attributes'])->not->toHaveKey('password');
});

it('logs deactivating a user', function () {
    $editor = User::factory()->editor($this->school)->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('deactivate')->table($editor));

    $activity = Activity::where('subject_id', $editor->id)->where('event', 'updated')->latest('id')->firstOrFail();

    expect($activity->properties['attributes']['is_active'])->toBeFalse()
        ->and($activity->properties['old']['is_active'])->toBeTrue();
});

it('shows login events in the activity log', function () {
    $editor = User::factory()->editor($this->school)->create(['name' => 'Urednica A']);
    activity()->causedBy($editor)->performedOn($editor)->event('login')->log('login');
    activity()->event('login_failed')->withProperties(['attributes' => ['email' => 'nepoznat@example.com']])->log('login_failed');

    Livewire::test(ListActivities::class)
        ->assertOk()
        ->assertSee('Prijava')
        ->assertSee('Neuspjela prijava')
        ->assertSee('Korisnik: Urednica A')
        ->assertSee('nepoznat@example.com');
});
