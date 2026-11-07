<?php
use ITAM\Core\Lang;
use ITAM\Services\Help;

// The employee guide on its own public page (guest layout): no staff navigation, links to staff screens are plain text.
page_title($section['title']);
$headings = array_filter($section['blocks'], fn ($b) => $b[0] === 'h');
?>
<div class="portal">
    <header class="portal-bar">
        <a class="public-brand" href="<?= url('portal') ?>"><img src="<?= brand_asset('logo_mark') ?>" alt=""><span><?= e(app_name()) ?></span></a>
        <div class="portal-user">
            <?php foreach (config('i18n.locales') as $code => $l): if ($code === Lang::locale()) continue; ?><a class="link small" href="<?= url('lang/' . $code) ?>"><?= e($l['label']) ?></a><?php endforeach; ?>
            <a class="btn btn-ghost btn-sm" href="<?= url('portal') ?>"><?= icon('user') ?> <?= e(t('portal.title')) ?></a>
        </div>
    </header>
    <main class="portal-main">
        <article class="help-article card">
            <div class="help-head">
                <h1><?= icon('book', 'icon icon-title') ?> <?= e($section['title']) ?></h1>
                <?php if ($section['summary']): ?><p class="help-summary"><?= Help::inline($section['summary']) ?></p><?php endif; ?>
                <div class="inline-actions">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= icon('printer') ?> <?= e(t('labels.print')) ?></button>
                </div>
                <?php if ($section['lang'] !== Lang::locale()): ?>
                    <p class="small muted"><?= icon('globe', 'icon icon-xs') ?> <?= e(t('help.fallback')) ?></p>
                <?php endif; ?>
            </div>
            <?php if (count($headings) > 2): ?>
                <nav class="help-toc" aria-label="<?= e(t('help.on_this_page')) ?>">
                    <strong><?= e(t('help.on_this_page')) ?></strong>
                    <?php foreach ($headings as $n => [, $text]): ?><a href="#s<?= $n ?>"><?= e(preg_replace('/[*`]/', '', $text)) ?></a><?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="help-body" dir="<?= e(config('i18n.locales')[$section['lang']]['dir'] ?? 'ltr') ?>">
                <?= partial('help/_blocks', ['blocks' => $section['blocks']]) ?>
            </div>
        </article>
    </main>
</div>
