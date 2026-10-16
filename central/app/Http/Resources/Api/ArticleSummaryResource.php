<?php

namespace App\Http\Resources\Api;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An article as it appears in lists and cards.
 *
 * @mixin Article
 */
class ArticleSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->getFirstMedia('cover');

        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'cover' => $cover === null ? null : new ImageResource($cover, $this->title),
            'published_at' => $this->published_at->toIso8601String(),
            'is_featured' => $this->is_featured,
        ];
    }
}
