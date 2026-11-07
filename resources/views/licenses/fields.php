<?php use ITAM\Controllers\LicenseController; ?>
<div class="fields">
    <label class="field span-2"><span><?= e(t('field.name')) ?> *</span><input name="name" value="<?= e($l['name'] ?? '') ?>" required></label>
    <label class="field"><span><?= e(t('field.publisher')) ?></span><input name="publisher" value="<?= e($l['publisher'] ?? '') ?>"></label>
    <label class="field"><span><?= e(t('field.edition')) ?></span><input name="edition" value="<?= e($l['edition'] ?? '') ?>"></label>
    <label class="field"><span><?= e(t('field.license_type')) ?></span><select name="license_type"><?= enum_options(LicenseController::TYPES, $l['license_type'] ?? 'per_device', 'license_type') ?></select></label>
    <label class="field"><span><?= e(t('field.seats')) ?> *</span><input type="number" min="1" name="seats" value="<?= e($l['seats'] ?? 1) ?>" required></label>
    <label class="field"><span><?= e(t('field.expires_at')) ?></span><input type="date" name="expires_at" value="<?= e($l['expires_at'] ?? '') ?>"></label>
    <label class="field"><span><?= e(t('field.license_key')) ?></span><input name="license_key" value="<?= e($l['license_key'] ?? '') ?>" class="mono" autocomplete="off"></label>
    <label class="field span-2"><span><?= e(t('field.notes')) ?></span><textarea name="notes" rows="2"><?= e($l['notes'] ?? '') ?></textarea></label>
</div>
