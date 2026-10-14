<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Scopes\SchoolScope;
use App\Policies\DocumentPolicy;
use App\Support\DocumentFileType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A downloadable file of one school, in a category and in manual order
 * within it. Replacing the file keeps the document, so its position and
 * download link stay the same.
 */
#[Fillable(['document_category_id', 'title', 'description', 'document_date', 'is_published'])]
#[UsePolicy(DocumentPolicy::class)]
class Document extends Model implements HasMedia
{
    use BelongsToSchool;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $attributes = [
        'is_published' => false,
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Both run after BelongsToSchool has set school_id.
        static::creating(function (Document $document): void {
            $document->guardCategory();
            $document->moveToEndOfCategory();
        });

        static::updating(function (Document $document): void {
            if ($document->isDirty('document_category_id')) {
                $document->guardCategory();
                $document->moveToEndOfCategory();
            }
        });
    }

    /**
     * The category must belong to the document's own school, whatever the
     * form sent.
     */
    protected function guardCategory(): void
    {
        $categorySchoolId = DocumentCategory::withoutGlobalScope(SchoolScope::class)
            ->whereKey($this->document_category_id)
            ->value('school_id');

        if ($categorySchoolId === null || (int) $categorySchoolId !== (int) $this->school_id) {
            throw new LogicException('A document must be in a category of its own school.');
        }
    }

    protected function moveToEndOfCategory(): void
    {
        $this->sort_order = (int) static::withoutGlobalScope(SchoolScope::class)
            ->withTrashed()
            ->where('document_category_id', $this->document_category_id)
            ->max('sort_order') + 1;
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * Visible to visitors: published, not in the trash, and of an active school.
     */
    public function isPublic(): bool
    {
        return $this->is_published
            && ! $this->trashed()
            && School::whereKey($this->school_id)->where('is_active', true)->exists();
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    /**
     * The link stays the same when the file is replaced.
     */
    public function downloadUrl(): string
    {
        return route('documents.download', $this);
    }

    /**
     * The original file name, with the extension of the stored file.
     */
    public function downloadName(): ?string
    {
        $media = $this->file();

        return $media === null ? null : $media->name.'.'.$media->extension;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')
            ->singleFile()
            ->useDisk((string) config('cms.documents.disk'))
            ->acceptsMimeTypes(DocumentFileType::mimeTypes());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'document_category_id', 'document_date', 'is_published'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
