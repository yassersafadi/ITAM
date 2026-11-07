<?php
/** Charts grouped in tabs instead of stacked cards. */
$c = $d->charts();
$j = fn (array $spec) => e(json_encode($spec, JSON_UNESCAPED_UNICODE));
?>
<div class="card dash-card" data-tabs>
    <div class="card-head dash-tabs-head">
        <h3><?= icon('chart') ?> <?= e(t('dash.w.charts')) ?></h3>
        <div class="seg seg-sm" role="tablist">
            <?php foreach (['fleet' => 'box', 'lifecycle' => 'calendar', 'service' => 'wrench', 'licensing' => 'key'] as $tab => $ic): ?>
                <button type="button" role="tab" data-tab="ch-<?= $tab ?>" class="<?= $tab === 'fleet' ? 'active' : '' ?>"><?= icon($ic, 'icon icon-xs') ?> <?= e(t('dash.tab.' . $tab)) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="tab-panel active" data-panel="ch-fleet">
        <div class="chart-grid two">
            <div><h4><?= e(t('dashboard.by_status')) ?></h4><div class="chart" data-chart='<?= $j(['type' => 'donut', 'data' => $c['status'], 'link' => url('assets', $q) . ($q ? '&' : '?') . 'status=']) ?>'></div></div>
            <div><h4><?= e(t('dashboard.by_category')) ?></h4><div class="chart" data-chart='<?= $j(['type' => 'hbar', 'data' => $c['categories']]) ?>'></div></div>
        </div>
    </div>
    <div class="tab-panel" data-panel="ch-lifecycle">
        <div class="chart-grid one">
            <div><h4><?= e(t('dash.ch.warranty')) ?></h4><div class="chart" data-chart='<?= $j(['type' => 'bar', 'data' => $c['warranty']]) ?>'></div></div>
        </div>
    </div>
    <div class="tab-panel" data-panel="ch-service">
        <div class="chart-grid two">
            <div><h4><?= e(t('dash.ch.workload')) ?></h4><div class="chart" data-chart='<?= $j(['type' => 'line'] + $c['workload']) ?>'></div></div>
            <div><h4><?= e(t('dash.ch.mttr')) ?></h4><div class="chart" data-chart='<?= $j(['type' => 'bar', 'data' => $c['mttr']]) ?>'></div></div>
        </div>
    </div>
    <div class="tab-panel" data-panel="ch-licensing">
        <h4><?= e(t('dash.ch.licenses')) ?></h4>
        <?php if ($c['licenses']): ?><div class="chart" data-chart='<?= $j(['type' => 'hbar', 'data' => $c['licenses'], 'unit' => '%']) ?>'></div>
        <?php else: ?><p class="muted small"><?= e(t('common.nothing')) ?></p><?php endif; ?>
    </div>
</div>
