<?php

namespace App\Models;

use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Activity log entry. school_id is copied from the subject (or is the subject
 * itself, for school settings) so the log can be filtered by school. Only the
 * super admin can read the log.
 */
class Activity extends BaseActivity
{
    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            $subject = $activity->subject;

            $activity->school_id ??= $subject instanceof School
                ? $subject->getKey()
                : $subject?->getAttribute('school_id');
        });
    }

    /**
     * The log spans all schools, so subjects are loaded regardless of the
     * school picked in the switcher (and including trashed ones, see config).
     */
    public function subject(): MorphTo
    {
        return parent::subject()->withoutGlobalScope(SchoolScope::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
