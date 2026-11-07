<?php
use ITAM\Core\Lang;

page_title(t('install.title'));
$i = array_search($step, $steps, true);
$db = $data['db'] ?? ['driver' => 'sqlite'];
$org = $data['org'] ?? ['vendor' => '', 'name' => 'ITAM', 'name_ar' => 'إدارة الأصول', 'locale' => Lang::locale(), 'timezone' => 'Asia/Riyadh', 'currency' => 'SAR'];
$admin = $data['admin'] ?? ['name' => '', 'email' => ''];
?>
<div class="public-page install-page">
    <div class="install-card card">
        <div class="install-head">
            <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(t('install.title')) ?></span></div>
            <div class="install-langs">
                <?php foreach ($locales as $code => $l): if ($code === Lang::locale()) continue; ?><a class="link small" href="<?= url('lang/' . $code) ?>"><?= e($l['label']) ?></a><?php endforeach; ?>
            </div>
        </div>
        <ol class="rma-steps install-steps">
            <?php foreach ($steps as $n => $s): ?><li class="<?= $n <= $i ? 'done' : '' ?><?= $s === $step ? ' current' : '' ?>"><span><?= $n + 1 ?></span><?= e(t('install.step.' . $s)) ?></li><?php endforeach; ?>
        </ol>

        <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endforeach; ?>

        <form method="post" action="<?= url('install/' . $step) ?>" class="stack" autocomplete="off">
            <?= csrf_field() ?>
            <h2><?= e(t('install.step.' . $step)) ?></h2>
            <p class="muted"><?= e(t('install.help.' . $step)) ?></p>

            <?php if ($step === 'language'): ?>
                <div class="radio-cards">
                    <?php foreach ($locales as $code => $l): ?>
                        <label class="radio-card"><input type="radio" name="locale" value="<?= e($code) ?>" <?= Lang::locale() === $code ? 'checked' : '' ?>><strong><?= e($l['label']) ?></strong></label>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($step === 'requirements'): ?>
                <ul class="list compact">
                    <?php foreach ($requirements as $r): ?>
                        <li><span class="mono"><?= e($r['name']) ?> <small class="muted"><?= e($r['detail']) ?></small></span>
                            <span class="badge badge-<?= $r['ok'] ? 'success' : ($r['required'] ? 'danger' : 'warning') ?>"><?= e(t($r['ok'] ? 'install.ok' : ($r['required'] ? 'install.missing' : 'install.optional'))) ?></span></li>
                    <?php endforeach; ?>
                </ul>

            <?php elseif ($step === 'database'): ?>
                <div class="radio-cards">
                    <label class="radio-card"><input type="radio" name="driver" value="sqlite" <?= $db['driver'] === 'sqlite' ? 'checked' : '' ?> data-db-driver><strong>SQLite</strong><small><?= e(t('install.sqlite_help')) ?></small></label>
                    <label class="radio-card"><input type="radio" name="driver" value="mysql" <?= $db['driver'] === 'mysql' ? 'checked' : '' ?> data-db-driver><strong>MySQL / MariaDB</strong><small><?= e(t('install.mysql_help')) ?></small></label>
                </div>
                <div class="form-grid" data-mysql-fields>
                    <label class="field"><span><?= e(t('install.db_host')) ?></span><input name="host" value="<?= e($db['host'] ?? '127.0.0.1') ?>" dir="ltr"></label>
                    <label class="field"><span><?= e(t('install.db_port')) ?></span><input type="number" name="port" value="<?= (int) ($db['port'] ?? 3306) ?>" dir="ltr"></label>
                    <label class="field"><span><?= e(t('install.db_name')) ?></span><input name="database" value="<?= e($db['database'] ?? 'itam') ?>" dir="ltr"></label>
                    <label class="field"><span><?= e(t('install.db_user')) ?></span><input name="username" value="<?= e($db['username'] ?? '') ?>" dir="ltr"></label>
                    <label class="field span-2"><span><?= e(t('install.db_password')) ?></span><input type="password" name="password" dir="ltr" autocomplete="new-password"></label>
                </div>

            <?php elseif ($step === 'organization'): ?>
                <label class="field"><span><?= e(t('settings.f.app.vendor')) ?> *</span><input name="vendor" value="<?= e($org['vendor']) ?>" required maxlength="80" dir="auto"></label>
                <div class="form-grid">
                    <label class="field"><span><?= e(t('settings.f.app.name')) ?> *</span><input name="name" value="<?= e($org['name']) ?>" required maxlength="80"></label>
                    <label class="field"><span><?= e(t('settings.f.app.name_ar')) ?> *</span><input name="name_ar" value="<?= e($org['name_ar']) ?>" required maxlength="80" dir="rtl"></label>
                    <label class="field"><span><?= e(t('settings.f.i18n.default')) ?></span><select name="locale"><?php foreach ($locales as $code => $l): ?><option value="<?= e($code) ?>" <?= $org['locale'] === $code ? 'selected' : '' ?>><?= e($l['label']) ?></option><?php endforeach; ?></select></label>
                    <label class="field"><span><?= e(t('settings.f.app.timezone')) ?></span><select name="timezone"><?php foreach ($timezones as $tz): ?><option <?= $org['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></label>
                    <label class="field"><span><?= e(t('settings.f.app.currency')) ?></span><select name="currency"><?php foreach (\ITAM\Controllers\InstallController::CURRENCIES as $cur): ?><option <?= ($org['currency'] ?? 'SAR') === $cur ? 'selected' : '' ?>><?= e($cur) ?></option><?php endforeach; ?></select></label>
                </div>

            <?php elseif ($step === 'admin'): ?>
                <label class="field"><span><?= e(t('field.name')) ?> *</span><input name="name" value="<?= e($admin['name']) ?>" required maxlength="120" dir="auto"></label>
                <label class="field"><span><?= e(t('install.email')) ?> *</span><input type="email" name="email" value="<?= e($admin['email']) ?>" required dir="ltr"></label>
                <div class="form-grid">
                    <label class="field"><span><?= e(t('install.password')) ?> *</span><input type="password" name="password" required autocomplete="new-password" dir="ltr"></label>
                    <label class="field"><span><?= e(t('install.password_confirm')) ?> *</span><input type="password" name="password_confirm" required autocomplete="new-password" dir="ltr"></label>
                </div>
                <small class="field-help"><?= e(t('install.password_help', ['n' => (int) config('security.password_min', 8)])) ?></small>

            <?php else: ?>
                <dl class="kv">
                    <dt><?= e(t('install.step.database')) ?></dt><dd class="mono" dir="ltr"><?= e($db['driver'] === 'mysql' ? "mysql://{$db['username']}@{$db['host']}:{$db['port']}/{$db['database']}" : 'storage/itam.sqlite') ?></dd>
                    <dt><?= e(t('settings.f.app.vendor')) ?></dt><dd dir="auto"><?= e($org['vendor']) ?></dd>
                    <dt><?= e(t('install.step.admin')) ?></dt><dd><?= e($admin['name']) ?> · <span dir="ltr"><?= e($admin['email']) ?></span></dd>
                </dl>
                <label class="check"><input type="checkbox" name="demo" value="1"> <?= e(t('install.demo')) ?></label>
                <p class="muted small"><?= icon('info', 'icon icon-xs') ?> <?= e(t('install.after_note')) ?></p>
            <?php endif; ?>

            <div class="install-actions">
                <?php if ($i > 0): ?><a class="btn btn-ghost" href="<?= url('install/' . $steps[$i - 1]) ?>"><?= e(t('common.back')) ?></a><?php else: ?><span></span><?php endif; ?>
                <button class="btn btn-primary" <?= $step === 'requirements' && !$ready ? 'disabled' : '' ?>><?= e(t($step === 'finish' ? 'install.install_now' : 'common.next')) ?></button>
            </div>
        </form>
    </div>
</div>
<script>
    // MySQL fields only when MySQL is chosen (works without the main bundle, which needs a signed-in page).
    (function () {
        var box = document.querySelector('[data-mysql-fields]');
        if (!box) return;
        var sync = function () { box.hidden = !document.querySelector('[data-db-driver][value=mysql]').checked; };
        document.querySelectorAll('[data-db-driver]').forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
</script>
