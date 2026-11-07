<?php page_title(t('assets.import')); ?>
<div class="page-head">
    <div>
        <a href="<?= url('assets') ?>" class="back-link">‹ <?= e(t('nav.assets')) ?></a>
        <h1><?= e(t('assets.import')) ?></h1>
        <p class="muted"><?= e(t('import.subtitle')) ?></p>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <form method="post" action="<?= url('assets/import') ?>" enctype="multipart/form-data" class="stack">
            <?= csrf_field() ?>
            <label class="dropzone" data-dropzone>
                <?= icon('upload', 'icon icon-lg') ?>
                <strong><?= e(t('import.drop')) ?></strong>
                <span class="muted small" data-filename><?= e(t('import.formats')) ?></span>
                <input type="file" name="csv" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </label>
            <button class="btn btn-primary" type="submit"><?= icon('upload') ?> <?= e(t('import.run')) ?></button>
        </form>
        <?php if ($result): ?>
            <div class="import-result">
                <div class="mini-stats">
                    <div><strong class="text-success"><?= (int) $result['created'] ?></strong><span><?= e(t('import.created')) ?></span></div>
                    <div><strong class="text-info"><?= (int) $result['updated'] ?></strong><span><?= e(t('import.updated')) ?></span></div>
                    <div><strong class="text-warning"><?= (int) $result['skipped'] ?></strong><span><?= e(t('import.skipped')) ?></span></div>
                </div>
                <?php foreach (array_slice($result['errors'], 0, 20) as $err): ?><p class="small text-danger"><?= e($err) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="card">
        <h3 class="card-title"><?= icon('info') ?> <?= e(t('import.how')) ?></h3>
        <ol class="steps">
            <li><?= e(t('import.step1')) ?> <a class="link" href="<?= url('assets/import/template', ['format' => 'xlsx']) ?>">Excel (.xlsx)</a> · <a class="link" href="<?= url('assets/import/template') ?>">CSV</a></li>
            <li><?= e(t('import.step2')) ?></li>
            <li><?= e(t('import.step3')) ?></li>
            <li><?= e(t('import.step4')) ?></li>
        </ol>
        <p class="muted small"><?= e(t('import.columns')) ?></p>
        <code class="code-block">tag, name, category, status, condition_state, criticality, manufacturer, model, serial, hostname, ip_address, mac_address, os, cpu, ram_gb, storage_gb, location, department, assigned_to, acquired_at, warranty_until, value, po_number, notes</code>
    </section>
</div>
