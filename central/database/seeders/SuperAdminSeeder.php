<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create or update the super admin account from config (set in .env).
     */
    public function run(): void
    {
        $email = config('cms.super_admin.email');
        $password = config('cms.super_admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in .env before seeding.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = config('cms.super_admin.name');
        $user->password = $password;
        $user->role = UserRole::SuperAdmin;
        $user->school_id = null;
        $user->is_active = true;
        $user->email_verified_at ??= now();
        $user->save();
    }
}
