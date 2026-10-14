<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Scopes\SchoolScope;
use App\Support\DocumentFileType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends a document's file as a download under its original name. Visitors
 * get only public documents; dashboard users may also download the
 * unpublished and trashed documents of the school they are working on.
 * Everything else is a 404, so the URL reveals nothing.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(int $document): StreamedResponse
    {
        $record = Document::withoutGlobalScope(SchoolScope::class)
            ->withTrashed()
            ->find($document);

        $isPublic = $record?->isPublic() === true;

        abort_unless($record !== null && ($isPublic || Gate::allows('view', $record)), 404);

        $media = $record->file();
        abort_if($media === null, 404);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->getPathRelativeToRoot()), 404);

        $name = (string) $record->downloadName();

        return $disk->download($media->getPathRelativeToRoot(), $name, [
            'Content-Type' => DocumentFileType::mimeTypeFor($media->extension) ?? 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $name,
                $this->asciiFallback($name),
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $isPublic ? 'public, max-age=300' : 'private, no-store',
        ]);
    }

    /**
     * Browsers that ignore the UTF-8 file name get a plain ASCII one.
     */
    protected function asciiFallback(string $name): string
    {
        $fallback = str_replace(['/', '\\', '%', '"'], '-', Str::ascii($name));

        return preg_replace('/[^\x20-\x7E]/', '', $fallback) ?: 'dokument';
    }
}
