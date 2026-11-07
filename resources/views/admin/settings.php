<?php
use ITAM\Services\PlatformSettings as PS;

page_title(t('nav.settings'));
$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
$icons = ['identity' => 'tag', 'footer' => 'link', 'general' => 'globe', 'security' => 'shield', 'sso' => 'lock', 'service' => 'clock', 'servicedesk' => 'inbox', 'health' => 'heart', 'intelligence' => 'sparkles', 'anomaly' => 'eye', 'operations' => 'zap', 'discovery' => 'radar', 'notifications' => 'bell', 'backup' => 'download', 'integrations' => 'link', 'whatsapp' => 'phone', 'finance' => 'trend', 'procurement' => 'tag', 'gatepass' => 'logout', 'approvals' => 'sliders', 'endpoint' => 'lock', 'ai' => 'sparkles', 'hr' => 'users'];
$units = [
    'security.session_idle' => t('common.minutes'), 'security.lockout_minutes' => t('common.minutes'),
    'security.password_min' => t('settings.unit_chars'), 'security.max_attempts' => t('settings.unit_attempts'),
    'intelligence.warranty_warn_days' => t('common.days'), 'intelligence.ghost_after_days' => t('common.days'),
    'intelligence.idle_stock_days' => t('common.days'), 'intelligence.license_low_usage' => '%',
    'intelligence.grid_factor' => 'kg CO₂e / kWh', 'backup.keep' => t('backup.copies'),
    'sla.critical' => t('common.hours'), 'sla.high' => t('common.hours'), 'sla.medium' => t('common.hours'), 'sla.low' => t('common.hours'),
    'reports.per_hour' => t('settings.unit_reports'),
    'health.age_start' => '%', 'health.downtime_hours' => t('common.hours'),
    'health.age_at_end' => t('settings.unit_points'),
    'health.age_max' => t('settings.unit_points'),
    'health.warranty_expired' => t('settings.unit_points'),
    'health.warranty_expiring' => t('settings.unit_points'),
    'health.repair_each' => t('settings.unit_points'),
    'health.repairs_max' => t('settings.unit_points'),
    'health.downtime_max' => t('settings.unit_points'),
    'health.condition_good' => t('settings.unit_points'),
    'health.condition_fair' => t('settings.unit_points'),
    'health.condition_poor' => t('settings.unit_points'),
    'health.in_repair' => t('settings.unit_points'),
    'health.not_seen' => t('settings.unit_points'),
    'health.unsupported' => t('settings.unit_points'),
    'health.battery_worn' => t('settings.unit_points'), 'health.disk_failing' => t('settings.unit_points'), 'health.disk_worn' => t('settings.unit_points'),
    'anomaly.move_locations' => t('settings.unit_locations'), 'anomaly.move_days' => t('common.days'), 'anomaly.reassign_count' => t('settings.unit_people'),
    'anomaly.failures' => t('settings.unit_faults'), 'anomaly.failure_days' => t('common.days'),
    'anomaly.work_start' => t('settings.unit_hour'), 'anomaly.work_end' => t('settings.unit_hour'), 'executive.day' => t('settings.unit_day_of_month'),
    'monitor.fail_threshold' => t('settings.unit_checks'), 'monitor.timeout' => t('settings.unit_seconds'), 'printer.low_percent' => '%', 'intune.stale_days' => t('common.days'),
    'health.unstable' => t('settings.unit_points'), 'telemetry.battery_replace_at' => '%', 'discovery.timeout' => t('settings.unit_seconds'),
    'repair.replace_ratio' => '%', 'repair.residual' => '%', 'posture.pass_score' => '/ 100', 'posture.patch_days' => t('common.days'),
    'loans.max_days' => t('common.days'), 'loans.ahead_days' => t('common.days'), 'login.overlay' => '%',
    'finance.declining_rate' => '%', 'finance.inflation' => '%', 'finance.kwh_price' => t('fin.unit_kwh'),
    'procurement.default_tax' => '%', 'procurement.sme_preference' => '%', 'procurement.local_preference' => '%', 'procurement.local_min' => '%', 'procurement.renewal_days' => t('common.days'), 'approvals.remind_hours' => t('common.hours'), 'approvals.escalate_hours' => t('common.hours'), 'gatepass.return_days' => t('common.days'), 'hr.overtime_min' => t('common.minutes'), 'hr.leave_advance_days' => t('common.days'), 'hr.leave_backdate_days' => t('common.days'), 'payroll.gosi_annuity' => '%', 'payroll.gosi_new_annuity' => '%', 'payroll.gosi_saned' => '%', 'payroll.gosi_hazards' => '%', 'payroll.gosi_non_saudi' => '%',
];

