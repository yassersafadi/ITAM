<?php
use ITAM\Controllers\MaintenanceController;
use ITAM\Services\Sla;

page_title(t('nav.maintenance'));
$tabs = ['active' => t('maintenance.tab.active'), 'open' => t('maintenance.status.open'), 'in_progress' => t('maintenance.status.in_progress'), 'done' => t('maintenance.status.done'), 'all' => t('common.all')];
$filtered = !empty($_GET['overdue']) || !empty($_GET['breached']) || !empty($_GET['source']) || !empty($_GET['technician']) || !empty($_GET['unassigned']);
?>
<div class="page-head">
    <div>
        <h1><?= e(t('nav.maintenance')) ?></h1>
        <p class="muted"><?= e(t('maintenance.subtitle')) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('maintenance.edit')): ?>
            <button class="btn btn-primary" data-modal-open="new-wo"><?= icon('plus') ?> <?= e(t('maintenance.new')) ?></button>
        <?php endif; ?>
    </div>
</div>

<section class="mini-kpis">
    <div class="card"><span><?= e(t('maintenance.status.open')) ?></span><strong><?= $stats['open'] ?></strong></div>
    <div class="card"><span><?= e(t('maintenance.status.in_progress')) ?></span><strong><?= $stats['progress'] ?></strong></div>
    <a class="card" href="<?= url('maintenance', ['breached' => 1]) ?>"><span><?= e(t('sla.breached')) ?></span><strong class="<?= $stats['breached'] ? 'text-danger' : 'text-success' ?>"><?= $stats['breached'] ?></strong></a>
    <div class="card"><span><?= e(t('sla.compliance_30')) ?></span><strong class="<?= $stats['sla'] === null ? '' : ($stats['sla'] >= 90 ? 'text-success' : ($stats['sla'] >= 75 ? 'text-warning' : 'text-danger')) ?>"><?= $stats['sla'] === null ? '—' : fmt_num($stats['sla']) . '%' ?></strong></div>
    <a class="card" href="<?= url('maintenance', ['source' => 'report']) ?>"><span><?= e(t('report.open_reports')) ?></span><strong class="<?= $stats['reports'] ? 'text-info' : '' ?>"><?= $stats['reports'] ?></strong></a>
    <a class="card" href="<?= url('maintenance', ['overdue' => 1]) ?>"><span><?= e(t('maintenance.overdue')) ?></span><strong class="<?= $stats['overdue'] ? 'text-danger' : '' ?>"><?= $stats['overdue'] ?></strong></a>
    <div class="card"><span title="Mean Time To Repair"><?= e(t('maintenance.mttr')) ?></span><strong><?= fmt_num($stats['mttr'], 1) ?> h</strong></div>
</section>

<div class="seg">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= url('maintenance', ['status' => $k, 'type' => $_GET['type'] ?? null]) ?>" class="<?= $status === $k && !$filtered ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('maintenance', ['source' => 'report']) ?>" class="<?= ($_GET['source'] ?? '') === 'report' ? 'active' : '' ?>"><?= icon('qr', 'icon icon-xs') ?> <?= e(t('report.reports')) ?></a>
    <a href="<?= url('maintenance', ['breached' => 1]) ?>" class="<?= !empty($_GET['breached']) ? 'active' : '' ?>"><?= icon('clock', 'icon icon-xs') ?> <?= e(t('sla.breached')) ?></a>
</div>

