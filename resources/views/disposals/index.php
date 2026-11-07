<?php page_title(t('nav.disposals')); ?>
<div class="page-head">
    <div>
        <h1><?= icon('trash', 'icon icon-title') ?> <?= e(t('nav.disposals')) ?></h1>
        <p class="muted"><?= e(t('disposal.index_subtitle')) ?></p>
    </div>
</div>

<section class="mini-kpis">
    <div class="card"><span><?= e(t('disposal.total')) ?></span><strong><?= (int) $stats['total'] ?></strong></div>
    <div class="card"><span><?= e(t('disposal.this_year')) ?></span><strong><?= (int) $stats['year'] ?></strong></div>
</section>

<section class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr>
                <th><?= e(t('disposal.number')) ?></th><th><?= e(t('field.asset')) ?></th><th><?= e(t('disposal.reason')) ?></th>
                <th><?= e(t('disposal.method')) ?></th><th><?= e(t('disposal.date')) ?></th><th class="hide-md"><?= e(t('disposal.by')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr data-href="<?= url('disposals/' . $r['id']) ?>">
                    <td><span class="mono"><?= e($r['certificate_no']) ?></span></td>
                    <td><strong class="mono"><?= e($r['asset_tag']) ?></strong><small class="block muted" dir="auto"><?= e($r['asset_name']) ?></small></td>
                    <td><?= e(t('disposal.reason.' . $r['reason'])) ?></td>
                    <td><?= e(t('disposal.method.' . $r['method'])) ?></td>
                    <td><?= fmt_date($r['disposed_at']) ?></td>
                    <td class="hide-md"><?= e($r['user_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state small"><?= icon('trash') ?><p><?= e(t('disposal.none')) ?></p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
