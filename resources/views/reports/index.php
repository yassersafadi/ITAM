<?php
use ITAM\Controllers\ReportController;

page_title(t('nav.reports'));
$icons = ['warranty' => 'shield', 'quality' => 'check', 'utilization' => 'chart'];
?>
<div class="page-head">
    <div>
        <h1><?= icon('chart', 'icon icon-title') ?> <?= e(t('nav.reports')) ?></h1>
        <p class="muted"><?= e(t('reports.subtitle')) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('reports.export')): ?>
            <?= partial('partials/export_menu', ['base' => url('reports/' . $tab . '/export'), 'query' => []]) ?>
        <?php endif; ?>
        <button class="btn btn-ghost" onclick="window.print()"><?= icon('printer') ?> <span class="hide-sm"><?= e(t('labels.print')) ?></span></button>
    </div>
</div>

<div class="seg">
    <?php foreach (ReportController::tabs() as $tb): ?>
        <a href="<?= url('reports', ['tab' => $tb]) ?>" class="<?= $tab === $tb ? 'active' : '' ?>"><?= icon($icons[$tb], 'icon icon-xs') ?> <?= e(t('reports.' . $tb)) ?></a>
    <?php endforeach; ?>
</div>

<p class="muted report-desc"><?= e(t('reports.' . $tab . '_desc')) ?></p>

<?php if ($tab === 'warranty'): ?>
    <div class="grid grid-3">
        <section class="card">
            <h3 class="card-title"><?= e(t('reports.by_quarter')) ?></h3>
            <div class="chart" data-chart='<?= e(json_encode(['type' => 'bar', 'data' => array_map(fn ($q, $v) => ['label' => $q, 'value' => $v['count']], array_keys($data['quarters']), $data['quarters'])])) ?>'></div>
            <table class="table compact">
                <thead><tr><th><?= e(t('reports.quarter')) ?></th><th><?= e(t('reports.units')) ?></th><th><?= e(t('field.value')) ?></th></tr></thead>
                <tbody><?php foreach ($data['quarters'] as $q => $v): ?><tr><td><?= e($q) ?></td><td><?= $v['count'] ?></td><td><?= fmt_num($v['value']) ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </section>
        <section class="card span-2 table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= e(t('field.asset')) ?></th><th><?= e(t('field.category_id')) ?></th><th><?= e(t('field.assigned_to')) ?></th><th><?= e(t('field.warranty_until')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($data['rows'] as $r): ?>
                        <tr data-href="<?= url('assets/' . $r['id']) ?>">
                            <td><a href="<?= url('assets/' . $r['id']) ?>"><strong class="mono"><?= e($r['tag']) ?></strong></a><small class="block muted" dir="auto"><?= e($r['name']) ?></small></td>
                            <td><?= e((string) $r['category']) ?></td>
                            <td dir="auto"><?= e((string) $r['assigned_to']) ?></td>
                            <td><?= fmt_date($r['warranty_until']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$data['rows']): ?><tr><td colspan="4" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

<?php elseif ($tab === 'quality'): ?>
    <div class="grid grid-3">
        <section class="card">
            <div class="chart gauge" data-chart='<?= e(json_encode(['type' => 'gauge', 'value' => $data['score'], 'label' => t('reports.quality_score')])) ?>'></div>
            <ul class="list compact">
                <?php foreach ($data['rows'] as $c): ?>
                    <li><span><?= e($c['check']) ?></span><span class="health health-<?= health_class($c['complete_pct']) ?>"><span class="health-bar"><span style="width: <?= $c['complete_pct'] ?>%"></span></span><b><?= $c['complete_pct'] ?>%</b></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="card span-2 table-card">
            <div class="card-head"><h3><?= icon('swap') ?> <?= e(t('reports.conflicts')) ?></h3></div>
            <div class="table-wrap">
                <table class="table">
                    <?php $canMerge = false; ?>
                    <thead><tr><th><?= e(t('reports.field')) ?></th><th><?= e(t('reports.value')) ?></th><th><?= e(t('nav.assets')) ?></th><?php if ($canMerge): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($data['dups'] as $d): ?>
                        <tr><td><?= e($d['field']) ?></td><td class="mono"><?= e($d['value']) ?></td>
                            <td><?php foreach ($d['assets'] as $id => $tag): ?><a class="chip" href="<?= url('assets/' . $id) ?>"><?= e($tag) ?></a> <?php endforeach; ?></td>
                            </tr>
                    <?php endforeach; ?>
                    <?php if (!$data['dups']): ?><tr><td colspan="<?= $canMerge ? 4 : 3 ?>" class="muted"><?= icon('check', 'icon icon-xs') ?> <?= e(t('reports.no_conflicts')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

<?php else: ?>
    <div class="grid grid-2">
        <section class="card table-card">
            <div class="card-head"><h3><?= e(t('reports.by_category')) ?></h3></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th><?= e(t('field.category_id')) ?></th><th><?= e(t('status.deployed')) ?></th><th><?= e(t('status.in_stock')) ?></th><th><?= e(t('status.in_repair')) ?></th><th><?= e(t('reports.utilization')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($data['rows'] as $r): ?>
                    <tr><td><?= e($r['category']) ?></td><td><?= (int) $r['deployed'] ?></td><td><?= (int) $r['in_stock'] ?></td><td><?= (int) $r['in_repair'] ?></td>
                        <td><span class="health health-<?= health_class($r['utilization_pct']) ?>"><span class="health-bar"><span style="width: <?= $r['utilization_pct'] ?>%"></span></span><b><?= $r['utilization_pct'] ?>%</b></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
        <section class="card">
            <h3 class="card-title"><?= e(t('reports.by_department')) ?></h3>
            <div class="chart" data-chart='<?= e(json_encode(['type' => 'hbar', 'data' => array_map(fn ($d) => ['label' => $d['department'], 'value' => (int) $d['assets']], $data['by_dept'])], JSON_UNESCAPED_UNICODE)) ?>'></div>
        </section>
    </div>
<?php endif; ?>
