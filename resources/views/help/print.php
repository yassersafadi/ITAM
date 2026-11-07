<?php
page_title(t('help.title_print', ['app' => app_name()]));
?>
<div class="print-toolbar no-print">
    <a class="btn btn-ghost" href="<?= url('help') ?>">‹ <?= e(t('nav.help')) ?></a>
    <strong><?= e(page_title()) ?></strong>
    <button class="btn btn-primary" onclick="window.print()"><?= icon('printer') ?> <?= e(t('labels.print')) ?></button>
</div>
<article class="help-print">
    <header class="help-print-cover">
        <img src="<?= brand_asset('logo_full') ?>" alt="" class="cert-logo">
        <h1><?= e(page_title()) ?></h1>
        <p class="muted"><?= e(vendor_name()) ?> · <?= e(fmt_date(today())) ?> · v<?= ITAM_VERSION ?></p>
        <ol class="help-print-toc">
            <?php foreach ($sections as $slug => $s): ?><li><a href="#<?= e($slug) ?>"><?= e($s['title']) ?></a></li><?php endforeach; ?>
        </ol>
    </header>
    <?php foreach ($sections as $slug => $s): ?>
        <section class="help-print-section" id="<?= e($slug) ?>">
            <h1><?= e($s['title']) ?></h1>
            <?php if ($s['summary']): ?><p class="help-summary"><?= \ITAM\Services\Help::inline($s['summary']) ?></p><?php endif; ?>
            <?= partial('help/_blocks', ['blocks' => $s['blocks']]) ?>
        </section>
    <?php endforeach; ?>
</article>