<section class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <th><?= e(t('field.title')) ?></th><th><?= e(t('field.asset')) ?></th><th><?= e(t('field.priority')) ?></th>
                <th><?= e(t('field.status')) ?></th><th><?= e(t('sla.target')) ?></th><th class="hide-md"><?= e(t('field.technician')) ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $m):
                $active = in_array($m['status'], ['open', 'in_progress'], true);
                $late = $active && $m['scheduled_for'] && $m['scheduled_for'] < today();
                $sla = Sla::state($m);
            ?>
                <tr>
                    <td>
                        <a href="<?= url('work-orders/' . $m['id']) ?>"><strong dir="auto"><?= e($m['title']) ?></strong></a>
                        <span class="row-badges">
                            <span class="badge badge-neutral"><?= e(t('maintenance.type.' . $m['type'])) ?></span>
                            <?php if (in_array($m['source'], ['report', 'portal'], true)): ?><span class="badge badge-info" title="<?= e(t('report.reported_by', ['name' => $m['reporter_name'] ?? ''])) ?>"><?= icon('qr', 'icon icon-xs') ?> <?= e(t('report.badge')) ?></span><?php endif; ?>
                            <?php if ($m['escalated_at']): ?><span class="badge badge-danger"><?= icon('alert', 'icon icon-xs') ?> <?= e(t('sla.escalated')) ?></span><?php endif; ?>
                            <?php if ($m['recurrence_days']): ?><span class="badge badge-neutral" title="<?= e(t('field.recurrence_days')) ?>">↻ <?= (int) $m['recurrence_days'] ?>d</span><?php endif; ?>
                        </span>
                        <?php if (in_array($m['source'], ['report', 'portal'], true) && $m['reporter_name']): ?><small class="block muted" dir="auto"><?= e($m['reporter_name']) ?> · <?php if ($m['source'] === 'portal'): ?><?= e(t('portal.via_portal')) ?><?php else: ?><span dir="ltr"><?= e($m['reporter_contact'] ?? '') ?></span><?php endif; ?></small><?php endif; ?>
                    </td>
                    <td><a href="<?= url('assets/' . $m['asset_id']) ?>" class="cell-main"><span class="cell-icon"><?= icon($m['icon'] ?? 'box') ?></span><span><strong class="mono"><?= e($m['tag']) ?></strong><small dir="auto"><?= e($m['asset_name']) ?></small></span></a></td>
                    <td><?= status_badge($m['priority'], 'priority') ?></td>
                    <td><?= status_badge($m['status'], 'maintenance.status') ?></td>
                    <td>
                        <?php if ($sla): ?>
                            <span class="sla sla-<?= $sla['state'] ?>" <?= $active ? 'data-due="' . e(date('c', strtotime($m['due_at']))) . '"' : '' ?>><?= icon($sla['state'] === 'met' ? 'check' : 'clock', 'icon icon-xs') ?> <span><?= e(Sla::label($sla)) ?></span></span>
                        <?php elseif ($m['scheduled_for']): ?>
                            <span class="<?= $late ? 'text-danger' : 'muted' ?>"><?= icon('calendar', 'icon icon-xs') ?> <?= fmt_date($m['scheduled_for']) ?></span>
                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                    </td>
                    <td class="hide-md"><?= $m['technician'] ? e($m['technician']) : '<span class="badge badge-warning">' . e(t('sla.unassigned')) . '</span>' ?></td>
                    <td class="actions">
                        <?php if (can('maintenance.edit') && $active): ?>
                            <form method="post" action="<?= url('maintenance/' . $m['id'] . '/status') ?>" class="inline-actions">
                                <?= csrf_field() ?>
                                <?php if ($m['status'] === 'open'): ?>
                                    <button class="btn btn-ghost btn-sm" name="status" value="in_progress"><?= e(t('maintenance.start')) ?></button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-primary btn-sm" data-modal-open="complete-wo" data-form-action="<?= url('maintenance/' . $m['id'] . '/status') ?>" data-caption="<?= e($m['tag'] . ' — ' . $m['title']) ?>" title="<?= e(t('maintenance.complete')) ?>"><?= icon('check') ?></button>
                                <button type="button" class="btn btn-ghost btn-sm" data-modal-open="assign-wo" data-form-action="<?= url('maintenance/' . $m['id'] . '/assign') ?>" data-caption="<?= e($m['tag'] . ' — ' . $m['title']) ?>" title="<?= e(t('sla.assign')) ?>"><?= icon('user') ?></button>
                                <button class="btn btn-ghost btn-sm" name="status" value="cancelled" data-confirm-click="<?= e(t('maintenance.cancel_confirm')) ?>" title="<?= e(t('maintenance.status.cancelled')) ?>"><?= icon('x') ?></button>
                            </form>
                        <?php elseif ($m['resolution']): ?>
                            <span class="muted small" title="<?= e($m['resolution']) ?>"><?= icon('info', 'icon icon-xs') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7"><div class="empty-state small"><?= icon('check') ?><p><?= e(t('common.nothing')) ?></p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('maintenance.edit')): ?>
