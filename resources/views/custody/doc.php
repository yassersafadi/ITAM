<?php
/** Receipt body shared by the signing screen and the printable copy. */
$assets = array_values(array_filter($r['items'], fn ($i) => $i['kind'] === 'asset'));
$licenses = array_values(array_filter($r['items'], fn ($i) => $i['kind'] === 'license'));
// Staff looking at an unsigned receipt can take off an item added by mistake (never on the copy the employee signs).
$removable = ($removable ?? false) && $r['type'] !== 'swap';
// A replacement shows the device returned, then the device received.
$lists = $r['type'] === 'swap'
    ? [['custody.items_returned', array_values(array_filter($assets, fn ($i) => ($i['side'] ?? '') === 'out'))], ['custody.items_received', array_values(array_filter($assets, fn ($i) => ($i['side'] ?? '') === 'in'))]]
    : [[$r['type'] === 'return' ? 'custody.items_returned' : 'custody.items_received', $assets]];
$remove = fn (array $i) => $removable && count($r['items']) > 1
    ? '<form method="post" action="' . url('custody/' . $r['id'] . '/items/remove') . '" class="no-print custody-remove" data-confirm="' . e(t('custody.remove_confirm')) . '">' . csrf_field()
        . '<input type="hidden" name="item" value="' . e(\ITAM\Services\Custody::itemKey($i)) . '"><button class="icon-btn sm" aria-label="' . e(t('custody.remove_item')) . '" title="' . e(t('custody.remove_item')) . '">' . icon('x') . '</button></form>'
    : '';
?>
<div class="cert-grid custody-doc">
    <section class="custody-box">
        <h2><?= icon('user') ?> <?= e(t('field.employee')) ?></h2>
        <dl>
            <dt><?= e(t('field.name')) ?></dt><dd dir="auto"><?= e($r['employee_name']) ?></dd>
            <dt><?= e(t('field.employee_no')) ?></dt><dd dir="auto"><?= e($r['employee_no'] ?? '—') ?: '—' ?></dd>
            <dt><?= e(t('field.department_id')) ?></dt><dd dir="auto"><?= e($r['department_name'] ?? '—') ?: '—' ?></dd>
        </dl>
    </section>
    <section class="custody-box">
        <h2><?= icon('clipboard') ?> <?= e(t('custody.receipt')) ?></h2>
        <dl>
            <dt><?= e(t('custody.receipt_no')) ?></dt><dd class="mono" dir="ltr"><?= e($r['receipt_no']) ?></dd>
            <dt><?= e(t('field.date')) ?></dt><dd dir="auto"><?= fmt_date($r['created_at'], true) ?></dd>
            <dt><?= e(t('custody.handed_by')) ?></dt><dd dir="auto"><?= e($r['staff_name'] ?? '—') ?></dd>
        </dl>
    </section>
    <section class="cert-wide">
        <?php foreach ($lists as [$heading, $rows]): ?>
        <h2><?= icon('laptop') ?> <?= e(t($heading)) ?></h2>
        <table class="cert-table custody-items">
            <thead><tr><th>#</th><th><?= e(t('field.tag')) ?></th><th><?= e(t('field.name')) ?></th><th><?= e(t('field.serial')) ?></th><th><?= e(t('field.condition_state')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $a): ?>
                <tr><td><?= $i + 1 ?></td><td class="mono" dir="ltr"><?= e($a['tag']) ?></td><td dir="auto"><?= e($a['name']) ?><?= $a['category'] ? '<small class="block muted">' . e($a['category']) . '</small>' : '' ?></td>
                    <td class="mono" dir="ltr"><?= e($a['serial'] ?? '—') ?: '—' ?></td><td><?= e(t('condition.' . $a['condition'])) ?><?= $remove($a) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>
        <?php if ($licenses): ?>
            <div class="small custody-licenses"><b><?= e(t('nav.licenses')) ?>:</b> <?php foreach ($licenses as $k => $l): ?><span class="custody-license"><span dir="auto"><?= e($l['name']) ?></span><?= $remove($l) ?></span><?= $k < count($licenses) - 1 ? ' · ' : '' ?><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($r['notes']): ?><p class="cert-notes" dir="auto"><?= e($r['notes']) ?></p><?php endif; ?>
    </section>
    <section class="cert-wide">
        <h2><?= icon('shield') ?> <?= e(t('custody.terms')) ?></h2>
        <div class="custody-terms" dir="auto"><?= nl2br(e($r['terms'])) ?></div>
    </section>
</div>
