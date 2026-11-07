<?php
page_title(t('nav.assets'));
$vars = compact('filters', 'prefs', 'total', 'page', 'pages', 'assets', 'summary', 'facets', 'presets', 'savedViews', 'lookups', 'customFields');
?>
<div class="page-head">
    <div>
        <h1><?= e(t('nav.assets')) ?></h1>
        <p class="muted" data-assets-count><?= e(t('assets.count', ['n' => fmt_num($total)])) ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('assets.edit')): ?>
            <a class="btn btn-ghost" href="<?= url('assets/import') ?>"><?= icon('upload') ?> <span class="hide-sm"><?= e(t('assets.import')) ?></span></a>
        <?php endif; ?>
        <?php if (can('reports.export')): ?>
            <?= partial('partials/export_menu', ['base' => url('assets/export'), 'query' => $filters, 'keep' => true]) ?>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= url('assets/labels', $filters) ?>" target="_blank" data-keep-query><?= icon('qr') ?> <span class="hide-sm"><?= e(t('assets.labels')) ?></span></a>
        <?php if (can('assets.edit')): ?>
            <a class="btn btn-primary" href="<?= url('assets/create') ?>"><?= icon('plus') ?> <?= e(t('assets.new')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="grid-shell" data-assets>
    <div data-part="toolbar"><?= partial('assets/_toolbar', $vars) ?></div>
    <div data-part="results" aria-live="polite"><?= partial('assets/_results', $vars) ?></div>

    <div class="drawer-backdrop" data-drawer-close hidden></div>
    <aside class="drawer" id="filters-drawer" data-drawer="filters" aria-label="<?= e(t('grid.filters')) ?>" hidden>
        <div data-part="drawer"><?= partial('assets/_drawer', $vars) ?></div>
    </aside>
    <aside class="drawer drawer-preview" data-drawer="preview" aria-label="<?= e(t('grid.preview')) ?>" hidden>
        <div data-preview-body></div>
    </aside>
</div>

<dialog class="modal" id="modal-save-view">
    <form method="post" action="<?= url('assets/views') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('bookmark') ?> <?= e(t('grid.save_view')) ?></h3>
        <p class="muted small"><?= e(t('grid.save_view_help')) ?></p>
        <input type="hidden" name="query" value="" data-current-query>
        <label class="field"><span><?= e(t('field.name')) ?></span><input name="name" required maxlength="40" placeholder="<?= e(t('grid.view_name_ph')) ?>"></label>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button>
            <button class="btn btn-primary"><?= e(t('common.save')) ?></button>
        </div>
    </form>
</dialog>
