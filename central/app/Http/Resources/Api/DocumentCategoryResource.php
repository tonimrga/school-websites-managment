<?php

namespace App\Http\Resources\Api;

use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document category with the documents the controller selected for it
 * (set as the "documents" relation).
 *
 * @mixin DocumentCategory
 */
class DocumentCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'documents' => DocumentResource::collection($this->documents),
        ];
    }
}
