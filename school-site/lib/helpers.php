<?php

/*
 * Escaping, formatting, URL and rendering helpers.
 */

const THEMES = ['blue', 'green', 'red'];
const TIMEZONE = 'Europe/Zagreb';

/**
 * Escape a value for HTML text and attributes.
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Escaped multi-line text, with line breaks kept.
 */
function e_lines(?string $value): string
{
    return nl2br(e($value), false);
}

/**
 * An ISO 8601 date or a Y-m-d date as d. m. Y. (e.g. 12. 10. 2026.).
 */
function format_date(?string $value): string
{
    $date = to_date($value);

    return $date === null ? '' : $date->format('d. m. Y.');
}

/**
 * A date for the datetime attribute of <time>.
 */
function iso_date(?string $value): string
{
    $date = to_date($value);

    return $date === null ? '' : $date->format(strlen((string) $value) === 10 ? 'Y-m-d' : DATE_ATOM);
}

function to_date(?string $value): ?DateTimeImmutable
{
    if ($value === null || $value === '') {
        return null;
    }

    try {
        $zone = new DateTimeZone(TIMEZONE);

        return (new DateTimeImmutable($value, $zone))->setTimezone($zone);
    } catch (Exception) {
        return null;
    }
}

/**
 * A file size in Croatian notation, e.g. 1,2 MB.
 */
function format_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' kB';
    }

    return number_format($bytes / 1024 / 1024, 1, ',', '.') . ' MB';
}

/**
 * A site path with an optional query, e.g. url('/vijesti', ['stranica' => 2]).
 */
function url(string $path, array $query = []): string
{
    $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

    return $path . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

/**
 * An absolute URL on this site, built from SITE_URL.
 */
function absolute_url(string $path, array $query = []): string
{
    return SITE_URL . url($path, $query);
}

/**
 * The URL if it is an http(s) link, otherwise null. Used for links that come
 * from school settings (social networks, map, old site).
 */
function safe_url(?string $value, bool $httpsOnly = false): ?string
{
    if ($value === null) {
        return null;
    }

    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    $allowed = $httpsOnly ? ['https'] : ['http', 'https'];

    return in_array($scheme, $allowed, true) ? $value : null;
}

/**
 * A phone number as a tel: link target.
 */
function tel_href(string $phone): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}

/**
 * The srcset of an API image: the 400 px thumbnail and the large version.
 */
function image_srcset(array $image): string
{
    return $image['thumb'] . ' 400w, ' . $image['large'] . ' ' . (int) ($image['width'] ?? 1600) . 'w';
}

/**
 * The theme name for <body data-theme>, falling back to blue.
 */
function theme(?array $school): string
{
    $theme = $school['theme'] ?? null;

    return in_array($theme, THEMES, true) ? $theme : THEMES[0];
}

/**
 * A query parameter as a string; null when missing or empty, false when it
 * is not a plain string (e.g. ?stranica[]=1).
 */
function query_param(string $name): string|false|null
{
    $value = $_GET[$name] ?? null;

    if ($value === null || $value === '') {
        return null;
    }

    return is_string($value) ? $value : false;
}

/**
 * Render a page template inside the layout and stop.
 *
 * $meta: title, description, path (canonical, with query), image (an API
 * image or logo: url, width, height, alt), type (website|article),
 * published, modified, noindex, nav (the active menu item).
 */
function render_page(string $template, array $vars, array $meta, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');

    $content = render_template($template, $vars);

    echo render_template('layout', ['school' => $vars['school'], 'meta' => $meta, 'content' => $content]);
    exit;
}

/**
 * Render a template from templates/ and return its HTML.
 */
function render_template(string $template, array $vars = []): string
{
    extract($vars, EXTR_SKIP);

    ob_start();
    require __DIR__ . '/../templates/' . $template . '.php';

    return (string) ob_get_clean();
}

/**
 * Render a partial from templates/partials/ and return its HTML.
 */
function partial(string $name, array $vars = []): string
{
    return render_template('partials/' . $name, $vars);
}

/**
 * Show the "temporarily unavailable" page (HTTP 503) and stop.
 */
function render_unavailable(): never
{
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 300');

    echo render_template('unavailable');
    exit;
}
