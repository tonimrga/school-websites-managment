<?php

use App\Models\School;
use App\Models\User;
use Database\Seeders\DemoSchoolsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('creates two active schools with different themes, each with one editor', function () {
    $this->seed(DemoSchoolsSeeder::class);

    $schools = School::with('users')->get();

    expect($schools)->toHaveCount(2)
        ->and($schools->pluck('theme')->unique())->toHaveCount(2)
        ->and($schools->every->is_active)->toBeTrue();

    foreach ($schools as $school) {
        expect($school->users)->toHaveCount(1)
            ->and($school->users->first()->isEditor())->toBeTrue()
            ->and($school->getFirstMedia('logo'))->not->toBeNull()
            ->and($school->map_embed_url)->not->toBeNull();
    }
});

it('can be run more than once', function () {
    $this->seed(DemoSchoolsSeeder::class);
    $this->seed(DemoSchoolsSeeder::class);

    expect(School::count())->toBe(2)
        ->and(User::count())->toBe(2)
        ->and(School::first()->getMedia('logo'))->toHaveCount(1);
});
