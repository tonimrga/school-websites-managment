<?php

namespace App\Support;

use Spatie\Image\Enums\Fit;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

/**
 * Prepares an uploaded image for storage: applies the EXIF orientation,
 * scales it down to fit the configured maximum dimension and re-encodes it.
 * Re-encoding with GD writes no metadata, so EXIF data (including GPS
 * location) is never stored.
 */
class ImageProcessor
{
    /**
     * Raster formats that may be uploaded, keyed by MIME type.
     *
     * @var array<string, string>
     */
    public const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    protected const QUALITY = 85;

    /**
     * GD keeps the whole bitmap in memory (about 4 bytes per pixel), so large
     * phone photos need more than PHP's default memory limit.
     */
    protected const MEMORY_LIMIT = '512M';

    public function supports(string $mimeType): bool
    {
        return array_key_exists($mimeType, self::ALLOWED_TYPES);
    }

    public function extensionFor(string $mimeType): string
    {
        return self::ALLOWED_TYPES[$mimeType];
    }

    /**
     * Write a cleaned copy of the image to a new temporary file and return its path.
     */
    public function process(string $sourcePath, string $mimeType): string
    {
        $extension = $this->extensionFor($mimeType);
        $maxDimension = (int) config('cms.images.max_dimension');
        $targetPath = $this->temporaryPath($extension);

        $previousMemoryLimit = $this->raiseMemoryLimit();

        try {
            Image::useImageDriver(ImageDriver::Gd)
                ->loadFile($sourcePath)
                ->fit(Fit::Max, $maxDimension, $maxDimension)
                ->format($extension)
                ->quality(self::QUALITY)
                ->save($targetPath);
        } catch (\Throwable $exception) {
            @unlink($targetPath);

            throw $exception;
        } finally {
            if ($previousMemoryLimit !== null) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
        }

        return $targetPath;
    }

    protected function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'image-');
        $path = $base.'.'.$extension;
        rename($base, $path);

        return $path;
    }

    /**
     * Raise the memory limit for this request when it is below MEMORY_LIMIT.
     * Returns the previous value when it was changed.
     */
    protected function raiseMemoryLimit(): ?string
    {
        $current = (string) ini_get('memory_limit');

        if ($current === '-1' || $this->toBytes($current) >= $this->toBytes(self::MEMORY_LIMIT)) {
            return null;
        }

        ini_set('memory_limit', self::MEMORY_LIMIT);

        return $current;
    }

    protected function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
