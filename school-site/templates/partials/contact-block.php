<?php
/**
 * Short contact details for the homepage.
 *
 * @var array $school
 */
?>
<section class="contact-block" aria-labelledby="kontakt-naslov">
    <h2 id="kontakt-naslov">Kontakt</h2>
    <dl class="details">
<?php if ($school['address'] || $school['city']): ?>
        <dt>Adresa</dt>
        <dd><?= e($school['address']) ?><?= $school['address'] && $school['city'] ? ', ' : '' ?><?= e(trim($school['postal_code'] . ' ' . $school['city'])) ?></dd>
<?php endif; ?>
<?php if ($school['phone']): ?>
        <dt>Telefon</dt>
        <dd><a href="<?= e(tel_href($school['phone'])) ?>"><?= e($school['phone']) ?></a></dd>
<?php endif; ?>
<?php if ($school['email']): ?>
        <dt>E-pošta</dt>
        <dd><a href="mailto:<?= e($school['email']) ?>"><?= e($school['email']) ?></a></dd>
<?php endif; ?>
    </dl>
    <p><a href="/kontakt">Svi kontakti i karta</a></p>
</section>
