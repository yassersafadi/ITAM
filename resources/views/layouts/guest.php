<?php use ITAM\Core\Lang; $locale = Lang::locale(); ?><!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e(Lang::dir()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((page_title() ? page_title() . ' · ' : '') . app_name()) ?></title>
    <link rel="icon" href="<?= brand_asset('favicon') ?>">
    <?= theme_fonts() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <?= import_map() ?>
    <?= brand_style() ?>
    <?php if (!empty($pwa)): ?>
        <link rel="manifest" href="<?= url('portal/manifest') ?>" data-portal-manifest data-sw="<?= url('portal/sw') ?>" data-scope="<?= url('portal') ?>">
        <meta name="theme-color" content="<?= e(\ITAM\Services\Themes::primary()) ?>">
        <link rel="apple-touch-icon" href="<?= url('portal/icon/192') ?>">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="<?= e(t('portal.title')) ?>">
    <?php endif; ?>
    <script>try { var t = localStorage.getItem('itam-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
</head>
<body class="guest">
    <?php if ((string) config('app.env') !== ''): ?><div class="env-strip env-<?= e((string) config('app.env')) ?>"><strong><?= e(strtoupper((string) config('app.env'))) ?></strong><?php if (!\ITAM\Services\Notifier::outbound()): ?> · <?= e(t('env.outbound_off')) ?><?php endif; ?></div><?php endif; ?>
    <?= $content ?>
    <?php if (empty($GLOBALS['itam_footer_rendered'])): ?><?= partial('partials/footer', ['variant' => 'guest']) ?><?php endif; ?>
    <script type="module" src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
