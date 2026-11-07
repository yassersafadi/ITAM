<?php page_title(t('reset.title')); ?>
<div class="login login-centered login-bg-theme">
    <section class="login-form-wrap">
        <form class="login-card" method="post" action="<?= url('forgot-password') ?>">
            <?= csrf_field() ?>
            <img src="<?= brand_asset('logo_full') ?>" alt="<?= e(vendor_name()) ?>" class="login-logo">
            <h2><?= e(t('reset.title')) ?></h2>
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>"><?= icon($f['type'] === 'success' ? 'check' : 'alert') ?><span><?= e($f['message']) ?></span></div>
            <?php endforeach; ?>
            <?php if ($email): ?>
                <p class="muted"><?= e(t('reset.intro')) ?></p>
                <label class="field"><span><?= e(t('field.email')) ?></span><input type="email" name="email" required autofocus autocomplete="username"></label>
                <button class="btn btn-primary btn-block" type="submit"><?= e(t('reset.send')) ?></button>
            <?php else: ?>
                <p class="muted"><?= e(t('reset.no_mail')) ?></p>
            <?php endif; ?>
            <p class="small muted"><?= e(t('reset.sso_note')) ?></p>
            <a class="link small" href="<?= url('login') ?>">‹ <?= e(t('reset.back')) ?></a>
        </form>
    </section>
</div>
