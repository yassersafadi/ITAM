<?php
use ITAM\Controllers\LoanController;
use ITAM\Services\Loans;

page_title(t('nav.loans'));
$canAssign = can('assets.assign');
$tabCount = ['calendar' => null, 'requests' => (int) ($counts['requested'] ?? 0), 'active' => (int) ($counts['approved'] ?? 0) + (int) ($counts['checked_out'] ?? 0), 'history' => null];
?>
<div class="page-head">
    <div>
        <h1><?= icon('swap', 'icon icon-title') ?> <?= e(t('nav.loans')) ?></h1>
        <p class="muted"><?= e(t('loans.subtitle')) ?></p>
    </div>
    <?php if ($canAssign && $pool): ?><div class="page-actions"><button class="btn btn-primary" data-modal-open="new-loan"><?= icon('plus') ?> <?= e(t('loans.new')) ?></button></div><?php endif; ?>
</div>

<section class="mini-kpis">
    <div class="card"><span><?= e(t('loans.pool_size')) ?></span><strong><?= count($loanable) ?></strong></div>
    <a class="card" href="<?= url('loans', ['view' => 'requests']) ?>"><span><?= e(t('loans.status.requested')) ?></span><strong class="<?= $tabCount['requests'] ? 'text-warning' : '' ?>"><?= $tabCount['requests'] ?></strong></a>
    <a class="card" href="<?= url('loans', ['view' => 'active']) ?>"><span><?= e(t('loans.status.checked_out')) ?></span><strong><?= (int) ($counts['checked_out'] ?? 0) ?></strong></a>
    <a class="card" href="<?= url('loans', ['view' => 'active']) ?>"><span><?= e(t('loans.overdue')) ?></span><strong class="<?= $overdue ? 'text-danger' : 'text-success' ?>"><?= $overdue ?></strong></a>
</section>

<div class="seg">
    <?php foreach (LoanController::VIEWS as $v): ?>
        <a href="<?= url('loans', ['view' => $v]) ?>" class="<?= $view === $v ? 'active' : '' ?>"><?= e(t('loans.v.' . $v)) ?><?= $tabCount[$v] ? ' <span class="count">' . $tabCount[$v] . '</span>' : '' ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$loanable): ?>
    <div class="callout callout-info"><?= icon('swap') ?><div><strong><?= e(t('loans.empty_pool')) ?></strong><p class="small"><?= e(t('loans.empty_pool_help')) ?></p></div></div>
<?php endif; ?>

