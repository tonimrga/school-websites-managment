<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/**
 * Schools are managed by the super admin only. Schools are deactivated,
 * never deleted. School settings for editors come in a later phase.
 */
class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, School $school): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, School $school): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, School $school): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
