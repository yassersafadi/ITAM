<?php
page_title(t('nav.dashboard'));
$greet = (int) date('H') < 12 ? 'dashboard.morning' : ((int) date('H') < 18 ? 'dashboard.afternoon' : 'dashboard.evening');
$urgent = count(array_filter($attention, fn ($i) => in_array($i['severity'], ['critical', 'high'], true)));
$q = $location ? ['location' => $location] : [];
$hidden = array_flip($layout['hidden']);
$fmtDate = class_exists(IntlDateFormatter::class)
    ? (new IntlDateFormatter(\ITAM\Core\Lang::locale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE))->format(time()) : date('l j F Y');
?>
<?php if (\ITAM\Controllers\AboutController::welcomePending()): $promo = config('promo'); ?>
    <section class="card welcome-card">
        <span class="welcome-icon"><?= icon('sparkles') ?></span>
        <div>
            <h3><?= e(t('about.welcome_title')) ?></h3>
            <p class="muted"><?= e(t('about.welcome_text')) ?></p>
            <p class="small"><?= e(t('about.welcome_aim', ['name' => $promo['name']])) ?> <a href="<?= url('about') ?>#aim"><?= e(t('about.promo_link', ['name' => $promo['name']])) ?></a></p>
        </div>
        <form method="post" action="<?= url('about/welcome/dismiss') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(t('about.welcome_ok')) ?></button></form>
    </section>
<?php endif; ?>

<section class="dash-hero">
    <div class="dash-hero-main">
        <div>
            <p class="muted small"><?= e($fmtDate) ?></p>
            <h1><?= e(t($greet, ['name' => explode(' ', auth()['name'])[0]])) ?></h1>
            <p class="dash-summary">
                <?php if ($urgent): ?><span class="badge badge-danger"><?= icon('alert', 'icon icon-xs') ?> <?= e(t('dash.summary_urgent', ['n' => $urgent])) ?></span>
                <?php elseif ($attention): ?><span class="badge badge-warning"><?= e(t('dash.summary_some', ['n' => count($attention)])) ?></span>
                <?php else: ?><span class="badge badge-success"><?= icon('check', 'icon icon-xs') ?> <?= e(t('dash.summary_clear')) ?></span><?php endif; ?>
            </p>
        </div>
        <div class="dash-tools">
            <form method="get" action="<?= url('/') ?>" class="dash-filter">
                <?= icon('pin', 'icon icon-xs') ?>
                <select name="location" onchange="this.form.submit()" aria-label="<?= e(t('field.location_id')) ?>">
                    <option value=""><?= e(scope_ids() ? t('dash.my_sites') : t('grid.any_location')) ?></option>
                    <?php foreach ($locations as $l): ?><option value="<?= (int) $l['id'] ?>"<?= $location === (int) $l['id'] ? ' selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
                </select>
            </form>
            <button type="button" class="btn btn-ghost" data-dash-customize><?= icon('sliders') ?> <span class="hide-sm"><?= e(t('dash.customize')) ?></span></button>
            <?php if (can('assets.edit')): ?><a class="btn btn-primary" href="<?= url('assets/create') ?>"><?= icon('plus') ?> <span class="hide-sm"><?= e(t('assets.new')) ?></span></a><?php endif; ?>
        </div>
    </div>
    <div class="dash-score dash-summary-card">
        <div>
            <strong><?= e(t('dash.assets_summary')) ?></strong>
            <ul class="score-parts">
                <?php foreach ($summary as $st => $n): ?><li><a href="<?= url('assets', $q + ['status' => $st]) ?>"><span><?= e(t('status.' . $st)) ?></span><b><?= fmt_num($n) ?></b></a></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<div class="dash-editbar" data-dash-editbar hidden>
    <span><?= icon('sliders') ?> <?= e(t('dash.edit_help')) ?></span>
    <button type="button" class="btn btn-ghost btn-sm" data-dash-reset><?= icon('refresh') ?> <?= e(t('dash.reset')) ?></button>
    <button type="button" class="btn btn-primary btn-sm" data-dash-save><?= icon('check') ?> <?= e(t('dash.done')) ?></button>
</div>

<div class="dash-widgets" data-dash data-live-url="<?= url('dashboard/live', $q) ?>" data-layout-url="<?= url('dashboard/layout') ?>">
    <?php foreach ($layout['order'] as $w): ?>
        <section class="dash-widget dw-<?= $w ?>" data-widget="<?= $w ?>"<?= isset($hidden[$w]) ? ' data-hidden hidden' : '' ?>>
            <div class="dash-widget-edit">
                <span class="drag-handle" aria-hidden="true"><?= icon('menu') ?></span>
                <strong><?= e(t('dash.w.' . $w)) ?></strong>
                <button type="button" class="btn btn-ghost btn-sm" data-dash-toggle><?= icon('eye') ?> <span data-on><?= e(t('dash.hide')) ?></span><span data-off hidden><?= e(t('dash.show')) ?></span></button>
            </div>
            <?= partial('dashboard/_' . $w, compact('d', 'attention', 'q', 'location')) ?>
        </section>
    <?php endforeach; ?>
</div>
