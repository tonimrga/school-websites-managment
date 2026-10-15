<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/**
 * Schools are managed by the super admin only. Schools are deactivated,
 * never deleted. An editor may only change the settings of their own school,
 * and only the fields in School::EDITOR_SETTINGS.
 */
class SchoolPolicy
{
    /**
     * Open "Postavke doma" for the given school and save its editor settings.
     */
    public function updateSettings(User $user, School $school): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->isSuperAdmin()
            || ($user->isEditor() && (int) $user->school_id === (int) $school->getKey());
    }

    /**
     * Change the settings in School::ADMIN_SETTINGS.
     */
    public function updateAdminSettings(User $user, School $school): bool
    {
        return $user->is_active && $user->isSuperAdmin();
    }

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
