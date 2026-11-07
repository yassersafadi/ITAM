<?php page_title(t('custody.link_invalid')); ?>
<div class="public-page">
    <div class="login-card public-card text-center">
        <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></div>
        <div class="thanks-icon warn"><?= icon('alert') ?></div>
        <h2><?= e(t('custody.link_invalid')) ?></h2>
        <p class="muted"><?= e(t('custody.link_invalid_help')) ?></p>
    </div>
</div>
