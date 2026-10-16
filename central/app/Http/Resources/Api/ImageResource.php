<?php

namespace App\Http\Resources\Api;

use App\Support\ConversionSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An article image: URLs of its conversions only (never the stored image),
 * with the size of the large version.
 *
 * @property Media $resource
 */
class ImageResource extends JsonResource
{
    public function __construct(Media $media, protected string $alt)
    {
        parent::__construct($media);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $size = ConversionSize::for($this->resource, 'large');

        return [
            'thumb' => $this->resource->getUrl('thumb'),
            'large' => $this->resource->getUrl('large'),
            'width' => $size['width'] ?? null,
            'height' => $size['height'] ?? null,
            'alt' => $this->alt,
        ];
    }
}
