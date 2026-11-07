<?php page_title(t('custody.thanks_title')); ?>
<div class="public-page">
    <div class="login-card public-card text-center">
        <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></div>
        <div class="thanks-icon"><?= icon('check') ?></div>
        <h2><?= e(t('custody.thanks_title')) ?></h2>
        <p class="muted"><?= e(t('custody.thanks_body', ['no' => $r['receipt_no']])) ?></p>
        <p class="report-ref"><?= e(t('custody.fingerprint')) ?><br><code class="mono cert-hash"><?= e($r['doc_hash']) ?></code></p>
    </div>
</div>
