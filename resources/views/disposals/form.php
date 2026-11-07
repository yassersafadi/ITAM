<?php
use ITAM\Controllers\DisposalController as D;

page_title(t('disposal.title') . ' · ' . $asset['tag']);
$a = $asset;
$c = $checks;
$sel = fn (string $name, string $default) => (string) old($name, $default);
?>
<div class="page-head">
    <div>
        <a href="<?= url('assets/' . $a['id']) ?>" class="back-link">‹ <?= e($a['tag']) ?></a>
        <h1><?= icon('trash', 'icon icon-title') ?> <?= e(t('disposal.title')) ?></h1>
        <p class="muted"><?= e(t('disposal.subtitle')) ?></p>
    </div>
</div>

<div class="grid grid-3">
    <section class="card">
        <h3 class="card-title"><?= icon('box') ?> <?= e($a['name']) ?></h3>
        <dl class="specs">
            <div class="spec"><dt><?= e(t('field.tag')) ?></dt><dd class="mono"><?= e($a['tag']) ?></dd></div>
            <div class="spec"><dt><?= e(t('field.serial')) ?></dt><dd class="mono"><?= e($a['serial'] ?: '—') ?></dd></div>
            <div class="spec"><dt><?= e(t('field.category_id')) ?></dt><dd><?= e($a['category_name'] ?: '—') ?></dd></div>
            <div class="spec"><dt><?= e(t('field.status')) ?></dt><dd><?= status_badge($a['status']) ?></dd></div>
        </dl>

        <h4 class="checks-title"><?= e(t('disposal.checks')) ?></h4>
        <ul class="checklist">
            <li class="<?= $c['assigned'] ? 'warn' : 'ok' ?>"><?= icon($c['assigned'] ? 'user' : 'check') ?>
                <span><?= e($c['assigned'] ? t('disposal.chk_assigned', ['name' => $c['assigned']]) : t('disposal.chk_unassigned')) ?></span></li>
            <li class="<?= $c['open_work'] ? 'warn' : 'ok' ?>"><?= icon($c['open_work'] ? 'wrench' : 'check') ?>
                <span><?= e($c['open_work'] ? t('disposal.chk_work', ['n' => $c['open_work']]) : t('disposal.chk_no_work')) ?></span></li>
            <li class="<?= $c['seats'] ? 'warn' : 'ok' ?>"><?= icon($c['seats'] ? 'key' : 'check') ?>
                <span><?= e($c['seats'] ? t('disposal.chk_seats', ['n' => $c['seats']]) : t('disposal.chk_no_seats')) ?></span></li>
        </ul>
    </section>

    <form method="post" action="<?= url('assets/' . $a['id'] . '/dispose') ?>" class="card span-2">
        <?= csrf_field() ?>
        <div class="fields">
            <label class="field"><span><?= e(t('disposal.reason')) ?> *</span><select name="reason" required><?= enum_options(D::REASONS, $sel('reason', 'end_of_life'), 'disposal.reason') ?></select></label>
            <label class="field"><span><?= e(t('disposal.method')) ?> *</span><select name="method" required><?= enum_options(D::METHODS, $sel('method', 'recycle'), 'disposal.method') ?></select></label>
            <label class="field"><span><?= e(t('disposal.recipient')) ?></span><input name="recipient" value="<?= e(old('recipient')) ?>" maxlength="160" placeholder="<?= e(t('disposal.recipient_ph')) ?>"></label>
            <label class="field"><span><?= e(t('disposal.date')) ?> *</span><input type="date" name="disposed_at" value="<?= e(old('disposed_at', today())) ?>" max="<?= today() ?>" required></label>
            <label class="field span-2"><span><?= e(t('field.notes')) ?></span><textarea name="notes" rows="2"><?= e(old('notes')) ?></textarea></label>
            <div class="callout callout-warning span-2"><?= icon('alert') ?><div class="small"><?= e(t('disposal.effects')) ?></div></div>
            <div class="form-actions">
                <a class="btn btn-ghost" href="<?= url('assets/' . $a['id']) ?>"><?= e(t('common.cancel')) ?></a>
                <button class="btn btn-danger"><?= icon('trash') ?> <?= e(t('disposal.confirm')) ?></button>
            </div>
        </div>
    </form>
</div>
<?php unset($_SESSION['_old']); ?>
