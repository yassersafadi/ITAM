<?php page_title(t('common.edit') . ' — ' . $row['name']); ?>
<div class="page-head">
    <div>
        <a href="<?= url('directory/' . $type) ?>" class="back-link">‹ <?= e(t('nav.' . $type)) ?></a>
        <h1><?= e($row['name']) ?></h1>
    </div>
</div>
<section class="card narrow">
    <form method="post" action="<?= url('directory/' . $type . '/' . $row['id']) ?>" class="stack">
        <?= csrf_field() ?>
        <?= partial('directory/fields', ['schema' => $schema, 'row' => $row, 'refs' => $refs, 'icons' => $icons]) ?>
        <div class="modal-actions">
            <button type="submit" class="btn btn-danger" formaction="<?= url('directory/' . $type . '/' . $row['id'] . '/delete') ?>" data-confirm-click="<?= e(t($type === 'employees' ? 'directory.confirm_delete_employee' : 'common.confirm_delete')) ?>"><?= icon('trash') ?> <?= e(t('common.delete')) ?></button>
            <span class="grow"></span>
            <a class="btn btn-ghost" href="<?= url('directory/' . $type) ?>"><?= e(t('common.cancel')) ?></a>
            <button class="btn btn-primary"><?= e(t('common.save')) ?></button>
        </div>
    </form>
</section>

<?php if ($type === 'categories'): $cfs = \ITAM\Services\CustomFields::forCategory((int) $row['id']); ?>
<section class="card narrow" id="fields" style="margin-top:18px">
    <h3 class="card-title"><?= icon('sliders') ?> <?= e(t('cf.section')) ?></h3>
    <p class="small muted"><?= e(t('cf.help')) ?></p>
    <ul class="list">
        <?php foreach ($cfs as $cf): ?>
            <li><span class="list-main"><span class="list-icon"><?= icon('sliders') ?></span><span><strong><?= e($cf['label']) ?><?= $cf['required'] ? ' *' : '' ?></strong>
                <small><?= e(t('cf.type.' . $cf['type'])) ?><?= $cf['choices'] ? ' · ' . e(implode(', ', $cf['choices'])) : '' ?></small></span></span>
                <?php if (can('catalog.edit')): ?><form method="post" action="<?= url('fields/' . $cf['id'] . '/delete') ?>" data-confirm="<?= e(t('cf.confirm_delete')) ?>"><?= csrf_field() ?><button class="icon-btn sm" aria-label="<?= e(t('common.delete')) ?>"><?= icon('trash') ?></button></form><?php endif; ?></li>
        <?php endforeach; ?>
        <?php if (!$cfs): ?><li class="muted"><?= e(t('common.nothing')) ?></li><?php endif; ?>
    </ul>
    <?php if (can('catalog.edit')): ?>
        <form method="post" action="<?= url('categories/' . $row['id'] . '/fields') ?>" class="stack" style="margin-top:12px">
            <?= csrf_field() ?>
            <div class="fields">
                <label class="field"><span><?= e(t('cf.label')) ?> *</span><input name="label" required maxlength="80" placeholder="<?= e(t('cf.label_ph')) ?>"></label>
                <label class="field"><span><?= e(t('field.type')) ?></span><select name="type"><?= enum_options(\ITAM\Services\CustomFields::TYPES, 'text', 'cf.type') ?></select></label>
                <label class="field span-2"><span><?= e(t('cf.options')) ?></span><input name="options" maxlength="1000" placeholder="STC, Mobily, Zain"></label>
                <label class="check span-2"><input type="checkbox" name="required" value="1"> <?= e(t('cf.required')) ?></label>
            </div>
            <div class="modal-actions"><span class="grow"></span><button class="btn btn-primary btn-sm"><?= icon('plus') ?> <?= e(t('cf.add')) ?></button></div>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>