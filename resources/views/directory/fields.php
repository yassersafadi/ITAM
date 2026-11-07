<div class="fields">
    <?php foreach ($schema['fields'] as $f => [$kind, $rule]): $val = old($f, $row[$f] ?? ''); $req = str_contains($rule, 'required'); ?>
        <?php if ($kind === 'checkbox'): ?>
            <label class="check span-2"><input type="checkbox" name="<?= $f ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= e(t('field.' . $f)) ?></label>
        <?php elseif ($kind === 'icon'): ?>
            <div class="field span-2"><span><?= e(t('field.icon')) ?></span>
                <div class="icon-picker">
                    <?php foreach (['laptop', 'monitor', 'server', 'cloud', 'network', 'printer', 'phone', 'tablet', 'zap', 'cpu', 'box', 'camera', 'key', 'radar'] as $ic): ?>
                        <label><input type="radio" name="icon" value="<?= $ic ?>" <?= $val === $ic ? 'checked' : '' ?>><span><?= icon($ic) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($kind === 'org_level'): ?>
            <label class="field"><span><?= e(t('field.level')) ?> *</span><select name="<?= $f ?>" required data-org-level>
                <?php foreach (\ITAM\Services\OrgUnits::LEVELS as $lv): ?><option value="<?= $lv ?>"<?= ($val ?: 'department') === $lv ? ' selected' : '' ?>><?= e(t('org.level.' . $lv)) ?></option><?php endforeach; ?>
            </select></label>
        <?php elseif ($kind === 'org_parent'): ?>
            <label class="field"><span><?= e(t('field.parent_id')) ?></span><select name="<?= $f ?>" data-org-parent>
                <option value=""><?= e(t('org.no_parent')) ?></option>
                <?php foreach (\ITAM\Services\OrgUnits::tree() as $u): if ((int) $u['id'] === (int) ($row['id'] ?? 0)) continue; ?>
                    <option value="<?= (int) $u['id'] ?>" data-rank="<?= \ITAM\Services\OrgUnits::rank($u['level']) ?>"<?= (string) $val === (string) $u['id'] ? ' selected' : '' ?>><?= e(str_repeat('— ', $u['depth']) . $u['name'] . ' (' . t('org.level.' . $u['level']) . ')') ?></option>
                <?php endforeach; ?>
            </select><small class="muted"><?= e(t('org.parent_help')) ?></small></label>
        <?php elseif (str_starts_with($kind, 'select:')): $ref = substr($kind, 7); ?>
            <label class="field"><span><?= e(t('field.' . $f)) ?><?= $req ? ' *' : '' ?></span><select name="<?= $f ?>"<?= $req ? ' required' : '' ?>><?= select_options($refs[$ref], $val) ?></select></label>
        <?php else: ?>
            <label class="field"><span><?= e(t($f === 'title' ? 'field.job_title_en' : ($f === 'title_ar' ? 'field.job_title_ar' : 'field.' . $f))) ?><?= $req ? ' *' : '' ?></span>
                <input type="<?= $kind === 'number' ? 'number' : ($kind === 'email' ? 'email' : ($kind === 'tel' ? 'tel' : 'text')) ?>" name="<?= $f ?>" value="<?= e($kind === 'tel' && preg_match('/^\d{8,15}$/', (string) $val) ? '+' . $val : $val) ?>" <?= $req ? 'required' : '' ?> <?= $kind === 'number' ? 'min="0"' : '' ?><?= $kind === 'tel' ? ' dir="ltr" inputmode="tel" maxlength="20"' : '' ?><?= $kind === 'url' ? ' dir="ltr" inputmode="url"' : '' ?>></label>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php if (isset($schema['fields']['level'])): ?>
<script>
// Only units of a higher level can hold this one: the others are hidden from the list.
document.querySelectorAll('[data-org-level]').forEach((level) => {
    const parent = level.closest('form').querySelector('[data-org-parent]');
    const rank = { executive: 0, sector: 1, department: 2, section: 3 };
    const sync = () => {
        parent.querySelectorAll('option[data-rank]').forEach((o) => {
            o.hidden = Number(o.dataset.rank) >= rank[level.value];
            if (o.hidden && o.selected) parent.value = '';
        });
        parent.disabled = level.value === 'executive';
    };
    level.addEventListener('change', sync);
    sync();
});
</script>
<?php endif; ?>
