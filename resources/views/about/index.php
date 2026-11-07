<?php
/** About ITAM: the project, its licence, and what the AIM platform adds. */
page_title(t('about.title'));
$rows = [
    ['about.cmp.core', true],
    ['about.cmp.sso_api', true],
    ['about.cmp.health', false],
    ['about.cmp.ai', false],
    ['about.cmp.agent', false],
    ['about.cmp.integrations', false],
    ['about.cmp.compliance', false],
    ['about.cmp.enterprise', false],
    ['about.cmp.modules', false],
    ['about.cmp.support', false],
];
?>
<div class="page-head">
    <div>
        <h1><?= icon('star', 'icon icon-title') ?> <?= e(t('about.title')) ?></h1>
    </div>
</div>

<section class="card about-hero">
    <img src="<?= asset('img/logo-mark.png') ?>" alt="" width="72" height="72">
    <div>
        <h2>ITAM <small class="muted" dir="ltr">v<?= e(ITAM_VERSION) ?></small></h2>
        <p class="muted"><?= e(t('about.lead')) ?></p>
        <span class="badge badge-success"><?= e(t('about.open_source')) ?> · <span dir="ltr">AGPL-3.0</span></span>
        <span class="badge badge-neutral"><?= e(t('about.by', ['vendor' => $promo['vendor']])) ?></span>
    </div>
</section>

<div class="about-grid">
    <section class="card">
        <h3 class="card-title"><?= icon('book') ?> <?= e(t('about.project')) ?></h3>
        <div class="about-items">
            <?php foreach ([['shield', 'about.licence', 'about.licence_text'], ['layers', 'about.source', 'about.source_text'], ['lock', 'about.privacy', 'about.privacy_text']] as [$ic, $title, $text]): ?>
                <div class="about-item"><span class="list-icon"><?= icon($ic) ?></span><div><strong><?= e(t($title)) ?></strong><p class="small muted"><?= e(t($text)) ?></p></div></div>
            <?php endforeach; ?>
            <a class="about-item" href="<?= url('help') ?>"><span class="list-icon"><?= icon('info') ?></span><div><strong><?= e(t('nav.help')) ?></strong><p class="small muted"><?= e(t('about.help_text')) ?></p></div></a>
        </div>
    </section>

    <section class="card" id="aim">
        <h3 class="card-title"><?= icon('sparkles') ?> <?= e(t('about.aim_title', ['name' => $promo['name']])) ?></h3>
        <p class="muted"><?= e(t('about.aim_text', ['name' => $promo['name']])) ?></p>
        <div class="table-wrap">
            <table class="table compact about-cmp">
                <thead><tr><th><?= e(t('about.feature')) ?></th><th class="c">ITAM</th><th class="c"><?= e($promo['name']) ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as [$key, $inItam]): ?>
                    <tr><td><?= e(t($key)) ?></td>
                        <td class="c"><?= $inItam ? '<span class="text-success" aria-label="' . e(t('common.yes')) . '">' . icon('check', 'icon icon-xs') . '</span>' : '<span class="muted" aria-label="' . e(t('common.no')) . '">—</span>' ?></td>
                        <td class="c"><span class="text-success" aria-label="<?= e(t('common.yes')) ?>"><?= icon('check', 'icon icon-xs') ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="about-cta">
            <a class="btn btn-primary" href="<?= e($promo['url']) ?>" target="_blank" rel="noopener"><?= icon('sparkles') ?> <?= e(t('about.aim_demo', ['name' => $promo['name']])) ?></a>
        </div>
        <p class="small muted"><?= e(t('about.aim_note')) ?></p>
    </section>
</div>
