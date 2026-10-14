<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Editors may only see and reorder their school's categories. Creating,
 * renaming and deleting them is left to the super admin.
 */
class DocumentCategoryPolicy extends SchoolContentPolicy
{
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && parent::create($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $user->isSuperAdmin() && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->isSuperAdmin() && parent::delete($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin() && parent::deleteAny($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->isSuperAdmin() && parent::restore($user, $record);
    }

    public function restoreAny(User $user): bool
    {
        return $user->isSuperAdmin() && parent::restoreAny($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->isSuperAdmin() && parent::forceDelete($user, $record);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isSuperAdmin() && parent::forceDeleteAny($user);
    }
}
