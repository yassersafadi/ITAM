<?php
page_title($audit['name']);
$open = $audit['status'] === 'open';
$counts = ['found' => 0, 'misplaced' => 0, 'unexpected' => 0, 'missing' => 0];
foreach ($items as $i) {
    $counts[$i['result']] = ($counts[$i['result']] ?? 0) + 1;
}
$progress = $expected ? (int) round(($counts['found'] + $counts['misplaced']) / max(1, count($expected)) * 100) : null;
?>
<div class="page-head">
    <div>
        <a href="<?= url('audits') ?>" class="back-link">‹ <?= e(t('nav.audits')) ?></a>
        <h1><?= e($audit['name']) ?></h1>
        <div class="title-meta"><?= status_badge($audit['status'], 'audit_status') ?><span class="muted"><?= e($audit['location_name'] ?? t('audits.any_location')) ?> · <?= fmt_date($audit['started_at'], true) ?></span></div>
    </div>
    <?php if ($open && can('audits.run')): ?>
        <form method="post" action="<?= url('audits/' . $audit['id'] . '/close') ?>" class="page-actions" data-confirm="<?= e(t('audits.close_confirm')) ?>">
            <?= csrf_field() ?>
            <?php if ($audit['location_id']): ?><label class="check"><input type="checkbox" name="apply_moves" value="1" checked> <?= e(t('audits.apply_moves')) ?></label><?php endif; ?>
            <button class="btn btn-primary"><?= icon('check') ?> <?= e(t('audits.close')) ?></button>
        </form>
    <?php endif; ?>
</div>

<section class="mini-kpis">
    <div class="card"><span><?= e(t('audit_result.found')) ?></span><strong class="text-success" data-count="found"><?= $counts['found'] ?></strong></div>
    <div class="card"><span><?= e(t('audit_result.misplaced')) ?></span><strong class="text-warning" data-count="misplaced"><?= $counts['misplaced'] ?></strong></div>
    <div class="card"><span><?= e(t('audit_result.unexpected')) ?></span><strong class="text-warning" data-count="unexpected"><?= $counts['unexpected'] ?></strong></div>
    <div class="card"><span><?= e($open ? t('audits.pending') : t('audit_result.missing')) ?></span><strong class="text-danger" data-count="pending"><?= $open ? count($pending) : $counts['missing'] ?></strong></div>
    <?php if ($progress !== null): ?><div class="card"><span><?= e(t('audits.progress')) ?></span><strong data-progress><?= $progress ?>%</strong></div><?php endif; ?>
</section>

<div class="grid grid-3">
    <?php if ($open && can('audits.run')): ?>
        <section class="card scanner" data-scanner="<?= url('audits/' . $audit['id'] . '/scan') ?>">
            <h3 class="card-title"><?= icon('camera') ?> <?= e(t('audits.scanner')) ?></h3>
            <div class="scanner-view">
                <video playsinline muted data-video></video>
                <div class="scanner-frame"></div>
                <button class="btn btn-primary" type="button" data-start-camera><?= icon('camera') ?> <?= e(t('audits.start_camera')) ?></button>
            </div>
            <form class="scan-manual" data-manual>
                <input type="text" placeholder="<?= e(t('audits.manual')) ?>" autocomplete="off" autofocus data-code>
                <button class="btn btn-ghost" type="submit"><?= icon('check') ?></button>
            </form>
            <p class="muted small"><?= e(t('audits.scanner_help')) ?></p>
            <div class="scan-toast" data-toast hidden></div>
        </section>
    <?php endif; ?>

    <section class="card <?= $open ? 'span-2' : 'span-3' ?> table-card">
        <div class="card-head"><h3><?= icon('history') ?> <?= e(t('audits.scans')) ?></h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(t('field.asset')) ?></th><th><?= e(t('audits.result')) ?></th><th><?= e(t('field.location_id')) ?></th><th><?= e(t('field.date')) ?></th></tr></thead>
                <tbody data-scan-list>
                <?php foreach ($items as $i): ?>
                    <tr>
                        <td><?= $i['asset_id'] ? '<a href="' . url('assets/' . $i['asset_id']) . '"><span class="mono">' . e($i['tag']) . '</span> ' . e($i['asset_name']) . '</a>' : '<span class="mono">' . e($i['scanned_value']) . '</span>' ?></td>
                        <td><?= status_badge($i['result'], 'audit_result') ?></td>
                        <td><?= e($i['location_name'] ?? '—') ?></td>
                        <td><?= fmt_date($i['scanned_at'], true) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($open && $pending): ?>
        <section class="card span-3">
            <h3 class="card-title"><?= icon('clock') ?> <?= e(t('audits.not_yet', ['n' => count($pending)])) ?></h3>
            <div class="tag-cloud" data-pending>
                <?php foreach ($pending as $p): ?><a href="<?= url('assets/' . $p['id']) ?>" class="chip" data-tag="<?= e($p['tag']) ?>"><span class="mono"><?= e($p['tag']) ?></span> <?= e(mb_strimwidth($p['name'], 0, 28, '…')) ?></a><?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
