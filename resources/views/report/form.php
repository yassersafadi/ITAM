<?php
use ITAM\Core\Lang;

page_title(t('report.title'));
?>
<div class="public-page">
    <div class="login-langs">
        <?php foreach (config('i18n.locales') as $code => $l): ?>
            <a href="<?= url('lang/' . $code) ?>" class="<?= $code === Lang::locale() ? 'active' : '' ?>"><?= e($l['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <form class="login-card public-card" method="post" action="<?= url('report/' . rawurlencode($asset['tag'])) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="public-brand"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></div>
        <h2><?= e(t('report.title')) ?></h2>
        <div class="report-asset"><span class="cell-icon"><?= icon($asset['icon'] ?? 'box') ?></span>
            <span><strong dir="auto"><?= e($asset['name']) ?></strong><small class="mono"><?= e($asset['tag']) ?><?= $asset['location_name'] ? ' · ' . e($asset['location_name']) : '' ?></small></span></div>
        <?php foreach (take_flashes() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= icon('alert') ?><span><?= e($f['message']) ?></span></div>
        <?php endforeach; ?>
        <?php if (!$enabled): ?>
            <p class="muted"><?= e(t('report.disabled')) ?></p>
        <?php else: ?>
            <label class="field"><span><?= e(t('report.urgency')) ?></span>
                <div class="radio-cards">
                    <?php foreach (['blocked', 'degraded', 'minor'] as $u): ?>
                        <label class="radio-card"><input type="radio" name="urgency" value="<?= $u ?>" <?= old('urgency', 'degraded') === $u ? 'checked' : '' ?>><strong><?= e(t('report.urgency.' . $u)) ?></strong></label>
                    <?php endforeach; ?>
                </div>
            </label>
            <label class="field"><span><?= e(t('report.what')) ?> *</span><textarea name="description" rows="4" maxlength="2000" required placeholder="<?= e(t('report.what_ph')) ?>"><?= e(old('description')) ?></textarea></label>
            <label class="field"><span><?= e(t('report.photo')) ?></span><input type="file" name="photo" accept="image/png,image/jpeg,image/webp" capture="environment"></label>
            <label class="field"><span><?= e(t('report.your_name')) ?> *</span><input name="reporter_name" value="<?= e(old('reporter_name')) ?>" maxlength="120" required autocomplete="name"></label>
            <label class="field"><span><?= e(t('report.contact')) ?> *</span><input name="reporter_contact" value="<?= e(old('reporter_contact')) ?>" maxlength="160" required placeholder="<?= e(t('report.contact_ph')) ?>" dir="ltr"></label>
            <label class="hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
            <button class="btn btn-primary btn-block" type="submit"><?= icon('wrench') ?> <?= e(t('report.submit')) ?></button>
            <p class="hint"><?= icon('info') ?> <?= e(t('report.staff_hint')) ?> <a href="<?= url('login') ?>"><?= e(t('login.submit')) ?></a></p>
        <?php endif; ?>
    </form>
</div>
<?php unset($_SESSION['_old']); ?>
