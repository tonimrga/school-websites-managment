<?php

namespace App\Rules;

use App\Support\DocumentFileType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Accepts only files whose contents are a PDF, DOC/DOCX, XLS/XLSX or
 * ODT/ODS document matching their extension, whatever their declared type
 * says.
 */
class AllowedDocumentContent implements ValidationRule
{
    public const MESSAGE = 'Dopuštene su samo datoteke PDF, DOC, DOCX, XLS, XLSX, ODT i ODS.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof File) {
            return;
        }

        $extension = $value instanceof UploadedFile
            ? $value->getClientOriginalExtension()
            : $value->getExtension();

        if (! $this->matches($value, $extension)) {
            $fail(self::MESSAGE);
        }
    }

    protected function matches(File $file, string $extension): bool
    {
        $detector = app(DocumentFileType::class);

        // A temporary upload may live on a non-local disk; check a local copy.
        if ($file instanceof TemporaryUploadedFile && ! is_file($file->getRealPath())) {
            $path = tempnam(sys_get_temp_dir(), 'document-');
            file_put_contents($path, (string) $file->get());

            try {
                return $detector->matches($path, $extension);
            } finally {
                @unlink($path);
            }
        }

        return $detector->matches($file->getPathname(), $extension);
    }
}
