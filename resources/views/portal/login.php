<?php
use ITAM\Core\Lang;

page_title(t('portal.title'));
?>
<div class="public-page">
    <div class="login-langs">
        <?php foreach (config('i18n.locales') as $code => $l): ?>
            <a href="<?= url('lang/' . $code) ?>" class="<?= $code === Lang::locale() ? 'active' : '' ?>"><?= e($l['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="login-card public-card">
        <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></div>
        <h2><?= e(t('portal.title')) ?></h2>
        <p class="muted"><?= e(t('portal.login_sub')) ?></p>
        <?php foreach (take_flashes() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= icon($f['type'] === 'success' ? 'check' : 'alert') ?><span><?= e($f['message']) ?></span></div>
        <?php endforeach; ?>
        <?php if ($sso): ?>
            <a class="btn btn-ghost btn-block sso-btn" href="<?= url('portal/sso') ?>"><svg class="icon" viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg> <?= e(t('sso.button')) ?></a>
        <?php endif; ?>
        <?php if ($sso && $email): ?><div class="or-divider"><span><?= e(t('sso.or')) ?></span></div><?php endif; ?>
        <?php if ($email): ?>
            <form method="post" action="<?= url('portal/link') ?>" class="stack">
                <?= csrf_field() ?>
                <label class="field"><span><?= e(t('portal.work_email')) ?></span><input type="email" name="email" required autocomplete="email" dir="ltr" placeholder="name@company.com"></label>
                <button class="btn btn-primary btn-block"><?= icon('mail') ?> <?= e(t('portal.send_link')) ?></button>
            </form>
        <?php endif; ?>
        <?php if (!$sso && !$email): ?>
            <div class="callout callout-info"><?= icon('info') ?><div class="small"><?= e(t('portal.not_configured')) ?></div></div>
        <?php endif; ?>
        <p class="hint"><?= icon('info') ?> <?= e(t('portal.staff_hint')) ?> <a href="<?= url('login') ?>"><?= e(t('login.submit')) ?></a></p>
    </div>
</div>
