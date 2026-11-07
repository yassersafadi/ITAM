<div class="print-toolbar no-print">
    <strong><?= e(t('labels.title')) ?></strong>
    <span class="muted"><?= e(t('labels.count', ['n' => count($assets)])) ?></span>
    <button class="btn btn-primary" onclick="window.print()"><?= icon('printer') ?> <?= e(t('labels.print')) ?></button>
</div>
<div class="labels">
    <?php foreach ($assets as $a): ?>
        <div class="label-cell">
            <div class="label">
                <div class="label-qr" data-qr="<?= e(rtrim($origin, '/') . url('a/' . rawurlencode($a['tag']))) ?>"></div>
                <div class="label-text">
                    <img src="<?= brand_asset('logo_mark') ?>" alt="" class="label-logo">
                    <strong class="mono"><?= e($a['tag']) ?></strong>
                    <span><?= e(mb_strimwidth($a['name'], 0, 34, '…')) ?></span>
                    <small><?= e($a['serial'] ? 'S/N ' . $a['serial'] : ($a['hostname'] ?? '')) ?></small>
                    <small class="label-org"><?= e(app_name()) ?></small>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