/** Render one configurable field. */
$field = function (string $key, string $class = '') use ($units): string {
    $f = PS::FIELDS[$key];
    $name = str_replace('.', '__', $key);
    $value = (string) old($name, PS::formValue($key));
    $label = e(t('settings.f.' . $key));
    $helpKey = 'settings.h.' . $key;
    $help = t($helpKey) !== $helpKey ? '<small class="field-help">' . e(t($helpKey)) . '</small>' : '';
    $req = !empty($f['required']) ? ' required' : '';
    if ($f['type'] === 'bool') {
        return '<label class="check ' . $class . '"><input type="checkbox" name="' . $name . '" value="1"' . ($value === '1' ? ' checked' : '') . '> '
            . $label . '</label>' . ($help ? '<div class="' . $class . '">' . $help . '</div>' : '');
    }
    $input = match ($f['type']) {
        'select'   => '<select name="' . $name . '">' . implode('', array_map(
            fn ($k, $l) => '<option value="' . e($k) . '"' . ((string) $k === $value ? ' selected' : '') . '>' . e($l) . '</option>',
            array_keys(PS::options($key)), PS::options($key)
        )) . '</select>',
        'textarea' => '<textarea name="' . $name . '" rows="3" maxlength="' . (int) $f['max'] . '">' . e($value) . '</textarea>',
        'int', 'percent' => '<input type="number" name="' . $name . '" value="' . e($value) . '" min="' . $f['min'] . '" max="' . $f['max'] . '" step="1" required>',
        'float'    => '<input type="number" name="' . $name . '" value="' . e($value) . '" min="' . $f['min'] . '" max="' . $f['max'] . '" step="0.001" required>',
        'url'      => '<input type="url" name="' . $name . '" value="' . e($value) . '" placeholder="https://…" dir="ltr">',
        'secret'   => '<input type="password" name="' . $name . '" value="" autocomplete="new-password" dir="ltr" placeholder="' . (config($key) ? '••••••••  ' . e(t('settings.secret_keep')) : '') . '">',
        'email'    => '<input type="email" name="' . $name . '" value="' . e($value) . '" dir="ltr">',
        'networks' => '<textarea name="' . $name . '" rows="3" dir="ltr" placeholder="10.1.0.0/24&#10;10.2.0.1-10.2.0.50">' . e($value) . '</textarea>',
        'emails'   => '<textarea name="' . $name . '" rows="3" dir="ltr" placeholder="it-team@example.com">' . e($value) . '</textarea>',
        default    => '<input name="' . $name . '" value="' . e($value) . '" maxlength="' . (int) ($f['max'] ?? 255) . '"' . $req
            . ($key === 'app.name_ar' ? ' dir="rtl"' : '') . ($key === 'app.tag_prefix' ? ' pattern="[A-Za-z0-9]{2,8}" dir="ltr" class="mono"' : '')
            . (str_ends_with($key, '_format') ? ' dir="ltr" class="mono" spellcheck="false"' : '') . '>',
    };
    if (isset($units[$key])) {
        $input = '<span class="input-unit">' . $input . '<em>' . e($units[$key]) . '</em></span>';
    }
    $default = PS::default($key);
    $defaultNote = '';
    if (in_array($f['type'], ['int', 'float', 'percent'], true)) {
        $shown = $f['type'] === 'percent' ? round((float) $default * 100) : $default;
        $defaultNote = '<small class="field-help">' . e(t('settings.default_is', ['v' => $shown])) . '</small>';
    }
    if (str_ends_with($key, '_format') && ($example = \ITAM\Services\Numbering::example($value)) !== '') {
        $defaultNote = '<small class="field-help">' . e(t('settings.number_example')) . ' <b class="mono" dir="ltr">' . e($example) . '</b></small>';
    }
    return '<label class="field ' . $class . '"><span>' . $label . (!empty($f['required']) ? ' *' : '') . '</span>' . $input . $help . $defaultNote . '</label>';
};
?>
<div class="page-head">
    <div>
        <?php if ($tab !== null): ?><a href="<?= url('admin/settings') ?>" class="back-link">‹ <?= e(t('nav.settings')) ?></a><?php endif; ?>
        <h1><?= icon($tab !== null ? $icons[$tab] : 'settings', 'icon icon-title') ?> <?= e($tab !== null ? t('settings.tab.' . $tab) : t('nav.settings')) ?></h1>
        <p class="muted"><?= e($tab !== null ? t('settings.tab.' . $tab . '_desc') : t('settings.subtitle')) ?></p>
    </div>
    <?php if ($tab !== null): ?>
        <div class="page-actions">
            <label class="settings-switch"><span class="sr-only"><?= e(t('settings.go_to')) ?></span>
                <select data-nav-select aria-label="<?= e(t('settings.go_to')) ?>">
                    <?php foreach (PS::GROUPS as $group => $tabs): ?>
                        <optgroup label="<?= e(t('settings.group.' . $group)) ?>">
                            <?php foreach ($tabs as $tb): ?><option value="<?= url('admin/settings', ['tab' => $tb]) ?>"<?= $tab === $tb ? ' selected' : '' ?>><?= e(t('settings.tab.' . $tb)) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($tab !== 'integrations'): ?>
                <form method="post" action="<?= url('admin/settings/reset') ?>" data-confirm="<?= e(t('settings.reset_confirm')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <button class="btn btn-ghost"><?= icon('refresh') ?> <span class="hide-sm"><?= e(t('settings.reset')) ?></span></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($tab === null): ?>
    <div class="settings-home">
        <label class="settings-search"><?= icon('search') ?><input type="search" placeholder="<?= e(t('settings.search')) ?>" data-settings-filter aria-label="<?= e(t('settings.search')) ?>"></label>
        <?php foreach (PS::GROUPS as $group => $tabs): ?>
            <section class="settings-group" data-settings-group>
                <h2><?= e(t('settings.group.' . $group)) ?></h2>
                <div class="settings-cards">
                    <?php foreach ($tabs as $tb): ?>
                        <a class="settings-card" href="<?= url('admin/settings', ['tab' => $tb]) ?>" data-settings-card data-search="<?= e(mb_strtolower(t('settings.tab.' . $tb) . ' ' . t('settings.card.' . $tb) . ' ' . t('settings.tab.' . $tb . '_desc'))) ?>">
                            <span class="settings-card-icon"><?= icon($icons[$tb]) ?></span>
                            <span class="settings-card-text"><strong><?= e(t('settings.tab.' . $tb)) ?></strong><small><?= e(t('settings.card.' . $tb)) ?></small></span>
                            <span class="settings-card-go" aria-hidden="true">‹</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <p class="muted small" data-settings-empty hidden><?= e(t('settings.search_none')) ?></p>
    </div>
