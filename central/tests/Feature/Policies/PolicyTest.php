<?php

use App\Models\School;
use App\Models\User;
use Tests\Fixtures\Models\Note;

beforeEach(function () {
    $this->schoolA = School::factory()->create(['name' => 'A škola']);
    $this->schoolB = School::factory()->create(['name' => 'B škola']);
    $this->editorA = User::factory()->editor($this->schoolA)->create();
    $this->admin = User::factory()->superAdmin()->create();
});

describe('SchoolPolicy', function () {
    it('allows only the super admin to manage schools', function (string $ability, bool $needsRecord) {
        $arguments = $needsRecord ? $this->schoolA : School::class;

        expect($this->admin->can($ability, $arguments))->toBeTrue()
            ->and($this->editorA->can($ability, $arguments))->toBeFalse();
    })->with([
        ['viewAny', false],
        ['create', false],
        ['view', true],
        ['update', true],
    ]);

    it('allows nobody to delete schools', function () {
        expect($this->admin->can('delete', $this->schoolA))->toBeFalse()
            ->and($this->admin->can('deleteAny', School::class))->toBeFalse();
    });
});

describe('UserPolicy', function () {
    it('allows only the super admin to manage users', function (string $ability, bool $needsRecord) {
        $arguments = $needsRecord ? $this->editorA : User::class;

        expect($this->admin->can($ability, $arguments))->toBeTrue()
            ->and($this->editorA->can($ability, $arguments))->toBeFalse();
    })->with([
        ['viewAny', false],
        ['create', false],
        ['view', true],
        ['update', true],
        ['deactivate', true],
    ]);

    it('does not let an editor manage their own account here', function () {
        expect($this->editorA->can('update', $this->editorA))->toBeFalse()
            ->and($this->editorA->can('deactivate', $this->editorA))->toBeFalse();
    });

    it('does not let the super admin deactivate themselves', function () {
        expect($this->admin->can('deactivate', $this->admin))->toBeFalse();
    });

    it('allows nobody to delete users', function () {
        expect($this->admin->can('delete', $this->editorA))->toBeFalse()
            ->and($this->admin->can('deleteAny', User::class))->toBeFalse();
    });
});

describe('SchoolContentPolicy', function () {
    beforeEach(function () {
        $this->noteA = createNote($this->schoolA);
        $this->noteB = createNote($this->schoolB);
    });

    $recordAbilities = ['view', 'update', 'delete', 'restore', 'forceDelete'];
    $modelAbilities = ['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny', 'reorder'];

    it('lets an editor act on their own school\'s records only', function (string $ability) {
        expect($this->editorA->can($ability, $this->noteA))->toBeTrue()
            ->and($this->editorA->can($ability, $this->noteB))->toBeFalse();
    })->with($recordAbilities);

    it('lets the super admin act on records of the selected school only', function (string $ability) {
        $this->actingAs($this->admin);

        expect($this->admin->can($ability, $this->noteA))->toBeTrue()
            ->and($this->admin->can($ability, $this->noteB))->toBeFalse();

        actingAsSuperAdmin($this->schoolB);
        $admin = auth()->user();

        expect($admin->can($ability, $this->noteB))->toBeTrue()
            ->and($admin->can($ability, $this->noteA))->toBeFalse();
    })->with($recordAbilities);

    it('allows model-level abilities for users with a school', function (string $ability) {
        expect($this->editorA->can($ability, Note::class))->toBeTrue();
    })->with($modelAbilities);

    it('denies everything to a user without a school context', function (string $ability) {
        $orphan = User::factory()->editor()->create();
        $orphan->school_id = null;

        expect($orphan->can($ability, Note::class))->toBeFalse()
            ->and($orphan->can('view', $this->noteA))->toBeFalse();
    })->with($modelAbilities);

    it('denies everything to an inactive editor', function () {
        $inactive = User::factory()->editor($this->schoolA)->inactive()->create();

        expect($inactive->can('viewAny', Note::class))->toBeFalse()
            ->and($inactive->can('update', $this->noteA))->toBeFalse();
    });
});
