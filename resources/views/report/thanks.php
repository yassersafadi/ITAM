<?php page_title(t('report.thanks_title')); ?>
<div class="public-page">
    <div class="login-card public-card text-center">
        <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></div>
        <div class="thanks-icon"><?= icon('check') ?></div>
        <h2><?= e(t('report.thanks_title')) ?></h2>
        <p class="muted"><?= e(t('report.thanks_body', ['tag' => $asset['tag']])) ?></p>
        <?php if ($ref): ?><p class="report-ref"><?= e(t('report.reference')) ?> <strong class="mono"><?= e((string) $ref) ?></strong></p><?php endif; ?>
        <a class="btn btn-ghost" href="<?= url('report/' . rawurlencode($asset['tag'])) ?>"><?= e(t('report.another')) ?></a>
    </div>
</div>
