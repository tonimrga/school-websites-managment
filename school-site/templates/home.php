<?php
/**
 * @var array $school
 * @var array $featured
 * @var array $latest
 */
?>
<div class="container">
    <h1 class="page-title"><?= e($school['name']) ?></h1>

<?php if ($featured !== []): ?>
    <section class="section" aria-labelledby="izdvojeno-naslov">
        <h2 id="izdvojeno-naslov">Izdvojeno</h2>
        <div class="grid grid--featured">
<?php foreach ($featured as $article): ?>
            <?= partial('article-card', ['article' => $article, 'heading' => 'h3', 'large' => true, 'showFeatured' => false]) ?>
<?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

    <section class="section" aria-labelledby="najnovije-naslov">
        <h2 id="najnovije-naslov">Najnovije vijesti</h2>
<?php if ($latest === [] && $featured === []): ?>
        <p class="empty">Još nema objavljenih vijesti.</p>
<?php else: ?>
        <div class="grid">
<?php foreach ($latest as $article): ?>
            <?= partial('article-card', ['article' => $article, 'heading' => 'h3']) ?>
<?php endforeach; ?>
        </div>
        <p class="section__more"><a class="button" href="/vijesti">Sve vijesti</a></p>
<?php endif; ?>
    </section>

    <?= partial('contact-block', ['school' => $school]) ?>
</div>
