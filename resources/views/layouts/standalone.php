<?php
use ITAM\Core\Lang;

/** Self-contained document: styles inlined and logo embedded, so it renders the same from a file (PDF) as on the server. */
$logoFile = ITAM_ROOT . '/public/assets/' . ((string) config('brand.logo_mark') !== '' && is_file(ITAM_ROOT . '/public/assets/' . config('brand.logo_mark')) ? config('brand.logo_mark') : 'img/logo-mark.png');
$logo = is_file($logoFile) ? 'data:' . (mime_content_type($logoFile) ?: 'image/png') . ';base64,' . base64_encode((string) file_get_contents($logoFile)) : '';
$brand = \ITAM\Services\Themes::primary();
?><!doctype html>
<html lang="<?= e(Lang::locale()) ?>" dir="<?= e(Lang::dir()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(page_title() ?? app_name()) ?></title>
    <?= theme_fonts() ?>
    <style>:root { --brand: <?= e($brand) ?>; --font: <?= \ITAM\Services\Themes::shapeVars(\ITAM\Services\Themes::active())['--font'] ?>; }
<?= file_get_contents(ITAM_ROOT . '/public/assets/css/report.css') ?></style>
</head>
<body>
    <?= str_replace('{{logo}}', e($logo), $content) ?>
</body>
</html>
