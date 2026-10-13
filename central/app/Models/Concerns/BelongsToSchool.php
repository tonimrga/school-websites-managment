<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tenant isolation for content models.
 *
 * - Queries are limited to the current school (see SchoolScope).
 * - On create, school_id is always taken from the current school when a user
 *   is logged in, overwriting anything else. Without a user (seeders,
 *   factories) it must be set explicitly.
 * - A logged-in user can never move a record to another school.
 *
 * Never add school_id to a model's fillable attributes.
 *
 * @mixin Model
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            $currentSchool = app(CurrentSchool::class);

            if ($currentSchool->hasUser()) {
                $model->school_id = $currentSchool->id()
                    ?? throw new LogicException('Cannot create '.$model::class.' without a current school.');

                return;
            }

            if ($model->school_id === null) {
                throw new LogicException('Cannot create '.$model::class.' without a school_id.');
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('school_id') && app(CurrentSchool::class)->hasUser()) {
                throw new LogicException('Cannot move '.$model::class.' to another school.');
            }
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
