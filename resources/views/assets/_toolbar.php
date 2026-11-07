<?php
use ITAM\Services\AssetGrid;
use ITAM\Services\AssetQuery;

/** Search, quick filters and display controls. Replaced in place when filters change by navigation. */
$quick = ['status', 'category', 'location'];
$advanced = array_diff_key(AssetQuery::active($filters), array_flip(array_merge($quick, ['q'])));
$multiOption = function (string $key) use ($filters): string {
    $v = (string) ($filters[$key] ?? '');
    return str_contains($v, ',') ? '<option value="' . e($v) . '" selected>' . e(t('grid.n_selected', ['n' => count(explode(',', $v))])) . '</option>' : '';
};
$single = fn (string $key) => str_contains((string) ($filters[$key] ?? ''), ',') ? '' : ($filters[$key] ?? '');
$viewIcons = ['table' => 'list', 'cards' => 'grid', 'groups' => 'group'];
?>
<form class="grid-toolbar" method="get" action="<?= url('assets') ?>" data-grid-form role="search">
    <div class="grid-search">
        <?= icon('search') ?>
        <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e(t('assets.search_placeholder')) ?>" autocomplete="off" data-live-search aria-label="<?= e(t('common.search')) ?>">
        <kbd class="hide-sm" title="<?= e(t('grid.kbd_hint')) ?>">/</kbd>
    </div>
    <select name="status" aria-label="<?= e(t('field.status')) ?>"><?= $multiOption('status') ?><?= enum_options(AssetQuery::STATUSES, $single('status'), 'status', true) ?></select>
    <select name="category" aria-label="<?= e(t('field.category_id')) ?>"><option value=""><?= e(t('grid.any_category')) ?></option><?= $multiOption('category') ?><?= substr(select_options($lookups['categories'], $single('category')), strlen('<option value="">—</option>')) ?></select>
    <select name="location" aria-label="<?= e(t('field.location_id')) ?>"><option value=""><?= e(t('grid.any_location')) ?></option><?= $multiOption('location') ?><?= substr(select_options($lookups['locations'], $single('location')), strlen('<option value="">—</option>')) ?></select>
    <?php foreach ($advanced + array_intersect_key($filters, ['sort' => 1, 'dir' => 1]) as $k => $v): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
    <?php endforeach; ?>
    <button type="button" class="btn btn-ghost" data-drawer-open="filters" aria-controls="filters-drawer">
        <?= icon('filter') ?> <span class="hide-sm"><?= e(t('grid.filters')) ?></span><?php if ($advanced): ?> <span class="count count-primary"><?= count($advanced) ?></span><?php endif; ?>
    </button>
    <noscript><button class="btn btn-ghost" type="submit"><?= e(t('common.filter')) ?></button></noscript>

    <div class="grid-display">
        <div class="seg seg-icons" role="group" aria-label="<?= e(t('grid.layout')) ?>">
            <?php foreach (AssetGrid::VIEWS as $v): ?>
                <a href="<?= url('assets', $filters + ['view' => $v]) ?>" class="<?= $prefs['view'] === $v ? 'active' : '' ?>" title="<?= e(t('grid.view.' . $v)) ?>" aria-label="<?= e(t('grid.view.' . $v)) ?>" data-pref="view" data-value="<?= $v ?>"><?= icon($viewIcons[$v]) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($prefs['view'] === 'groups'): ?>
            <select data-pref="group" aria-label="<?= e(t('grid.group_by')) ?>">
                <?php foreach (AssetGrid::GROUPS as $g): ?><option value="<?= $g ?>"<?= $prefs['group'] === $g ? ' selected' : '' ?>><?= e(t('grid.group.' . $g)) ?></option><?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($prefs['view'] === 'table'): ?>
            <div class="dropdown">
                <button type="button" class="btn btn-ghost btn-icon" data-dropdown title="<?= e(t('grid.columns')) ?>" aria-label="<?= e(t('grid.columns')) ?>"><?= icon('columns') ?></button>
                <div class="dropdown-menu col-menu" data-cols>
                    <strong class="small muted"><?= e(t('grid.columns')) ?></strong>
                    <?php foreach (AssetGrid::COLUMNS as $key => [$label]): ?>
                        <label class="check"><input type="checkbox" value="<?= $key ?>"<?= in_array($key, $prefs['columns'], true) ? ' checked' : '' ?>> <?= e(t($label)) ?></label>
                    <?php endforeach; ?>
                    <a href="<?= url('assets', $filters + ['cols' => implode(',', AssetGrid::DEFAULT_COLUMNS)]) ?>" class="small" data-pref="cols" data-value="<?= implode(',', AssetGrid::DEFAULT_COLUMNS) ?>"><?= e(t('grid.reset_columns')) ?></a>
                </div>
            </div>
            <a class="btn btn-ghost btn-icon<?= $prefs['density'] === 'compact' ? ' active' : '' ?>" href="<?= url('assets', $filters + ['density' => $prefs['density'] === 'compact' ? 'comfortable' : 'compact']) ?>"
               title="<?= e(t('grid.density.' . ($prefs['density'] === 'compact' ? 'comfortable' : 'compact'))) ?>" data-pref="density" data-value="<?= $prefs['density'] === 'compact' ? 'comfortable' : 'compact' ?>"><?= icon('rows') ?></a>
        <?php endif; ?>
        <?php if ($prefs['view'] !== 'groups'): ?>
            <select data-pref="per_page" aria-label="<?= e(t('grid.per_page')) ?>">
                <?php foreach (AssetGrid::PER_PAGE as $n): ?><option value="<?= $n ?>"<?= (int) $prefs['per_page'] === $n ? ' selected' : '' ?>><?= e(t('grid.n_per_page', ['n' => $n])) ?></option><?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
</form>
