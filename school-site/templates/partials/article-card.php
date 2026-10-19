<?php
/**
 * An article in a list.
 *
 * @var array $article  An article list item from the API
 * @var string $heading  Heading tag: h2 or h3
 * @var bool $large  A large card (featured articles on the homepage)
 * @var bool $showFeatured  Mark featured articles
 */

$heading = $heading ?? 'h3';
$large = $large ?? false;
$showFeatured = $showFeatured ?? true;
$cover = $article['cover'];
$sizes = $large ? '(min-width: 64em) 36rem, 100vw' : '(min-width: 64em) 22rem, (min-width: 40em) 50vw, 100vw';
?>
<article class="card<?= $large ? ' card--large' : '' ?><?= $article['is_featured'] && $showFeatured ? ' card--featured' : '' ?>">
<?php if ($cover !== null): ?>
    <img class="card__image" src="<?= e($cover['thumb']) ?>" srcset="<?= e(image_srcset($cover)) ?>" sizes="<?= e($sizes) ?>" width="400" height="300" alt="<?= e($cover['alt']) ?>" loading="lazy">
<?php else: ?>
    <div class="card__image card__image--empty" aria-hidden="true"></div>
<?php endif; ?>
    <div class="card__body">
<?php if ($article['is_featured'] && $showFeatured): ?>
        <p class="badge">Izdvojeno</p>
<?php endif; ?>
        <<?= $heading ?> class="card__title"><a href="/vijesti/<?= e(rawurlencode($article['slug'])) ?>"><?= e($article['title']) ?></a></<?= $heading ?>>
        <p class="card__date"><time datetime="<?= e(iso_date($article['published_at'])) ?>"><?= e(format_date($article['published_at'])) ?></time></p>
<?php if ($article['excerpt']): ?>
        <p class="card__excerpt"><?= e($article['excerpt']) ?></p>
<?php endif; ?>
    </div>
</article>