<?php else: ?>
<div class="settings-section">
<?php if ($tab === 'identity'): ?>
    <?= partial('admin/_identity', ['field' => $field]) ?>

<?php elseif ($tab === 'backup'): ?>
    <div class="grid grid-3">
        <section class="card span-2 table-card">
            <div class="card-head">
                <h3><?= icon('download') ?> <?= e(t('backup.list')) ?></h3>
                <?php if (\ITAM\Services\Backup::supported()): ?>
                    <form method="post" action="<?= url('admin/backups') ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm"><?= icon('plus') ?> <?= e(t('backup.create')) ?></button></form>
                <?php endif; ?>
            </div>
            <?php if (!\ITAM\Services\Backup::supported()): ?>
                <div class="callout callout-info" style="margin:16px"><?= icon('info') ?><div class="small"><?= e(t('backup.unsupported')) ?></div></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table compact">
                        <thead><tr><th><?= e(t('backup.file')) ?></th><th><?= e(t('field.date')) ?></th><th><?= e(t('backup.size')) ?></th><th><?= e(t('field.type')) ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td class="mono"><?= e($b['name']) ?></td>
                                <td><?= fmt_date(date('Y-m-d H:i:s', $b['time']), true) ?></td>
                                <td><?= fmt_num($b['size'] / 1048576, 2) ?> MB</td>
                                <td><span class="badge badge-<?= $b['auto'] ? 'neutral' : 'info' ?>"><?= e(t($b['auto'] ? 'backup.auto' : 'backup.manual')) ?></span></td>
                                <td class="actions"><span class="inline-actions">
                                    <a class="icon-btn sm" href="<?= url('admin/backups/' . $b['name']) ?>" title="<?= e(t('files.download')) ?>" aria-label="<?= e(t('files.download')) ?>"><?= icon('download') ?></a>
                                    <form method="post" action="<?= url('admin/backups/' . $b['name'] . '/delete') ?>" data-confirm="<?= e(t('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="icon-btn sm" aria-label="<?= e(t('common.delete')) ?>"><?= icon('trash') ?></button></form>
                                </span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$backups): ?><tr><td colspan="5" class="muted"><?= e(t('backup.none')) ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <div class="stack">
            <form method="post" action="<?= url('admin/settings') ?>" class="card stack">
                <?= csrf_field() ?><input type="hidden" name="tab" value="backup">
                <?= $field('backup.daily') ?>
                <?= $field('backup.keep') ?>
                <button class="btn btn-primary"><?= icon('check') ?> <?= e(t('common.save')) ?></button>
            </form>
            <section class="card">
                <h3 class="card-title"><?= icon('refresh') ?> <?= e(t('backup.restore')) ?></h3>
                <p class="small muted"><?= e(t('backup.restore_help')) ?></p>
                <pre class="code-block">storage/itam.sqlite</pre>
            </section>
        </div>
    </div>

