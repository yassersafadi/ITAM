<?php
use ITAM\Controllers\AttachmentController;
use ITAM\Controllers\MaintenanceController;
use ITAM\Services\AssetQuery;

page_title($asset['tag']);
$a = $asset;
$wDays = $a['warranty_until'] ? -1 * days_between($a['warranty_until']) : null;
$ageDays = $a['acquired_at'] ? days_between($a['acquired_at']) : null;
$spec = fn ($label, $value, $mono = false) => '<div class="spec"><dt>' . e(t('field.' . $label)) . '</dt><dd' . ($mono ? ' class="mono"' : '') . '>' . ($value !== null && $value !== '' ? e($value) : '<span class="muted">—</span>') . '</dd></div>';
?>
<div class="page-head">
    <div>
        <a href="<?= url('assets') ?>" class="back-link">‹ <?= e(t('nav.assets')) ?></a>
        <h1 class="asset-title">
            <span class="title-icon"><?= icon($a['category_icon'] ?? 'box') ?></span>
            <span><?= e($a['name']) ?> <small class="mono muted"><?= e($a['tag']) ?></small></span>
        </h1>
        <div class="title-meta">
            <?= status_badge($a['status']) ?>
            <span class="badge badge-neutral"><?= e(t('criticality.' . $a['criticality'])) ?></span>
            <?php if ($a['source'] !== 'manual'): ?><span class="badge badge-info"><?= icon('agent', 'icon icon-xs') ?> <?= e(t('source.' . $a['source'])) ?></span><?php endif; ?>
            <?php if ($a['category_name']): ?><span class="muted"><?= e($a['category_name']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= url('assets/labels', ['ids' => $a['id']]) ?>" target="_blank"><?= icon('qr') ?> <span class="hide-sm"><?= e(t('assets.label')) ?></span></a>
        <?php if (can('assets.edit')): ?>
            <a class="btn btn-ghost" href="<?= url('assets/create', ['clone' => $a['id']]) ?>"><?= icon('layers') ?> <span class="hide-sm"><?= e(t('assets.clone')) ?></span></a>
            <a class="btn btn-ghost" href="<?= url('assets/' . $a['id'] . '/edit') ?>"><?= icon('edit') ?> <?= e(t('common.edit')) ?></a>
        <?php endif; ?>
        <?php if (can('assets.delete') && $a['status'] !== 'retired'): ?>
            <a class="btn btn-ghost" href="<?= url('assets/' . $a['id'] . '/dispose') ?>"><?= icon('trash') ?> <span class="hide-sm"><?= e(t('disposal.action')) ?></span></a>
        <?php endif; ?>
        <?php if (can('assets.assign') && !in_array($a['status'], ['retired', 'lost'], true)): ?>
            <?php if ($a['assigned_to']): ?>
                <button class="btn btn-primary" data-modal-open="checkin"><?= icon('box') ?> <?= e(t('assets.checkin')) ?></button>
            <?php else: ?>
                <button class="btn btn-primary" data-modal-open="checkout"><?= icon('user') ?> <?= e(t('assets.checkout')) ?></button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($disposal): ?>
    <div class="callout callout-danger"><?= icon('trash') ?>
        <div><strong><?= e(t('disposal.retired_banner', ['date' => fmt_date($disposal['disposed_at'])])) ?></strong></div>
    </div>
<?php endif; ?>

<section class="asset-hero asset-hero-plain">
    <div class="hero-stats">
        <div class="stat card">
            <span class="stat-label"><?= icon('user') ?> <?= e(t('field.assigned_to')) ?></span>
            <strong class="ellipsis"><?= $a['employee_name'] ? '<a href="' . url('employees/' . $a['assigned_to']) . '">' . e($a['employee_name']) . '</a>' : '—' ?></strong>
            <small class="muted"><?= e($a['location_name'] ?? '') ?></small>
        </div>
        <div class="stat card">
            <span class="stat-label"><?= icon('shield') ?> <?= e(t('field.warranty_until')) ?></span>
            <strong class="<?= $wDays === null ? '' : ($wDays < 0 ? 'text-danger' : ($wDays <= (int) config('alerts.warranty_warn_days', 60) ? 'text-warning' : 'text-success')) ?>">
                <?= $wDays === null ? '—' : ($wDays < 0 ? e(t('assets.expired_ago', ['n' => -$wDays])) : e(t('assets.days_left', ['n' => $wDays]))) ?>
            </strong>
            <small class="muted"><?= fmt_date($a['warranty_until']) ?></small>
        </div>
        <div class="stat card">
            <span class="stat-label"><?= icon('calendar') ?> <?= e(t('field.acquired_at')) ?></span>
            <strong><?= fmt_date($a['acquired_at']) ?></strong>
            <small class="muted"><?= $ageDays !== null ? e(t('assets.age', ['y' => fmt_num($ageDays / 365, 1)])) : '' ?></small>
        </div>
        <div class="stat card">
            <span class="stat-label"><?= icon('wrench') ?> <?= e(t('tabs.maintenance')) ?></span>
            <strong><?= count(array_filter($maintenance, fn ($m) => in_array($m['status'], ['open', 'in_progress'], true))) ?></strong>
            <small class="muted"><?= e(t('assets.open_work')) ?></small>
        </div>
    </div>
</section>

<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist">
        <button role="tab" data-tab="overview" class="active"><?= icon('info') ?> <?= e(t('tabs.overview')) ?></button>
        <button role="tab" data-tab="timeline"><?= icon('history') ?> <?= e(t('tabs.timeline')) ?></button>
        <button role="tab" data-tab="maintenance"><?= icon('wrench') ?> <?= e(t('tabs.maintenance')) ?> <span class="count"><?= count($maintenance) ?></span></button>
        <button role="tab" data-tab="assignments"><?= icon('users') ?> <?= e(t('tabs.assignments')) ?></button>
        <button role="tab" data-tab="licenses"><?= icon('key') ?> <?= e(t('tabs.licenses')) ?> <span class="count"><?= count($licenses) ?></span></button>
        <button role="tab" data-tab="files"><?= icon('upload') ?> <?= e(t('tabs.files')) ?> <span class="count"><?= count($files) ?></span></button>
        <button role="tab" data-tab="comments"><?= icon('users') ?> <?= e(t('comments.title')) ?> <span class="count"><?= count(\ITAM\Services\Comments::thread('asset', (int) $a['id'])) ?></span></button>
    </div>

    <div class="tab-panel active" data-panel="overview">
        <div class="grid grid-2">
            <section class="card">
                <h3 class="card-title"><?= e(t('assets.section.identity')) ?></h3>
                <dl class="specs">
                    <?= $spec('manufacturer', $a['manufacturer']) ?><?= $spec('model', $a['model']) ?>
                    <?= $spec('serial', $a['serial'], true) ?><?= $spec('condition_state', t('condition.' . $a['condition_state'])) ?>
                    <?= $spec('department_id', $a['department_name']) ?><?= $spec('location_id', $a['location_name']) ?>
                    <?= $spec('acquired_at', $a['acquired_at'] ? fmt_date($a['acquired_at']) : null) ?><?= $spec('value', $a['value'] !== null ? fmt_num((float) $a['value'], 2) : null) ?>
                    <?= $spec('po_number', $a['po_number'], true) ?>
                </dl>
            </section>
            <section class="card">
                <h3 class="card-title"><?= e(t('assets.section.technical')) ?></h3>
                <dl class="specs">
                    <?= $spec('hostname', $a['hostname'], true) ?><?= $spec('ip_address', $a['ip_address'], true) ?>
                    <?= $spec('mac_address', $a['mac_address'], true) ?><?= $spec('os', $a['os']) ?>
                    <?= $spec('cpu', $a['cpu']) ?><?= $spec('ram_gb', $a['ram_gb'] ? $a['ram_gb'] . ' GB' : null) ?>
                    <?= $spec('storage_gb', $a['storage_gb'] ? $a['storage_gb'] . ' GB' : null) ?><?= $spec('power_watts', $a['power_watts'] ? $a['power_watts'] . ' W' : null) ?>
                </dl>
            </section>
            <?php if ($custom): ?>
                <section class="card span-2">
                    <h3 class="card-title"><?= icon('sliders') ?> <?= e(t('cf.section')) ?></h3>
                    <dl class="specs">
                        <?php foreach ($custom as $cv): ?><div class="spec"><dt><?= e($cv['label']) ?></dt><dd dir="auto"><?= e($cv['value']) ?></dd></div><?php endforeach; ?>
                    </dl>
                </section>
            <?php endif; ?>
            <?php if ($a['notes']): ?>
                <section class="card span-2"><h3 class="card-title"><?= e(t('field.notes')) ?></h3><p class="pre"><?= e($a['notes']) ?></p></section>
            <?php endif; ?>
        </div>
        <?php if (can('assets.delete')): ?>
            <form method="post" action="<?= url('assets/' . $a['id'] . '/delete') ?>" class="danger-zone" data-confirm="<?= e(t('assets.confirm_delete', ['tag' => $a['tag']])) ?>">
                <?= csrf_field() ?>
                <span class="muted small"><?= e(t('assets.delete_hint')) ?></span>
                <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> <?= e(t('common.delete')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="timeline">
        <section class="card">
            <ol class="timeline">
                <?php foreach ($timeline as $ev): ?>
                    <li class="tl-<?= e($ev['tone']) ?>">
                        <span class="tl-icon"><?= icon($ev['icon']) ?></span>
                        <div><strong><?= e($ev['title']) ?></strong><small><?= fmt_date($ev['at'], true) ?><?= $ev['by'] ? ' · ' . e($ev['by']) : '' ?></small></div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$timeline): ?><li class="muted"><?= e(t('common.nothing')) ?></li><?php endif; ?>
            </ol>
        </section>
    </div>

    <div class="tab-panel" data-panel="maintenance">
        <div class="grid grid-3">
            <section class="card span-2 table-card">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th><?= e(t('field.title')) ?></th><th><?= e(t('field.type')) ?></th><th><?= e(t('field.status')) ?></th><th><?= e(t('field.date')) ?></th><th><?= e(t('field.downtime_hours')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($maintenance as $m): ?>
                            <tr data-href="<?= url('work-orders/' . $m['id']) ?>">
                                <td><a href="<?= url('work-orders/' . $m['id']) ?>"><strong dir="auto"><?= e($m['title']) ?></strong></a><?php if ($m['description']): ?><small class="block muted"><?= e($m['description']) ?></small><?php endif; ?></td>
                                <td><?= e(t('maintenance.type.' . $m['type'])) ?></td>
                                <td><?= status_badge($m['status'], 'maintenance.status') ?></td>
                                <td><?= fmt_date($m['completed_at'] ?? $m['scheduled_for'] ?? $m['created_at']) ?></td>
                                <td><?= (float) $m['downtime_hours'] ? fmt_num((float) $m['downtime_hours'], 1) . ' h' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$maintenance): ?><tr><td colspan="5" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php if (can('maintenance.edit')): ?>
                <section class="card">
                    <h3 class="card-title"><?= icon('plus') ?> <?= e(t('maintenance.new')) ?></h3>
                    <form method="post" action="<?= url('maintenance') ?>" class="stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="asset_id" value="<?= (int) $a['id'] ?>">
                        <label class="field"><span><?= e(t('field.title')) ?> *</span><input name="title" required></label>
                        <label class="field"><span><?= e(t('field.type')) ?></span><select name="type"><?= enum_options(MaintenanceController::TYPES, 'corrective', 'maintenance.type') ?></select></label>
                        <label class="field"><span><?= e(t('field.priority')) ?></span><select name="priority"><?= enum_options(MaintenanceController::PRIORITIES, 'medium', 'priority') ?></select></label>
                        <label class="field"><span><?= e(t('field.scheduled_for')) ?></span><input type="date" name="scheduled_for"></label>
                        <label class="field"><span><?= e(t('field.description')) ?></span><textarea name="description" rows="2"></textarea></label>
                        <label class="check"><input type="checkbox" name="mark_in_repair" value="1"> <?= e(t('maintenance.mark_in_repair')) ?></label>
                        <button class="btn btn-primary" type="submit"><?= e(t('common.create')) ?></button>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-panel" data-panel="assignments">
        <section class="card table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= e(t('field.employee')) ?></th><th><?= e(t('field.checked_out_at')) ?></th><th><?= e(t('field.expected_return')) ?></th><th><?= e(t('field.checked_in_at')) ?></th><th><?= e(t('field.condition_state')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $x): $late = !$x['checked_in_at'] && $x['expected_return'] && $x['expected_return'] < today(); ?>
                        <tr>
                            <td><a href="<?= url('employees/' . $x['employee_id']) ?>"><?= e($x['employee_name']) ?></a></td>
                            <td><?= fmt_date($x['checked_out_at']) ?></td>
                            <td class="<?= $late ? 'text-danger' : '' ?>"><?= fmt_date($x['expected_return']) ?><?= $late ? ' · ' . e(t('nlq.flag.overdue')) : '' ?></td>
                            <td><?= $x['checked_in_at'] ? fmt_date($x['checked_in_at']) : '<span class="badge badge-success">' . e(t('assets.current')) . '</span>' ?></td>
                            <td><?= e(t('condition.' . ($x['condition_out'] ?? 'good'))) ?><?= $x['condition_in'] ? ' → ' . e(t('condition.' . $x['condition_in'])) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assignments): ?><tr><td colspan="5" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php $receipts = db()->all('SELECT id, receipt_no, type, status, employee_name, signed_at, created_at FROM custody_receipts WHERE items LIKE ? ORDER BY id DESC', ['%"id":' . (int) $a['id'] . ',%']); ?>
        <?php if ($receipts): ?>
            <section class="card" style="margin-top:16px">
                <h3 class="card-title"><?= icon('edit') ?> <?= e(t('custody.receipts')) ?></h3>
                <ul class="list"><?php foreach ($receipts as $rc): ?>
                    <li><a class="list-main" href="<?= url('custody/' . $rc['id']) ?>" target="_blank"><span class="list-icon"><?= icon($rc['type'] === 'return' ? 'box' : 'user') ?></span><span><strong class="mono"><?= e($rc['receipt_no']) ?></strong><small><?= e(t('custody.type.' . $rc['type'])) ?> · <?= e($rc['employee_name']) ?> · <?= fmt_date($rc['signed_at'] ?? $rc['created_at']) ?></small></span></a>
                        <span class="badge badge-<?= $rc['status'] === 'signed' ? 'success' : 'warning' ?>"><?= e(t('custody.status.' . $rc['status'])) ?></span></li>
                <?php endforeach; ?></ul>
            </section>
        <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="licenses">
        <section class="card">
            <ul class="list">
                <?php foreach ($licenses as $l): ?>
                    <li><a class="list-main" href="<?= url('licenses/' . $l['id']) ?>"><span class="list-icon"><?= icon('key') ?></span><span><strong><?= e($l['name']) ?></strong><small><?= e($l['edition']) ?></small></span></a>
                        <span class="muted small"><?= fmt_date($l['expires_at']) ?></span></li>
                <?php endforeach; ?>
                <?php if (!$licenses): ?><li class="muted"><?= e(t('common.nothing')) ?></li><?php endif; ?>
            </ul>
        </section>
    </div>

    <div class="tab-panel" data-panel="comments">
        <?= partial('comments/thread', ['entity' => 'asset', 'entityId' => (int) $a['id']]) ?>
    </div>

    <div class="tab-panel" data-panel="files" id="files">
        <div class="grid grid-3">
            <section class="card span-2">
                <?php $images = array_filter($files, fn ($f) => str_starts_with((string) $f['mime'], 'image/')); ?>
                <?php if ($images): ?>
                    <div class="photo-strip">
                        <?php foreach ($images as $f): ?>
                            <a href="<?= url('files/' . $f['id'], ['view' => 1]) ?>" target="_blank" title="<?= e($f['name']) ?>"><img src="<?= url('files/' . $f['id'], ['view' => 1]) ?>" alt="<?= e($f['name']) ?>" loading="lazy"></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <ul class="list">
                    <?php foreach ($files as $f): ?>
                        <li>
                            <a class="list-main" href="<?= url('files/' . $f['id'], ['view' => 1]) ?>" target="_blank">
                                <span class="list-icon"><?= icon(str_starts_with((string) $f['mime'], 'image/') ? 'camera' : 'clipboard') ?></span>
                                <span><strong><?= e($f['name']) ?></strong><small><?= e(t('files.kind.' . $f['kind'])) ?> · <?= fmt_num($f['size'] / 1024, 0) ?> KB · <?= fmt_date($f['created_at']) ?><?= $f['user_name'] ? ' · ' . e($f['user_name']) : '' ?></small></span>
                            </a>
                            <span class="inline-actions">
                                <a class="icon-btn sm" href="<?= url('files/' . $f['id']) ?>" aria-label="<?= e(t('files.download')) ?>" title="<?= e(t('files.download')) ?>"><?= icon('download') ?></a>
                                <?php if (can('assets.edit')): ?>
                                    <form method="post" action="<?= url('files/' . $f['id'] . '/delete') ?>" data-confirm="<?= e(t('common.confirm_delete')) ?>"><?= csrf_field() ?><button class="icon-btn sm" aria-label="<?= e(t('common.delete')) ?>"><?= icon('trash') ?></button></form>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$files): ?><li class="muted"><?= e(t('files.empty')) ?></li><?php endif; ?>
                </ul>
            </section>
            <?php if (can('assets.edit')): ?>
                <section class="card">
                    <h3 class="card-title"><?= icon('upload') ?> <?= e(t('files.add')) ?></h3>
                    <form method="post" action="<?= url('assets/' . $a['id'] . '/files') ?>" enctype="multipart/form-data" class="stack" data-upload-form data-max-bytes="<?= AttachmentController::maxBytes() ?>" data-too-big="<?= e(t('files.too_big', ['n' => fmt_num(AttachmentController::maxBytes() / 1048576, 1)])) ?>">
                        <?= csrf_field() ?>
                        <label class="dropzone" data-dropzone>
                            <?= icon('upload', 'icon icon-lg') ?>
                            <strong><?= e(t('files.drop')) ?></strong>
                            <span class="muted small" data-filename><?= e(t('files.limits', ['n' => fmt_num(AttachmentController::maxBytes() / 1048576, 1)])) ?></span>
                            <input type="file" name="file" accept="<?= e(AttachmentController::accept()) ?>" required>
                        </label>
                        <label class="field"><span><?= e(t('files.kind')) ?></span><select name="kind"><?= enum_options(AttachmentController::KINDS, 'photo', 'files.kind') ?></select></label>
                        <button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(t('files.upload')) ?></button>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if (can('assets.assign')): ?>
