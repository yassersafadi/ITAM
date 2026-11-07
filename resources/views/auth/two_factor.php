<?php page_title(t('twofa.title')); ?>
<div class="public-page">
    <form class="login-card public-card" method="post" action="<?= url('login/2fa') ?>">
        <?= csrf_field() ?>
        <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></div>
        <div class="thanks-icon"><?= icon('lock') ?></div>
        <h2><?= e(t('twofa.title')) ?></h2>
        <p class="muted"><?= e(t('twofa.prompt')) ?></p>
        <?php foreach (take_flashes() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= icon('alert') ?><span><?= e($f['message']) ?></span></div>
        <?php endforeach; ?>
        <label class="field"><span><?= e(t('twofa.code')) ?></span>
            <input name="code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus dir="ltr" placeholder="123 456"></label>
        <button class="btn btn-primary btn-block" type="submit"><?= e(t('twofa.verify')) ?></button>
        <p class="hint"><?= icon('info') ?> <?= e(t('twofa.recovery_hint')) ?></p>
        <a class="link small" href="<?= url('login') ?>">‹ <?= e(t('twofa.back')) ?></a>
    </form>
</div>
