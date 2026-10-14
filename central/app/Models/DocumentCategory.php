<?php

namespace App\Models;

use App\Exceptions\CategoryHasDocuments;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Scopes\SchoolScope;
use App\Policies\DocumentCategoryPolicy;
use App\Support\CurrentSchool;
use Database\Factories\DocumentCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A group of documents on a school's documents page, in manual order.
 *
 * A category that still has documents cannot be deleted. Documents in the
 * trash do not block it: they are permanently deleted together with it.
 */
#[Fillable(['name', 'slug'])]
#[UsePolicy(DocumentCategoryPolicy::class)]
class DocumentCategory extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Created for every new school, in this order.
     */
    public const DEFAULTS = [
        'Natječaji',
        'Upisi',
        'Godišnji plan i program rada',
        'Statut i pravilnici',
        'Financijski dokumenti',
        'Javna nabava',
        'Pravo na pristup informacijama',
        'Ostalo',
    ];

    protected static function booted(): void
    {
        // Runs after BelongsToSchool has set school_id.
        static::creating(function (DocumentCategory $category): void {
            $category->sort_order = (int) static::withoutGlobalScope(SchoolScope::class)
                ->where('school_id', $category->school_id)
                ->max('sort_order') + 1;
        });

        static::deleting(function (DocumentCategory $category): void {
            // Unscoped, so the school scope can never hide a document from this check.
            if ($category->documents()->withoutGlobalScope(SchoolScope::class)->exists()) {
                throw CategoryHasDocuments::make();
            }

            $category->documents()
                ->withoutGlobalScope(SchoolScope::class)
                ->onlyTrashed()
                ->lazyById()
                ->each(fn (Document $document) => $document->forceDelete());
        });
    }

    /**
     * Deleting also removes the category's trashed documents, so both happen
     * together or not at all.
     */
    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    /**
     * Add the default categories a school does not have yet (matched by
     * slug). Safe to run more than once. System defaults are not logged as
     * user activity.
     */
    public static function createDefaultsFor(School $school): void
    {
        app(CurrentSchool::class)->actingFor($school, fn () => activity()->withoutLogs(function () use ($school): void {
            $existing = static::withoutGlobalScope(SchoolScope::class)
                ->where('school_id', $school->getKey())
                ->pluck('slug');

            foreach (self::DEFAULTS as $name) {
                $slug = Str::slug($name);

                if ($existing->contains($slug)) {
                    continue;
                }

                $category = new static(['name' => $name, 'slug' => $slug]);
                $category->school_id = $school->getKey();
                $category->save();
            }
        }));
    }

    /**
     * Documents in the category, without trashed ones.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
