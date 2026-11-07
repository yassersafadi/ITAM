<?php page_title(t('portal.nav.documents')); ?>
<div class="portal-head">
    <div><h1><?= e(t('portal.nav.documents')) ?></h1><p class="muted"><?= e(t('portal.docs_sub')) ?></p></div>
</div>

<?php if ($pending): ?>
    <section class="card portal-attn">
        <h3 class="card-title"><?= icon('edit') ?> <?= e(t('portal.to_sign')) ?></h3>
        <ul class="list">
            <?php foreach ($pending as $p): ?>
                <li><span class="list-main"><span class="list-icon"><?= icon($p['type'] === 'return' ? 'box' : 'user') ?></span>
                    <span><strong class="mono"><?= e($p['receipt_no']) ?></strong><small><?= e(t('custody.type.' . $p['type'])) ?> · <?= fmt_date($p['created_at']) ?></small></span></span>
                    <a class="btn btn-primary btn-sm" href="<?= url('sign/' . $p['sign_token']) ?>"><?= icon('edit') ?> <?= e(t('custody.sign_now')) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card" style="margin-top:16px">
    <h3 class="card-title"><?= icon('shield') ?> <?= e(t('portal.signed_receipts')) ?> <span class="count"><?= count($signed) ?></span></h3>
    <?php if (!$signed): ?><p class="muted small"><?= e(t('portal.no_receipts')) ?></p><?php else: ?>
        <ul class="list">
            <?php foreach ($signed as $s): ?>
                <li><span class="list-main"><span class="list-icon"><?= icon($s['type'] === 'return' ? 'box' : 'user') ?></span>
                    <span><strong class="mono"><?= e($s['receipt_no']) ?></strong><small><?= e(t('custody.type.' . $s['type'])) ?> · <?= fmt_date($s['signed_at']) ?></small></span></span>
                    <span class="inline-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= url('portal/receipts/' . $s['id']) ?>"><?= icon('eye') ?> <span class="hide-sm"><?= e(t('portal.view')) ?></span></a>
                        <a class="btn btn-ghost btn-sm" href="<?= url('portal/receipts/' . $s['id'] . '/pdf') ?>"><?= icon('download') ?> PDF</a>
                    </span></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($licenses): ?>
    <section class="card" style="margin-top:16px">
        <h3 class="card-title"><?= icon('key') ?> <?= e(t('portal.my_software')) ?></h3>
        <ul class="list compact">
            <?php foreach ($licenses as $l): ?><li><span class="list-main"><span><strong dir="auto"><?= e($l['name']) ?></strong><?php if ($l['edition']): ?><small dir="auto"><?= e($l['edition']) ?></small><?php endif; ?></span></span></li><?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
