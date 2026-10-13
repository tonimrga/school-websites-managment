<?php

use App\Livewire\SchoolSwitcher;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Livewire\Livewire;
use Tests\Fixtures\Filament\Resources\Notes\Pages\ListNotes;

beforeEach(function () {
    $this->schoolA = School::factory()->create(['name' => 'A škola']);
    $this->schoolB = School::factory()->create(['name' => 'B škola']);
});

it('shows the switcher to the super admin', function () {
    actingAsSuperAdmin();

    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(SchoolSwitcher::class)
        ->assertSee('A škola')
        ->assertSee('B škola');
});

it('marks inactive schools in the switcher', function () {
    School::factory()->inactive()->create(['name' => 'C škola']);
    actingAsSuperAdmin();

    Livewire::test(SchoolSwitcher::class)->assertSee('C škola (neaktivan)');
});

it('does not show the switcher to an editor', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create());

    $this->get('/admin')
        ->assertOk()
        ->assertDontSeeLivewire(SchoolSwitcher::class);
});

it('forbids an editor from using the switcher directly', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create());

    Livewire::test(SchoolSwitcher::class)->assertForbidden();
});

it('switches the super admin to another school', function () {
    actingAsSuperAdmin();
    $noteA = createNote($this->schoolA);
    $noteB = createNote($this->schoolB);

    Livewire::test(SchoolSwitcher::class)
        ->assertSet('schoolId', $this->schoolA->id)
        ->set('schoolId', $this->schoolB->id)
        ->assertRedirect();

    expect(app(CurrentSchool::class)->id())->toBe($this->schoolB->id);

    Livewire::test(ListNotes::class)
        ->assertCanSeeTableRecords([$noteB])
        ->assertCanNotSeeTableRecords([$noteA]);
});

it('ignores an unknown school id', function () {
    actingAsSuperAdmin($this->schoolB);

    Livewire::test(SchoolSwitcher::class)
        ->set('schoolId', 999999)
        ->assertNoRedirect()
        ->assertSet('schoolId', $this->schoolB->id);

    expect(app(CurrentSchool::class)->id())->toBe($this->schoolB->id);
});

it('does not let the editor\'s session override their own school', function () {
    $editor = User::factory()->editor($this->schoolA)->create();
    $this->actingAs($editor);

    session()->put(CurrentSchool::SESSION_KEY, $this->schoolB->id);

    expect(app(CurrentSchool::class)->id())->toBe($this->schoolA->id);
});
