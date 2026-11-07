<?php
page_title(t('nav.' . $type));
$refName = function (string $field, $id) use ($refs) {
    $list = $refs[match ($field) { 'department_id' => 'departments', 'manufacturer_id' => 'manufacturers', 'category_id' => 'categories', default => 'locations' }];
    foreach ($list as $r) {
        if ((string) $r['id'] === (string) $id) return $r['name'];
    }
    return '—';
};
// Organisation structure: units that have sub-units get an arrow to fold and unfold them.
$hasChildren = $type === 'departments' ? array_count_values(array_filter(array_map(fn ($r) => $r['parent_id'] ?? null, $rows), fn ($p) => $p !== null)) : [];
?>
<div class="page-head">
    <div>
        <h1><?= icon($schema['icon'], 'icon icon-title') ?> <?= e(t('nav.' . $type)) ?></h1>
        <p class="muted"><?= e(t('directory.subtitle.' . $type)) ?></p>
    </div>
    <div class="page-actions">
        <?php if ($type === 'models'): ?>
            <form method="get" class="inline-form" style="margin:0"><select name="manufacturer" onchange="this.form.submit()" aria-label="<?= e(t('field.manufacturer_id')) ?>">
                <option value=""><?= e(t('mfr.all_manufacturers')) ?></option>
                <?php foreach ($refs['manufacturers'] as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === $only ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
            </select></form>
        <?php endif; ?>
        <?php if ($type === 'departments' && $hasChildren): ?>
            <button type="button" class="btn btn-ghost" data-org-all="open"><?= icon('plus') ?> <?= e(t('org.expand_all')) ?></button>
            <button type="button" class="btn btn-ghost" data-org-all="close"><?= icon('chevron') ?> <?= e(t('org.collapse_all')) ?></button>
        <?php endif; ?>
        <input type="search" class="table-filter" placeholder="<?= e(t('common.filter')) ?>…" data-table-filter="#dir-table">
        <?php if (can('catalog.edit')): ?>
            <button class="btn btn-primary" data-modal-open="new-entry"><?= icon('plus') ?> <?= e(t('common.add')) ?></button>
        <?php endif; ?>
    </div>
</div>

<section class="card table-card">
    <div class="table-wrap">
        <table class="table<?= $type === 'departments' ? ' org-table' : '' ?>" id="dir-table"<?= $type === 'departments' ? ' data-org-tree' : '' ?>>
            <thead><tr>
                <?php foreach ($schema['columns'] as $c): ?><th><?= $c === 'icon' ? '' : e(t('field.' . $c)) ?></th><?php endforeach; ?>
                <th><?= e(t('nav.assets')) ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr<?= $type === 'employees' ? ' data-href="' . url('employees/' . $r['id']) . '"' : '' ?><?= $type === 'departments' ? ' data-org="' . (int) $r['id'] . '" data-org-parent="' . (int) ($r['parent_id'] ?? 0) . '" data-org-depth="' . (int) $r['depth'] . '"' : '' ?>>
                    <?php foreach ($schema['columns'] as $c): ?>
                        <td>
                            <?php if ($c === 'icon'): ?><span class="cell-icon"><?= icon($r['icon']) ?></span>
                            <?php elseif (in_array($c, ['department_id', 'location_id', 'manufacturer_id', 'category_id'], true)): ?><?= e($refName($c, $r[$c])) ?>
                            <?php elseif ($c === 'name' && $type === 'employees'): ?><a href="<?= url('employees/' . $r['id']) ?>"><strong><?= e($r['name']) ?></strong></a><?= !$r['active'] ? ' <span class="badge badge-neutral">' . e(t('common.inactive')) . '</span>' : '' ?>
                            <?php elseif ($c === 'name' && $type === 'departments'): ?><span class="org-name" style="--depth: <?= (int) $r['depth'] ?>"><?php if (!empty($hasChildren[$r['id']])): ?><button type="button" class="org-toggle" data-org-toggle aria-expanded="true" aria-label="<?= e(t('org.toggle', ['name' => $r['name'], 'n' => $hasChildren[$r['id']]])) ?>"><?= icon('chevron', 'icon icon-xs') ?></button><?php elseif ($r['depth']): ?><span class="org-branch" aria-hidden="true">└</span><?php endif; ?><strong dir="auto"><?= e($r['name']) ?></strong><?php if (!empty($hasChildren[$r['id']])): ?> <small class="muted org-count"><?= e(t('org.n_units', ['n' => $hasChildren[$r['id']]])) ?></small><?php endif; ?></span>
                            <?php elseif ($c === 'level'): ?><span class="badge org-level org-<?= e($r['level']) ?>"><?= e(t('org.level.' . $r['level'])) ?></span>
                            <?php elseif ($c === 'name'): ?><strong><?= e($r['name']) ?></strong>
                            <?php elseif ($c === 'model_count'): ?><a href="<?= url('directory/models', ['manufacturer' => $r['id']]) ?>"><?= (int) $r[$c] ?></a>
                            <?php elseif ($c === 'website' && $r[$c]): ?><a href="<?= e(preg_match('~^https?://~i', $r[$c]) ? $r[$c] : 'https://' . $r[$c]) ?>" target="_blank" rel="noopener" dir="ltr"><?= e(preg_replace('~^https?://~i', '', $r[$c])) ?></a>
                            <?php elseif ($c === 'support_phone' && $r[$c]): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $r[$c])) ?>" dir="ltr"><?= e($r[$c]) ?></a>
                            <?php elseif ($c === 'lifespan_months'): ?><?= (int) $r[$c] ?> <?= e(t('common.months')) ?>
                            <?php elseif ($c === 'power_watts'): ?><?= (int) $r[$c] ?> W
                            <?php elseif ($c === 'maintenance_interval_days'): ?><?= $r[$c] ? (int) $r[$c] . ' ' . e(t('common.days')) : '—' ?>
                            <?php else: ?><?= e($r[$c] ?? '—') ?><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td><a class="badge badge-info" href="<?= url('assets', match ($type) {
                        'manufacturers' => ['manufacturer' => $r['name']],
                        'models' => ['manufacturer' => $r['manufacturer_name'], 'q' => $r['name']],
                        default => [match ($type) { 'employees' => 'assigned', 'locations' => 'location', 'departments' => 'department', 'categories' => 'category' } => $r['id']],
                    }) ?>"><?= (int) ($r['total_count'] ?? $r['asset_count']) ?></a><?php if (isset($r['total_count']) && $r['total_count'] !== (int) $r['asset_count']): ?> <small class="muted" title="<?= e(t('org.own_count')) ?>">(<?= (int) $r['asset_count'] ?>)</small><?php endif; ?></td>
                    <td class="actions">
                        <?php if (can('catalog.edit')): ?>
                            <a class="icon-btn sm" href="<?= url('directory/' . $type . '/' . $r['id'] . '/edit') ?>" aria-label="<?= e(t('common.edit')) ?>"><?= icon('edit') ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="<?= count($schema['columns']) + 2 ?>" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('catalog.edit')): ?>
<dialog class="modal" id="modal-new-entry">
    <form method="post" action="<?= url('directory/' . $type) ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon($schema['icon']) ?> <?= e(t('common.add')) ?> — <?= e(t('nav.' . $type)) ?></h3>
        <?= partial('directory/fields', ['schema' => $schema, 'row' => ['active' => 1, 'lifespan_months' => 48, 'hours_per_day' => 8, 'icon' => 'box', 'manufacturer_id' => $only ?: ''], 'refs' => $refs, 'icons' => $icons]) ?>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.create')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
