<?php use ITAM\Core\Auth; page_title(t('nav.users')); ?>
<div class="page-head">
    <div>
        <h1><?= icon('user', 'icon icon-title') ?> <?= e(t('nav.users')) ?></h1>
        <p class="muted"><?= e(t('users.subtitle')) ?></p>
    </div>
    <div class="page-actions"><?php if (Auth::isAdmin()): ?><a class="btn btn-ghost" href="<?= url('admin/roles') ?>"><?= icon('shield') ?> <?= e(t('roles.title')) ?></a><?php endif; ?><button class="btn btn-primary" data-modal-open="new-user"><?= icon('plus') ?> <?= e(t('users.new')) ?></button></div>
</div>

<section class="roles-help">
    <?php foreach (Auth::roles() as $r): ?>
        <div class="card"><strong><?= e(role_name($r)) ?></strong><p class="small muted"><?= e(\ITAM\Core\Auth::roleSummary($r)) ?></p></div>
    <?php endforeach; ?>
</section>

<?php foreach ($users as $u): ?>
    <form method="post" action="<?= url('admin/users/' . $u['id']) ?>" id="uf-<?= $u['id'] ?>" hidden><?= csrf_field() ?></form>
    <?php if ((int) $u['totp_enabled'] && (int) $u['id'] !== Auth::id()): ?>
        <form method="post" action="<?= url('admin/users/' . $u['id'] . '/2fa-reset') ?>" id="u2fa-<?= $u['id'] ?>" data-confirm="<?= e(t('users.2fa_reset_confirm', ['name' => $u['name']])) ?>" hidden><?= csrf_field() ?></form>
    <?php endif; ?>
<?php endforeach; ?>
<section class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= e(t('field.name')) ?></th><th><?= e(t('field.email')) ?></th><th><?= e(t('field.role')) ?></th><th><?= e(t('scope.locations')) ?></th><th><?= e(t('users.last_login')) ?></th><th><?= e(t('users.2fa')) ?></th><th><?= e(t('field.active')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><div class="cell-main"><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span><strong><?= e($u['name']) ?></strong></div></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?php if ($u['managed_by'] === 'entra'): ?><span class="badge badge-info" title="<?= e(t('access.by_group')) ?>">Entra</span> <?php endif; ?><select name="role" form="uf-<?= $u['id'] ?>"><?php foreach (Auth::roles() as $r): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(role_name($r)) ?></option><?php endforeach; ?></select></td>
                    <td><?php $scope = array_filter(explode(',', (string) $u['location_scope'])); $names = array_column(array_filter($locations, fn ($l) => in_array((string) $l['id'], $scope, true)), 'name'); ?>
                        <details class="scope-pick"><summary class="small"><?= $names ? e(implode(', ', $names)) : e(t('scope.all')) ?></summary>
                            <div class="scope-menu"><?php foreach ($locations as $l): ?><label class="check small"><input type="checkbox" name="location_scope[]" value="<?= (int) $l['id'] ?>" form="uf-<?= $u['id'] ?>"<?= in_array((string) $l['id'], $scope, true) ? ' checked' : '' ?>> <?= e($l['name']) ?></label><?php endforeach; ?>
                            <small class="muted"><?= e(t('scope.help')) ?></small></div></details>
                        ></td>
                    <td><?= e(time_ago($u['last_login_at'])) ?></td>
                    <td><?php if ((int) $u['totp_enabled']): ?><span class="badge badge-success"><?= e(t('twofa.on')) ?></span>
                        <?php if ((int) $u['id'] !== Auth::id()): ?><button class="btn btn-ghost btn-sm" form="u2fa-<?= $u['id'] ?>" title="<?= e(t('users.2fa_reset_help')) ?>"><?= icon('refresh') ?> <?= e(t('users.2fa_reset')) ?></button><?php endif; ?>
                    <?php else: ?><span class="badge badge-neutral"><?= e(t('twofa.off')) ?></span><?php endif; ?></td>
                    <td><input type="checkbox" name="active" value="1" form="uf-<?= $u['id'] ?>" <?= $u['active'] ? 'checked' : '' ?>></td>
                    <td class="actions">
                        <input type="password" name="password" placeholder="<?= e(t('users.new_password')) ?>" form="uf-<?= $u['id'] ?>" class="tiny-input wide" autocomplete="new-password">
                        <button class="btn btn-ghost btn-sm" form="uf-<?= $u['id'] ?>"><?= e(t('common.save')) ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="modal" id="modal-new-user">
    <form method="post" action="<?= url('admin/users') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('user') ?> <?= e(t('users.new')) ?></h3>
        <div class="fields">
            <label class="field span-2"><span><?= e(t('field.name')) ?> *</span><input name="name" required></label>
            <label class="field span-2"><span><?= e(t('field.email')) ?> *</span><input type="email" name="email" required></label>
            <label class="field"><span><?= e(t('field.role')) ?></span><select name="role"><?php foreach (Auth::roles() as $r): ?><option value="<?= $r ?>" <?= $r === 'technician' ? 'selected' : '' ?>><?= e(role_name($r)) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span><?= e(t('field.locale')) ?></span><select name="locale"><?php foreach (config('i18n.locales') as $c => $l): ?><option value="<?= $c ?>"><?= e($l['label']) ?></option><?php endforeach; ?></select></label>
            <fieldset class="field scope-field span-2"><legend><?= e(t('scope.locations')) ?></legend>
                <div class="scope-grid"><?php foreach ($locations as $l): ?><label class="scope-option"><input type="checkbox" name="location_scope[]" value="<?= (int) $l['id'] ?>"> <span dir="auto"><?= e($l['name']) ?></span></label><?php endforeach; ?></div>
                <small class="muted"><?= e(t('scope.help')) ?></small></fieldset>
            <label class="field span-2"><span><?= e(t('field.password')) ?> *</span><input type="password" name="password" minlength="<?= (int) config('security.password_min', 8) ?>" required autocomplete="new-password"></label>
        </div>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.create')) ?></button></div>
    </form>
</dialog>
