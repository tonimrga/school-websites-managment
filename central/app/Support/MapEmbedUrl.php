<?php

namespace App\Support;

/**
 * The map on a school's contact page is shown in an iframe on the school
 * site, so only https embed URLs of Google Maps and OpenStreetMap are allowed.
 */
class MapEmbedUrl
{
    public const MAX_LENGTH = 2048;

    /**
     * Allowed hosts, each with the path prefix an embed URL must have.
     *
     * @var array<string, string>
     */
    protected const ALLOWED = [
        'www.google.com' => '/maps/embed',
        'google.com' => '/maps/embed',
        'www.openstreetmap.org' => '/export/embed.html',
        'openstreetmap.org' => '/export/embed.html',
    ];

    /**
     * Editors often paste the whole <iframe> snippet from the map's "share"
     * dialog; keep only its src. Anything else is only trimmed.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if (stripos($value, '<iframe') !== false
            && preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/is', $value, $matches) === 1) {
            $value = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5));
        }

        return $value === '' ? null : $value;
    }

    public static function isAllowed(string $url): bool
    {
        if (strlen($url) > self::MAX_LENGTH || preg_match('/[\s<>"\'\\\\]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $prefix = self::ALLOWED[$host] ?? null;

        if ($prefix === null) {
            return false;
        }

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
