<?php page_title(t('nav.profile')); ?>
<div class="page-head">
    <div>
        <h1><?= icon('user', 'icon icon-title') ?> <?= e(t('nav.profile')) ?></h1>
        <p class="muted"><?= e($user['email']) ?> · <?= e(role_name($user['role'])) ?></p>
    </div>
</div>
<section class="card narrow">
    <form method="post" action="<?= url('profile') ?>" class="stack">
        <?= csrf_field() ?>
        <div class="fields">
            <label class="field span-2"><span><?= e(t('field.name')) ?></span><input name="name" value="<?= e($user['name']) ?>" required></label>
            <label class="field"><span><?= e(t('field.locale')) ?></span><select name="locale"><?php foreach (config('i18n.locales') as $c => $l): ?><option value="<?= $c ?>" <?= $user['locale'] === $c ? 'selected' : '' ?>><?= e($l['label']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span><?= e(t('common.theme')) ?></span><select name="theme" data-theme-select><?php foreach (['system', 'light', 'dark'] as $th): ?><option value="<?= $th ?>" <?= $user['theme'] === $th ? 'selected' : '' ?>><?= e(t('theme.' . $th)) ?></option><?php endforeach; ?></select></label>
        </div>
        <h4><?= e(t('profile.change_password')) ?></h4>
        <div class="fields">
            <label class="field"><span><?= e(t('profile.current_password')) ?></span><input type="password" name="current_password" autocomplete="current-password"></label>
            <label class="field"><span><?= e(t('profile.new_password')) ?></span><input type="password" name="new_password" minlength="<?= (int) config('security.password_min', 8) ?>" autocomplete="new-password"></label>
        </div>
        <div class="modal-actions"><span class="grow"></span><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
    </form>
</section>

<script src="<?= asset('vendor/qrcode.js') ?>"></script>
<section class="card narrow twofa" id="twofa">
    <h3 class="card-title"><?= icon('lock') ?> <?= e(t('twofa.section')) ?>
        <?php if ($user['totp_enabled']): ?><span class="badge badge-success"><?= e(t('twofa.on')) ?></span><?php else: ?><span class="badge badge-warning"><?= e(t('twofa.off')) ?></span><?php endif; ?></h3>
    <?php if (!empty($_GET['require2fa'])): ?>
        <div class="callout callout-warning"><?= icon('alert') ?><div class="small"><?= e(t('twofa.required')) ?></div></div>
    <?php endif; ?>
    <?php if ($codes = $_SESSION['2fa_codes'] ?? null): unset($_SESSION['2fa_codes']); ?>
        <div class="callout callout-success"><?= icon('check') ?><div>
            <strong><?= e(t('twofa.codes_title')) ?></strong>
            <p class="small"><?= e(t('twofa.codes_once')) ?></p>
            <code class="code-block recovery-codes copyable" data-copy><?= e(implode("\n", $codes)) ?></code>
        </div></div>
    <?php endif; ?>

    <?php if ($user['totp_enabled']): ?>
        <p class="muted small"><?= e(t('twofa.active_help')) ?></p>
        <form method="post" action="<?= url('profile/2fa/disable') ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="password" name="current_password" placeholder="<?= e(t('profile.current_password')) ?>" required autocomplete="current-password">
            <button class="btn btn-danger btn-sm"><?= e(t('twofa.disable')) ?></button>
        </form>
    <?php elseif ($secret = $_SESSION['2fa_setup'] ?? null): ?>
        <div class="twofa-setup">
            <div class="cert-qr twofa-qr" data-qr="<?= e(\ITAM\Services\Totp::uri($secret, $user['email'])) ?>"></div>
            <div class="stack">
                <p class="small"><?= e(t('twofa.scan')) ?></p>
                <code class="code-block mono"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code>
                <form method="post" action="<?= url('profile/2fa/enable') ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input name="code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="123456" dir="ltr">
                    <button class="btn btn-primary btn-sm"><?= e(t('twofa.confirm')) ?></button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <p class="muted small"><?= e(t('twofa.intro')) ?></p>
        <form method="post" action="<?= url('profile/2fa/setup') ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm"><?= icon('lock') ?> <?= e(t('twofa.enable')) ?></button></form>
    <?php endif; ?>
</section>
