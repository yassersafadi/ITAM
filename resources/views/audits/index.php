<?php page_title(t('nav.audits')); ?>
<div class="page-head">
    <div>
        <h1><?= e(t('nav.audits')) ?></h1>
        <p class="muted"><?= e(t('audits.subtitle')) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('audits.run')): ?>
            <button class="btn btn-primary" data-modal-open="new-audit"><?= icon('camera') ?> <?= e(t('audits.new')) ?></button>
        <?php endif; ?>
    </div>
</div>


<section class="card table-card" style="margin-top:18px">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= e(t('field.name')) ?></th><th><?= e(t('cycle.scope')) ?></th><th><?= e(t('field.status')) ?></th><th><?= e(t('audit_result.found')) ?></th><th><?= e(t('audit_result.missing')) ?></th><th><?= e(t('audits.anomalies')) ?></th><th><?= e(t('field.date')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($audits as $au): ?>
                <tr data-href="<?= url('audits/' . $au['id']) ?>">
                    <td><a href="<?= url('audits/' . $au['id']) ?>"><strong><?= e($au['name']) ?></strong></a><small class="block muted"><?= $au['plan_id'] ? icon('calendar', 'icon icon-xs') . ' ' . e(t('cycle.automatic')) : e($au['started_by_name']) ?></small></td>
                    <td><?= e(implode(' · ', array_filter([$au['location_name'], $au['category_name']])) ?: t('audits.any_location')) ?></td>
                    <td><?= status_badge($au['status'], 'audit_status') ?></td>
                    <td class="text-success"><?= (int) $au['found'] ?></td>
                    <td class="<?= $au['missing'] ? 'text-danger' : '' ?>"><?= (int) $au['missing'] ?></td>
                    <td class="<?= $au['anomalies'] ? 'text-warning' : '' ?>"><?= (int) $au['anomalies'] ?></td>
                    <td><?= fmt_date($au['started_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$audits): ?><tr><td colspan="7"><div class="empty-state small"><?= icon('clipboard') ?><p><?= e(t('common.nothing')) ?></p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('audits.run')): ?>
<dialog class="modal" id="modal-new-audit">
    <form method="post" action="<?= url('audits') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('clipboard') ?> <?= e(t('audits.new')) ?></h3>
        <label class="field"><span><?= e(t('field.name')) ?> *</span><input name="name" required value="<?= e(t('audits.default_name', ['date' => date('Y-m-d')])) ?>"></label>
        <div class="form-grid">
            <label class="field"><span><?= e(t('field.location_id')) ?></span><select name="location_id"><?= select_options($locations, '') ?></select></label>
            <label class="field"><span><?= e(t('field.category_id')) ?></span><select name="category_id"><?= select_options($categories, '') ?></select></label>
        </div>
        <small class="field-help"><?= e(t('audits.location_help')) ?></small>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('audits.start')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