<dialog class="modal" id="modal-complete-wo">
    <form method="post" action="" class="stack">
        <?= csrf_field() ?><input type="hidden" name="status" value="done">
        <h3><?= icon('check') ?> <?= e(t('maintenance.complete')) ?></h3>
        <p class="muted small" data-modal-caption></p>
        <div class="form-grid">
            <label class="field"><span><?= e(t('field.downtime_hours')) ?></span><input type="number" name="downtime_hours" step="0.5" min="0"></label>
            <label class="field"><span><?= e(t('rr.wo_cost')) ?></span><input type="number" name="cost" step="0.01" min="0" placeholder="0"></label>
        </div>
        <label class="field"><span><?= e(t('field.resolution')) ?></span><textarea name="resolution" rows="3" placeholder="<?= e(t('kb.resolution_ph')) ?>"></textarea></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('maintenance.complete')) ?></button></div>
    </form>
</dialog>
<dialog class="modal" id="modal-assign-wo">
    <form method="post" action="" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('user') ?> <?= e(t('sla.assign')) ?></h3>
        <p class="muted small" data-modal-caption></p>
        <label class="field"><span><?= e(t('field.technician')) ?></span><select name="technician_id"><?= select_options($technicians, '') ?></select></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>


<?php if (can('maintenance.edit')): ?>
<dialog class="modal" id="modal-new-wo">
    <form method="post" action="<?= url('maintenance') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('wrench') ?> <?= e(t('maintenance.new')) ?></h3>
        <label class="field"><span><?= e(t('field.asset')) ?> *</span><select name="asset_id" required><?= select_options(array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['tag'] . ' — ' . $a['name']], $assets), '') ?></select></label>
        <label class="field"><span><?= e(t('field.title')) ?> *</span><input name="title" required></label>
        <div class="fields">
            <label class="field"><span><?= e(t('field.type')) ?></span><select name="type"><?= enum_options(MaintenanceController::TYPES, 'corrective', 'maintenance.type') ?></select></label>
            <label class="field"><span><?= e(t('field.priority')) ?></span><select name="priority"><?= enum_options(MaintenanceController::PRIORITIES, 'medium', 'priority') ?></select></label>
            <label class="field"><span><?= e(t('field.scheduled_for')) ?></span><input type="date" name="scheduled_for"></label>
            <label class="field"><span><?= e(t('field.recurrence_days')) ?></span><input type="number" min="0" name="recurrence_days" placeholder="0"></label>
            <label class="field span-2"><span><?= e(t('field.technician')) ?></span><select name="technician_id"><?= select_options($technicians, auth()['id']) ?></select></label>
        </div>
        <label class="field"><span><?= e(t('field.description')) ?></span><textarea name="description" rows="2"></textarea></label>
        <label class="check"><input type="checkbox" name="mark_in_repair" value="1"> <?= e(t('maintenance.mark_in_repair')) ?></label>
        <p class="small muted"><?= icon('clock', 'icon icon-xs') ?> <?= e(t('sla.new_hint', ['c' => config('sla.critical'), 'h' => config('sla.high'), 'm' => config('sla.medium'), 'l' => config('sla.low')])) ?></p>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.create')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
