<?php
use ITAM\Core\Auth;

page_title(t('roles.title'));
$isAdmin = $slug === 'admin';
$perms = Auth::permissionsOf($slug);
$order = array_values(array_filter(array_keys($rows), fn ($s) => $s !== 'admin'));
?>
<div class="page-head">
    <div>
        <a href="<?= url('admin/users') ?>" class="back-link">‹ <?= e(t('nav.users')) ?></a>
        <h1><?= icon('shield', 'icon icon-title') ?> <?= e(t('roles.title')) ?></h1>
        <p class="muted"><?= e(t('roles.subtitle')) ?></p>
    </div>
    <div class="page-actions"><button type="button" class="btn btn-primary" data-modal-open="new-role"><?= icon('plus') ?> <?= e(t('roles.new')) ?></button></div>
</div>

<div class="roles-layout">
    <nav class="card roles-list" aria-label="<?= e(t('roles.title')) ?>">
        <?php foreach ($rows as $s => $r): ?>
            <a href="<?= url('admin/roles', ['role' => $s]) ?>" class="<?= $s === $slug ? 'active' : '' ?>"<?= $s === $slug ? ' aria-current="page"' : '' ?>>
                <span><?= $s === 'admin' ? icon('lock', 'icon icon-xs') . ' ' : '' ?><bdi><?= e(Auth::roleName($s)) ?></bdi>
                    <?php if (!in_array($s, Auth::ROLES, true)): ?><span class="badge badge-success"><?= e(t('roles.custom')) ?></span><?php endif; ?></span>
                <span class="count"><?= (int) ($counts[$s] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
        <p class="small muted"><?= e(t('roles.order_help')) ?></p>
    </nav>

    <section class="card">
        <form method="post" action="<?= url('admin/roles/' . $slug) ?>" class="stack">
            <?= csrf_field() ?>
            <section class="role-section">
                <h3 class="card-title"><?= icon('user') ?> <?= e(t('roles.names')) ?></h3>
                <div class="form-grid three">
                    <?php foreach (['ar' => 'العربية', 'en' => 'English'] as $l => $label): ?>
                        <label class="field"><span><?= e(t('roles.name_in', ['lang' => $label])) ?></span>
                            <input name="name_<?= $l ?>" value="<?= e((string) ($role['name_' . $l] ?? '')) ?>" maxlength="80" dir="<?= $l === 'ar' ? 'rtl' : 'ltr' ?>"
                                placeholder="<?= e(\ITAM\Core\Lang::with($l, fn () => t('role.' . $slug) !== 'role.' . $slug ? t('role.' . $slug) : '')) ?>"></label>
                    <?php endforeach; ?>
                </div>
                <?php if (in_array($slug, Auth::ROLES, true)): ?><p class="small muted role-note"><?= e(t('roles.name_default')) ?></p><?php endif; ?>
            </section>

            <section class="role-section">
                <h3 class="card-title"><?= icon('key') ?> <?= e(t('roles.permissions')) ?></h3>
                <?php if ($isAdmin): ?><p class="callout"><?= icon('lock') ?> <?= e(t('roles.admin_all')) ?></p><?php endif; ?>
                <div class="perm-tables">
                    <?php foreach (Auth::PERMISSION_GROUPS as $group => $list):
                        $on = count(array_filter($list, fn ($p) => in_array('*', $perms, true) || in_array($p, $perms, true))); ?>
                        <section class="perm-table">
                            <header class="perm-table-head">
                                <h4><?= e(t('permgroup.' . $group)) ?></h4>
                                <span class="badge badge-<?= $on === count($list) ? 'success' : ($on ? 'info' : 'neutral') ?>"><?= $on ?>/<?= count($list) ?></span>
                                <?php if (!$isAdmin): ?>
                                    <label class="perm-all"><input type="checkbox" <?= $on === count($list) ? 'checked' : '' ?> onchange="this.closest('.perm-table').querySelectorAll('tbody input[type=checkbox]').forEach(i => { i.checked = this.checked; })">
                                        <span><?= e(t('roles.select_all')) ?></span></label>
                                <?php endif; ?>
                            </header>
                            <table>
                                <thead><tr><th class="perm-col-on"><span class="sr-only"><?= e(t('roles.allowed')) ?></span></th><th><?= e(t('roles.permission')) ?></th><th><?= e(t('roles.what_it_allows')) ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($list as $p): $checked = in_array('*', $perms, true) || in_array($p, $perms, true); ?>
                                    <tr class="<?= $checked ? 'is-on' : '' ?>">
                                        <td class="perm-col-on"><label class="perm-switch"><input type="checkbox" name="permissions[]" value="<?= e($p) ?>" <?= $checked ? 'checked' : '' ?><?= $isAdmin ? ' disabled' : '' ?>
                                            onchange="this.closest('tr').classList.toggle('is-on', this.checked)" aria-label="<?= e(t('perm.' . $p)) ?>"><span></span></label></td>
                                        <td class="perm-name"><strong><?= e(t('perm.' . $p)) ?></strong><code dir="ltr"><?= e($p) ?></code></td>
                                        <td class="perm-desc"><?= e(t('perm.' . $p . '_help')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </section>
                    <?php endforeach; ?>
                </div>
                <p class="small muted role-note"><?= icon('info', 'icon icon-xs') ?> <?= e(t('roles.view_note')) ?></p>
            </section>

            <section class="role-section">
                <h3 class="card-title"><?= icon('link') ?> Microsoft Entra</h3>
            </section>

            <div class="form-actions roles-actions">
                <button class="btn btn-primary"><?= icon('check') ?> <?= e(t('common.save')) ?></button>
                <button type="button" class="btn btn-ghost" data-modal-open="new-role" data-fill="<?= e(json_encode(['copy_from' => $slug])) ?>"><?= icon('layers') ?> <?= e(t('roles.copy')) ?></button>
            </div>
        </form>

        <?php if (!$isAdmin): $i = array_search($slug, $order, true); ?>
            <div class="roles-footer">
                <form method="post" action="<?= url('admin/roles/' . $slug . '/move') ?>" class="inline-actions"><?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" name="dir" value="up"<?= $i === 0 ? ' disabled' : '' ?>>↑ <?= e(t('roles.up')) ?></button>
                    <button class="btn btn-ghost btn-sm" name="dir" value="down"<?= $i === count($order) - 1 ? ' disabled' : '' ?>>↓ <?= e(t('roles.down')) ?></button>
                </form>
                <form method="post" action="<?= url('admin/roles/' . $slug . '/delete') ?>" data-confirm="<?= e(t('roles.delete_confirm', ['name' => Auth::roleName($slug)])) ?>"><?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm text-danger"<?= ($counts[$slug] ?? 0) ? ' disabled title="' . e(t('roles.in_use', ['n' => (int) $counts[$slug]])) . '"' : '' ?>><?= icon('trash') ?> <?= e(t('roles.delete')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>

<dialog class="modal" id="modal-new-role">
    <form method="post" action="<?= url('admin/roles') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('plus') ?> <?= e(t('roles.new')) ?></h3>
        <?php foreach (['ar' => 'العربية', 'en' => 'English'] as $l => $label): ?>
            <label class="field"><span><?= e(t('roles.name_in', ['lang' => $label])) ?></span><input name="name_<?= $l ?>" maxlength="80" dir="<?= $l === 'ar' ? 'rtl' : 'ltr' ?>"></label>
        <?php endforeach; ?>
        <label class="field"><span><?= e(t('roles.start_from')) ?></span><select name="copy_from">
            <option value=""><?= e(t('roles.start_empty')) ?></option>
            <?php foreach (array_keys($rows) as $s): if ($s === 'admin') continue; ?><option value="<?= e($s) ?>"><?= e(Auth::roleName($s)) ?></option><?php endforeach; ?>
        </select></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('roles.create')) ?></button></div>
    </form>
</dialog>
