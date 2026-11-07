<?php
page_title(t('nav.consumables'));
$canEdit = can('assets.assign');
?>
<div class="page-head">
    <div>
        <h1><?= icon('layers', 'icon icon-title') ?> <?= e(t('nav.consumables')) ?></h1>
        <p class="muted"><?= e(t('consumables.subtitle')) ?></p>
    </div>
    <?php if ($canEdit): ?><div class="page-actions"><button class="btn btn-primary" data-modal-open="new-consumable"><?= icon('plus') ?> <?= e(t('consumables.new')) ?></button></div><?php endif; ?>
</div>

<?php if ($request): ?>
    <div class="callout callout-info"><?= icon('clipboard') ?>
        <div><strong><?= e(t('consumables.for_request', ['n' => 'REQ-' . $request['id'], 'name' => $request['name']])) ?></strong>
            <p class="small" dir="auto"><?= e(mb_strimwidth($request['description'], 0, 200, '…')) ?></p>
            <p class="small muted"><?= e(t('consumables.for_request_help')) ?></p></div>
    </div>
<?php endif; ?>

<section class="mini-kpis">
    <div class="card"><span><?= e(t('consumables.items')) ?></span><strong><?= fmt_num($total) ?></strong></div>
    <a class="card" href="<?= url('consumables', ['low' => 1]) ?>"><span><?= e(t('parts.low')) ?></span><strong class="<?= $lowCount ? 'text-danger' : 'text-success' ?>"><?= $lowCount ?></strong></a>
    <div class="card"><span><?= e(t('consumables.issued_30')) ?></span><strong><?= fmt_num($issued30) ?></strong></div>
    <div class="card"><span><?= e(t('consumables.stock_value')) ?></span><strong><?= fmt_num($value) ?></strong></div>
</section>

<div class="toolbar">
    <div class="seg">
        <a href="<?= url('consumables') ?>" class="<?= !$low && $kind === '' ? 'active' : '' ?>"><?= e(t('common.all')) ?></a>
        <a href="<?= url('consumables', ['low' => 1]) ?>" class="<?= $low ? 'active' : '' ?>"><?= icon('alert', 'icon icon-xs') ?> <?= e(t('parts.low')) ?></a>
        <a href="<?= url('consumables', ['kind' => 'spare_part']) ?>" class="<?= $kind === 'spare_part' ? 'active' : '' ?>"><?= icon('cpu', 'icon icon-xs') ?> <?= e(t('consumables.k.spare_part')) ?></a>
    </div>
    <form method="get" action="<?= url('consumables') ?>" class="inline-form search-form">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('consumables.search')) ?>">
        <?php if ($request): ?><input type="hidden" name="request" value="<?= (int) $request['id'] ?>"><?php endif; ?>
    </form>
</div>

<section class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= e(t('field.name')) ?></th><th><?= e(t('parts.stock')) ?></th><th class="hide-md"><?= e(t('field.location_id')) ?></th><th><?= e(t('consumables.issued_90')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $c):
                $qty = (int) $c['quantity'];
                $min = (int) $c['min_quantity'];
                $isLow = $qty <= $min;
                $pct = (int) min(100, round($qty / max(1, $min * 3) * 100)); ?>
                <tr>
                    <td><a href="<?= url('consumables/' . $c['id']) ?>" class="cell-main"><span class="cell-icon"><?= icon('layers') ?></span>
                        <span><strong dir="auto"><?= e($c['name']) ?></strong><small><?= e(trim(($c['sku'] ? $c['sku'] . ' · ' : '') . t('consumables.k.' . $c['kind']), ' ·')) ?></small></span></a></td>
                    <td class="stock-cell">
                        <span><b class="<?= $isLow ? 'text-danger' : '' ?>"><?= $qty ?></b> <?= e(t('parts.unit.' . $c['unit'])) ?>
                            <?php if ($isLow): ?><span class="badge badge-danger"><?= e(t('parts.reorder')) ?></span><?php endif; ?></span>
                        <span class="meter"><span class="bg-<?= $isLow ? 'danger' : ($qty <= $min * 2 ? 'warning' : 'success') ?>" style="width: <?= $pct ?>%"></span></span>
                        <small class="muted"><?= e(t('parts.min', ['n' => $min])) ?></small>
                    </td>
                    <td class="hide-md"><?= e($c['location_name'] ?? '—') ?></td>
                    <td><?= (int) ($usage[$c['id']] ?? 0) ?></td>
                    <td class="actions">
                        <?php if ($canEdit): ?>
                            <button type="button" class="btn <?= $request ? 'btn-primary' : 'btn-ghost' ?> btn-sm" data-modal-open="issue" data-form-action="<?= url('consumables/' . $c['id'] . '/move') ?>"
                                    data-caption="<?= e($c['name'] . ' · ' . t('consumables.in_stock_n', ['n' => $qty])) ?>" <?= $qty < 1 ? 'disabled' : '' ?>><?= icon('user') ?> <?= e(t('consumables.issue')) ?></button>
                            <form method="post" action="<?= url('consumables/' . $c['id'] . '/move') ?>" class="inline-actions">
                                <?= csrf_field() ?><input type="hidden" name="action" value="receive">
                                <input type="number" name="quantity" min="1" placeholder="+" class="tiny-input" required aria-label="<?= e(t('parts.receive')) ?>">
                                <button class="btn btn-ghost btn-sm" title="<?= e(t('parts.receive')) ?>"><?= icon('plus') ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state small"><?= icon('layers') ?><p><?= e(t('consumables.none')) ?></p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canEdit): ?>
<dialog class="modal" id="modal-new-consumable">
    <form method="post" action="<?= url('consumables') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('layers') ?> <?= e(t('consumables.new')) ?></h3>
        <?= partial('consumables/fields', ['c' => ['min_quantity' => 5], 'locations' => $locations, 'withQty' => true]) ?>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.create')) ?></button></div>
    </form>
</dialog>
<dialog class="modal" id="modal-issue">
    <form method="post" action="" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="issue">
        <?php if ($request): ?><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><?php endif; ?>
        <h3><?= icon('user') ?> <?= e(t('consumables.issue')) ?></h3>
        <p class="muted small" data-modal-caption></p>
        <label class="field"><span><?= e(t('field.employee_id')) ?> *</span><select name="employee_id" required><?= select_options($employees, $request['employee_id'] ?? '') ?></select></label>
        <div class="form-grid">
            <label class="field"><span><?= e(t('consumables.quantity')) ?> *</span><input type="number" name="quantity" min="1" value="1" required></label>
            <label class="field"><span><?= e(t('field.notes')) ?></span><input name="note" maxlength="255"></label>
        </div>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('consumables.issue')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
