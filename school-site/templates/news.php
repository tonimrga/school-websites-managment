<?php
/**
 * @var array $school
 * @var array $data
 * @var array $meta
 */

$oldSite = safe_url($school['old_site_url']);
$current = (int) $meta['current_page'];
?>
<div class="container">
    <h1 class="page-title">Vijesti<?php if ($current > 1): ?> <span class="page-title__note">– stranica <?= $current ?> od <?= (int) $meta['last_page'] ?></span><?php endif; ?></h1>

<?php if ($data === []): ?>
    <p class="empty">Još nema objavljenih vijesti.</p>
<?php else: ?>
    <div class="grid">
<?php foreach ($data as $article): ?>
        <?= partial('article-card', ['article' => $article, 'heading' => 'h2']) ?>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <?= partial('pagination', ['meta' => $meta]) ?>

<?php if ($oldSite !== null && $current === (int) $meta['last_page']): ?>
    <p class="archive-link">Starije vijesti potražite na <a href="<?= e($oldSite) ?>" rel="noopener">starim mrežnim stranicama doma</a>.</p>
<?php endif; ?>
</div>
