<?php
/**
 * The small card under the side menu that introduces AIM. Administrators only, never on the employee portal, and it
 * can be hidden (for 90 days, per user). It is a plain link: nothing is loaded from or sent to another server.
 */
use ITAM\Controllers\AboutController;

if (!can('settings.manage') || !AboutController::promoVisible()) {
    return;
}
$promo = config('promo');
?>
<div class="itam-promo">
    <form method="post" action="<?= url('about/promo/hide') ?>" class="itam-promo-x"><?= csrf_field() ?><button type="submit" aria-label="<?= e(t('about.promo_hide')) ?>" title="<?= e(t('about.promo_hide')) ?>"><?= icon('x', 'icon icon-xs') ?></button></form>
    <strong><?= e(t('about.promo_title')) ?></strong>
    <span><?= e(t('about.promo_text', ['name' => $promo['name']])) ?></span>
    <a href="<?= url('about') ?>#aim"><?= e(t('about.promo_link', ['name' => $promo['name']])) ?></a>
</div>
