<?php
/**
 * One document in a category list.
 *
 * @var array $document  {title, description, document_date, file}
 */

$file = $document['file'];
?>
<li class="document">
    <div class="document__main">
        <h3 class="document__title"><?= e($document['title']) ?></h3>
<?php if ($document['document_date']): ?>
        <p class="document__date"><time datetime="<?= e(iso_date($document['document_date'])) ?>"><?= e(format_date($document['document_date'])) ?></time></p>
<?php endif; ?>
<?php if ($document['description']): ?>
        <p class="document__description"><?= e_lines($document['description']) ?></p>
<?php endif; ?>
    </div>
<?php if ($file !== null): ?>
    <a class="button document__download" href="<?= e($file['url']) ?>">
        Preuzmi<span class="visually-hidden"> dokument „<?= e($document['title']) ?>”</span>
        <span class="document__meta">(<?= e(strtoupper((string) $file['extension'])) ?>, <?= e(format_size((int) $file['size_bytes'])) ?>)</span>
    </a>
<?php endif; ?>
</li>
