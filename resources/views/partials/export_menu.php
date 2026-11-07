<?php
use ITAM\Services\Exporter;

/** Export button with a choice of file type. Expects $base (URL), $query (array); $keep = follow live filter changes. */
$icons = ['csv' => 'download', 'xlsx' => 'chart', 'pdf' => 'printer', 'json' => 'command'];
?>
<div class="dropdown export-menu">
    <button type="button" class="btn btn-ghost" data-dropdown aria-haspopup="menu"><?= icon('download') ?> <span class="hide-sm"><?= e(t('export.button')) ?></span> <?= icon('chevron', 'icon icon-xs') ?></button>
    <div class="dropdown-menu end" role="menu">
        <?php foreach (Exporter::FORMATS as $f): ?>
            <a role="menuitem" href="<?= e($base . '?' . http_build_query($query + ['format' => $f])) ?>"<?= !empty($keep) ? ' data-keep-query' : '' ?>>
                <?= icon($icons[$f]) ?><span><strong><?= e(t('export.f.' . $f)) ?></strong><small class="muted"><?= e(t('export.h.' . $f)) ?></small></span></a>
        <?php endforeach; ?>
    </div>
</div>
