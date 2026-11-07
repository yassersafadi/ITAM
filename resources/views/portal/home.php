<?php
page_title(t('portal.title'));
$loanTone = ['requested' => 'warning', 'approved' => 'info', 'checked_out' => 'success'];
?>
<section class="portal-hero">
    <h1><?= e(t('portal.hello', ['name' => explode(' ', $emp['name'])[0]])) ?></h1>
    <p class="muted"><?= e(t('portal.home_sub')) ?></p>
</section>

<?php if ($pending): ?>
    <section class="card portal-attn">
        <h3 class="card-title"><?= icon('edit') ?> <?= e(t('portal.to_sign')) ?></h3>
        <ul class="list">
            <?php foreach ($pending as $p): ?>
                <li><span class="list-main"><span class="list-icon"><?= icon($p['type'] === 'return' ? 'box' : 'user') ?></span>
                    <span><strong class="mono"><?= e($p['receipt_no']) ?></strong><small><?= e(t('custody.type.' . $p['type'])) ?> · <?= fmt_date($p['created_at']) ?></small></span></span>
                    <a class="btn btn-primary btn-sm" href="<?= url('sign/' . $p['sign_token']) ?>"><?= icon('edit') ?> <?= e(t('custody.sign_now')) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<div class="portal-actions">
    <button type="button" class="portal-action" data-modal-open="report"><span class="portal-action-icon"><?= icon('wrench') ?></span><strong><?= e(t('portal.report_problem')) ?></strong><small><?= e(t('portal.act.report')) ?></small></button>
    <a class="portal-action" href="<?= url('portal/devices') ?>"><span class="portal-action-icon"><?= icon('laptop') ?></span><strong><?= e(t('portal.my_devices')) ?></strong><small><?= e(t('portal.act.devices_sub')) ?></small></a>
    <?php if ($loanPool): ?>
        <button type="button" class="portal-action" data-modal-open="loan"><span class="portal-action-icon"><?= icon('calendar') ?></span><strong><?= e(t('loans.book')) ?></strong><small><?= e(t('portal.act.loan_sub')) ?></small></button>
    <?php endif; ?>
    <a class="portal-action" href="<?= url('portal/documents') ?>"><span class="portal-action-icon"><?= icon('shield') ?></span><strong><?= e(t('portal.nav.documents')) ?></strong><small><?= e(t('portal.act.documents_sub')) ?></small></a>
</div>

<div class="portal-stats">
    <a href="<?= url('portal/devices') ?>"><span><?= e(t('portal.my_devices')) ?></span><strong><?= count($assets) ?></strong></a>
    <span><span><?= e(t('portal.open_reports')) ?></span><strong><?= (int) $openTickets ?></strong></span>
    <a href="<?= url('portal/devices') ?>" class="<?= $warrantySoon ? 'warn' : '' ?>"><span><?= e(t('portal.warranty_soon')) ?></span><strong><?= (int) $warrantySoon ?></strong></a>
</div>

<?php if ($loans): ?>
    <section class="card" style="margin-bottom:16px">
        <h3 class="card-title"><?= icon('swap') ?> <?= e(t('portal.my_loans')) ?></h3>
        <ul class="list">
            <?php foreach ($loans as $b): ?>
                <li><span class="list-main"><span class="list-icon"><?= icon('calendar') ?></span>
                    <span><strong dir="auto"><?= e($b['category_name'] ?? '—') ?></strong><small><bdi class="mono"><?= e($b['number']) ?></bdi> · <?= fmt_date($b['starts_on']) ?> – <?= fmt_date($b['ends_on']) ?></small></span></span>
                    <span class="inline-actions"><span class="badge badge-<?= $loanTone[$b['status']] ?? 'neutral' ?>"><?= e(t('loans.status.' . $b['status'])) ?></span>
                    <?php if ($b['status'] === 'requested'): ?><form method="post" action="<?= url('portal/loans/' . $b['id'] . '/cancel') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(t('common.cancel')) ?></button></form><?php endif; ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card">
    <div class="card-head"><h3><?= icon('activity') ?> <?= e(t('portal.latest')) ?></h3></div>
    <?php if (!$tickets): ?>
        <p class="muted small"><?= e(t('portal.latest_empty')) ?></p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($tickets as $tk): ?>
                <li><span class="list-main"><span class="list-icon"><?= icon('wrench') ?></span><span><strong dir="auto"><?= e($tk['title']) ?></strong><small><bdi class="mono">WO-<?= (int) $tk['id'] ?></bdi> · <bdi class="mono"><?= e($tk['tag']) ?></bdi> · <?= fmt_date($tk['created_at']) ?></small></span></span>
                    <?= status_badge($tk['status'], 'maintenance.status') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
