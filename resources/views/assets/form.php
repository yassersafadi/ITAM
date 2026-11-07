<?php
use ITAM\Services\AssetQuery;
use ITAM\Services\CustomFields;

$isEdit = $mode === 'edit';
page_title($isEdit ? t('assets.edit') : t('assets.new'));
$v = fn (string $k) => old($k, $asset[$k] ?? '');
?>
<div class="page-head">
    <div>
        <a href="<?= $isEdit ? url('assets/' . $asset['id']) : url('assets') ?>" class="back-link">‹ <?= e(t('common.back')) ?></a>
        <h1><?= e(page_title()) ?><?= $isEdit ? ' <span class="mono muted">' . e($asset['tag']) . '</span>' : '' ?></h1>
    </div>
</div>

<form method="post" action="<?= $isEdit ? url('assets/' . $asset['id']) : url('assets') ?>" class="form-grid" data-asset-form>
    <?= csrf_field() ?>
    <section class="card">
        <h3 class="card-title"><?= icon('box') ?> <?= e(t('assets.section.identity')) ?></h3>
        <div class="fields">
            <label class="field span-2"><span><?= e(t('field.name')) ?> *</span><input name="name" value="<?= e($v('name')) ?>" required maxlength="160"></label>
            <label class="field"><span><?= e(t('field.tag')) ?></span><input name="tag" value="<?= e($v('tag')) ?>" placeholder="<?= e(t('assets.tag_auto')) ?>" class="mono"></label>
            <label class="field"><span><?= e(t('field.category_id')) ?></span><select name="category_id" data-category><?= select_options($lookups['categories'], $v('category_id')) ?></select></label>
            <label class="field"><span><?= e(t('field.status')) ?></span><select name="status"><?= enum_options(AssetQuery::STATUSES, $v('status'), 'status') ?></select></label>
            <label class="field"><span><?= e(t('field.condition_state')) ?></span><select name="condition_state"><?= enum_options(AssetQuery::CONDITIONS, $v('condition_state'), 'condition') ?></select></label>
            <label class="field"><span><?= e(t('field.criticality')) ?></span>
                <select name="criticality">
                    <?php for ($c = 1; $c <= 4; $c++): ?><option value="<?= $c ?>" <?= (int) $v('criticality') === $c ? 'selected' : '' ?>><?= $c ?> — <?= e(t('criticality.' . $c)) ?></option><?php endfor; ?>
                </select>
            </label>
            <label class="field"><span><?= e(t('field.manufacturer')) ?></span><input name="manufacturer" value="<?= e($v('manufacturer')) ?>" list="manufacturers" maxlength="80" dir="auto"></label>
            <label class="field"><span><?= e(t('field.model')) ?></span><input name="model" value="<?= e($v('model')) ?>" maxlength="120" dir="auto"></label>
            <label class="field"><span><?= e(t('field.serial')) ?></span><input name="serial" value="<?= e($v('serial')) ?>" class="mono"></label>
        </div>
        <?php $catalog = \ITAM\Services\Catalog::forForm(); ?>
        <datalist id="manufacturers"><?php foreach ($catalog['manufacturers'] as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist>
        <div hidden data-catalog="<?= e(json_encode($catalog + ['canAdd' => can('catalog.edit')], JSON_UNESCAPED_UNICODE)) ?>"></div>
        <?php if (can('catalog.edit')): ?><p class="small muted"><?= e(t('mfr.form_help_admin')) ?> <a href="<?= url('directory/manufacturers') ?>"><?= e(t('nav.manufacturers')) ?></a> · <a href="<?= url('directory/models') ?>"><?= e(t('nav.models')) ?></a></p><?php endif; ?>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('cpu') ?> <?= e(t('assets.section.technical')) ?></h3>
        <div class="fields">
            <label class="field"><span><?= e(t('field.hostname')) ?></span><input name="hostname" value="<?= e($v('hostname')) ?>" class="mono"></label>
            <label class="field"><span><?= e(t('field.ip_address')) ?></span><input name="ip_address" value="<?= e($v('ip_address')) ?>" class="mono" pattern="[0-9a-fA-F:.]+"></label>
            <label class="field"><span><?= e(t('field.mac_address')) ?></span><input name="mac_address" value="<?= e($v('mac_address')) ?>" class="mono"></label>
            <label class="field"><span><?= e(t('field.os')) ?></span><input name="os" value="<?= e($v('os')) ?>"></label>
            <label class="field"><span><?= e(t('field.cpu')) ?></span><input name="cpu" value="<?= e($v('cpu')) ?>"></label>
            <label class="field"><span><?= e(t('field.ram_gb')) ?></span><input name="ram_gb" type="number" min="0" value="<?= e($v('ram_gb')) ?>"></label>
            <label class="field"><span><?= e(t('field.storage_gb')) ?></span><input name="storage_gb" type="number" min="0" value="<?= e($v('storage_gb')) ?>"></label>
            <label class="field"><span><?= e(t('field.power_watts')) ?></span><input name="power_watts" type="number" min="0" value="<?= e($v('power_watts')) ?>" placeholder="<?= e(t('assets.power_default')) ?>"></label>
        </div>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('pin') ?> <?= e(t('assets.section.placement')) ?></h3>
        <div class="fields">
            <label class="field"><span><?= e(t('field.location_id')) ?></span><select name="location_id"><?= select_options($lookups['locations'], $v('location_id')) ?></select></label>
            <label class="field"><span><?= e(t('field.department_id')) ?></span><select name="department_id"><?= select_options($lookups['departments'], $v('department_id')) ?></select></label>
        </div>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('calendar') ?> <?= e(t('assets.section.lifecycle')) ?></h3>
        <div class="fields">
            <label class="field"><span><?= e(t('field.acquired_at')) ?></span><input type="date" name="acquired_at" value="<?= e($v('acquired_at')) ?>" data-acquired></label>
            <label class="field"><span><?= e(t('field.warranty_until')) ?></span><input type="date" name="warranty_until" value="<?= e($v('warranty_until')) ?>" data-warranty>
                <small class="field-help"><button type="button" class="link" data-warranty-plus="12">+1y</button> · <button type="button" class="link" data-warranty-plus="36">+3y</button> · <button type="button" class="link" data-warranty-plus="60">+5y</button></small></label>
            <label class="field"><span><?= e(t('field.value')) ?></span><input type="number" step="0.01" min="0" name="value" value="<?= e($v('value')) ?>" data-asset-value></label>
            <label class="field"><span><?= e(t('field.po_number')) ?></span><input name="po_number" value="<?= e($v('po_number')) ?>" maxlength="60" dir="ltr" class="mono" placeholder="PO-2026-0001"></label>
            <label class="field span-2"><span><?= e(t('field.notes')) ?></span><textarea name="notes" rows="3"><?= e($v('notes')) ?></textarea></label>
        </div>
    </section>

    <?php
    $cfAll = CustomFields::byCategory();
    $cfValues = $isEdit ? CustomFields::values((int) $asset['id']) : [];
    $cfOld = $_SESSION['_old']['cf'] ?? null;
    ?>
    <?php if ($cfAll): ?>
        <section class="card span-all" data-cf-section>
            <h3 class="card-title"><?= icon('sliders') ?> <?= e(t('cf.section')) ?></h3>
            <?php foreach ($cfAll as $catId => $fields): $on = (string) $v('category_id') === (string) $catId; ?>
                <div class="fields" data-cf-category="<?= (int) $catId ?>" <?= $on ? '' : 'hidden' ?>>
                    <?php foreach ($fields as $cf):
                        $val = (string) (is_array($cfOld) ? ($cfOld[$cf['id']] ?? '') : ($cfValues[$cf['id']] ?? ''));
                        $attrs = 'name="cf[' . (int) $cf['id'] . ']"' . ($cf['required'] ? ' required' : '') . ($on ? '' : ' disabled');
                    ?>
                        <label class="field"><span><?= e($cf['label']) ?><?= $cf['required'] ? ' *' : '' ?></span>
                            <?php if ($cf['type'] === 'select'): ?>
                                <select <?= $attrs ?>><option value="">—</option><?php foreach ($cf['choices'] as $ch): ?><option value="<?= e($ch) ?>" <?= $val === $ch ? 'selected' : '' ?>><?= e($ch) ?></option><?php endforeach; ?></select>
                            <?php else: ?>
                                <input type="<?= $cf['type'] === 'number' ? 'number' : ($cf['type'] === 'date' ? 'date' : 'text') ?>" <?= $attrs ?> value="<?= e($val) ?>" <?= $cf['type'] === 'number' ? 'step="any"' : 'maxlength="255"' ?>>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <p class="small muted" data-cf-empty <?= isset($cfAll[(int) $v('category_id')]) ? 'hidden' : '' ?>><?= e(t('cf.none_for_category')) ?></p>
        </section>
    <?php endif; ?>

    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= $isEdit ? url('assets/' . $asset['id']) : url('assets') ?>"><?= e(t('common.cancel')) ?></a>
        <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= e(t('common.save')) ?></button>
    </div>
</form>
