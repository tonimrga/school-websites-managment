<?php

namespace App\Policies;

use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for every content model that uses BelongsToSchool.
 *
 * A user may act on a record only if it belongs to the school they are
 * currently working on (their own school for editors, the switcher's school
 * for the super admin). This is a second line of defence behind the global
 * scope, so it must not rely on that scope having run.
 */
abstract class SchoolContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasSchool($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->ownsRecord($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->hasSchool($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->ownsRecord($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->ownsRecord($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasSchool($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->ownsRecord($user, $record);
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasSchool($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->ownsRecord($user, $record);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasSchool($user);
    }

    /**
     * Filament checks reordering per model, not per record; the reordered
     * records themselves are loaded through the school-scoped table query.
     */
    public function reorder(User $user): bool
    {
        return $this->hasSchool($user);
    }

    protected function hasSchool(User $user): bool
    {
        return $user->is_active && app(CurrentSchool::class)->idFor($user) !== null;
    }

    protected function ownsRecord(User $user, Model $record): bool
    {
        if (! $this->hasSchool($user) || $record->getAttribute('school_id') === null) {
            return false;
        }

        return (int) $record->getAttribute('school_id') === app(CurrentSchool::class)->idFor($user);
    }
}
