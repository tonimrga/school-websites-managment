<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Exceptions\ArticleNotPublishable;
use App\Models\Concerns\BelongsToSchool;
use App\Policies\ArticlePolicy;
use App\Support\HtmlSanitizer;
use App\Support\ImageProcessor;
use Carbon\CarbonInterface;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Constraint;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A news item of one school.
 *
 * Status is not fillable: it only changes through publish() and unpublish(),
 * which enforce the publishing rules.
 */
#[Fillable(['title', 'slug', 'excerpt', 'body', 'published_at', 'is_featured'])]
#[UsePolicy(ArticlePolicy::class)]
class Article extends Model implements HasMedia
{
    use BelongsToSchool;

    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    public const EXCERPT_LENGTH = 300;

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Article $article): void {
            if (blank($article->excerpt)) {
                $article->excerpt = static::excerptFrom($article->body);
            }

            if (($userId = Auth::id()) !== null) {
                $article->updated_by = $userId;

                if (! $article->exists) {
                    $article->created_by = $userId;
                }
            }
        });
    }

    /**
     * Rich text is sanitized whenever it is set, whatever the source.
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): string => app(HtmlSanitizer::class)->sanitize($value),
        );
    }

    public static function excerptFrom(?string $html): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES | ENT_HTML5)));

        return $text === '' ? null : Str::limit($text, self::EXCERPT_LENGTH - 3, '...', preserveWords: true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published;
    }

    /**
     * Published, but with a publish date still in the future.
     */
    public function isScheduled(): bool
    {
        return $this->isPublished() && $this->published_at?->isFuture() === true;
    }

    public function statusLabel(): string
    {
        return $this->isScheduled() ? 'Zakazano' : $this->status->getLabel();
    }

    public function statusColor(): string
    {
        return $this->isScheduled() ? 'warning' : $this->status->getColor();
    }

    /**
     * What is still missing before the article can be published, as
     * Croatian labels; empty when it can be published.
     *
     * @return list<string>
     */
    public function missingForPublishing(): array
    {
        return array_values(array_filter([
            blank($this->title) ? 'naslov' : null,
            static::excerptFrom($this->body) === null ? 'tekst' : null,
            $this->getFirstMedia('cover') === null ? 'naslovna fotografija' : null,
        ]));
    }

    /**
     * Publish now, or schedule when $at (or the existing publish date) is in
     * the future.
     *
     * @throws ArticleNotPublishable
     */
    public function publish(?CarbonInterface $at = null): void
    {
        $this->load('media');

        if (($missing = $this->missingForPublishing()) !== []) {
            throw ArticleNotPublishable::missing($missing);
        }

        $this->status = ArticleStatus::Published;
        $this->published_at = $at ?? $this->published_at ?? now();

        $this->saveAndLog('published');
    }

    public function unpublish(): void
    {
        $this->status = ArticleStatus::Draft;

        $this->saveAndLog('unpublished');
    }

    /**
     * Save a status change as one dedicated activity entry instead of a
     * generic "updated" entry.
     */
    protected function saveAndLog(string $event): void
    {
        $changes = collect($this->getDirty())->only(['status', 'published_at']);
        $old = $changes->mapWithKeys(fn ($value, string $key): array => [$key => $this->getOriginal($key)]);

        activity()->withoutLogs(fn () => $this->save());

        activity()
            ->performedOn($this)
            ->event($event)
            ->withProperties([
                'attributes' => $changes->map(fn ($value, string $key) => $this->getAttributeValue($key)),
                'old' => $old,
            ])
            ->log($event);
    }

    /**
     * @param  Builder<Article>  $query
     */
    public function scopeDrafts(Builder $query): void
    {
        $query->where('status', ArticleStatus::Draft);
    }

    /**
     * Published and already visible.
     *
     * @param  Builder<Article>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', ArticleStatus::Published)->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Article>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', ArticleStatus::Published)->where('published_at', '>', now());
    }

    public function registerMediaCollections(): void
    {
        $imageTypes = array_keys(ImageProcessor::ALLOWED_TYPES);

        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes($imageTypes);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes($imageTypes)
            ->acceptsFile(fn (File $file, Article $article): bool => $article->media()
                ->where('collection_name', 'gallery')
                ->count() < (int) config('cms.articles.gallery_max'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 400, 300)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('large')
            ->width(1600, [Constraint::PreserveAspectRatio, Constraint::DoNotUpsize])
            ->format('webp')
            ->nonQueued();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'excerpt', 'is_featured', 'published_at', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
