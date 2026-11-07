<?php
use ITAM\Services\Sla;

page_title('WO-' . $m['id'] . ' · ' . $m['title']);
$prioTone = ['critical' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'neutral'];
$open = in_array($m['status'], ['open', 'in_progress'], true);
$canEdit = can('maintenance.edit');
?>
<div class="page-head">
    <div>
        <a href="<?= url('maintenance') ?>" class="back-link">‹ <?= e(t('nav.maintenance')) ?></a>
        <h1><span class="mono muted">WO-<?= (int) $m['id'] ?></span> <span dir="auto"><?= e($m['title']) ?></span></h1>
        <p class="wo-badges">
            <span class="badge badge-<?= $prioTone[$m['priority']] ?? 'neutral' ?>"><?= e(t('priority.' . $m['priority'])) ?></span>
            <?= status_badge($m['status'], 'maintenance.status') ?>
            <span class="badge badge-neutral"><?= e(t('maintenance.type.' . $m['type'])) ?></span>
            <?php if ($sla): ?><span class="small sla-<?= e($sla['state']) ?>"><?= icon('clock', 'icon icon-xs') ?> <?= e(Sla::label($sla)) ?></span><?php endif; ?>
        </p>
    </div>
</div>

<div class="wo-layout">
    <div class="stack">
        <a class="card wo-asset" href="<?= url('assets/' . $m['asset_id']) ?>">
            <span class="cell-icon"><?= icon($m['icon'] ?? 'box') ?></span>
            <div><strong class="mono"><?= e($m['tag']) ?></strong><span dir="auto"><?= e($m['asset_name']) ?></span>
                <small class="muted"><?= e(trim(($m['location_name'] ?? '') . ($m['employee_name'] ? ' · ' . $m['employee_name'] : '') . ($m['serial'] ? ' · ' . $m['serial'] : ''))) ?></small></div>
            <?= icon('chevron', 'icon chev-end') ?>
        </a>

        <?php if ($m['description'] || $m['reporter_name']): ?>
            <section class="card"><h3 class="card-title"><?= icon('info') ?> <?= e(t('field.problem')) ?></h3>
                <p class="pre" dir="auto"><?= nl2br(e((string) $m['description'])) ?></p>
                <?php if ($m['reporter_name']): ?><small class="muted"><?= e(t('field.reported_by', ['name' => $m['reporter_name']])) ?> · <?= e(time_ago($m['created_at'])) ?></small><?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canEdit && $open): ?>
            <section class="card wo-actions">
                <?php if (!$m['technician_id'] || (int) $m['technician_id'] !== (int) auth()['id']): ?>
                    <form method="post" action="<?= url('work-orders/' . $m['id'] . '/take') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-block"><?= icon('user') ?> <?= e(t('field.take')) ?></button></form>
                <?php endif; ?>
                <?php if ($m['status'] === 'open'): ?>
                    <form method="post" action="<?= url('maintenance/' . $m['id'] . '/status') ?>"><?= csrf_field() ?><input type="hidden" name="status" value="in_progress"><button class="btn btn-primary btn-block"><?= icon('wrench') ?> <?= e(t('field.start')) ?></button></form>
                <?php endif; ?>
                <details class="wo-complete"<?= $m['status'] === 'in_progress' ? ' open' : '' ?>>
                    <summary class="btn btn-ghost btn-block"><?= icon('check') ?> <?= e(t('field.complete')) ?></summary>
                    <form method="post" action="<?= url('maintenance/' . $m['id'] . '/status') ?>" class="stack">
                        <?= csrf_field() ?><input type="hidden" name="status" value="done">
                        <label class="field"><span><?= e(t('field.resolution')) ?></span><textarea name="resolution" rows="3" required maxlength="2000" dir="auto"></textarea></label>
                        <label class="field"><span><?= e(t('field.downtime')) ?></span><input type="number" name="downtime_hours" min="0" step="0.25" value="<?= e((string) $m['downtime_hours']) ?>"></label>
                        <label class="field"><span><?= e(t('rr.wo_cost')) ?></span><input type="number" name="cost" min="0" step="0.01" value="<?= e((string) $m['cost']) ?>"><small class="field-help"><?= e(t('rr.wo_cost_help')) ?></small></label>
                        <button class="btn btn-primary btn-block"><?= icon('check') ?> <?= e(t('field.mark_done')) ?></button>
                    </form>
                </details>
            </section>
        <?php elseif ($m['resolution']): ?>
            <section class="card"><h3 class="card-title"><?= icon('check') ?> <?= e(t('field.resolution')) ?></h3><p dir="auto"><?= nl2br(e($m['resolution'])) ?></p></section>
        <?php endif; ?>

        <section class="card" id="photos">
            <h3 class="card-title"><?= icon('camera') ?> <?= e(t('field.photos')) ?></h3>
            <div class="wo-photos">
                <?php foreach ($photos as $stage => $list): ?>
                    <div>
                        <strong class="small"><?= e(t('field.photo_' . $stage)) ?></strong>
                        <div class="photo-row">
                            <?php foreach ($list as $p): ?><a href="<?= url('files/' . $p['id']) ?>" target="_blank"><img src="<?= url('files/' . $p['id']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></a><?php endforeach; ?>
                            <?php if ($canEdit): ?>
                                <form method="post" action="<?= url('work-orders/' . $m['id'] . '/photos') ?>" enctype="multipart/form-data" class="photo-add">
                                    <?= csrf_field() ?><input type="hidden" name="stage" value="<?= $stage ?>">
                                    <label title="<?= e(t('field.add_photo')) ?>"><?= icon('plus') ?><input type="file" name="photo" accept="image/*" capture="environment" onchange="this.form.submit()" hidden></label>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
                    </section>

    </div>

    <div class="stack">
        <?= partial('comments/thread', ['entity' => 'maintenance', 'entityId' => (int) $m['id']]) ?>
        <?php if ($history): ?>
            <section class="card" id="history"><h3 class="card-title"><?= icon('history') ?> <?= e(t('wo.history')) ?></h3>
                <ul class="list compact"><?php foreach ($history as $h): ?><li><span dir="auto" class="small"><?= e($h['summary']) ?></span><small class="muted"><?= e($h['user_name']) ?> · <?= e(time_ago($h['created_at'])) ?></small></li><?php endforeach; ?></ul></section>
        <?php endif; ?>
    </div>
</div>
