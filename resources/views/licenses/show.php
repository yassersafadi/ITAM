<?php
$l = $license;
page_title($l['name']);
$used = count($seats);
$pct = (int) round($used / max(1, (int) $l['seats']) * 100);
$maskedKey = $l['license_key'] ? substr($l['license_key'], 0, 5) . str_repeat('•', max(0, strlen($l['license_key']) - 10)) . substr($l['license_key'], -5) : null;
?>
<div class="page-head">
    <div>
        <a href="<?= url('licenses') ?>" class="back-link">‹ <?= e(t('nav.licenses')) ?></a>
        <h1><?= e($l['name']) ?> <small class="muted"><?= e($l['edition']) ?></small></h1>
        <div class="title-meta"><span class="badge badge-neutral"><?= e(t('license_type.' . $l['license_type'])) ?></span><span class="muted"><?= e($l['publisher']) ?></span></div>
    </div>
    <div class="page-actions">
        <?php if (can('licenses.edit')): ?>
            <button class="btn btn-ghost" data-modal-open="edit-license"><?= icon('edit') ?> <?= e(t('common.edit')) ?></button>
            <button class="btn btn-primary" data-modal-open="assign-seat"><?= icon('plus') ?> <?= e(t('licenses.assign')) ?></button>
        <?php endif; ?>
    </div>
</div>

<section class="mini-kpis">
    <div class="card"><span><?= e(t('licenses.used')) ?></span><strong class="<?= $used > $l['seats'] ? 'text-danger' : '' ?>"><?= $used ?> / <?= (int) $l['seats'] ?></strong></div>
    <div class="card"><span><?= e(t('licenses.utilization')) ?></span><strong><?= $pct ?>%</strong></div>
    <div class="card"><span><?= e(t('field.expires_at')) ?></span><strong><?= $l['expires_at'] ? fmt_date($l['expires_at']) : e(t('licenses.perpetual')) ?></strong></div>
    <div class="card"><span><?= e(t('field.license_key')) ?></span><strong class="mono small" <?= can('licenses.edit') ? 'data-reveal="' . e($l['license_key'] ?? '') . '"' : '' ?> title="<?= e(t('licenses.reveal')) ?>"><?= e($maskedKey ?? '—') ?></strong></div>
</section>


<section class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= e(t('licenses.assigned_to')) ?></th><th><?= e(t('field.assigned_at')) ?></th><th><?= e(t('field.last_used_at')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($seats as $s): ?>
                <tr>
                    <td>
                        <?php if ($s['asset_id']): ?><a href="<?= url('assets/' . $s['asset_id']) ?>"><?= icon('box', 'icon icon-xs') ?> <span class="mono"><?= e($s['tag']) ?></span> <?= e($s['asset_name']) ?></a><?php endif; ?>
                        <?php if ($s['employee_id']): ?><a href="<?= url('employees/' . $s['employee_id']) ?>"><?= icon('user', 'icon icon-xs') ?> <?= e($s['employee_name']) ?></a><?php endif; ?>
                    </td>
                    <td><?= fmt_date($s['assigned_at']) ?></td>
                    <td><?= e(time_ago($s['last_used_at'])) ?></td>
                    <td class="actions">
                        <?php if (can('licenses.edit')): ?>
                            <form method="post" action="<?= url('licenses/' . $l['id'] . '/seats/' . $s['id'] . '/delete') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(t('licenses.release')) ?></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$seats): ?><tr><td colspan="4" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($l['notes']): ?><section class="card"><h3 class="card-title"><?= e(t('field.notes')) ?></h3><p class="pre"><?= e($l['notes']) ?></p></section><?php endif; ?>

<?php if (can('licenses.edit')): ?>
<dialog class="modal" id="modal-edit-license">
    <form method="post" action="<?= url('licenses/' . $l['id']) ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('edit') ?> <?= e(t('common.edit')) ?></h3>
        <?= partial('licenses/fields', ['l' => $l]) ?>
        <div class="modal-actions">
            <button type="submit" formaction="<?= url('licenses/' . $l['id'] . '/delete') ?>" class="btn btn-danger" data-confirm-click="<?= e(t('common.confirm_delete')) ?>"><?= icon('trash') ?></button>
            <span class="grow"></span>
            <button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button>
        </div>
    </form>
</dialog>
<dialog class="modal" id="modal-assign-seat">
    <form method="post" action="<?= url('licenses/' . $l['id'] . '/seats') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('plus') ?> <?= e(t('licenses.assign')) ?></h3>
        <label class="field"><span><?= e(t('field.asset')) ?></span><select name="asset_id"><?= select_options(array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['tag'] . ' — ' . $a['name']], $assets), '') ?></select></label>
        <label class="field"><span><?= e(t('field.employee')) ?></span><select name="employee_id"><?= select_options($employees, '') ?></select></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('licenses.assign')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
