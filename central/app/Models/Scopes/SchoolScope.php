<?php

namespace App\Models\Scopes;

use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits queries to the current school while a dashboard user is logged in.
 *
 * Fails closed: a logged-in user without a school context sees nothing. Code
 * running without a user (console, public API) is not scoped and must filter
 * by school explicitly.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentSchool = app(CurrentSchool::class);

        if (! $currentSchool->hasUser()) {
            return;
        }

        $schoolId = $currentSchool->id();

        if ($schoolId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('school_id'), $schoolId);
    }
}
