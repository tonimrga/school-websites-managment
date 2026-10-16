<?php

namespace App\Http\Resources\Api;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published document. The file link is the document's download route, not
 * a storage URL, so it stays the same when the file is replaced.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $file = $this->file();

        return [
            'title' => $this->title,
            'description' => $this->description,
            'document_date' => $this->document_date?->toDateString(),
            'file' => $file === null ? null : [
                'url' => $this->downloadUrl(),
                'name' => $this->downloadName(),
                'extension' => $file->extension,
                'size_bytes' => (int) $file->size,
            ],
        ];
    }
}
