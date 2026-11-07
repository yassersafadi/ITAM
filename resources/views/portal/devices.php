<?php page_title(t('portal.my_devices')); ?>
<div class="portal-head">
    <h1><?= e(t('portal.my_devices')) ?> <span class="count"><?= count($assets) ?></span></h1>
    <?php if ($assets): ?><button type="button" class="btn btn-primary btn-sm" data-modal-open="report"><?= icon('wrench') ?> <?= e(t('portal.report_problem')) ?></button><?php endif; ?>
</div>

<?php if (!$assets): ?>
    <section class="card empty-state"><?= icon('laptop') ?><p><?= e(t('portal.no_devices')) ?></p></section>
<?php endif; ?>

<div class="portal-device-list">
    <?php foreach ($assets as $a):
        $days = $a['warranty_until'] ? (int) floor((strtotime($a['warranty_until']) - strtotime(today())) / 86400) : null; ?>
        <a class="card portal-device" href="<?= url('portal/devices/' . $a['id']) ?>">
            <span class="portal-device-icon"><?= icon($a['icon'] ?? 'box') ?></span>
            <span class="portal-device-text">
                <strong dir="auto"><?= e($a['name']) ?></strong>
                <small class="mono" dir="ltr"><?= e($a['tag']) ?><?= $a['serial'] ? ' · ' . e($a['serial']) : '' ?></small>
                <small class="muted"><?= $a['since'] ? e(t('portal.since', ['date' => fmt_date($a['since'])])) : e($a['category_name'] ?? '') ?></small>
            </span>
            <span class="portal-device-tags">
                <?php if ($days !== null): ?><span class="badge badge-<?= $days < 0 ? 'neutral' : ($days <= 60 ? 'warning' : 'success') ?>"><?= e($days < 0 ? t('portal.warranty_over') : t('portal.warranty_left', ['n' => $days])) ?></span><?php endif; ?>
                <span class="portal-go" aria-hidden="true">‹</span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($licenses): ?>
    <section class="card" style="margin-top:16px">
        <h3 class="card-title"><?= icon('key') ?> <?= e(t('portal.my_software')) ?></h3>
        <ul class="list compact">
            <?php foreach ($licenses as $l): ?><li><span class="list-main"><span><strong dir="auto"><?= e($l['name']) ?></strong><?php if ($l['edition']): ?><small dir="auto"><?= e($l['edition']) ?></small><?php endif; ?></span></span></li><?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
