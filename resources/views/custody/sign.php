<?php
page_title(t('custody.sign_title') . ' · ' . $r['receipt_no']);
$action = $remote ? url('sign/' . $token) : url('custody/' . $r['id'] . '/sign');
?>
<div class="<?= $remote ? 'public-page' : '' ?>">
    <?php if ($remote): ?>
        <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></div>
    <?php else: ?>
        <div class="page-head">
            <div>
                <?php if ($r['employee_id']): ?><a href="<?= url('employees/' . $r['employee_id']) ?>" class="back-link">‹ <?= e($r['employee_name']) ?></a><?php endif; ?>
                <h1><?= icon('edit', 'icon icon-title') ?> <?= e(t('custody.sign_title')) ?> <small class="mono muted"><?= e($r['receipt_no']) ?></small></h1>
                <p class="muted"><?= e(t('custody.sign_sub_' . $r['type'], ['name' => $r['employee_name']])) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $action ?>" class="card custody-sign<?= $remote ? ' public-card wide' : '' ?>" data-sign-form>
        <?= csrf_field() ?>
        <input type="hidden" name="seen" value="<?= e(\ITAM\Services\Custody::contentKey($r)) ?>">
        <?php foreach (take_flashes() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= icon('alert') ?><span><?= e($f['message']) ?></span></div>
        <?php endforeach; ?>
        <h2 class="custody-title"><?= e(t('custody.title_' . $r['type'])) ?></h2>
        <?= partial('custody/doc', ['r' => $r]) ?>

        <div class="sign-grid">
            <div class="sign-box" data-signature="required">
                <div class="sign-head"><strong><?= e(t('custody.employee_signature')) ?> *</strong>
                    <button type="button" class="btn btn-ghost btn-sm" data-signature-clear><?= icon('refresh') ?> <?= e(t('custody.clear')) ?></button></div>
                <canvas class="sign-canvas" aria-label="<?= e(t('custody.employee_signature')) ?>"></canvas>
                <input type="hidden" name="employee_signature">
                <span class="sign-hint"><?= e(t('custody.sign_here')) ?></span>
                <label class="field"><span><?= e(t('custody.signer_name')) ?></span><input name="signer_name" value="<?= e($r['employee_name']) ?>" maxlength="120" required></label>
            </div>
        </div>
        <p class="small muted custody-delivered-note">
            <?= icon('check') ?> <span><b><?= e(t('custody.delivered_by')) ?>:</b> <span dir="auto"><?= e($r['staff_name'] ?? '—') ?></span> · <?= e(t('custody.delivered_note')) ?></span></p>

        <label class="check"><input type="checkbox" required> <?= e(t('custody.agree')) ?></label>
        <div class="form-actions">
            <?php if (!$remote): ?><a class="btn btn-ghost" href="<?= url('custody/' . $r['id']) ?>"><?= e(t('custody.later')) ?></a><?php endif; ?>
            <button class="btn btn-primary"><?= icon('check') ?> <?= e(t('custody.confirm')) ?></button>
        </div>
    </form>

    <?php if ($link): ?>
        <section class="card remote-sign">
            <div class="cert-qr" data-qr="<?= e($link) ?>"></div>
            <div>
                <h3><?= icon('phone') ?> <?= e(t('custody.remote_title')) ?></h3>
                <p class="small muted"><?= e(t('custody.remote_help', ['date' => fmt_date($r['token_expires'])])) ?></p>
                <code class="code-block copyable" data-copy dir="ltr"><?= e($link) ?></code>
            </div>
        </section>
        <script src="<?= asset('vendor/qrcode.js') ?>"></script>
    <?php elseif (!$remote): ?>
        <section class="card remote-sign">
            <div>
                <h3><?= icon('phone') ?> <?= e(t('custody.remote_title')) ?></h3>
                <p class="small muted"><?= e(t('custody.link_expired')) ?></p>
                <form method="post" action="<?= url('custody/' . $r['id'] . '/link') ?>"><?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm"><?= icon('refresh') ?> <?= e(t('custody.new_link')) ?></button></form>
            </div>
        </section>
    <?php endif; ?>
</div>