<?php elseif ($tab === 'integrations'): ?>
    <div class="grid grid-2">
        <section class="card">
            <h3 class="card-title"><?= icon('key') ?> <?= e(t('settings.tokens')) ?></h3>
            <?php if ($newToken): ?>
                <div class="callout callout-success">
                    <?= icon('check') ?>
                    <div><strong><?= e(t('settings.token_created')) ?></strong>
                        <p class="small"><?= e(t('settings.token_once')) ?></p>
                        <code class="code-block copyable" data-copy><?= e($newToken) ?></code></div>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= url('admin/tokens') ?>" class="inline-form">
                <?= csrf_field() ?>
                <input name="name" placeholder="<?= e(t('settings.token_name')) ?>" required>
                <button class="btn btn-primary btn-sm"><?= icon('plus') ?> <?= e(t('settings.create_token')) ?></button>
                <span class="token-scopes">
                    <?php foreach (\ITAM\Controllers\ApiController::SCOPES as $sc): ?>
                        <label class="check"><input type="checkbox" name="scopes[]" value="<?= $sc ?>" checked> <?= e(t('token.scope.' . $sc)) ?></label>
                    <?php endforeach; ?>
                </span>
            </form>
            <p class="small muted"><?= e(t('token.scopes_help')) ?></p>
            <ul class="list">
                <?php foreach ($tokens as $tk): ?>
                    <li><span class="list-main"><span class="list-icon"><?= icon('key') ?></span><span><strong><?= e($tk['name']) ?></strong><small><?= e(t('settings.token_used', ['t' => time_ago($tk['last_used_at'])])) ?></small>
                        <small><?= $tk['scopes'] === null ? '<span class="badge badge-warning">' . e(t('token.legacy')) . '</span>' : e(implode(' · ', array_map(fn ($s) => t('token.scope.' . $s), explode(',', (string) $tk['scopes'])))) ?></small></span></span>
                        <form method="post" action="<?= url('admin/tokens/' . $tk['id'] . '/delete') ?>" data-confirm="<?= e(t('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(t('settings.revoke')) ?></button></form></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card">
            <h3 class="card-title"><?= icon('link') ?> <?= e(t('settings.api_ref')) ?></h3>
            <pre class="code-block">GET <?= e($origin) ?>/api/v1/assets?status=deployed&amp;warranty=expiring
