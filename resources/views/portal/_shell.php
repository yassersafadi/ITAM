<?php
/**
 * The portal's app shell: a slim top bar, the page, and a navigation bar that sits at the bottom of the screen on phones
 * and under the top bar on wider screens. The dialogs used from several pages live here too.
 */
use ITAM\Core\Lang;

$locale = Lang::locale();
$active = ['device' => 'devices'][$page] ?? $page;
$nav = [
    'home' => ['portal', 'dashboard'],
    'devices' => ['portal/devices', 'laptop'],
    'documents' => ['portal/documents', 'shield'],
];
?>
<div class="portal portal-app">
    <header class="portal-bar">
        <a class="public-brand" href="<?= url('portal') ?>"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></a>
        <div class="portal-user">
            <div class="dropdown">
                <button class="icon-btn" data-dropdown aria-label="<?= e(t('portal.language')) ?>"><?= icon('globe') ?><span class="hide-sm"><?= strtoupper($locale) ?></span></button>
                <div class="dropdown-menu end">
                    <?php foreach (config('i18n.locales') as $code => $l): ?><a href="<?= url('lang/' . $code) ?>" class="<?= $code === $locale ? 'active' : '' ?>"><?= e($l['label']) ?></a><?php endforeach; ?>
                </div>
            </div>
            <div class="dropdown">
                <button class="user-chip" data-dropdown>
                    <span class="avatar"><?= e(mb_strtoupper(mb_substr($emp['name'], 0, 1))) ?></span>
                    <span class="hide-sm"><bdi><?= e($emp['name']) ?></bdi><small><?= e($emp['department_name'] ?? '') ?></small></span>
                </button>
                <div class="dropdown-menu end">
                    <span class="dropdown-head"><strong dir="auto"><?= e($emp['name']) ?></strong><small class="muted" dir="auto"><?= e($emp['email'] ?? '') ?></small></span>
                    <form method="post" action="<?= url('portal/logout') ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout') ?> <?= e(t('nav.logout')) ?></button></form>
                </div>
            </div>
        </div>
    </header>

    <nav class="portal-nav" aria-label="<?= e(t('portal.title')) ?>">
        <?php foreach ($nav as $key => [$href, $ic]): ?>
            <a href="<?= url($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
                <span class="portal-nav-icon"><?= icon($ic) ?><?php if ($key === 'documents' && $toSign): ?><span class="dot-count"><?= (int) $toSign ?></span><?php endif; ?></span>
                <span><?= e(t('portal.nav.' . $key)) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <main class="portal-main">
        <div class="toasts" aria-live="polite">
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= icon($f['type'] === 'success' ? 'check' : 'alert') ?><span><?= e($f['message']) ?></span>
                    <button class="flash-close" data-action="dismiss" aria-label="close"><?= icon('x') ?></button></div>
            <?php endforeach; ?>
        </div>
        <?= $content ?>
    </main>
</div>

<dialog class="modal" id="modal-report">
    <form method="post" action="<?= url('portal/report') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('wrench') ?> <?= e(t('portal.report_problem')) ?></h3>
        <?php if ($assets): ?>
            <label class="field"><span><?= e(t('portal.which_device')) ?> *</span><select name="asset_id" required>
                <?php foreach ($assets as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name'] . ' — ' . $a['tag']) ?></option><?php endforeach; ?>
            </select></label>
            <fieldset class="field"><legend><?= e(t('report.urgency')) ?></legend>
                <div class="radio-cards">
                    <?php foreach (['blocked', 'degraded', 'minor'] as $u): ?>
                        <label class="radio-card"><input type="radio" name="urgency" value="<?= $u ?>" <?= $u === 'degraded' ? 'checked' : '' ?>><strong><?= e(t('report.urgency.' . $u)) ?></strong></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <label class="field"><span><?= e(t('report.what')) ?> *</span><textarea name="description" rows="4" required maxlength="2000" placeholder="<?= e(t('report.what_ph')) ?>" dir="auto"></textarea></label>
            <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('report.submit')) ?></button></div>
        <?php else: ?>
            <p class="muted"><?= e(t('portal.no_devices')) ?></p>
            <div class="modal-actions"><button type="button" class="btn btn-primary" data-modal-close><?= e(t('common.close')) ?></button></div>
        <?php endif; ?>
    </form>
</dialog>

<?php if ($loanPool): ?>
<dialog class="modal" id="modal-loan">
    <form method="post" action="<?= url('portal/loans') ?>" class="stack">
        <?= csrf_field() ?>
        <h3><?= icon('swap') ?> <?= e(t('loans.book')) ?></h3>
        <p class="muted small"><?= e(t('loans.portal_help', ['n' => (int) config('loans.max_days', 14)])) ?></p>
        <label class="field"><span><?= e(t('loans.type')) ?></span><select name="category_id" required><?= select_options(array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name']], $loanPool), '', 'id', 'name', false) ?></select></label>
        <div class="form-grid">
            <label class="field"><span><?= e(t('loans.from')) ?> *</span><input type="date" name="starts_on" required min="<?= today() ?>" max="<?= days_from_today((int) config('loans.ahead_days', 60)) ?>" value="<?= today() ?>"></label>
            <label class="field"><span><?= e(t('loans.to')) ?> *</span><input type="date" name="ends_on" required min="<?= today() ?>" value="<?= days_from_today(2) ?>"></label>
        </div>
        <label class="field"><span><?= e(t('loans.purpose')) ?></span><input name="purpose" maxlength="255" placeholder="<?= e(t('loans.purpose_ph')) ?>"></label>
        <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('loans.send_request')) ?></button></div>
    </form>
</dialog>
<?php endif; ?>
