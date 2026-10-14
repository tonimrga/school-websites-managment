<?php

namespace App\Support;

use finfo;
use ZipArchive;

/**
 * Decides whether a file really is one of the allowed document formats, by
 * its contents and its extension together: a file passes only when its
 * contents match the format its extension claims.
 *
 * fileinfo recognises PDF, the old Office formats and OpenDocument reliably.
 * For DOCX and XLSX it depends on the order of the entries in the zip, so a
 * file it only reports as a zip archive is opened and checked for the parts
 * every Office Open XML document has.
 */
class DocumentFileType
{
    /**
     * Allowed extensions with the MIME type sent on download.
     *
     * @var array<string, string>
     */
    public const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];

    /**
     * The folder inside the zip that holds an Office Open XML document's content.
     *
     * @var array<string, string>
     */
    protected const OFFICE_OPEN_XML_FOLDERS = [
        'docx' => 'word/',
        'xlsx' => 'xl/',
    ];

    /**
     * What fileinfo may report for a zip-based document whose type it could
     * not tell from the first entries.
     */
    protected const GENERIC_ZIP_TYPES = ['application/zip', 'application/octet-stream'];

    /**
     * @return list<string>
     */
    public static function extensions(): array
    {
        return array_keys(self::MIME_TYPES);
    }

    /**
     * @return list<string>
     */
    public static function mimeTypes(): array
    {
        return array_values(self::MIME_TYPES);
    }

    public static function mimeTypeFor(string $extension): ?string
    {
        return self::MIME_TYPES[strtolower($extension)] ?? null;
    }

    /**
     * The MIME type of the document at $path when it really is of the format
     * $extension names, otherwise null.
     */
    public function detect(string $path, string $extension): ?string
    {
        return $this->matches($path, $extension) ? self::MIME_TYPES[strtolower($extension)] : null;
    }

    /**
     * Whether the file at $path is a document of the format $extension names.
     */
    public function matches(string $path, string $extension): bool
    {
        $extension = strtolower($extension);

        if (! isset(self::MIME_TYPES[$extension]) || ! is_file($path)) {
            return false;
        }

        $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($detected === self::MIME_TYPES[$extension]) {
            return true;
        }

        if (isset(self::OFFICE_OPEN_XML_FOLDERS[$extension]) && in_array($detected, self::GENERIC_ZIP_TYPES, true)) {
            return $this->isOfficeOpenXml($path, self::OFFICE_OPEN_XML_FOLDERS[$extension]);
        }

        return false;
    }

    protected function isOfficeOpenXml(string $path, string $folder): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false) {
                return false;
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                if (str_starts_with((string) $zip->getNameIndex($index), $folder)) {
                    return true;
                }
            }

            return false;
        } finally {
            $zip->close();
        }
    }
}
