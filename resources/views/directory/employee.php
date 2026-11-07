<?php
page_title($emp['name']);
$loanTone = ['requested' => 'warning', 'approved' => 'info', 'checked_out' => 'success', 'returned' => 'neutral', 'rejected' => 'danger', 'cancelled' => 'neutral'];
?>
<div class="page-head">
    <div>
        <a href="<?= url('directory/employees') ?>" class="back-link">‹ <?= e(t('nav.employees')) ?></a>
        <h1 class="asset-title"><span class="avatar lg"><?= e(mb_strtoupper(mb_substr($emp['name'], 0, 1))) ?></span><span><?= e($emp['name']) ?><?= !$emp['active'] ? ' <span class="badge badge-neutral">' . e(t('common.inactive')) . '</span>' : '' ?></span></h1>
        <div class="title-meta">
            <span class="muted"><?= e($emp['employee_no']) ?></span>
            <?php if ($emp['email']): ?><a href="mailto:<?= e($emp['email']) ?>" class="link"><?= e($emp['email']) ?></a><?php endif; ?>
            <?php if (!empty($emp['mobile'])): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $emp['mobile'])) ?>" class="link" dir="ltr"><?= e($emp['mobile']) ?></a><?php endif; ?>
            <span class="muted"><?= e(trim(($emp['department_name'] ?? '') . ' · ' . ($emp['location_name'] ?? ''), ' ·')) ?></span>
            <?php if ($emp['manager_name']): ?><a class="link small" href="<?= url('employees/' . $emp['manager_id']) ?>"><?= icon('user', 'icon icon-xs') ?> <?= e(t('team.manager_is', ['name' => $emp['manager_name']])) ?></a><?php endif; ?>
        </div>
    </div>
    <div class="page-actions">
        <?php if ($assets): ?><a class="btn btn-ghost" href="<?= url('assets/labels', ['assigned' => $emp['id']]) ?>" target="_blank"><?= icon('qr') ?> <?= e(t('assets.labels')) ?></a><?php endif; ?>
        <?php if (can('users.manage')): ?>
            <?php if ($account): ?>
                <a class="btn btn-ghost" href="<?= url('admin/users') ?>"><?= icon('user') ?> <span class="hide-sm"><?= e(t('access.has_account', ['role' => role_name((string) $account['role'])])) ?></span></a>
            <?php elseif ($emp['active'] && $emp['email']): ?>
                <button type="button" class="btn btn-ghost" data-modal-open="grant-access"><?= icon('key') ?> <span class="hide-sm"><?= e(t('access.grant')) ?></span></button>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (can('catalog.edit')): ?><a class="btn btn-ghost" href="<?= url('directory/employees/' . $emp['id'] . '/edit') ?>"><?= icon('edit') ?> <?= e(t('common.edit')) ?></a><?php endif; ?>
    </div>
</div>

<section class="grid-kpis">
    <div class="kpi-tile"><span><?= e(t('emp.devices')) ?></span><strong><?= count($assets) ?></strong></div>
    <div class="kpi-tile"><span><?= e(t('tabs.licenses')) ?></span><strong><?= count($licenses) ?></strong></div>
    <div class="kpi-tile"><span><?= e(t('emp.value')) ?></span><strong><?= fmt_num($value) ?></strong></div>
    <div class="kpi-tile"><span><?= e(t('custody.receipts')) ?></span><strong><?= count($receipts) ?></strong></div>
</section>

