<?php
/**
 * News list pagination.
 *
 * @var array $meta  {current_page, last_page, per_page, total}
 */

$current = (int) $meta['current_page'];
$last = (int) $meta['last_page'];
$pageUrl = fn (int $page): string => url('/vijesti', ['stranica' => $page > 1 ? $page : null]);

if ($last <= 1) {
    return;
}
?>
<nav class="pagination" aria-label="Stranice vijesti">
    <ul>
<?php if ($current > 1): ?>
        <li><a href="<?= e($pageUrl($current - 1)) ?>" rel="prev">‹ Prethodna</a></li>
<?php endif; ?>
<?php for ($page = 1; $page <= $last; $page++): ?>
<?php if ($page === 1 || $page === $last || abs($page - $current) <= 2): ?>
        <li><a href="<?= e($pageUrl($page)) ?>"<?= $page === $current ? ' aria-current="page"' : '' ?>><span class="visually-hidden">Stranica </span><?= $page ?></a></li>
<?php elseif ($page === 2 || $page === $last - 1): ?>
        <li><span class="pagination__gap" aria-hidden="true">…</span></li>
<?php endif; ?>
<?php endfor; ?>
<?php if ($current < $last): ?>
        <li><a href="<?= e($pageUrl($current + 1)) ?>" rel="next">Sljedeća ›</a></li>
<?php endif; ?>
    </ul>
</nav>
