<?php
use ITAM\Services\AssetGrid;
use ITAM\Services\AssetQuickEdit;
use ITAM\Services\AssetQuery;

/** Everything below the toolbar: ready-made views, active filters, headline figures, bulk actions and the list itself. */
$lookupName = function (array $rows, string $ids): string {
    $byId = array_column($rows, 'name', 'id');
    return implode(', ', array_filter(array_map(fn ($id) => $byId[(int) $id] ?? null, explode(',', $ids))));
};
$cfLabels = array_column($customFields, 'label', 'id');
$chips = [];
foreach (AssetQuery::active($filters) as $k => $v) {
    if ($k === 'cfv') {
        continue;
    }
    $label = match ($k) {
        'q' => '“' . $v . '”',
        'status' => implode(', ', array_map(fn ($s) => t('status.' . $s), explode(',', (string) $v))),
        'category' => $lookupName($lookups['categories'], (string) $v),
        'location' => $lookupName($lookups['locations'], (string) $v),
        'department' => $lookupName($lookups['departments'], (string) $v),
        'assigned' => $lookupName($lookups['employees'], (string) $v),
        'condition' => implode(', ', array_map(fn ($c) => t('condition.' . $c), explode(',', (string) $v))),
        'warranty' => t('warranty.' . $v),
        'age_min' => t('nlq.age_min', ['n' => $v]),
        'age_max' => t('grid.chip.age_max', ['n' => $v]),
        'warranty_from' => t('grid.chip.warranty_from', ['d' => fmt_date((string) $v)]),
        'warranty_to' => t('grid.chip.warranty_to', ['d' => fmt_date((string) $v)]),
        'seen_days' => t('grid.chip.seen_days', ['n' => $v]),
        'assigned_state' => t('grid.assigned.' . $v),
        'criticality' => t('grid.chip.criticality', ['level' => t('criticality.' . (int) $v)]),
        'cf' => ($cfLabels[(int) $v] ?? '?') . ': ' . ($filters['cfv'] ?? ''),
        'ids' => t('grid.chip.ids', ['n' => count(explode(',', (string) $v))]),
        'seen' => t('nlq.flag.stale'),
        'overdue' => t('nlq.flag.overdue'),
        'unassigned' => t('nlq.flag.unassigned'),
        default => (string) $v,
    };
    $without = $filters;
    unset($without[$k]);
    if ($k === 'cf') {
        unset($without['cfv']);
    }
    $chips[] = [$label, url('assets', $without)];
}
$sortLink = function (string $col) use ($filters): string {
    $dir = ($filters['sort'] ?? '') === $col && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
    return url('assets', array_merge($filters, ['sort' => $col, 'dir' => $dir]));
};
$sortMark = fn (string $col) => ($filters['sort'] ?? '') === $col ? '<span class="sort-mark">' . (($filters['dir'] ?? 'asc') === 'desc' ? '▼' : '▲') . '</span>' : '';
$with = fn (array $extra) => url('assets', array_merge(array_diff_key($filters, ['page' => 1]), $extra));
$s = $summary;
$warnDays = (int) config('intelligence.warranty_warn_days', 60);

