<?php
use ITAM\Services\AssetQuery;

/** Advanced filters with live counts next to each option (counts honour every other active filter). */
$sel = fn (string $key) => array_filter(explode(',', (string) ($filters[$key] ?? '')), 'strlen');
$checks = function (string $key, array $options, array $counts) use ($sel): string {
    $chosen = $sel($key);
    $html = '';
    foreach ($options as $value => $label) {
        $n = (int) ($counts[$value] ?? 0);
        $on = in_array((string) $value, $chosen, true);
        if (!$n && !$on) {
            continue;
        }
        $html .= '<label class="facet' . ($on ? ' on' : '') . '"><input type="checkbox" data-multi="' . $key . '" value="' . e((string) $value) . '"' . ($on ? ' checked' : '') . '>'
            . '<span dir="auto">' . e($label) . '</span><em>' . fmt_num($n) . '</em></label>';
    }
    return $html !== '' ? $html : '<p class="small muted">' . e(t('common.nothing')) . '</p>';
};
$opts = fn (array $rows) => array_column($rows, 'name', 'id');
$v = fn (string $k) => e((string) ($filters[$k] ?? ''));
?>
<form method="get" action="<?= url('assets') ?>" class="drawer-form" data-drawer-form>
    <header class="drawer-head">
        <h3><?= icon('filter') ?> <?= e(t('grid.filters')) ?></h3>
        <button type="button" class="icon-btn" data-drawer-close aria-label="<?= e(t('common.close')) ?>"><?= icon('x') ?></button>
    </header>
    <div class="drawer-body">
        <?php if (!empty($filters['q'])): ?><input type="hidden" name="q" value="<?= $v('q') ?>"><?php endif; ?>
        <?php foreach (['sort', 'dir'] as $k): if (isset($filters[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= $v($k) ?>"><?php endif; endforeach; ?>
        <?php foreach (['status', 'category', 'location', 'department', 'condition'] as $k): ?>
            <input type="hidden" name="<?= $k ?>" value="<?= $v($k) ?>" data-multi-target="<?= $k ?>">
        <?php endforeach; ?>

        <details class="facet-group" open><summary><?= e(t('field.status')) ?></summary>
            <div class="facets"><?= $checks('status', array_combine(AssetQuery::STATUSES, array_map(fn ($s) => t('status.' . $s), AssetQuery::STATUSES)), $facets['status']) ?></div>
            <p class="small muted"><?= e(t('grid.status_default')) ?></p>
        </details>
        <details class="facet-group" open><summary><?= e(t('field.category_id')) ?></summary>
            <div class="facets"><?= $checks('category', $opts($lookups['categories']), $facets['category']) ?></div></details>
        <details class="facet-group"<?= !empty($filters['location']) ? ' open' : '' ?>><summary><?= e(t('field.location_id')) ?></summary>
            <div class="facets"><?= $checks('location', $opts($lookups['locations']), $facets['location']) ?></div></details>
        <details class="facet-group"<?= !empty($filters['department']) ? ' open' : '' ?>><summary><?= e(t('field.department_id')) ?></summary>
            <div class="facets"><?= $checks('department', $opts($lookups['departments']), $facets['department']) ?></div></details>
        <details class="facet-group"<?= !empty($filters['condition']) ? ' open' : '' ?>><summary><?= e(t('field.condition_state')) ?></summary>
            <div class="facets"><?= $checks('condition', array_combine(AssetQuery::CONDITIONS, array_map(fn ($c) => t('condition.' . $c), AssetQuery::CONDITIONS)), $facets['condition']) ?></div></details>


        <details class="facet-group"<?= array_intersect_key($filters, array_flip(['warranty', 'warranty_from', 'warranty_to'])) ? ' open' : '' ?>><summary><?= e(t('field.warranty_until')) ?></summary>
            <label class="field"><select name="warranty"><option value=""><?= e(t('common.all')) ?></option>
                <?php foreach (['active', 'expiring', 'expired', 'none'] as $w): ?><option value="<?= $w ?>"<?= ($filters['warranty'] ?? '') === $w ? ' selected' : '' ?>><?= e(t('warranty.' . $w)) ?></option><?php endforeach; ?></select></label>
            <div class="range-row"><span><?= e(t('grid.f.between')) ?></span>
                <input type="date" name="warranty_from" value="<?= $v('warranty_from') ?>" aria-label="<?= e(t('grid.f.from')) ?>">
                <input type="date" name="warranty_to" value="<?= $v('warranty_to') ?>" aria-label="<?= e(t('grid.f.to')) ?>"></div>
        </details>

        <details class="facet-group"<?= array_intersect_key($filters, array_flip(['assigned_state', 'seen_days', 'cf'])) ? ' open' : '' ?>><summary><?= e(t('grid.f.usage')) ?></summary>
            <label class="field"><span><?= e(t('field.assigned_to')) ?></span><select name="assigned_state"><option value=""><?= e(t('common.all')) ?></option>
                <?php foreach (['any', 'none'] as $o): ?><option value="<?= $o ?>"<?= ($filters['assigned_state'] ?? '') === $o ? ' selected' : '' ?>><?= e(t('grid.assigned.' . $o)) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span><?= e(t('grid.f.not_seen')) ?></span><select name="seen_days"><option value=""><?= e(t('common.all')) ?></option>
                <?php foreach ([7, 30, 90, 180, 365] as $d): ?><option value="<?= $d ?>"<?= (string) ($filters['seen_days'] ?? '') === (string) $d ? ' selected' : '' ?>><?= e(t('grid.f.days', ['n' => $d])) ?></option><?php endforeach; ?></select></label>
            <?php if ($customFields): ?>
                <label class="field"><span><?= e(t('grid.f.custom')) ?></span>
                    <span class="range-row tight"><select name="cf"><option value="">—</option>
                        <?php foreach ($customFields as $cf): ?><option value="<?= (int) $cf['id'] ?>"<?= (string) ($filters['cf'] ?? '') === (string) $cf['id'] ? ' selected' : '' ?>><?= e($cf['category'] . ' › ' . $cf['label']) ?></option><?php endforeach; ?></select>
                    <input name="cfv" value="<?= $v('cfv') ?>" placeholder="<?= e(t('grid.f.contains')) ?>"></span></label>
            <?php endif; ?>
        </details>
    </div>
    <footer class="drawer-foot">
        <a class="btn btn-ghost" href="<?= url('assets') ?>"><?= e(t('common.clear_all')) ?></a>
        <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= e(t('grid.show_n', ['n' => fmt_num($total)])) ?></button>
    </footer>
</form>
