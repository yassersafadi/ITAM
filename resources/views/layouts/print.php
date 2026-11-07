<?php use ITAM\Core\Lang; ?><!doctype html>
<html lang="<?= e(Lang::locale()) ?>" dir="<?= e(Lang::dir()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(page_title() ?? t('labels.title')) ?></title>
    <link rel="icon" href="<?= brand_asset('favicon') ?>">
    <?= theme_fonts() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <?= import_map() ?>
    <?= brand_style() ?>
</head>
<body class="print-body">
    <?php if ((string) config('app.env') !== ''): ?><div class="env-strip env-<?= e((string) config('app.env')) ?>"><strong><?= e(strtoupper((string) config('app.env'))) ?></strong><?php if (!\ITAM\Services\Notifier::outbound()): ?> · <?= e(t('env.outbound_off')) ?><?php endif; ?></div><?php endif; ?>
    <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?> no-print print-flash"><?= icon('info') ?><span><?= e($f['message']) ?></span></div>
    <?php endforeach; ?>
    <?= $content ?>
    <script src="<?= asset('vendor/qrcode.js') ?>"></script>
    <script type="module" src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
