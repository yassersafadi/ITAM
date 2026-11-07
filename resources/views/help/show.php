<?php
use ITAM\Core\Lang;
use ITAM\Services\Help;

page_title($section['title'] . ' · ' . t('nav.help'));
$headings = array_filter($section['blocks'], fn ($b) => $b[0] === 'h');
?>
<div class="help-layout">
    <aside class="help-nav card" aria-label="<?= e(t('help.contents')) ?>">
        <a class="help-home" href="<?= url('help') ?>"><?= icon('book') ?> <?= e(t('nav.help')) ?></a>
        <?php foreach ($groups as $group => $items): ?>
            <div class="help-nav-group"><?= e(\ITAM\Services\Help::groupName($group)) ?></div>
            <?php foreach ($items as $it): ?>
                <a href="<?= url('help/' . $it['slug']) ?>" class="<?= $it['slug'] === $slug ? 'active' : '' ?>"><?= e($it['title']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </aside>

    <article class="help-article card">
        <div class="help-head">
            <a href="<?= url('help') ?>" class="back-link">‹ <?= e(t('nav.help')) ?> · <?= e(\ITAM\Services\Help::groupName($meta[0])) ?></a>
            <h1><?= icon($meta[1], 'icon icon-title') ?> <?= e($section['title']) ?></h1>
            <?php if ($section['summary']): ?><p class="help-summary"><?= Help::inline($section['summary']) ?></p><?php endif; ?>
            <div class="inline-actions">
                <?php if ($meta[2] !== null): ?><a class="btn btn-primary btn-sm" href="<?= url($meta[2]) ?>"><?= icon('chevron') ?> <?= e(t('help.open_page')) ?></a><?php endif; ?>
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
        <nav class="help-pager">
            <?php if ($prev && ($p = Help::load($prev))): ?><a href="<?= url('help/' . $prev) ?>">‹ <?= e($p['title']) ?></a><?php else: ?><span></span><?php endif; ?>
            <?php if ($next && ($x = Help::load($next))): ?><a href="<?= url('help/' . $next) ?>"><?= e($x['title']) ?> ›</a><?php endif; ?>
        </nav>
    </article>
</div>
