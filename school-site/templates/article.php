<?php
/**
 * @var array $school
 * @var array $article
 * @var array $recent
 */

$cover = $article['cover'];
?>
<div class="container">
    <article class="article">
        <header class="article__header">
            <p class="breadcrumb"><a href="/vijesti">‹ Sve vijesti</a></p>
            <h1 class="page-title"><?= e($article['title']) ?></h1>
            <p class="article__date">Objavljeno <time datetime="<?= e(iso_date($article['published_at'])) ?>"><?= e(format_date($article['published_at'])) ?></time></p>
        </header>

<?php if ($cover !== null): ?>
        <img class="article__cover" src="<?= e($cover['large']) ?>" srcset="<?= e(image_srcset($cover)) ?>" sizes="(min-width: 50em) 48rem, 100vw"<?php if ($cover['width'] && $cover['height']): ?> width="<?= e($cover['width']) ?>" height="<?= e($cover['height']) ?>"<?php endif; ?> alt="<?= e($cover['alt']) ?>">
<?php endif; ?>

        <div class="article__body prose">
            <?= $article['body'] /* Sanitized by the central server. */ ?>
        </div>

<?php if ($article['gallery'] !== []): ?>
        <?= partial('gallery', ['gallery' => $article['gallery']]) ?>
<?php endif; ?>
    </article>

<?php if ($recent !== []): ?>
    <section class="section" aria-labelledby="ostale-naslov">
        <h2 id="ostale-naslov">Ostale vijesti</h2>
        <div class="grid">
<?php foreach ($recent as $item): ?>
            <?= partial('article-card', ['article' => $item, 'heading' => 'h3']) ?>
<?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
</div>
<?php if ($article['gallery'] !== []): ?>
<script src="/assets/js/gallery.js" defer></script>
<?php endif; ?>