/** One optional column cell. */
$cell = function (array $a, string $col): string {
    $muted = '<span class="muted">—</span>';
    switch ($col) {
        case 'status': return status_badge($a['status']);
        case 'assigned': return $a['employee_name'] ? '<a href="' . url('employees/' . $a['assigned_to']) . '" dir="auto">' . e($a['employee_name']) . '</a>' : $muted;
        case 'location': return $a['location_name'] ? e($a['location_name']) : $muted;
        case 'department': return $a['department_name'] ? e($a['department_name']) : $muted;
        case 'warranty':
            if (!$a['warranty_until']) {
                return $muted;
            }
            $days = -1 * days_between($a['warranty_until']);
            return '<span class="' . ($days < 0 ? 'text-danger' : ($days <= (int) config('intelligence.warranty_warn_days', 60) ? 'text-warning' : '')) . '">' . fmt_date($a['warranty_until']) . '</span>';
        case 'criticality': return '<span class="badge badge-' . ((int) $a['criticality'] >= 3 ? 'danger' : 'neutral') . '">' . e(t('criticality.' . (int) $a['criticality'])) . '</span>';
        case 'serial': return $a['serial'] ? '<span class="mono">' . e($a['serial']) . '</span>' : $muted;
        case 'model': return $a['model'] ? '<span dir="auto">' . e($a['model']) . '</span>' : $muted;
        case 'category': return $a['category_name'] ? '<span dir="auto">' . e($a['category_name']) . '</span>' : $muted;
        case 'manufacturer': return $a['manufacturer'] ? '<span dir="auto">' . e($a['manufacturer']) . '</span>' : $muted;
        case 'ip': return $a['ip_address'] ? '<span class="mono small" dir="ltr">' . e($a['ip_address']) . '</span>' : $muted;
        case 'os': return $a['os'] ? '<span class="small">' . e($a['os']) . '</span>' : $muted;
        case 'seen': return $a['last_seen_at'] ? '<span class="small" title="' . e(fmt_date($a['last_seen_at'], true)) . '">' . e(time_ago($a['last_seen_at'])) . '</span>' : $muted;
        case 'age': return $a['acquired_at'] ? e(t('grid.years', ['n' => fmt_num(max(0, (time() - strtotime($a['acquired_at'])) / 31557600), 1)])) : $muted;
        case 'po': return $a['po_number'] ? '<span class="mono small" dir="ltr">' . e($a['po_number']) . '</span>' : $muted;
        case 'value': return $a['value'] !== null && $a['value'] !== '' ? fmt_num((float) $a['value']) : $muted;
    }
    return $muted;
};

/** Row actions shared by rows and cards (always visible). */
$actions = function (array $a): string {
    $h = '<button type="button" class="icon-btn sm" data-preview="' . url('assets/' . $a['id'] . '/preview') . '" title="' . e(t('grid.preview')) . '" aria-label="' . e(t('grid.preview')) . '">' . icon('eye') . '</button>';
    $h .= '<a class="icon-btn sm" href="' . url('assets/labels', ['ids' => $a['id']]) . '" target="_blank" title="' . e(t('assets.label')) . '" aria-label="' . e(t('assets.label')) . '">' . icon('qr') . '</a>';
    if (can('assets.edit')) {
        $h .= '<a class="icon-btn sm" href="' . url('assets/' . $a['id'] . '/edit') . '" title="' . e(t('common.edit')) . '" aria-label="' . e(t('common.edit')) . '">' . icon('edit') . '</a>';
    }
    return '<span class="row-actions">' . $h . '</span>';
};

