<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config([
        'cms.super_admin.name' => 'Glavni admin',
        'cms.super_admin.email' => 'admin@example.com',
        'cms.super_admin.password' => 'a-long-secret-password',
    ]);
});

it('creates an active super admin without a school', function () {
    $this->seed(SuperAdminSeeder::class);

    $admin = User::sole();

    expect($admin->email)->toBe('admin@example.com')
        ->and($admin->name)->toBe('Glavni admin')
        ->and($admin->role)->toBe(UserRole::SuperAdmin)
        ->and($admin->isSuperAdmin())->toBeTrue()
        ->and($admin->school_id)->toBeNull()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->password)->not->toBe('a-long-secret-password')
        ->and(Hash::check('a-long-secret-password', $admin->password))->toBeTrue();
});

it('can be run again without creating a duplicate', function () {
    $this->seed(SuperAdminSeeder::class);

    config(['cms.super_admin.password' => 'a-changed-secret-password']);
    $this->seed(SuperAdminSeeder::class);

    expect(User::count())->toBe(1)
        ->and(Hash::check('a-changed-secret-password', User::sole()->password))->toBeTrue();
});

it('refuses to run without credentials in config', function (string $key) {
    config([$key => null]);

    $this->seed(SuperAdminSeeder::class);
})->with([
    'missing email' => 'cms.super_admin.email',
    'missing password' => 'cms.super_admin.password',
])->throws(RuntimeException::class, 'SUPER_ADMIN_EMAIL');
