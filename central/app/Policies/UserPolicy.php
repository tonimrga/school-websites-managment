<?php

namespace App\Policies;

use App\Models\User;

/**
 * Users are managed by the super admin only. Users are deactivated, never
 * deleted, and the super admin cannot deactivate their own account.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->isNot($model);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
