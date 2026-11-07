<?php
use ITAM\Controllers\LicenseController;

page_title(t('nav.licenses'));
$lowUsage = (float) config('intelligence.license_low_usage', 0.6);
$filters = ['' => t('common.all'), 'over' => t('licenses.f.over'), 'expiring' => t('licenses.f.expiring'), 'expired' => t('licenses.f.expired')];
?>
<div class="page-head">
    <div>
        <h1><?= e(t('nav.licenses')) ?></h1>
        <p class="muted"><?= e(t('licenses.subtitle')) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('licenses.edit')): ?>
            <button class="btn btn-primary" data-modal-open="new-license"><?= icon('plus') ?> <?= e(t('licenses.new')) ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="seg">
    <?php foreach ($filters as $k => $label): ?>
        <a href="<?= url('licenses', ['filter' => $k]) ?>" class="<?= $filter === $k ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="license-grid">
    <?php foreach ($licenses as $l):
        $used = (int) $l['used'];
        $seats = (int) $l['seats'];
        $pct = (int) round($used / max(1, $seats) * 100);
        $expDays = $l['expires_at'] ? -1 * days_between($l['expires_at']) : null;
        $tone = $used > $seats ? 'danger' : ($used / max(1, $seats) < $lowUsage && $seats >= 5 ? 'warning' : 'success');
    ?>
        <a class="card license-card" href="<?= url('licenses/' . $l['id']) ?>">
            <div class="license-head">
                <span class="kpi-icon tone-teal"><?= icon('key') ?></span>
                <div>
                    <strong><?= e($l['name']) ?></strong>
                    <small class="muted"><?= e(trim($l['publisher'] . ' · ' . $l['edition'], ' ·')) ?></small>
                </div>
            </div>
            <div class="license-usage">
                <span><b><?= $used ?></b> / <?= $seats ?> <?= e(t('licenses.seats')) ?></span>
                <span class="text-<?= $tone ?>"><?= $pct ?>%</span>
            </div>
            <span class="meter"><span class="bg-<?= $tone ?>" style="width: <?= min(100, $pct) ?>%"></span></span>
            <div class="license-foot">
                <span class="badge badge-neutral"><?= e(t('license_type.' . $l['license_type'])) ?></span>
                <?php if ($expDays !== null): ?>
                    <span class="small <?= $expDays < 0 ? 'text-danger' : ($expDays <= 45 ? 'text-warning' : 'muted') ?>">
                        <?= $expDays < 0 ? e(t('licenses.expired')) : e(t('assets.days_left', ['n' => $expDays])) ?>
                    </span>
                <?php else: ?><span class="small muted"><?= e(t('licenses.perpetual')) ?></span><?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
    <?php if (!$licenses): ?><div class="empty-state"><?= icon('key') ?><p><?= e(t('common.nothing')) ?></p></div><?php endif; ?>
</div>

<?php if (can('licenses.edit')): ?>
<dialog class="modal" id="modal-new-license">
    <form method="post" action="<?= url('licenses') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('key') ?> <?= e(t('licenses.new')) ?></h3>
        <?= partial('licenses/fields', ['l' => ['license_type' => 'per_device', 'seats' => 1]]) ?>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.create')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
