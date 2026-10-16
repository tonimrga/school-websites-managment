<?php

namespace App\Models;

use App\Enums\SchoolTheme;
use App\Models\Scopes\SchoolScope;
use App\Support\ImageProcessor;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A tenant. Every content row belongs to exactly one school. A new school
 * gets the default document categories.
 *
 * School is not a BelongsToSchool model: code that edits "the current school"
 * must always load it through CurrentSchool, never by an id from a request.
 */
#[Fillable([
    'name', 'slug', 'site_url', 'theme',
    'address', 'postal_code', 'city', 'phone', 'email',
    'office_hours', 'map_embed_url', 'contact_note', 'social_links',
    'old_site_url', 'is_active',
])]
class School extends Model implements HasMedia
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use LogsActivity;

    /**
     * Settings an editor may change for their own school (the logo too).
     */
    public const EDITOR_SETTINGS = [
        'address', 'postal_code', 'city', 'phone', 'email',
        'office_hours', 'map_embed_url', 'contact_note', 'social_links',
    ];

    /**
     * Settings only the super admin may change.
     */
    public const ADMIN_SETTINGS = [
        'name', 'slug', 'site_url', 'theme', 'old_site_url', 'is_active',
    ];

    /**
     * Logo conversions fit within these many pixels; see ConversionSize.
     */
    public const LOGO_SMALL = 256;

    public const LOGO_LARGE = 1200;

    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'youtube'];

    protected $attributes = [
        'theme' => 'blue',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'theme' => SchoolTheme::class,
            'social_links' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep only the links that are set, so an empty form is not a change.
        static::saving(function (School $school): void {
            $links = array_filter((array) $school->social_links, fn (mixed $link): bool => filled($link));

            $school->social_links = $links === [] ? null : $links;
        });

        static::created(function (School $school): void {
            DocumentCategory::createDefaultsFor($school);
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<DocumentCategory, $this>
     */
    public function documentCategories(): HasMany
    {
        return $this->hasMany(DocumentCategory::class)->withoutGlobalScope(SchoolScope::class);
    }

    /**
     * Adds `storage_bytes`: the total size of the school's stored files (its
     * logo, article images and documents, including those in the trash).
     * Generated image conversions are not counted.
     *
     * @param  Builder<School>  $query
     */
    public function scopeWithStorageBytes(Builder $query): void
    {
        $mediaModel = config('media-library.media_model');
        $schoolKey = $this->qualifyColumn('id');

        $ownedBy = fn (string $model): Builder => $model::withoutGlobalScopes()
            ->select('id')
            ->whereColumn((new $model)->qualifyColumn('school_id'), $schoolKey);

        $size = $mediaModel::query()
            ->selectRaw('coalesce(sum(size), 0)')
            ->where(fn (Builder $media) => $media
                ->where(fn (Builder $logo) => $logo
                    ->where('model_type', $this->getMorphClass())
                    ->whereColumn('model_id', $schoolKey))
                ->orWhere(fn (Builder $articles) => $articles
                    ->where('model_type', (new Article)->getMorphClass())
                    ->whereIn('model_id', $ownedBy(Article::class)))
                ->orWhere(fn (Builder $documents) => $documents
                    ->where('model_type', (new Document)->getMorphClass())
                    ->whereIn('model_id', $ownedBy(Document::class))));

        $query->addSelect(['storage_bytes' => $size]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(array_keys(ImageProcessor::ALLOWED_TYPES));
    }

    /**
     * Logos are scaled to fit, never cropped; WebP keeps transparency.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('small')
            ->fit(Fit::Max, self::LOGO_SMALL, self::LOGO_SMALL)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('large')
            ->fit(Fit::Max, self::LOGO_LARGE, self::LOGO_LARGE)
            ->format('webp')
            ->nonQueued();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([...self::ADMIN_SETTINGS, ...self::EDITOR_SETTINGS])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
