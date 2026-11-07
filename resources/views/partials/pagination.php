<?php
// The ‹ › arrows are mirrored by the browser in Arabic, so they need no flip.
// Numbered pages: up to five either side of the current one, then a gap and the first or last page.
// $place is 'top' (straight above the table) or 'bottom'; both sit at the end of the line (left in Arabic).
$place ??= 'bottom';
if ($pages > 1):
    $page = min($pages, max(1, (int) $page));
    $link = fn (int $n) => url($path, array_merge($query, ['page' => $n]));
    $from = max(1, $page - 5);
    $to = min($pages, $page + 5);
    $items = [];
    if ($from > 1) {
        $items[] = 1;
        if ($from > 2) $items[] = null;
    }
    $items = array_merge($items, range($from, $to));
    if ($to < $pages) {
        if ($to < $pages - 1) $items[] = null;
        $items[] = $pages;
    }
?>
    <nav class="pagination pagination-<?= e($place) ?>" aria-label="<?= e(t('common.page_of', ['n' => $page, 'm' => $pages])) ?>">
        <?php if ($page > 1): ?>
            <a href="<?= $link($page - 1) ?>" class="page-box page-step" aria-label="<?= e(t('common.prev')) ?>">‹</a>
        <?php else: ?>
            <span class="page-box page-step disabled" aria-hidden="true">‹</span>
        <?php endif; ?>
        <?php foreach ($items as $n): ?>
            <?php if ($n === null): ?>
                <span class="page-gap" aria-hidden="true">…</span>
            <?php elseif ($n === $page): ?>
                <span class="page-box current" aria-current="page"><?= $n ?></span>
            <?php else: ?>
                <a href="<?= $link($n) ?>" class="page-box"><?= $n ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($page < $pages): ?>
            <a href="<?= $link($page + 1) ?>" class="page-box page-step" aria-label="<?= e(t('common.next')) ?>">›</a>
        <?php else: ?>
            <span class="page-box page-step disabled" aria-hidden="true">›</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>
