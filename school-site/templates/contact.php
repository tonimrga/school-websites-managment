<?php
/**
 * @var array $school
 */

$map = safe_url($school['map_embed_url'], httpsOnly: true);
?>
<div class="container">
    <h1 class="page-title">Kontakt</h1>

    <div class="contact">
        <div class="contact__details">
            <dl class="details">
                <dt>Naziv</dt>
                <dd><?= e($school['name']) ?></dd>
<?php if ($school['address'] || $school['city']): ?>
                <dt>Adresa</dt>
                <dd>
                    <address>
<?php if ($school['address']): ?>
                        <?= e($school['address']) ?><br>
<?php endif; ?>
                        <?= e(trim($school['postal_code'] . ' ' . $school['city'])) ?>
                    </address>
                </dd>
<?php endif; ?>
<?php if ($school['phone']): ?>
                <dt>Telefon</dt>
                <dd><a href="<?= e(tel_href($school['phone'])) ?>"><?= e($school['phone']) ?></a></dd>
<?php endif; ?>
<?php if ($school['email']): ?>
                <dt>E-pošta</dt>
                <dd><a href="mailto:<?= e($school['email']) ?>"><?= e($school['email']) ?></a></dd>
<?php endif; ?>
<?php if ($school['office_hours']): ?>
                <dt>Radno vrijeme</dt>
                <dd><?= e_lines($school['office_hours']) ?></dd>
<?php endif; ?>
            </dl>

<?php if ($school['contact_note']): ?>
            <div class="contact__note">
                <p><?= e_lines($school['contact_note']) ?></p>
            </div>
<?php endif; ?>
        </div>

<?php if ($map !== null): ?>
        <div class="contact__map">
            <iframe src="<?= e($map) ?>" title="Karta s lokacijom doma" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
<?php endif; ?>
    </div>
</div>