GET <?= e($origin) ?>/api/v1/assets/{tag}
Authorization: Bearer &lt;token&gt;</pre>
        </section>
    </div>

<?php else: ?>
    <form method="post" action="<?= url('admin/settings') ?>" class="card">
        <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="fields">
            <?php foreach (array_keys(PS::fields($tab)) as $key): ?>
                <?= $field($key, PS::FIELDS[$key]['type'] === 'bool' ? 'span-2' : '') ?>
            <?php endforeach; ?>
            <div class="form-actions"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('common.save')) ?></button></div>
        </div>
    </form>
    <?php if ($tab === 'sso'): ?>
        <section class="card" style="margin-top:18px">
            <h3 class="card-title"><?= icon('info') ?> <?= e(t('sso.setup_title')) ?></h3>
            <ol class="steps small">
                <li><?= e(t('sso.step1')) ?></li>
                <li><?= e(t('sso.step2')) ?> <code class="mono" dir="ltr"><?= e(\ITAM\Services\Oidc::redirectUri()) ?></code></li>
                <li><?= e(t('sso.step3')) ?></li>
                <li><?= e(t('sso.step4')) ?></li>
            </ol>
        </section>
    <?php endif; ?>
    <?php if ($tab === 'notifications'): ?>
        <section class="card table-card" style="margin-top:18px">
            <div class="card-head">
                <h3><?= icon('bell') ?> <?= e(t('notify.outbox')) ?></h3>
                <form method="post" action="<?= url('admin/notifications/test') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('zap') ?> <?= e(t('notify.send_test')) ?></button></form>
            </div>
            <div class="table-wrap">
                <table class="table compact">
                    <thead><tr><th><?= e(t('field.date')) ?></th><th><?= e(t('notify.channel')) ?></th><th><?= e(t('notify.recipient')) ?></th><th><?= e(t('field.title')) ?></th><th><?= e(t('field.status')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($outbox as $o): ?>
                        <tr>
                            <td><?= fmt_date($o['created_at'], true) ?></td>
                            <td><?= e(t('notify.email')) ?></td>
                            <td class="mono small"><?= e($o['recipient']) ?></td>
                            <td dir="auto"><?= e($o['subject']) ?></td>
                            <td><span class="badge badge-<?= $o['status'] === 'sent' ? 'success' : ($o['status'] === 'failed' ? 'danger' : 'neutral') ?>" title="<?= e($o['error'] ?? '') ?>"><?= e(t('notify.status.' . $o['status'])) ?></span>
                                <?php if ($o['status'] === 'pending' && !empty($o['next_attempt_at'])): ?><small class="block muted"><?= e(t('notify.retry_at', ['n' => (int) $o['attempts'], 'time' => fmt_date($o['next_attempt_at'], true)])) ?></small><?php endif; ?>
                                <?php if ($o['error']): ?><small class="block text-danger" dir="ltr"><?= e($o['error']) ?></small><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$outbox): ?><tr><td colspan="5" class="muted"><?= e(t('notify.outbox_empty')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php endif; ?>
<?php unset($_SESSION['_old']); ?>
