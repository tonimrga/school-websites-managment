<?php

namespace App\Console\Commands;

use App\Support\ConversionSize;
use App\Support\ImageProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Records the width and height of images uploaded before sizes were recorded
 * on upload. Each stored image is read once; images that already have a size
 * are skipped, so the command is safe to run more than once.
 */
#[Signature('media:record-dimensions')]
#[Description('Record the width and height of stored images that do not have them yet')]
class RecordMediaDimensions extends Command
{
    public function handle(): int
    {
        $recorded = 0;
        $failed = 0;

        $mediaModel = config('media-library.media_model');

        $mediaModel::query()
            ->whereIn('mime_type', array_keys(ImageProcessor::ALLOWED_TYPES))
            ->lazyById()
            ->each(function (Media $media) use (&$recorded, &$failed): void {
                if ($media->hasCustomProperty('width') && $media->hasCustomProperty('height')) {
                    return;
                }

                $disk = Storage::disk($media->disk);
                $path = $media->getPathRelativeToRoot();
                $size = $disk->exists($path) ? ConversionSize::measure((string) $disk->get($path)) : [];

                if ($size === []) {
                    $failed++;
                    $this->components->warn("Media {$media->getKey()}: image could not be read.");

                    return;
                }

                foreach ($size as $key => $value) {
                    $media->setCustomProperty($key, $value);
                }

                $media->save();
                $recorded++;
            });

        $this->components->info("{$recorded} images recorded, {$failed} could not be read.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
