<?php
page_title($c['name']);
$canEdit = can('assets.assign');
$isLow = (int) $c['quantity'] <= (int) $c['min_quantity'];
$reasonTone = ['issue' => 'info', 'use' => 'info', 'receive' => 'success', 'restock' => 'success', 'count' => 'neutral', 'adjust' => 'warning', 'transfer' => 'neutral', 'return' => 'warning'];
?>
<div class="page-head">
    <div>
        <a href="<?= url('consumables') ?>" class="back-link">‹ <?= e(t('nav.consumables')) ?></a>
        <h1><?= icon('layers', 'icon icon-title') ?> <span dir="auto"><?= e($c['name']) ?></span></h1>
        <p class="muted"><?= e(trim(($c['sku'] ? $c['sku'] . ' · ' : '') . t('consumables.k.' . $c['kind']), ' ·')) ?><?= $c['location_name'] ? ' · ' . e($c['location_name']) : '' ?></p>
    </div>
</div>

<section class="mini-kpis">
    <div class="card"><span><?= e(t('parts.stock')) ?></span><strong class="<?= $isLow ? 'text-danger' : 'text-success' ?>"><?= (int) $c['quantity'] ?> <small><?= e(t('parts.unit.' . $c['unit'])) ?></small></strong></div>
    <div class="card"><span><?= e(t('parts.min_quantity')) ?></span><strong><?= (int) $c['min_quantity'] ?></strong></div>
    <div class="card"><span><?= e(t('reorder.qty')) ?></span><strong><?= (int) $c['reorder_qty'] ?: '—' ?></strong></div>
    <div class="card"><span><?= e(t('rr.unit_cost')) ?></span><strong><?= $c['unit_cost'] !== null ? fmt_num((float) $c['unit_cost'], 2) : '—' ?></strong></div>
</section>

<div class="grid grid-2">
    <?php if ($canEdit): ?>
        <section class="card">
            <h3 class="card-title"><?= icon('swap') ?> <?= e(t('consumables.stock_moves')) ?></h3>
            <form method="post" action="<?= url('consumables/' . $c['id'] . '/move') ?>" class="stack">
                <?= csrf_field() ?>
                <div class="radio-cards">
                    <?php foreach (['issue', 'receive', 'count'] as $i => $a): ?>
                        <label class="radio-card"><input type="radio" name="action" value="<?= $a ?>" <?= $i === 0 ? 'checked' : '' ?>><strong><?= e(t('consumables.action.' . $a)) ?></strong></label>
                    <?php endforeach; ?>
                </div>
                <div class="form-grid">
                    <label class="field"><span><?= e(t('field.employee_id')) ?></span><select name="employee_id"><?= select_options($employees, '') ?></select><small class="field-help"><?= e(t('consumables.employee_help')) ?></small></label>
                    <label class="field"><span><?= e(t('consumables.quantity')) ?> *</span><input type="number" name="quantity" min="0" value="1" required></label>
                </div>
                <label class="field"><span><?= e(t('field.notes')) ?></span><input name="note" maxlength="255"></label>
                <div><button class="btn btn-primary btn-sm"><?= icon('check') ?> <?= e(t('common.save')) ?></button></div>
            </form>
        </section>
    <?php endif; ?>
    <section class="card table-card">
        <div class="card-head"><h3><?= icon('history') ?> <?= e(t('consumables.history')) ?></h3></div>
        <div class="table-wrap">
            <table class="table compact">
                <thead><tr><th><?= e(t('field.date')) ?></th><th><?= e(t('consumables.move')) ?></th><th><?= e(t('field.employee_id')) ?></th><th><?= e(t('consumables.balance')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($moves as $m): ?>
                    <tr>
                        <td class="small"><?= fmt_date($m['created_at'], true) ?><small class="block muted"><?= e($m['user_name'] ?? '') ?></small></td>
                        <td><span class="badge badge-<?= $reasonTone[$m['reason']] ?? 'neutral' ?>"><?= e(t('consumables.action.' . $m['reason'])) ?></span>
                            <b dir="ltr"><?= (int) $m['change_qty'] > 0 ? '+' : '' ?><?= (int) $m['change_qty'] ?></b><?= $m['request_id'] ? ' <small class="mono">REQ-' . (int) $m['request_id'] . '</small>' : '' ?>
                            <?= $m['note'] ? '<small class="block muted" dir="auto">' . e($m['note']) . '</small>' : '' ?></td>
                        <td dir="auto"><?= $m['employee_id'] ? '<a href="' . url('employees/' . $m['employee_id']) . '">' . e($m['employee_name']) . '</a>' : '—' ?></td>
                        <td><?= (int) $m['balance'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$moves): ?><tr><td colspan="4" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php if ($canEdit): ?>
        <section class="card span-2">
            <h3 class="card-title"><?= icon('edit') ?> <?= e(t('common.edit')) ?></h3>
            <form method="post" action="<?= url('consumables/' . $c['id']) ?>" class="stack">
                <?= csrf_field() ?>
                <?= partial('consumables/fields', ['c' => $c, 'locations' => $locations]) ?>
                <div><button class="btn btn-primary btn-sm"><?= e(t('common.save')) ?></button></div>
            </form>
        </section>
    <?php endif; ?>
</div>
