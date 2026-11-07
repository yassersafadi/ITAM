<?php
/** Quick look at one asset from the list (loaded into the side panel). */
$wDays = $a['warranty_until'] ? -1 * days_between($a['warranty_until']) : null;
$spec = function (string $label, ?string $value, bool $mono = false): string {
    if ($value === null || $value === '') {
        return '';
    }
    return '<div><dt>' . e($label) . '</dt><dd' . ($mono ? ' class="mono" dir="ltr"' : ' dir="auto"') . '>' . e($value) . '</dd></div>';
};
?>
<header class="drawer-head">
    <div class="pv-title">
        <span class="cell-icon"><?= icon($a['category_icon'] ?? 'box') ?></span>
        <div><a class="mono" href="<?= url('assets/' . $a['id']) ?>"><?= e($a['tag']) ?></a><strong dir="auto"><?= e($a['name']) ?></strong></div>
    </div>
    <button type="button" class="icon-btn" data-drawer-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button>
</header>
<div class="drawer-body">
    <div class="pv-hero">
        <div class="pv-badges">
            <?= status_badge($a['status']) ?>
            <?php if ((int) $a['criticality'] >= 3): ?><span class="badge badge-danger"><?= e(t('criticality.' . (int) $a['criticality'])) ?></span><?php endif; ?>
        </div>
    </div>

    <div class="pv-actions">
        <a class="btn btn-primary btn-sm" href="<?= url('assets/' . $a['id']) ?>"><?= icon('eye') ?> <?= e(t('grid.pv.open')) ?></a>
        <?php if (can('assets.edit')): ?><a class="btn btn-ghost btn-sm" href="<?= url('assets/' . $a['id'] . '/edit') ?>"><?= icon('edit') ?> <?= e(t('common.edit')) ?></a><?php endif; ?>
        <?php if (can('assets.assign')): ?><a class="btn btn-ghost btn-sm" href="<?= url('assets/' . $a['id']) ?>#assignments"><?= icon($a['assigned_to'] ? 'box' : 'user') ?> <?= e(t($a['assigned_to'] ? 'assets.checkin' : 'assets.checkout')) ?></a><?php endif; ?>
        <?php if (can('maintenance.edit')): ?>
            <a class="btn btn-ghost btn-sm" href="<?= url('assets/' . $a['id']) ?>#maintenance"><?= icon('wrench') ?> <?= e(t('grid.pv.work_order')) ?></a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= url('assets/labels', ['ids' => $a['id']]) ?>" target="_blank"><?= icon('qr') ?> <?= e(t('assets.label')) ?></a>
    </div>

    <dl class="pv-specs">
        <?= $spec(t('field.category_id'), $a['category_name']) ?>
        <?= $spec(t('field.model'), trim(($a['manufacturer'] ?? '') . ' ' . ($a['model'] ?? ''))) ?>
        <?= $spec(t('field.serial'), $a['serial'], true) ?>
        <?= $spec(t('field.hostname'), $a['hostname'], true) ?>
        <?= $spec(t('field.ip_address'), $a['ip_address'], true) ?>
        <?= $spec(t('field.os'), $a['os']) ?>
        <?= $spec(t('field.assigned_to'), $a['employee_name']) ?>
        <?= $spec(t('field.location_id'), $a['location_name']) ?>
        <?= $spec(t('field.department_id'), $a['department_name']) ?>
        <?= $spec(t('field.acquired_at'), $a['acquired_at'] ? fmt_date($a['acquired_at']) : null) ?>
        <?= $spec(t('field.po_number'), $a['po_number'], true) ?>
        <?php if ($a['warranty_until']): ?><div><dt><?= e(t('field.warranty_until')) ?></dt><dd class="<?= $wDays < 0 ? 'text-danger' : ($wDays <= (int) config('alerts.warranty_warn_days', 60) ? 'text-warning' : '') ?>"><?= fmt_date($a['warranty_until']) ?></dd></div><?php endif; ?>
        <?= $spec(t('grid.col.last_seen'), $a['last_seen_at'] ? time_ago($a['last_seen_at']) : null) ?>
    </dl>

    <?php if ($openWork): ?>
        <section class="pv-section"><h4><?= icon('wrench', 'icon icon-xs') ?> <?= e(t('grid.pv.open_work')) ?></h4>
            <ul class="list compact"><?php foreach ($openWork as $m): ?><li><span dir="auto"><?= e($m['title']) ?></span><?= status_badge($m['priority'], 'priority') ?></li><?php endforeach; ?></ul>
        </section>
    <?php endif; ?>

    <?php if ($timeline): ?>
        <section class="pv-section"><h4><?= icon('history', 'icon icon-xs') ?> <?= e(t('grid.pv.recent')) ?></h4>
            <ol class="pv-timeline">
                <?php foreach ($timeline as $ev): ?>
                    <li class="tone-<?= e($ev['tone']) ?>"><?= icon($ev['icon'], 'icon icon-xs') ?><div><span dir="auto"><?= e($ev['title']) ?></span><small class="muted"><?= e(time_ago($ev['at'])) ?><?= $ev['by'] ? ' · ' . e($ev['by']) : '' ?></small></div></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>
</div>
