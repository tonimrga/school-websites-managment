<?php

use App\Models\School;
use App\Models\User;
use Tests\Fixtures\Models\Note;

beforeEach(function () {
    $this->schoolA = School::factory()->create(['name' => 'A škola']);
    $this->schoolB = School::factory()->create(['name' => 'B škola']);

    $this->noteA = createNote($this->schoolA, ['title' => 'Bilješka A']);
    $this->noteB = createNote($this->schoolB, ['title' => 'Bilješka B']);
});

it('limits queries to the editor\'s own school', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create());

    expect(Note::pluck('title')->all())->toBe(['Bilješka A'])
        ->and(Note::find($this->noteB->id))->toBeNull()
        ->and(Note::whereKey($this->noteB->id)->exists())->toBeFalse();
});

it('limits queries to the school selected by the super admin', function () {
    actingAsSuperAdmin($this->schoolB);

    expect(Note::pluck('title')->all())->toBe(['Bilješka B']);
});

it('falls back to the first school by name when the super admin has not picked one', function () {
    actingAsSuperAdmin();

    expect(Note::pluck('title')->all())->toBe(['Bilješka A']);
});

it('returns nothing for a logged-in user without a school context', function () {
    $orphan = User::factory()->editor()->create();
    $orphan->school_id = null;
    $this->actingAs($orphan);

    expect(Note::count())->toBe(0);
});

it('does not scope queries without a logged-in user', function () {
    expect(Note::count())->toBe(2);
});

it('sets school_id from the current school on create', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create());

    $note = Note::create(['title' => 'Nova']);

    expect($note->school_id)->toBe($this->schoolA->id);
});

it('ignores a school_id passed as input on create', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create());

    $note = Note::create(['title' => 'Nova', 'school_id' => $this->schoolB->id]);

    expect($note->fresh()->school_id)->toBe($this->schoolA->id);
});

it('overwrites a school_id set directly in code with the current school on create', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create());

    $note = new Note(['title' => 'Nova']);
    $note->school_id = $this->schoolB->id;
    $note->save();

    expect($note->fresh()->school_id)->toBe($this->schoolA->id);
});

it('creates content in the school selected by the super admin', function () {
    actingAsSuperAdmin($this->schoolB);

    expect(Note::create(['title' => 'Nova'])->school_id)->toBe($this->schoolB->id);
});

it('refuses to create content without a school when nobody is logged in', function () {
    Note::create(['title' => 'Nova']);
})->throws(LogicException::class);

it('refuses to create content for a logged-in user without a school context', function () {
    $orphan = User::factory()->editor()->create();
    $orphan->school_id = null;
    $this->actingAs($orphan);

    Note::create(['title' => 'Nova']);
})->throws(LogicException::class);

it('refuses to move a record to another school', function () {
    $this->actingAs(User::factory()->editor($this->schoolA)->create());

    $this->noteA->school_id = $this->schoolB->id;
    $this->noteA->save();
})->throws(LogicException::class);
