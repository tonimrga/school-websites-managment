<?php

namespace App\Support;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves which school the current dashboard user is working on.
 *
 * Editors always work on their own school. The super admin works on the
 * school picked in the school switcher (stored in the session), falling back
 * to the first school by name. Without a logged-in user (console, seeders,
 * public API) there is no school context at all.
 */
class CurrentSchool
{
    public const SESSION_KEY = 'current_school_id';

    /**
     * Whether a dashboard user is logged in, i.e. whether tenant scoping applies.
     */
    public function hasUser(): bool
    {
        return Auth::user() instanceof User;
    }

    /**
     * The school id of the logged-in user's context, or null when there is none.
     */
    public function id(): ?int
    {
        $user = Auth::user();

        return $user instanceof User ? $this->idFor($user) : null;
    }

    /**
     * The school id the given user is working on, or null when there is none.
     */
    public function idFor(User $user): ?int
    {
        if ($user->isSuperAdmin()) {
            return $this->selectedSchoolId();
        }

        return $user->school_id;
    }

    public function get(): ?School
    {
        $id = $this->id();

        return $id === null ? null : School::find($id);
    }

    /**
     * Select the school the super admin works on.
     */
    public function select(School $school): void
    {
        session()->put(self::SESSION_KEY, $school->getKey());
    }

    protected function selectedSchoolId(): ?int
    {
        $selected = session()->get(self::SESSION_KEY);

        if ($selected !== null && School::whereKey($selected)->exists()) {
            return (int) $selected;
        }

        return School::orderBy('name')->value('id');
    }
}
