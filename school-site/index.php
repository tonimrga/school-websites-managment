<?php

/*
 * Front controller and router. Apache/LiteSpeed rewrite every request here
 * (.htaccess); locally it is the router script for PHP's built-in server:
 *
 *   php -S localhost:8081 index.php
 *
 * Each page makes one API call. Only error pages may make a second one, to
 * tell a missing article or category (404 page) from an unknown or inactive
 * school ("temporarily unavailable" page).
 */

declare(strict_types=1);

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Built-in server: serve static files, but only those that are public.
if (PHP_SAPI === 'cli-server'
    && (str_starts_with($path, '/assets/') || $path === '/robots.txt')
    && !str_contains($path, '..')
    && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/config.php';
require __DIR__ . '/lib/api.php';
require __DIR__ . '/lib/helpers.php';

// On shared hosting .htaccess sends these; the built-in server ignores it.
if (PHP_SAPI === 'cli-server') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
}

// A copy that was uploaded without editing config.php must not show another
// school's content or point at a local API.
if (SCHOOL_SLUG === '' || SITE_URL === ''
    || (PHP_SAPI !== 'cli-server' && preg_match('#^https?://(localhost|127\.0\.0\.1)[:/]#', API_BASE_URL . '/'))) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo render_template('not-configured');
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Metoda nije dopuštena.';
    exit;
}

// One canonical form for every path: no trailing slash.
if ($path !== '/' && str_ends_with($path, '/')) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . rtrim($path, '/') . ($query === '' ? '' : '?' . $query), true, 301);
    exit;
}

match (true) {
    $path === '/' => page_home(),
    $path === '/vijesti' => page_news(),
    $path === '/dokumenti' => page_documents(),
    $path === '/o-nama' => page_about(),
    $path === '/kontakt' => page_contact(),
    $path === '/sitemap.xml' => page_sitemap(),
    (bool) preg_match('#^/vijesti/([a-z0-9-]+)$#', $path, $match) => page_article($match[1]),
    default => page_not_found(),
};

/**
 * Fetch the data of a page, or show the right error page and stop.
 *
 * A 404 from a school-level call can only mean that the school is unknown or
 * inactive. A 404 for a specific article, page or category is checked with
 * page_not_found().
 */
function fetch_page(string $path, array $query = [], bool $schoolLevel = true): array
{
    $result = api_get($path, $query);

    if ($result['status'] === API_OK && is_array($result['data']['school'] ?? null)) {
        return $result['data'];
    }

    if ($result['status'] === API_NOT_FOUND && !$schoolLevel) {
        page_not_found();
    }

    render_unavailable();
}

/**
 * The 404 page inside the school's layout, or the unavailable page when the
 * school itself is unknown or inactive.
 */
function page_not_found(): never
{
    $result = api_get('/');

    if ($result['status'] !== API_OK || !is_array($result['data']['school'] ?? null)) {
        render_unavailable();
    }

    render_page('404', ['school' => $result['data']['school']], [
        'title' => 'Stranica nije pronađena',
        'description' => 'Tražena stranica ne postoji ili je uklonjena.',
        'path' => null,
        'noindex' => true,
    ], 404);
}

function page_home(): never
{
    $data = fetch_page('/home');
    $school = $data['school'];

    render_page('home', $data, [
        'title' => null,
        'description' => $school['name'] . ': vijesti, dokumenti i kontakt.',
        'path' => '/',
        'nav' => 'home',
    ]);
}

function page_news(): never
{
    $page = query_param('stranica');

    if ($page === false) {
        page_not_found();
    }

    $data = $page === null
        ? fetch_page('/articles')
        : fetch_page('/articles', ['page' => $page], schoolLevel: false);

    $current = (int) $data['meta']['current_page'];
    $suffix = $current > 1 ? ' – stranica ' . $current : '';

    render_page('news', $data, [
        'title' => 'Vijesti' . $suffix,
        'description' => 'Vijesti i novosti – ' . $data['school']['name'] . $suffix . '.',
        'path' => url('/vijesti', ['stranica' => $current > 1 ? $current : null]),
        'nav' => 'news',
    ]);
}

function page_article(string $slug): never
{
    $data = fetch_page('/articles/' . $slug, schoolLevel: false);
    $article = $data['article'];
    $cover = $article['cover'];

    render_page('article', $data, [
        'title' => $article['title'],
        'description' => $article['excerpt'],
        'path' => '/vijesti/' . $article['slug'],
        'image' => $cover === null ? null : [
            'url' => $cover['large'],
            'width' => $cover['width'],
            'height' => $cover['height'],
            'alt' => $cover['alt'],
        ],
        'type' => 'article',
        'published' => $article['published_at'],
        'modified' => $article['updated_at'],
        'nav' => 'news',
    ]);
}

function page_documents(): never
{
    $category = query_param('kategorija');

    if ($category === false) {
        page_not_found();
    }

    $data = $category === null
        ? fetch_page('/documents')
        : fetch_page('/documents', ['category' => $category], schoolLevel: false);

    $selected = null;

    foreach ($data['categories'] as $item) {
        if ($item['slug'] === $category) {
            $selected = $item;
        }
    }

    render_page('documents', $data + ['selected' => $selected], [
        'title' => $selected === null ? 'Dokumenti' : $selected['name'] . ' – Dokumenti',
        'description' => ($selected === null ? 'Dokumenti' : $selected['name']) . ' – ' . $data['school']['name'] . '.',
        'path' => url('/dokumenti', ['kategorija' => $selected['slug'] ?? null]),
        'nav' => 'documents',
    ]);
}

function page_about(): never
{
    $data = fetch_page('/');

    render_page('static-page', $data + ['title' => 'O nama', 'file' => __DIR__ . '/pages/o-nama.php'], [
        'title' => 'O nama',
        'description' => 'O nama – ' . $data['school']['name'] . '.',
        'path' => '/o-nama',
        'nav' => 'about',
    ]);
}

function page_contact(): never
{
    $data = fetch_page('/');

    render_page('contact', $data, [
        'title' => 'Kontakt',
        'description' => 'Adresa, telefon, e-pošta i radno vrijeme – ' . $data['school']['name'] . '.',
        'path' => '/kontakt',
        'nav' => 'contact',
    ]);
}

/**
 * sitemap.xml: the fixed pages plus every published article.
 */
function page_sitemap(): never
{
    $result = api_get('/sitemap');

    if ($result['status'] !== API_OK || !is_array($result['data']['articles'] ?? null)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Retry-After: 300');
        echo 'Sitemap trenutačno nije dostupan.';
        exit;
    }

    $x = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    header('Content-Type: application/xml; charset=UTF-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";

    foreach (['/', '/vijesti', '/dokumenti', '/o-nama', '/kontakt'] as $page) {
        echo '  <url><loc>', $x(absolute_url($page)), '</loc></url>', "\n";
    }

    foreach ($result['data']['articles'] as $article) {
        echo '  <url><loc>', $x(absolute_url('/vijesti/' . rawurlencode((string) $article['slug']))), '</loc>',
            '<lastmod>', $x((string) $article['updated_at']), '</lastmod></url>', "\n";
    }

    echo '</urlset>', "\n";
    exit;
}
