<?php page_title(t('reset.new_title')); ?>
<div class="login login-centered login-bg-theme">
    <section class="login-form-wrap">
        <form class="login-card" method="post" action="<?= url('reset-password/' . $token) ?>">
            <?= csrf_field() ?>
            <img src="<?= brand_asset('logo_full') ?>" alt="<?= e(vendor_name()) ?>" class="login-logo">
            <h2><?= e(t('reset.new_title')) ?></h2>
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>"><?= icon($f['type'] === 'success' ? 'check' : 'alert') ?><span><?= e($f['message']) ?></span></div>
            <?php endforeach; ?>
            <?php if ($valid): ?>
                <p class="muted"><?= e(t('reset.new_intro', ['n' => (int) config('security.password_min', 8)])) ?></p>
                <label class="field"><span><?= e(t('reset.new_password')) ?></span><input type="password" name="password" required minlength="<?= (int) config('security.password_min', 8) ?>" autocomplete="new-password" autofocus></label>
                <label class="field"><span><?= e(t('reset.confirm')) ?></span><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
                <button class="btn btn-primary btn-block" type="submit"><?= e(t('reset.save')) ?></button>
            <?php else: ?>
                <p class="muted"><?= e(t('reset.invalid')) ?></p>
                <a class="btn btn-ghost btn-block" href="<?= url('forgot-password') ?>"><?= e(t('reset.again')) ?></a>
            <?php endif; ?>
            <a class="link small" href="<?= url('login') ?>">‹ <?= e(t('reset.back')) ?></a>
        </form>
    </section>
</div>
