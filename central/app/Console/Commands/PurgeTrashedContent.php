<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Scopes\SchoolScope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Permanently deletes soft-deleted content, with its media files, once it has
 * been in the trash for the configured number of days, across all schools.
 */
#[Signature('content:purge-trashed')]
#[Description('Permanently delete content that has been in the trash longer than the retention period')]
class PurgeTrashedContent extends Command
{
    /**
     * Soft-deleting content models whose trash is purged.
     *
     * @var list<class-string<Model>>
     */
    public const MODELS = [
        Article::class,
    ];

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('cms.trash.purge_after_days'));

        foreach (self::MODELS as $modelClass) {
            /** @var class-string<Model&SoftDeletes> $modelClass */
            $purged = 0;

            $modelClass::onlyTrashed()
                ->withoutGlobalScope(SchoolScope::class)
                ->where('deleted_at', '<=', $cutoff)
                ->lazyById()
                ->each(function (Model $record) use (&$purged): void {
                    $record->forceDelete();
                    $purged++;
                });

            $this->components->info(class_basename($modelClass).": {$purged} permanently deleted.");
        }

        return self::SUCCESS;
    }
}
