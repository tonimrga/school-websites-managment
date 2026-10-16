<?php

namespace App\Http\Resources\Api;

use App\Models\Article;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A full article: the list fields plus body and gallery.
 *
 * @mixin Article
 */
class ArticleResource extends ArticleSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'body' => $this->body,
            'gallery' => $this->getMedia('gallery')
                ->map(function (Media $media): array {
                    $caption = $media->getCustomProperty('caption');
                    $caption = filled($caption) ? (string) $caption : null;

                    return [
                        'image' => new ImageResource($media, $caption ?? $this->title),
                        'caption' => $caption,
                    ];
                })
                ->values()
                ->all(),
            'updated_at' => static::lastModified($this->resource),
        ];
    }

    /**
     * An article scheduled for later is new to visitors once it goes live.
     */
    public static function lastModified(Article $article): string
    {
        return $article->updated_at->max($article->published_at)->toIso8601String();
    }
}