<div class="grid grid-2">
    <section class="card table-card span-2">
        <div class="card-head"><h3><?= icon('laptop') ?> <?= e(t('emp.devices')) ?></h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(t('field.tag')) ?></th><th><?= e(t('field.name')) ?></th><th><?= e(t('field.category_id')) ?></th><th><?= e(t('field.serial')) ?></th><th><?= e(t('field.warranty_until')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($assets as $a): ?>
                    <tr data-href="<?= url('assets/' . $a['id']) ?>">
                        <td><a class="mono" href="<?= url('assets/' . $a['id']) ?>"><?= e($a['tag']) ?></a></td>
                        <td dir="auto"><?= e($a['name']) ?></td>
                        <td><?= e($a['category_name'] ?? '') ?></td>
                        <td class="mono"><?= e((string) $a['serial']) ?></td>
                        <td><?= fmt_date($a['warranty_until']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$assets): ?><tr><td colspan="5" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('key') ?> <?= e(t('tabs.licenses')) ?></h3>
        <ul class="list compact">
            <?php foreach ($licenses as $l): ?><li><a class="list-main" href="<?= url('licenses/' . $l['id']) ?>"><span><strong dir="auto"><?= e($l['name']) ?></strong><?php if ($l['edition']): ?><small><?= e($l['edition']) ?></small><?php endif; ?></span></a></li><?php endforeach; ?>
            <?php if (!$licenses): ?><li class="muted"><?= e(t('common.nothing')) ?></li><?php endif; ?>
        </ul>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('edit') ?> <?= e(t('custody.receipts')) ?></h3>
        <ul class="list compact">
            <?php foreach ($receipts as $rc): ?>
                <li><a class="list-main" href="<?= url('custody/' . $rc['id']) ?>" target="_blank"><span><strong class="mono"><?= e($rc['receipt_no']) ?></strong><small><?= e(t('custody.type.' . $rc['type'])) ?> · <?= fmt_date($rc['signed_at'] ?? $rc['created_at']) ?></small></span></a>
                    <span class="badge badge-<?= $rc['status'] === 'signed' ? 'success' : 'warning' ?>"><?= e(t('custody.status.' . $rc['status'])) ?></span></li>
            <?php endforeach; ?>
            <?php if (!$receipts): ?><li class="muted"><?= e(t('common.nothing')) ?></li><?php endif; ?>
        </ul>
    </section>

    <?php if ($loans): ?>
        <section class="card">
            <h3 class="card-title"><?= icon('swap') ?> <?= e(t('nav.loans')) ?></h3>
            <ul class="list compact">
                <?php foreach ($loans as $b): ?>
                    <li><span class="list-main"><span><strong dir="auto"><?= e($b['category_name'] ?? '—') ?></strong><small><bdi class="mono"><?= e($b['number']) ?></bdi> · <?= fmt_date($b['starts_on']) ?> – <?= fmt_date($b['ends_on']) ?></small></span></span>
                        <span class="badge badge-<?= $loanTone[$b['status']] ?? 'neutral' ?>"><?= e(t('loans.status.' . $b['status'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="card<?= $loans ? '' : ' span-2' ?>">
        <h3 class="card-title"><?= icon('history') ?> <?= e(t('emp.history')) ?></h3>
        <ul class="list compact">
            <?php foreach ($history as $h): ?>
                <li><a class="list-main" href="<?= url('assets/' . $h['asset_id']) ?>"><span><strong><bdi class="mono"><?= e($h['tag']) ?></bdi> · <span dir="auto"><?= e($h['asset_name']) ?></span></strong>
                    <small><?= fmt_date($h['checked_out_at']) ?> → <?= $h['checked_in_at'] ? fmt_date($h['checked_in_at']) : e(t('assets.current')) ?></small></span></a></li>
            <?php endforeach; ?>
            <?php if (!$history): ?><li class="muted"><?= e(t('common.nothing')) ?></li><?php endif; ?>
        </ul>
    </section>
</div>

<?php if (can('users.manage') && !$account && $emp['active'] && $emp['email']): ?>
<dialog class="modal" id="modal-grant-access">
    <form method="post" action="<?= url('employees/' . $emp['id'] . '/grant-access') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('key') ?> <?= e(t('access.grant')) ?></h3>
        <p class="small"><?= e(t('access.help', ['name' => $emp['name'], 'email' => $emp['email']])) ?></p>
        <label class="field"><span><?= e(t('field.role')) ?></span><select name="role">
            <?php foreach (\ITAM\Core\Auth::roles() as $r): ?><option value="<?= $r ?>"<?= $r === 'technician' ? ' selected' : '' ?>><?= e(role_name($r)) ?> — <?= e(\ITAM\Core\Auth::roleSummary($r)) ?></option><?php endforeach; ?>
        </select></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('access.grant')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>