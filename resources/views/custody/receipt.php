<?php
page_title($r['receipt_no']);
$signed = $r['status'] === 'signed';
// The employee portal and the PDF file show the same receipt; they supply their own signature and logo sources.
$portal ??= false;
$forPdf ??= false;
$sigUrl ??= fn (string $who) => url('custody/' . $r['id'] . '/signature/' . $who);
?>
<?php if ($portal): ?>
<div class="print-toolbar no-print">
    <a class="btn btn-ghost" href="<?= url('portal/documents') ?>">‹ <?= e(t('portal.nav.documents')) ?></a>
    <strong><?= e($r['receipt_no']) ?></strong>
    <?php if ($pdf ?? false): ?><a class="btn btn-primary" href="<?= url('portal/receipts/' . $r['id'] . '/pdf') ?>"><?= icon('download') ?> <?= e(t('portal.download_pdf')) ?></a>
    <?php else: ?><button class="btn btn-primary" onclick="window.print()" title="<?= e(t('portal.save_pdf_hint')) ?>"><?= icon('download') ?> <?= e(t('portal.download_pdf')) ?></button><?php endif; ?>
</div>
<?php elseif (!$forPdf): ?>
<div class="print-toolbar no-print">
    <?php if ($r['employee_id']): ?><a class="btn btn-ghost" href="<?= url('employees/' . $r['employee_id']) ?>">‹ <?= e($r['employee_name']) ?></a><?php endif; ?>
    <strong><?= e($r['receipt_no']) ?></strong>
    <?php if (!$signed && can('assets.assign')): ?>
        <a class="btn btn-ghost" href="<?= url('custody/' . $r['id'] . '/sign') ?>"><?= icon('edit') ?> <?= e(t('custody.sign_now')) ?></a>
        <form method="post" action="<?= url('custody/' . $r['id'] . '/link') ?>"><?= csrf_field() ?>
            <button class="btn btn-ghost"><?= icon('phone') ?> <?= e(t('custody.new_link')) ?></button></form>
    <?php endif; ?>
    <button class="btn btn-primary" onclick="window.print()"><?= icon('printer') ?> <?= e(t('labels.print')) ?></button>
</div>
<?php endif; ?>
<article class="certificate">
    <header class="cert-head">
        <img src="<?= e($logo ?? brand_asset('logo_full')) ?>" alt="<?= e(vendor_name()) ?>" class="cert-logo">
        <div>
            <h1><?= e(t('custody.title_' . $r['type'])) ?></h1>
            <p><?= e(app_name()) ?> · <?= e(vendor_name()) ?></p>
        </div>
        <div class="cert-no"><small><?= e(t('custody.receipt')) ?></small><strong class="mono"><?= e($r['receipt_no']) ?></strong></div>
    </header>

    <?php if (!$signed): ?><div class="cert-banner bad"><?= icon('clock') ?> <?= e(t('custody.awaiting')) ?></div><?php endif; ?>

    <?= partial('custody/doc', ['r' => $r, 'removable' => ($removable ?? false) && !$forPdf && !$portal]) ?>

    <div class="cert-signs custody-signs">
        <div>
            <span><?= e(t('custody.employee_signature')) ?></span>
            <div class="sig-area"><?php if ($r['employee_signature']): ?><img src="<?= e($sigUrl('employee')) ?>" alt="" class="sig-img"><?php endif; ?></div>
            <strong dir="auto"><?= e($r['signer_name'] ?? $r['employee_name']) ?></strong>
            <small class="muted"><?= $r['signed_at'] ? fmt_date($r['signed_at'], true) . ' · ' . e(t('custody.via.' . $r['signed_via'])) : '&nbsp;' ?></small>
        </div>
        <div class="custody-delivered">
            <span><?= e(t('custody.delivered_by')) ?></span>
            <div class="sig-area"><?php if ($r['staff_signature']): ?><img src="<?= e($sigUrl('staff')) ?>" alt="" class="sig-img"><?php else: ?><span class="delivered-mark"><?= icon('check') ?></span><?php endif; ?></div>
            <strong dir="auto"><?= e($r['staff_name'] ?? '—') ?></strong>
            <small class="muted"><?= fmt_date($r['created_at'], true) ?></small>
        </div>
    </div>

    <footer class="cert-foot">
        <?php if ($verifyUrl !== ''): ?><div class="cert-qr" data-qr="<?= e($verifyUrl) ?>"></div><?php endif; ?>
        <div>
            <?php if ($signed): ?>
                <p class="cert-integrity <?= $intact ? 'ok' : 'bad' ?>"><?= icon($intact ? 'shield' : 'alert') ?> <?= e($intact ? t('custody.intact') : t('custody.tampered')) ?></p>
                <p class="small"><?= e(t('custody.fingerprint')) ?></p>
                <code class="mono cert-hash"><?= e($r['doc_hash']) ?></code>
            <?php else: ?>
                <p class="small muted"><?= e(t('custody.awaiting_help')) ?></p>
                <?php if ($link): ?><code class="mono cert-hash no-print"><?= e($link) ?></code><?php endif; ?>
            <?php endif; ?>
        </div>
    </footer>
</article>
