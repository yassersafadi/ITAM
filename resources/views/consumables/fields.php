<?php
use ITAM\Services\Consumables;

$v = fn (string $k) => $c[$k] ?? '';
?>
<div class="fields">
    <label class="field span-2"><span><?= e(t('field.name')) ?> *</span><input name="name" value="<?= e($v('name')) ?>" maxlength="160" required placeholder="<?= e(t('consumables.name_ph')) ?>"></label>
    <label class="field"><span><?= e(t('consumables.sku')) ?></span><input name="sku" value="<?= e($v('sku')) ?>" maxlength="60" dir="ltr"></label>
    <label class="field"><span><?= e(t('consumables.kind')) ?></span><select name="kind"><?= enum_options(Consumables::KINDS, $v('kind') ?: 'accessory', 'consumables.k') ?></select></label>
    <?php if (!empty($withQty)): ?>
        <label class="field"><span><?= e(t('parts.opening_stock')) ?></span><input type="number" name="quantity" min="0" value="0"></label>
    <?php endif; ?>
    <label class="field"><span><?= e(t('parts.min_quantity')) ?></span><input type="number" name="min_quantity" min="0" value="<?= e((string) $v('min_quantity')) ?>"></label>
    <label class="field"><span><?= e(t('reorder.qty')) ?></span><input type="number" name="reorder_qty" min="0" max="10000" value="<?= e((string) ($v('reorder_qty') ?: '')) ?>" placeholder="0"><small class="field-help"><?= e(t('reorder.qty_help')) ?></small></label>
    <label class="field"><span><?= e(t('parts.unit_label')) ?></span><select name="unit"><?= enum_options(Consumables::UNITS, $v('unit') ?: 'pcs', 'parts.unit') ?></select></label>
    <label class="field"><span><?= e(t('rr.unit_cost')) ?></span><input type="number" name="unit_cost" min="0" step="0.01" value="<?= e((string) $v('unit_cost')) ?>"></label>
    <label class="field"><span><?= e(t('field.location_id')) ?></span><select name="location_id"><?= select_options($locations, $v('location_id')) ?></select></label>
    <label class="field span-2"><span><?= e(t('field.notes')) ?></span><textarea name="notes" rows="2"><?= e($v('notes')) ?></textarea></label>
</div>
