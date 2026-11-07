<?php
/**
 * Site footer: the organisation's rights notice, social links (Settings › Footer) and the ITAM attribution.
 * $variant: 'app' (inside the workspace), 'login' or 'guest' (portal and public pages).
 * The attribution line is the notice the licence asks to keep (see NOTICE); the portal only says "ITAM".
 */
$variant ??= 'app';
$GLOBALS['itam_footer_rendered'] = true;
$custom = trim((string) config('brand.footer_text'));
$vendor = vendor_name();
$rights = $custom !== '' ? strtr($custom, [':year' => date('Y'), ':vendor' => $vendor])
    : ($vendor !== '' ? t('footer.rights', ['year' => date('Y'), 'vendor' => $vendor]) : '');
$links = social_links();
$promo = config('promo');
?>
<footer class="site-footer footer-<?= e($variant) ?>">
    <div class="footer-brand">
        <img src="<?= brand_asset('logo_mark') ?>" alt="" width="22" height="22">
        <span>
            <?php if ($rights !== ''): ?><span dir="auto"><?= e($rights) ?></span><?php endif; ?>
            <?php if ($variant === 'guest'): ?>
                <small class="muted"><?= e(t('about.powered_by')) ?></small>
            <?php else: ?>
                <small class="muted itam-credit"><?= e(t('about.credit_open')) ?> <span dir="ltr">AGPL-3.0</span> · <?= e(t('about.credit_by')) ?>
                    <a href="<?= e($promo['url']) ?>" target="_blank" rel="noopener"><?= e($promo['name']) ?></a> · <?= e($promo['vendor']) ?>
                    <?php if ($variant === 'app'): ?> · <a href="<?= url('about') ?>">ITAM <span dir="ltr">v<?= e(ITAM_VERSION) ?></span></a><?php endif; ?></small>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($links): ?>
        <nav class="footer-social" aria-label="<?= e(t('footer.follow')) ?>">
            <?php foreach ($links as [$key, $label, $href]): ?>
                <a class="social-<?= e($key) ?>" href="<?= e($href) ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"<?= $key === 'email' ? '' : ' target="_blank" rel="noopener noreferrer"' ?>><?= social_icon($key) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</footer>