<?php if ($view === 'calendar' && $cal): $days = []; for ($i = 0; $i < $cal['days']; $i++) { $days[] = date('Y-m-d', strtotime($cal['start'] . " +$i days")); } ?>
    <div class="toolbar">
        <div class="inline-actions">
            <a class="btn btn-ghost btn-sm" href="<?= url('loans', ['start' => date('Y-m-d', strtotime($cal['start'] . ' -7 days'))]) ?>">‹</a>
            <strong class="small"><?= date_range($cal['start'], $cal['end']) ?></strong>
            <a class="btn btn-ghost btn-sm" href="<?= url('loans', ['start' => date('Y-m-d', strtotime($cal['start'] . ' +7 days'))]) ?>">›</a>
        </div>
    </div>
    <div class="card loan-cal-wrap">
        <div class="loan-cal">
            <div class="lc-head"><div class="lc-name muted"><?= e(t('field.asset')) ?></div>
                <div class="lc-days"><?php foreach ($days as $d): $w = (int) date('w', strtotime($d)); ?><span class="<?= in_array($w, [5, 6], true) ? 'we' : '' ?><?= $d === today() ? ' today' : '' ?>"><?= date('j', strtotime($d)) ?></span><?php endforeach; ?></div></div>
            <?php foreach ($cal['assets'] as $a): ?>
                <div class="lc-row">
                    <div class="lc-name"><a class="mono" href="<?= url('assets/' . $a['id']) ?>"><?= e($a['tag']) ?></a><small class="block muted" dir="auto"><?= e($a['name']) ?></small></div>
                    <div class="lc-days">
                        <?php foreach ($days as $d): ?><span class="<?= in_array((int) date('w', strtotime($d)), [5, 6], true) ? 'we' : '' ?>"></span><?php endforeach; ?>
                        <?php foreach ($a['bookings'] as $b):
                            $s = max(0, (int) days_between($cal['start'], max($b['starts_on'], $cal['start'])));
                            $endShown = $b['status'] === 'checked_out' && $b['ends_on'] < today() ? min(today(), $cal['end']) : min($b['ends_on'], $cal['end']);
                            $len = max(1, (int) days_between(max($b['starts_on'], $cal['start']), $endShown) + 1);
                            $late = $b['status'] === 'checked_out' && $b['ends_on'] < today(); ?>
                            <a dir="auto" class="lc-bar st-<?= e($b['status']) ?><?= $late ? ' late' : '' ?>" style="inset-inline-start:calc(<?= $s ?> / <?= $cal['days'] ?> * 100%);width:calc(<?= $len ?> / <?= $cal['days'] ?> * 100% - 4px)"
                               href="<?= url('loans', ['view' => $b['status'] === 'requested' ? 'requests' : 'active']) ?>" title="<?= e($b['number'] . ' · ' . $b['employee_name'] . ' · ' . $b['starts_on'] . ' → ' . $b['ends_on']) ?>"><?= e($b['employee_name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <p class="small muted"><span class="badge badge-warning"><?= e(t('loans.status.requested')) ?></span> <span class="badge badge-info"><?= e(t('loans.status.approved')) ?></span> <span class="badge badge-success"><?= e(t('loans.status.checked_out')) ?></span> <span class="badge badge-danger"><?= e(t('loans.overdue')) ?></span></p>

<?php else: ?>
    <div class="grid grid-3">
        <section class="card table-card <?= $view === 'active' && $canAssign ? 'span-2' : 'span-3' ?>">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>#</th><th><?= e(t('field.asset')) ?></th><th><?= e(t('field.employee_id')) ?></th><th><?= e(t('loans.dates')) ?></th><th><?= e(t('field.status')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $b): $late = $b['status'] === 'checked_out' && $b['ends_on'] < today(); ?>
                        <tr>
                            <td class="mono small"><?= e($b['number']) ?><small class="block muted"><?= e(t('loans.via.' . $b['via'])) ?></small></td>
                            <td><a href="<?= url('assets/' . $b['asset_id']) ?>" class="cell-main"><span class="cell-icon"><?= icon($b['icon'] ?? 'laptop') ?></span><span><strong class="mono"><?= e($b['tag']) ?></strong><small dir="auto"><?= e($b['asset_name']) ?></small></span></a></td>
                            <td dir="auto"><a href="<?= url('employees/' . $b['employee_id']) ?>"><?= e($b['employee_name']) ?></a><?= $b['purpose'] ? '<small class="block muted" dir="auto">' . e($b['purpose']) . '</small>' : '' ?></td>
                            <td class="small"><?= date_range($b['starts_on'], $b['ends_on']) ?><?= $late ? '<small class="block text-danger">' . e(t('loans.days_late', ['n' => (int) days_between($b['ends_on'])])) . '</small>' : '' ?></td>
                            <td><span class="badge badge-<?= $late ? 'danger' : Loans::TONE[$b['status']] ?>"><?= e(t('loans.status.' . $b['status'])) ?></span></td>
                            <td class="row-actions">
                                <?php if ($canAssign): ?>
                                    <?php if ($b['status'] === 'requested'): ?>
                                        <form method="post" action="<?= url('loans/' . $b['id'] . '/decide') ?>" class="inline-actions"><?= csrf_field() ?>
                                            <button class="btn btn-primary btn-xs" name="status" value="approved"><?= e(t('loans.approve')) ?></button>
                                            <button class="btn btn-ghost btn-xs" name="status" value="rejected"><?= e(t('loans.reject')) ?></button></form>
                                    <?php elseif ($b['status'] === 'approved'): ?>
                                        <form method="post" action="<?= url('loans/' . $b['id'] . '/checkout') ?>" class="inline-actions"><?= csrf_field() ?>
                                            <button class="btn btn-primary btn-xs" <?= $b['starts_on'] > today() ? 'disabled title="' . e(t('loans.not_ready')) . '"' : '' ?>><?= e(t('loans.hand_over')) ?></button></form>
                                        <form method="post" action="<?= url('loans/' . $b['id'] . '/decide') ?>" data-confirm="<?= e(t('loans.cancel_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-xs" name="status" value="cancelled"><?= e(t('common.cancel')) ?></button></form>
                                    <?php elseif ($b['status'] === 'checked_out'): ?>
                                        <form method="post" action="<?= url('loans/' . $b['id'] . '/checkin') ?>"><?= csrf_field() ?><button class="btn btn-primary btn-xs"><?= e(t('loans.take_back')) ?></button></form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e(t('common.nothing')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php if ($view === 'active' && $canAssign): ?>
            <section class="card scanner" data-scanner="<?= url('loans/scan') ?>" data-online-only>
                <h3 class="card-title"><?= icon('qr') ?> <?= e(t('loans.desk')) ?></h3>
                <p class="small muted"><?= e(t('loans.desk_help')) ?></p>
                <div class="scanner-view">
                    <video playsinline muted data-video></video>
                    <div class="scanner-frame"></div>
                    <button class="btn btn-primary" type="button" data-start-camera><?= icon('camera') ?> <?= e(t('audits.start_camera')) ?></button>
                </div>
                <form class="scan-manual" data-manual method="post" action="<?= url('loans/scan') ?>">
                    <?= csrf_field() ?>
                    <input type="text" name="code" placeholder="<?= e(t('audits.manual')) ?>" autocomplete="off" data-code>
                    <button class="btn btn-ghost" type="submit"><?= icon('check') ?></button>
                </form>
                <div class="scan-toast" data-toast hidden></div>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($canAssign): ?>
    <section class="card" style="margin-top:18px">
        <h3 class="card-title"><?= icon('layers') ?> <?= e(t('loans.pool')) ?></h3>
        <p class="small muted"><?= e(t('loans.pool_help')) ?></p>
        <div class="chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px">
            <?php foreach ($loanable as $a): ?>
                <form method="post" action="<?= url('loans/pool') ?>" class="chip"><?= csrf_field() ?><input type="hidden" name="asset_id" value="<?= (int) $a['id'] ?>">
                    <span class="mono"><?= e($a['tag']) ?></span> <span dir="auto"><?= e($a['name']) ?></span>
                    <button class="icon-btn" aria-label="<?= e(t('loans.remove')) ?>" title="<?= e(t('loans.remove')) ?>"><?= icon('x', 'icon icon-xs') ?></button></form>
            <?php endforeach; ?>
        </div>
        <?php if ($candidates): ?>
            <form method="post" action="<?= url('loans/pool') ?>" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="loanable" value="1">
                <select name="asset_id" required><?= select_options(array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['tag'] . ' — ' . $a['name']], $candidates), '') ?></select>
                <button class="btn btn-ghost btn-sm"><?= icon('plus') ?> <?= e(t('loans.add_to_pool')) ?></button>
            </form>
        <?php endif; ?>
    </section>

    <dialog class="modal" id="modal-new-loan">
        <form method="post" action="<?= url('loans') ?>" class="stack">
            <?= csrf_field() ?>
            <h3><?= icon('swap') ?> <?= e(t('loans.new')) ?></h3>
            <label class="field"><span><?= e(t('field.employee_id')) ?> *</span><select name="employee_id" required><?= select_options($employees, '') ?></select></label>
            <div class="form-grid">
                <label class="field"><span><?= e(t('loans.type')) ?></span><select name="category_id"><?= select_options($pool, '') ?></select></label>
                <label class="field"><span><?= e(t('loans.or_device')) ?></span><select name="asset_id"><?= select_options(array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['tag'] . ' — ' . $a['name']], $loanable), '') ?></select></label>
                <label class="field"><span><?= e(t('loans.from')) ?> *</span><input type="date" name="starts_on" required value="<?= today() ?>"></label>
                <label class="field"><span><?= e(t('loans.to')) ?> *</span><input type="date" name="ends_on" required value="<?= days_from_today(2) ?>"></label>
            </div>
            <label class="field"><span><?= e(t('loans.purpose')) ?></span><input name="purpose" maxlength="255"></label>
            <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('loans.book')) ?></button></div>
        </form>
    </dialog>
<?php endif; ?>
