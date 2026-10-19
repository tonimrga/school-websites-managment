<?php
/**
 * An article gallery. Each thumbnail links to the large image, so it works
 * without JavaScript; gallery.js turns the links into a lightbox.
 *
 * @var array $gallery  List of {image, caption}
 */
?>
<section class="gallery" aria-labelledby="galerija-naslov">
    <h2 id="galerija-naslov">Galerija</h2>
    <ul class="gallery__grid" data-gallery>
<?php foreach ($gallery as $index => $item): ?>
<?php $image = $item['image']; ?>
        <li>
            <figure>
                <a href="<?= e($image['large']) ?>" data-gallery-item data-caption="<?= e($item['caption']) ?>" data-alt="<?= e($image['alt']) ?>">
                    <img src="<?= e($image['thumb']) ?>" srcset="<?= e(image_srcset($image)) ?>" sizes="(min-width: 64em) 15rem, (min-width: 40em) 33vw, 50vw" width="400" height="300" alt="<?= e($image['alt']) ?> (slika <?= $index + 1 ?> od <?= count($gallery) ?>)" loading="lazy">
                </a>
<?php if ($item['caption'] !== null && $item['caption'] !== ''): ?>
                <figcaption><?= e($item['caption']) ?></figcaption>
<?php endif; ?>
            </figure>
        </li>
<?php endforeach; ?>
    </ul>
</section>
