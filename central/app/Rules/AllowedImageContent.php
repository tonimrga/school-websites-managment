<?php

namespace App\Rules;

use App\Support\ImageProcessor;
use Closure;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Accepts only files whose contents are a JPG, PNG or WebP image, whatever
 * their name or declared type says.
 */
class AllowedImageContent implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof File) {
            return;
        }

        $contents = $value instanceof TemporaryUploadedFile
            ? (string) $value->get()
            : (string) file_get_contents($value->getPathname());

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (! app(ImageProcessor::class)->supports((string) $mimeType)) {
            $fail('Dopuštene su samo fotografije u formatu JPG, PNG ili WebP.');
        }
    }
}
