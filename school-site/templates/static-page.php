<?php
/**
 * A hand-written page from pages/, inside the shared layout. The file is
 * trusted HTML and is included as it is.
 *
 * @var array $school
 * @var string $title
 * @var string $file
 */
?>
<div class="container">
    <article class="prose static-page">
        <h1 class="page-title"><?= e($title) ?></h1>
<?php if (is_file($file)): ?>
<?php require $file; ?>
<?php else: ?>
        <p>Sadržaj ove stranice uskoro će biti dostupan.</p>
<?php endif; ?>
    </article>
</div>
