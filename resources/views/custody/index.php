<?php page_title(t('nav.custody')); ?>
<div class="page-head">
    <div>
        <h1><?= icon('edit', 'icon icon-title') ?> <?= e(t('nav.custody')) ?></h1>
        <p class="muted"><?= e(t('custody.subtitle')) ?></p>
    </div>
</div>

<div class="seg">
    <a href="<?= url('custody') ?>" class="<?= !$status ? 'active' : '' ?>"><?= e(t('common.all')) ?> <span class="count"><?= (int) ($counts['pending'] ?? 0) + (int) ($counts['signed'] ?? 0) ?></span></a>
    <a href="<?= url('custody', ['status' => 'pending']) ?>" class="<?= $status === 'pending' ? 'active' : '' ?>"><?= icon('clock', 'icon icon-xs') ?> <?= e(t('custody.status.pending')) ?> <span class="count"><?= (int) ($counts['pending'] ?? 0) ?></span></a>
    <a href="<?= url('custody', ['status' => 'late']) ?>" class="<?= $status === 'late' ? 'active' : '' ?>"><?= icon('alert', 'icon icon-xs') ?> <?= e(t('custody.late')) ?> <span class="count<?= $counts['late'] ? ' count-danger' : '' ?>"><?= (int) $counts['late'] ?></span></a>
    <a href="<?= url('custody', ['status' => 'signed']) ?>" class="<?= $status === 'signed' ? 'active' : '' ?>"><?= icon('check', 'icon icon-xs') ?> <?= e(t('custody.status.signed')) ?> <span class="count"><?= (int) ($counts['signed'] ?? 0) ?></span></a>
</div>

<section class="card table-card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= e(t('custody.receipt')) ?></th><th><?= e(t('field.type')) ?></th><th><?= e(t('field.employee')) ?></th><th><?= e(t('custody.items')) ?></th><th><?= e(t('field.status')) ?></th><th><?= e(t('field.date')) ?></th><th class="hide-md"><?= e(t('custody.handed_by')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $n = count(json_decode((string) $r['items'], true) ?: []); ?>
                <tr>
                    <td><a class="mono" href="<?= url('custody/' . $r['id']) ?>" target="_blank"><?= e($r['receipt_no']) ?></a></td>
                    <td><?= e(t('custody.type.' . $r['type'])) ?></td>
                    <td><?= $r['employee_id'] ? '<a href="' . url('employees/' . $r['employee_id']) . '">' . e($r['employee_name']) . '</a>' : e($r['employee_name']) ?></td>
                    <td><?= $n ?></td>
                    <td><span class="badge badge-<?= $r['status'] === 'signed' ? 'success' : 'warning' ?>"><?= e(t('custody.status.' . $r['status'])) ?></span>
                        <?php if ($r['signed_at']): ?><small class="block muted"><?= fmt_date($r['signed_at'], true) ?></small><?php endif; ?>
                        <?php if ($r['status'] === 'pending' && $r['created_at'] < $cut): ?><span class="badge badge-danger"><?= e(t('custody.late_n', ['n' => (int) days_between(substr((string) $r['created_at'], 0, 10))])) ?></span><?php endif; ?>
                        <?php if ($r['status'] === 'pending' && $r['reminded_at']): ?><small class="block muted"><?= e(t('custody.reminded', ['d' => fmt_date($r['reminded_at'])])) ?></small><?php endif; ?></td>
                    <td><?= fmt_date($r['created_at']) ?></td>
                    <td class="hide-md"><?= e($r['staff_name'] ?? '—') ?></td>
                    <td class="actions">
                        <?php if ($r['status'] === 'pending' && can('assets.assign')): ?>
                            <span class="inline-actions">
                                <a class="btn btn-primary btn-sm" href="<?= url('custody/' . $r['id'] . '/sign') ?>"><?= icon('edit') ?> <?= e(t('custody.sign_now')) ?></a>
                                <form method="post" action="<?= url('custody/' . $r['id'] . '/delete') ?>" data-confirm="<?= e(t('custody.confirm_cancel')) ?>"><?= csrf_field() ?><button class="icon-btn sm" aria-label="<?= e(t('common.delete')) ?>"><?= icon('x') ?></button></form>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8"><div class="empty-state small"><?= icon('edit') ?><p><?= e(t('custody.none')) ?></p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
