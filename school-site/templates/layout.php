<?php
/**
 * @var array $school
 * @var array $meta
 * @var string $content
 */

$title = ($meta['title'] ?? null) === null ? $school['name'] : $meta['title'] . ' – ' . $school['name'];
$description = (string) ($meta['description'] ?? '');
$canonical = ($meta['path'] ?? null) === null ? null : absolute_url($meta['path']);
$type = $meta['type'] ?? 'website';
$nav = $meta['nav'] ?? null;

$logo = $school['logo'];
$image = $meta['image'] ?? ($logo === null ? null : [
    'url' => $logo['large'],
    'width' => $logo['width'],
    'height' => $logo['height'],
    'alt' => $school['name'],
]);

$menu = [
    'home' => ['/', 'Naslovna'],
    'news' => ['/vijesti', 'Vijesti'],
    'documents' => ['/dokumenti', 'Dokumenti'],
    'about' => ['/o-nama', 'O nama'],
    'contact' => ['/kontakt', 'Kontakt'],
];

$social = array_filter([
    'Facebook' => safe_url($school['social_links']['facebook'] ?? null),
    'Instagram' => safe_url($school['social_links']['instagram'] ?? null),
    'YouTube' => safe_url($school['social_links']['youtube'] ?? null),
]);
$oldSite = safe_url($school['old_site_url']);
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
<?php if (!empty($meta['noindex'])): ?>
    <meta name="robots" content="noindex">
<?php endif; ?>
<?php if ($canonical !== null): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
    <meta property="og:type" content="<?= e($type) ?>">
    <meta property="og:site_name" content="<?= e($school['name']) ?>">
    <meta property="og:title" content="<?= e($meta['title'] ?? $school['name']) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:locale" content="hr_HR">
<?php if ($image !== null): ?>
    <meta property="og:image" content="<?= e($image['url']) ?>">
<?php if ($image['width'] !== null && $image['height'] !== null): ?>
    <meta property="og:image:width" content="<?= e($image['width']) ?>">
    <meta property="og:image:height" content="<?= e($image['height']) ?>">
<?php endif; ?>
    <meta property="og:image:alt" content="<?= e($image['alt']) ?>">
<?php endif; ?>
<?php if ($type === 'article'): ?>
    <meta property="article:published_time" content="<?= e($meta['published']) ?>">
    <meta property="article:modified_time" content="<?= e($meta['modified']) ?>">
<?php endif; ?>
    <meta name="twitter:card" content="<?= ($meta['image'] ?? null) !== null ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($meta['title'] ?? $school['name']) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
<?php if ($image !== null): ?>
    <meta name="twitter:image" content="<?= e($image['url']) ?>">
    <meta name="twitter:image:alt" content="<?= e($image['alt']) ?>">
<?php endif; ?>
    <link rel="stylesheet" href="/assets/css/style.css">
<?php if ($logo !== null): ?>
    <link rel="icon" href="<?= e($logo['small']) ?>">
<?php endif; ?>
    <script src="/assets/js/menu.js" defer></script>
</head>
<body data-theme="<?= e(theme($school)) ?>">
<a class="skip-link" href="#sadrzaj">Preskoči na sadržaj</a>

<header class="site-header" data-menu="open">
    <div class="container site-header__inner">
        <a class="brand" href="/">
<?php if ($logo !== null): ?>
            <img class="brand__logo" src="<?= e($logo['small']) ?>" alt=""<?php if ($logo['width'] && $logo['height']): ?> width="<?= e($logo['width']) ?>" height="<?= e($logo['height']) ?>"<?php endif; ?>>
<?php endif; ?>
            <span class="brand__name"><?= e($school['name']) ?></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="true" aria-controls="glavni-izbornik" hidden>
            <span class="menu-toggle__icon" aria-hidden="true"></span>
            Izbornik
        </button>
        <nav class="site-nav" id="glavni-izbornik" aria-label="Glavni izbornik">
            <ul>
<?php foreach ($menu as $key => [$href, $label]): ?>
                <li><a href="<?= e($href) ?>"<?= $key === $nav ? ' class="is-active"' . (($meta['path'] ?? null) === $href ? ' aria-current="page"' : '') : '' ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
    </div>
</header>

<main id="sadrzaj" tabindex="-1">
<?= $content ?>
</main>

<footer class="site-footer">
    <div class="container site-footer__inner">
        <div>
            <h2 class="site-footer__title"><?= e($school['name']) ?></h2>
            <address>
<?php if ($school['address']): ?>
                <?= e($school['address']) ?><br>
<?php endif; ?>
<?php if ($school['postal_code'] || $school['city']): ?>
                <?= e(trim($school['postal_code'] . ' ' . $school['city'])) ?><br>
<?php endif; ?>
<?php if ($school['phone']): ?>
                Telefon: <a href="<?= e(tel_href($school['phone'])) ?>"><?= e($school['phone']) ?></a><br>
<?php endif; ?>
<?php if ($school['email']): ?>
                E-pošta: <a href="mailto:<?= e($school['email']) ?>"><?= e($school['email']) ?></a>
<?php endif; ?>
            </address>
        </div>
<?php if ($social !== [] || $oldSite !== null): ?>
        <div>
<?php if ($social !== []): ?>
            <h2 class="site-footer__title">Pratite nas</h2>
            <ul class="site-footer__links">
<?php foreach ($social as $network => $link): ?>
                <li><a href="<?= e($link) ?>" rel="noopener"><?= e($network) ?></a></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
<?php if ($oldSite !== null): ?>
            <p><a href="<?= e($oldSite) ?>" rel="noopener">Stare mrežne stranice doma</a></p>
<?php endif; ?>
        </div>
<?php endif; ?>
    </div>
</footer>
</body>
</html>
