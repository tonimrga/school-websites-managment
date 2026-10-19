<?php

namespace Database\Seeders;

use Illuminate\Support\Str;

/**
 * Small one-page PDFs with readable text for the demo documents.
 */
class DemoPdf
{
    /**
     * Write a PDF with a title and a few lines of text to a temporary file
     * and return its path. The text is transliterated to ASCII because the
     * built-in PDF fonts lack č, ć and đ.
     *
     * @param  list<string>  $lines
     */
    public static function make(string $title, array $lines = []): string
    {
        $text = 'BT /F1 18 Tf 72 760 Td ('.self::escape($title).") Tj ET\n";
        $y = 720;

        foreach ($lines as $line) {
            $text .= "BT /F1 11 Tf 72 {$y} Td (".self::escape($line).") Tj ET\n";
            $y -= 18;
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n".'0000000000 65535 f '."\n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $base = tempnam(sys_get_temp_dir(), 'demo-');
        $path = $base.'.pdf';
        rename($base, $path);
        file_put_contents($path, $pdf);

        return $path;
    }

    protected static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($text));
    }
}
