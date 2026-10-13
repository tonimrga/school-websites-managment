<?php

use App\Filament\Pages\Auth\Login;
use App\Models\School;
use App\Models\User;
use Livewire\Livewire;

it('rejects login for an editor of an inactive school', function () {
    $school = School::factory()->inactive()->create();
    $editor = User::factory()->editor($school)->create(['password' => 'correct-horse-battery']);

    Livewire::test(Login::class)
        ->set('data.email', $editor->email)
        ->set('data.password', 'correct-horse-battery')
        ->call('authenticate')
        ->assertHasErrors(['data.email' => __('filament-panels::auth/pages/login.messages.failed')]);

    $this->assertGuest();
});

it('locks out a logged-in editor once their school is deactivated', function () {
    $school = School::factory()->create();
    $editor = User::factory()->editor($school)->create();

    $this->actingAs($editor)->get('/admin')->assertOk();

    $school->update(['is_active' => false]);

    $this->actingAs($editor->fresh())->get('/admin')->assertForbidden();
});

it('still lets the super admin in when schools are inactive', function () {
    School::factory()->inactive()->create();

    actingAsSuperAdmin();

    $this->get('/admin')->assertOk();
});

it('locks out an editor without a school', function () {
    $editor = User::factory()->editor()->create();
    $editor->forceFill(['school_id' => null])->save();

    $this->actingAs($editor)->get('/admin')->assertForbidden();
});
