<?php
page_title($a['name']);
$days = $a['warranty_until'] ? (int) floor((strtotime($a['warranty_until']) - strtotime(today())) / 86400) : null;
$specs = array_filter([
    'field.manufacturer' => $a['manufacturer'], 'field.model' => $a['model'], 'field.serial' => $a['serial'], 'field.hostname' => $a['hostname'],
    'field.os' => $a['os'], 'field.cpu' => $a['cpu'],
    'field.ram_gb' => $a['ram_gb'] ? $a['ram_gb'] . ' GB' : null, 'field.storage_gb' => $a['storage_gb'] ? $a['storage_gb'] . ' GB' : null,
    'field.location_id' => $a['location_name'],
], fn ($v) => $v !== null && $v !== '');
?>
<a class="back-link" href="<?= url('portal/devices') ?>">‹ <?= e(t('portal.my_devices')) ?></a>
<section class="card portal-device-hero">
    <span class="portal-device-icon lg"><?= icon($a['icon'] ?? 'box') ?></span>
    <div>
        <h1 dir="auto"><?= e($a['name']) ?></h1>
        <p class="muted"><bdi class="mono"><?= e($a['tag']) ?></bdi><?= $a['category_name'] ? ' · ' . e($a['category_name']) : '' ?><?= $a['since'] ? ' · ' . e(t('portal.since', ['date' => fmt_date($a['since'])])) : '' ?></p>
    </div>
    <div class="portal-device-buttons">
        <button type="button" class="btn btn-primary" data-modal-open="report" data-fill="<?= e(json_encode(['asset_id' => (string) $a['id']])) ?>"><?= icon('wrench') ?> <?= e(t('portal.report_problem')) ?></button>
    </div>
</section>

<div class="portal-stats">
    <div class="<?= $days !== null && $days >= 0 && $days <= 60 ? 'warn' : '' ?>"><span><?= e(t('field.warranty_until')) ?></span>
        <strong><?= $a['warranty_until'] ? fmt_date($a['warranty_until']) : '—' ?></strong>
        <?php if ($days !== null): ?><small><?= e($days < 0 ? t('portal.warranty_over') : t('portal.warranty_left', ['n' => $days])) ?></small><?php endif; ?></div>
    <div><span><?= e(t('portal.faults')) ?></span><strong><?= count($history) ?></strong><small><?= e(t('portal.since_you')) ?></small></div>
</div>

<div class="portal-grid">
    <?php if ($specs): ?>
        <section class="card">
            <h3 class="card-title"><?= icon('cpu') ?> <?= e(t('portal.specs')) ?></h3>
            <dl class="portal-specs">
                <?php foreach ($specs as $label => $v): ?><dt><?= e(t($label)) ?></dt><dd dir="auto"><?= e((string) $v) ?></dd><?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <section class="card">
        <h3 class="card-title"><?= icon('history') ?> <?= e(t('portal.fault_history')) ?></h3>
        <?php if (!$history): ?><p class="muted small"><?= e(t('portal.no_faults')) ?></p><?php else: ?>
            <ul class="list compact">
                <?php foreach ($history as $h): ?>
                    <li><span class="list-main"><span><strong dir="auto"><?= e($h['title']) ?></strong><small><bdi class="mono">WO-<?= (int) $h['id'] ?></bdi> · <?= fmt_date($h['created_at']) ?><?= $h['completed_at'] ? ' → ' . fmt_date($h['completed_at']) : '' ?></small></span></span>
                        <?= status_badge($h['status'], 'maintenance.status') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
