<?php

namespace App\Support;

use App\Models\Article;
use App\Models\School;
use Spatie\Image\Enums\Constraint;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Size;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Image sizes without reading files: the stored image's width and height are
 * recorded as custom properties on upload, and the size of each conversion is
 * derived from them with the same calculation spatie/image uses when it
 * generates the conversion.
 */
class ConversionSize
{
    /**
     * The width and height of an image, or [] when the contents are not one.
     *
     * @return array{width?: int, height?: int}
     */
    public static function measure(string $contents): array
    {
        $info = $contents === '' ? false : @getimagesizefromstring($contents);

        return $info === false ? [] : ['width' => $info[0], 'height' => $info[1]];
    }

    /**
     * The size of a conversion of an image, or null when the image's size was
     * never recorded or the conversion is unknown.
     *
     * @return array{width: int, height: int}|null
     */
    public static function for(Media $media, string $conversion): ?array
    {
        $width = $media->getCustomProperty('width');
        $height = $media->getCustomProperty('height');

        if (! is_int($width) || ! is_int($height) || $width < 1 || $height < 1) {
            return null;
        }

        $size = match ([$media->model_type, $conversion]) {
            [(new Article)->getMorphClass(), 'thumb'] => new Size(...Article::THUMB_SIZE),
            [(new Article)->getMorphClass(), 'large'] => static::scaledToWidth($width, $height, Article::LARGE_WIDTH),
            [(new School)->getMorphClass(), 'small'] => Fit::Max->calculateSize($width, $height, School::LOGO_SMALL, School::LOGO_SMALL),
            [(new School)->getMorphClass(), 'large'] => Fit::Max->calculateSize($width, $height, School::LOGO_LARGE, School::LOGO_LARGE),
            default => null,
        };

        return $size === null ? null : ['width' => $size->width, 'height' => $size->height];
    }

    /**
     * What ->width($target, [PreserveAspectRatio, DoNotUpsize]) produces.
     */
    protected static function scaledToWidth(int $width, int $height, int $target): Size
    {
        $size = new Size($width, $height);

        return $size->resize($target, (int) round($target / $size->aspectRatio()), [
            Constraint::PreserveAspectRatio,
            Constraint::DoNotUpsize,
        ]);
    }
}