<dialog class="modal" id="modal-checkout">
    <form method="post" action="<?= url('assets/' . $a['id'] . '/checkout') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('user') ?> <?= e(t('assets.checkout')) ?></h3>
        <label class="field"><span><?= e(t('field.employee')) ?> *</span><select name="employee_id" required><?= select_options($lookups['employees'], '') ?></select></label>
        <label class="field"><span><?= e(t('field.expected_return')) ?></span><input type="date" name="expected_return" min="<?= today() ?>"></label>
        <label class="field"><span><?= e(t('field.notes')) ?></span><textarea name="notes" rows="2"></textarea></label>
        <label class="check"><input type="checkbox" name="receipt" value="1" checked> <?= e(t('custody.with_signature')) ?></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('assets.checkout')) ?></button></div>
    </form>
</dialog>
<dialog class="modal" id="modal-checkin">
    <form method="post" action="<?= url('assets/' . $a['id'] . '/checkin') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('box') ?> <?= e(t('assets.checkin')) ?></h3>
        <p class="muted"><?= e(t('assets.checkin_from', ['name' => $a['employee_name'] ?? ''])) ?></p>
        <label class="field"><span><?= e(t('field.condition_state')) ?></span><select name="condition_in"><?= enum_options(AssetQuery::CONDITIONS, $a['condition_state'], 'condition') ?></select></label>
        <label class="check"><input type="checkbox" name="receipt" value="1" checked> <?= e(t('custody.with_return_signature')) ?></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('assets.checkin')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
