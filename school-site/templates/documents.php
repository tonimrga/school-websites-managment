<?php
/**
 * @var array $school
 * @var array $categories  Categories with their documents
 * @var array|null $selected  The category picked in the filter
 */

$groups = $selected === null ? $categories : [$selected];
?>
<div class="container">
    <h1 class="page-title">Dokumenti</h1>

<?php if ($categories === []): ?>
    <p class="empty">Još nema objavljenih dokumenata.</p>
<?php else: ?>
    <nav class="filter" aria-label="Kategorije dokumenata">
        <ul>
            <li><a href="/dokumenti"<?= $selected === null ? ' aria-current="page"' : '' ?>>Sve kategorije</a></li>
<?php foreach ($categories as $category): ?>
            <li><a href="<?= e(url('/dokumenti', ['kategorija' => $category['slug']])) ?>"<?= $selected !== null && $selected['slug'] === $category['slug'] ? ' aria-current="page"' : '' ?>><?= e($category['name']) ?></a></li>
<?php endforeach; ?>
        </ul>
    </nav>

<?php foreach ($groups as $category): ?>
    <section class="section document-group" aria-labelledby="kategorija-<?= e($category['slug']) ?>">
        <h2 id="kategorija-<?= e($category['slug']) ?>"><?= e($category['name']) ?></h2>
<?php if ($category['documents'] === []): ?>
        <p class="empty">U ovoj kategoriji trenutačno nema dokumenata.</p>
<?php else: ?>
        <ul class="document-list">
<?php foreach ($category['documents'] as $document): ?>
            <?= partial('document-row', ['document' => $document]) ?>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </section>
<?php endforeach; ?>
<?php endif; ?>
</div>
