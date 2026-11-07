<?php page_title(t('nav.ledger')); ?>
<div class="page-head">
    <div>
        <h1><?= icon('history', 'icon icon-title') ?> <?= e(t('nav.ledger')) ?></h1>
        <p class="muted"><?= e(t('ledger.subtitle')) ?></p>
    </div>
</div>

<form class="filters" method="get" action="<?= url('ledger') ?>">
    <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(t('common.search')) ?>…">
    <select name="entity"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($entities as $en): ?><option value="<?= e($en) ?>" <?= ($_GET['entity'] ?? '') === $en ? 'selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?></select>
    <button class="btn btn-ghost"><?= icon('filter') ?> <?= e(t('common.filter')) ?></button>
    <span class="muted small"><?= e(t('ledger.total', ['n' => fmt_num($total)])) ?></span>
</form>

<?= partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'path' => 'ledger', 'query' => array_diff_key($_GET, ['page' => 1, 'verify' => 1]), 'place' => 'top']) ?>
<section class="card table-card">
    <div class="table-wrap">
        <table class="table ledger">
            <thead><tr><th>#</th><th><?= e(t('field.date')) ?></th><th><?= e(t('ledger.user')) ?></th><th><?= e(t('ledger.action')) ?></th><th><?= e(t('ledger.summary')) ?></th><th class="hide-md">Hash</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $l): ?>
                <tr>
                    <td class="mono muted"><?= (int) $l['id'] ?></td>
                    <td class="nowrap"><?= fmt_date($l['created_at'], true) ?></td>
                    <td><?= e($l['user_name']) ?></td>
                    <td><span class="badge badge-neutral"><?= e($l['entity']) ?>.<?= e($l['action']) ?></span></td>
                    <td>
                        <?php if ($l['entity'] === 'asset' && $l['entity_id']): ?><a href="<?= url('assets/' . $l['entity_id']) ?>"><?= e($l['summary']) ?></a><?php else: ?><?= e($l['summary']) ?><?php endif; ?>
                        <?php if ($l['payload'] && in_array($l['action'], ['update', 'drift'], true)): ?>
                            <details class="payload"><summary><?= e(t('ledger.changes')) ?></summary><pre><?= e(json_encode(json_decode((string) $l['payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?= partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'path' => 'ledger', 'query' => array_diff_key($_GET, ['page' => 1, 'verify' => 1])]) ?>