$columns = $prefs['columns'];
// Serial sits right after the tag; every other chosen column follows the name.
$lead = array_values(array_intersect(AssetGrid::LEADING, $columns));
$rest = array_values(array_diff($columns, $lead));
/** Table of assets (used flat, and inside each group). */
$table = function (array $rows, bool $sortable) use ($lead, $rest, $cell, $actions, $sortLink, $sortMark): string {
    $th = function (string $c) use ($sortable, $sortLink, $sortMark): string {
        [$label, $sort] = AssetGrid::COLUMNS[$c];
        return '<th class="col-' . $c . '">' . ($sortable && $sort ? '<a href="' . e($sortLink($sort)) . '">' . e(t($label)) . $sortMark($sort) . '</a>' : e(t($label))) . '</th>';
    };
    ob_start(); ?>
    <div class="table-wrap grid-scroll">
        <table class="table grid-table">
            <thead><tr>
                <th class="w-check"><input type="checkbox" data-check-all aria-label="<?= e(t('grid.select_all')) ?>"></th>
                <th><?= $sortable ? '<a href="' . e($sortLink('tag')) . '">' . e(t('field.tag')) . $sortMark('tag') . '</a>' : e(t('field.tag')) ?></th>
                <?= implode('', array_map($th, $lead)) ?>
                <th><?= $sortable ? '<a href="' . e($sortLink('name')) . '">' . e(t('field.name')) . $sortMark('name') . '</a>' : e(t('field.name')) ?></th>
                <?= implode('', array_map($th, $rest)) ?>
                <th class="w-actions"><?= e(t('common.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <tr data-row data-id="<?= (int) $a['id'] ?>" data-maker="<?= e((string) $a['manufacturer']) ?>" data-url="<?= url('assets/' . $a['id']) ?>" data-preview-url="<?= url('assets/' . $a['id'] . '/preview') ?>">
                    <td class="w-check"><input type="checkbox" name="ids[]" value="<?= (int) $a['id'] ?>" data-check aria-label="<?= e($a['tag']) ?>"></td>
                    <td class="mono"<?= AssetQuickEdit::attrs($a, 'tag') ?>><a href="<?= url('assets/' . $a['id']) ?>"><?= e($a['tag']) ?></a></td>
                    <?php foreach ($lead as $c): ?><td class="col-<?= $c ?>"<?= AssetQuickEdit::attrs($a, $c) ?>><?= $cell($a, $c) ?></td><?php endforeach; ?>
                    <td class="col-name"<?= AssetQuickEdit::attrs($a, 'name') ?>><div class="cell-main"><span class="cell-icon"><?= icon($a['category_icon'] ?? 'box') ?></span>
                        <span><strong dir="auto"><?= e($a['name']) ?></strong><?php if ($a['hostname']): ?><small class="mono" dir="ltr"><?= e($a['hostname']) ?></small><?php endif; ?></span></div></td>
                    <?php foreach ($rest as $c): ?><td class="col-<?= $c ?>"<?= AssetQuickEdit::attrs($a, $c) ?>><?= $cell($a, $c) ?></td><?php endforeach; ?>
                    <td class="w-actions"><?= $actions($a) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="preset-bar" role="navigation" aria-label="<?= e(t('grid.views')) ?>">
    <?php foreach ($presets as $key => $p): ?>
        <a class="preset<?= $p['active'] ? ' active' : '' ?>" href="<?= url('assets', $p['query']) ?>"><?= icon($p['icon'], 'icon icon-xs') ?> <?= e(t('grid.preset.' . $key)) ?> <span class="count"><?= fmt_num($p['count']) ?></span></a>
    <?php endforeach; ?>
    <?php foreach ($savedViews as $i => $v): $active = AssetGrid::matches($filters, $v['query']); ?>
        <span class="preset saved<?= $active ? ' active' : '' ?>">
            <a href="<?= url('assets', $v['query']) ?>"><?= icon('bookmark', 'icon icon-xs') ?> <span dir="auto"><?= e($v['name']) ?></span></a>
            <form method="post" action="<?= url('assets/views/' . $i . '/delete') ?>" data-confirm="<?= e(t('grid.delete_view', ['name' => $v['name']])) ?>"><?= csrf_field() ?><button class="preset-x" aria-label="<?= e(t('common.delete')) ?>"><?= icon('x') ?></button></form>
        </span>
    <?php endforeach; ?>
    <?php if (AssetQuery::active($filters)): ?>
        <button type="button" class="preset add" data-modal-open="save-view"><?= icon('plus', 'icon icon-xs') ?> <?= e(t('grid.save_view')) ?></button>
    <?php endif; ?>
</div>

<?php if ($chips): ?>
    <div class="active-filters">
        <?php foreach ($chips as [$label, $link]): ?>
            <a class="chip" href="<?= e($link) ?>"><span dir="auto"><?= e($label) ?></span> <?= icon('x') ?></a>
        <?php endforeach; ?>
        <a class="link small" href="<?= url('assets') ?>"><?= e(t('common.clear_all')) ?></a>
    </div>
<?php endif; ?>

<section class="grid-kpis">
    <div class="kpi-tile"><span><?= e(t('grid.kpi.total')) ?></span><strong><?= fmt_num($s['total'] ?? 0) ?></strong></div>
    <a class="kpi-tile" href="<?= $with(['warranty' => 'expiring']) ?>"><span><?= e(t('grid.kpi.expiring', ['n' => $warnDays])) ?></span><strong class="<?= $s['expiring'] ? 'text-warning' : '' ?>"><?= fmt_num($s['expiring'] ?? 0) ?></strong></a>
    <a class="kpi-tile" href="<?= $with(['warranty' => 'expired']) ?>"><span><?= e(t('grid.kpi.expired')) ?></span><strong><?= fmt_num($s['expired'] ?? 0) ?></strong></a>
    <a class="kpi-tile" href="<?= $with(['seen' => 'stale']) ?>"><span><?= e(t('grid.kpi.stale')) ?></span><strong class="<?= $s['stale'] ? 'text-warning' : '' ?>"><?= fmt_num($s['stale'] ?? 0) ?></strong></a>
    <a class="kpi-tile" href="<?= $with(['assigned_state' => 'none']) ?>"><span><?= e(t('grid.kpi.unassigned')) ?></span><strong><?= fmt_num($s['unassigned'] ?? 0) ?></strong></a>
    <div class="kpi-tile"><span><?= e(t('grid.kpi.value')) ?></span><strong><?= fmt_num($s['value'] ?? 0) ?></strong></div>
</section>

<form method="post" action="<?= url('assets/bulk') ?>" data-grid-bulk data-view="<?= e($prefs['view']) ?>" class="density-<?= e($prefs['density']) ?>">
    <?= csrf_field() ?>
    <div class="bulkbar" data-bulkbar hidden>
        <span class="bulk-count"><strong data-bulk-count>0</strong> <?= e(t('assets.selected')) ?></span>
        <button class="btn btn-sm btn-ghost" name="bulk_action" value="labels"><?= icon('qr') ?> <?= e(t('assets.labels')) ?></button>
        <?php if (can('reports.export')): ?><span class="bulk-export"><select name="bulk_format" aria-label="<?= e(t('export.format')) ?>"><?php foreach (\ITAM\Services\Exporter::FORMATS as $fmt): ?><option value="<?= $fmt ?>"><?= e(t('export.f.' . $fmt)) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-ghost" name="bulk_action" value="export"><?= icon('download') ?> <?= e(t('export.button')) ?></button></span><?php endif; ?>
        <?php if (can('assets.edit')): ?>
            <span class="bulk-sep"></span>
            <select data-bulk-action aria-label="<?= e(t('grid.bulk.change')) ?>">
                <option value=""><?= e(t('grid.bulk.change')) ?>…</option>
                <option value="status"><?= e(t('field.status')) ?></option>
                <option value="location"><?= e(t('field.location_id')) ?></option>
                <option value="department"><?= e(t('field.department_id')) ?></option>
                <option value="criticality"><?= e(t('field.criticality')) ?></option>
                <?php if (can('maintenance.edit')): ?><option value="work_order"><?= e(t('grid.bulk.work_order')) ?></option><?php endif; ?>
            </select>
            <span class="bulk-fields" data-bulk-for="status" hidden><select name="bulk_status"><?= enum_options(AssetQuery::STATUSES, '', 'status') ?></select></span>
            <span class="bulk-fields" data-bulk-for="location" hidden><select name="bulk_location"><?= select_options($lookups['locations'], '') ?></select></span>
            <span class="bulk-fields" data-bulk-for="department" hidden><select name="bulk_department"><?= select_options($lookups['departments'], '') ?></select></span>
            <span class="bulk-fields" data-bulk-for="criticality" hidden><select name="bulk_criticality"><?php foreach ([1, 2, 3, 4] as $c): ?><option value="<?= $c ?>"><?= e(t('criticality.' . $c)) ?></option><?php endforeach; ?></select></span>
            <span class="bulk-fields" data-bulk-for="work_order" hidden>
                <input name="bulk_title" maxlength="200" placeholder="<?= e(t('grid.bulk.wo_title')) ?>">
                <select name="bulk_type"><?= enum_options(\ITAM\Controllers\MaintenanceController::TYPES, 'inspection', 'maintenance.type') ?></select>
                <select name="bulk_priority"><?= enum_options(\ITAM\Controllers\MaintenanceController::PRIORITIES, 'medium', 'priority') ?></select>
            </span>
            <button class="btn btn-sm btn-primary" name="bulk_action" value="" data-bulk-apply hidden><?= icon('check') ?> <?= e(t('grid.bulk.apply')) ?></button>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-ghost bulk-clear" data-bulk-clear><?= icon('x') ?> <?= e(t('grid.bulk.clear')) ?></button>
    </div>

    <?php if (!$assets): ?>
        <div class="card empty-state"><?= icon('search') ?><p><?= e(t('assets.none')) ?></p>
            <?php if (AssetQuery::active($filters)): ?><a class="btn btn-ghost btn-sm" href="<?= url('assets') ?>"><?= e(t('common.clear_all')) ?></a><?php endif; ?></div>

    <?php elseif ($prefs['view'] === 'cards'): ?>
        <div class="asset-cards">
            <?php foreach ($assets as $a): $wDays = $a['warranty_until'] ? -1 * days_between($a['warranty_until']) : null; ?>
                <article class="asset-card" data-row data-id="<?= (int) $a['id'] ?>" data-url="<?= url('assets/' . $a['id']) ?>" data-preview-url="<?= url('assets/' . $a['id'] . '/preview') ?>">
                    <header>
                        <input type="checkbox" name="ids[]" value="<?= (int) $a['id'] ?>" data-check aria-label="<?= e($a['tag']) ?>">
                        <span class="cell-icon"><?= icon($a['category_icon'] ?? 'box') ?></span>
                        <a class="mono" href="<?= url('assets/' . $a['id']) ?>"><?= e($a['tag']) ?></a>
                        <?= status_badge($a['status']) ?>
                    </header>
                    <div class="asset-card-body">
                        <div>
                            <strong dir="auto"><?= e($a['name']) ?></strong>
                            <small class="muted"><?= e($a['category_name'] ?? '') ?><?= $a['model'] ? ' · ' . e($a['model']) : '' ?></small>
                        </div>
                    </div>
                    <dl class="asset-card-meta">
                        <div><dt><?= icon('user', 'icon icon-xs') ?></dt><dd dir="auto"><?= e($a['employee_name'] ?? t('grid.assigned.none')) ?></dd></div>
                        <div><dt><?= icon('pin', 'icon icon-xs') ?></dt><dd><?= e($a['location_name'] ?? '—') ?></dd></div>
                        <div><dt><?= icon('shield', 'icon icon-xs') ?></dt><dd class="<?= $wDays !== null && $wDays < 0 ? 'text-danger' : ($wDays !== null && $wDays <= $warnDays ? 'text-warning' : '') ?>"><?= $a['warranty_until'] ? fmt_date($a['warranty_until']) : '—' ?></dd></div>
                    </dl>
                    <footer><?= $actions($a) ?></footer>
                </article>
            <?php endforeach; ?>
        </div>

    <?php elseif ($prefs['view'] === 'groups'):
        $groups = [];
        foreach ($assets as $a) {
            $label = match ($prefs['group']) {
                'location' => $a['location_name'],
                'status' => t('status.' . $a['status']),
                'department' => $a['department_name'],
                'criticality' => t('criticality.' . (int) $a['criticality']),
                default => $a['category_name'],
            } ?? t('grid.no_group');
            $groups[$label][] = $a;
        }
        uasort($groups, fn ($x, $y) => count($y) <=> count($x)); ?>
        <?php if ($total > count($assets)): ?><p class="small muted"><?= e(t('grid.group_limit', ['n' => count($assets), 'total' => $total])) ?></p><?php endif; ?>
        <?php foreach ($groups as $label => $rows):
            ?>
            <details class="card grid-group" open>
                <summary>
                    <span class="grid-group-title"><?= icon('chevron', 'icon icon-xs chev') ?> <strong dir="auto"><?= e((string) $label) ?></strong> <span class="count"><?= count($rows) ?></span></span>
                </summary>
                <?= $table($rows, false) ?>
            </details>
        <?php endforeach; ?>

    <?php else: ?>
        <?= partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'path' => 'assets', 'query' => $filters, 'place' => 'top']) ?>
        <div class="card table-card grid-card"><?= $table($assets, true) ?></div>
    <?php endif; ?>
</form>

<?php if ($prefs['view'] !== 'groups'): ?>
    <?= partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'path' => 'assets', 'query' => $filters]) ?>
<?php endif; ?>
