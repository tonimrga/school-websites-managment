<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\School;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const STRONG_PASSWORD = 'konj-baterija-spajalica';

describe('super admin', function () {
    beforeEach(function () {
        $this->school = School::factory()->create();
        $this->admin = actingAsSuperAdmin();
        fakePwnedPasswords(['lozinka123456']);
    });

    it('lists users', function () {
        $editors = User::factory()->count(2)->editor($this->school)->create();

        $this->get('/admin/users')->assertOk();

        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([...$editors, $this->admin]);
    });

    it('creates an editor for a school', function () {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Anić',
                'email' => 'ana@example.com',
                'school_id' => $this->school->id,
                'password' => STRONG_PASSWORD,
                'password_confirmation' => STRONG_PASSWORD,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'ana@example.com')->sole();

        expect($user->role)->toBe(UserRole::Editor)
            ->and($user->school_id)->toBe($this->school->id)
            ->and($user->is_active)->toBeTrue()
            ->and(Hash::check(STRONG_PASSWORD, $user->password))->toBeTrue();
    });

    it('never creates a super admin, even when the role is injected', function () {
        Livewire::test(CreateUser::class)
            ->set('data.role', UserRole::SuperAdmin->value)
            ->fillForm([
                'name' => 'Ana Anić',
                'email' => 'ana@example.com',
                'school_id' => $this->school->id,
                'password' => STRONG_PASSWORD,
                'password_confirmation' => STRONG_PASSWORD,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(User::where('email', 'ana@example.com')->sole()->role)->toBe(UserRole::Editor);
    });

    it('requires a school for a new editor', function () {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Anić',
                'email' => 'ana@example.com',
                'password' => STRONG_PASSWORD,
                'password_confirmation' => STRONG_PASSWORD,
            ])
            ->call('create')
            ->assertHasFormErrors(['school_id' => 'required']);
    });

    it('rejects an unknown school', function () {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Anić',
                'email' => 'ana@example.com',
                'school_id' => 999999,
                'password' => STRONG_PASSWORD,
                'password_confirmation' => STRONG_PASSWORD,
            ])
            ->call('create')
            ->assertHasFormErrors(['school_id']);
    });

    it('rejects short and breached passwords', function (string $password) {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Anić',
                'email' => 'ana@example.com',
                'school_id' => $this->school->id,
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);

        expect(User::where('email', 'ana@example.com')->exists())->toBeFalse();
    })->with([
        'shorter than 12 characters' => 'kratka-1234',
        'found in a data breach' => 'lozinka123456',
    ]);

    it('requires a unique email', function () {
        User::factory()->create(['email' => 'ana@example.com']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Ana Anić', 'email' => 'ana@example.com'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    });

    it('edits an editor and moves them to another school', function () {
        $editor = User::factory()->editor($this->school)->create();
        $otherSchool = School::factory()->create();

        $this->get("/admin/users/{$editor->id}/edit")->assertOk();

        Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
            ->fillForm(['name' => 'Novo Ime', 'school_id' => $otherSchool->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($editor->fresh())
            ->name->toBe('Novo Ime')
            ->school_id->toBe($otherSchool->id);
    });

    it('keeps the password when the field is left empty and changes it when filled', function () {
        $editor = User::factory()->editor($this->school)->create(['password' => 'stara-lozinka-123']);

        Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
            ->fillForm(['name' => 'Novo Ime'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Hash::check('stara-lozinka-123', $editor->fresh()->password))->toBeTrue();

        Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
            ->fillForm(['password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Hash::check(STRONG_PASSWORD, $editor->fresh()->password))->toBeTrue();
    });

    it('does not give the super admin a school when editing their own account', function () {
        Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
            ->assertFormFieldHidden('school_id')
            ->fillForm(['name' => 'Glavni admin'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->admin->fresh())
            ->school_id->toBeNull()
            ->role->toBe(UserRole::SuperAdmin);
    });

    it('deactivates a user and ends their existing sessions', function () {
        $editor = User::factory()->editor($this->school)->create();
        $other = User::factory()->editor($this->school)->create();

        foreach ([$editor, $editor, $other] as $i => $user) {
            DB::table('sessions')->insert([
                'id' => "session-{$i}",
                'user_id' => $user->id,
                'payload' => '',
                'last_activity' => now()->timestamp,
            ]);
        }

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('deactivate')->table($editor));

        expect($editor->fresh()->is_active)->toBeFalse()
            ->and(DB::table('sessions')->where('user_id', $editor->id)->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
    });

    it('reactivates a user', function () {
        $editor = User::factory()->editor($this->school)->inactive()->create();

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('activate')->table($editor));

        expect($editor->fresh()->is_active)->toBeTrue();
    });

    it('cannot deactivate their own account', function () {
        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('deactivate')->table($this->admin));

        expect($this->admin->can('deactivate', $this->admin))->toBeFalse();
    });

    it('sends a password reset link', function () {
        Notification::fake();
        $editor = User::factory()->editor($this->school)->create();

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('sendPasswordReset')->table($editor))
            ->assertNotified('Poveznica za lozinku je poslana.');

        Notification::assertSentTo($editor, ResetPassword::class, function (ResetPassword $notification) {
            return str_contains($notification->url, '/admin/password-reset/reset');
        });
    });

    it('offers no way to delete a user', function () {
        $editor = User::factory()->editor($this->school)->create();

        Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
            ->assertActionDoesNotExist('delete');

        expect($this->admin->can('delete', $editor))->toBeFalse();
    });
});

describe('editor', function () {
    beforeEach(function () {
        $this->editor = User::factory()->editor()->create();
        $this->actingAs($this->editor);
    });

    it('cannot open user management', function () {
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get("/admin/users/{$this->editor->id}/edit")->assertForbidden();
    });

    it('does not see user management in the navigation', function () {
        $this->get('/admin')->assertOk()->assertDontSee('/admin/users');
    });
});
