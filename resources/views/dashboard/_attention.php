<?php
/** "Needs your attention": one prioritised list with a link to each matching list. */
$tone = ['critical' => 'danger', 'high' => 'danger', 'medium' => 'warning', 'low' => 'info'];
$byModule = array_count_values(array_column($attention, 'module'));
?>
<div class="card dash-card" data-live-part="attention">
    <div class="card-head"><h3><?= icon('alert') ?> <?= e(t('dash.w.attention')) ?> <span class="count"><?= count($attention) ?></span></h3>
    </div>
    <?php if (!$attention): ?>
        <div class="empty-state small"><?= icon('check') ?><p><?= e(t('dash.nothing_urgent')) ?></p></div>
    <?php else: ?>
        <?php if (count($byModule) > 1): ?>
            <div class="att-filter" role="group" aria-label="<?= e(t('ov.filter')) ?>">
                <button type="button" class="chip active" data-att-filter="" aria-pressed="true"><?= e(t('ov.all')) ?> <b><?= count($attention) ?></b></button>
                <?php foreach ($byModule as $m => $n): ?>
                    <button type="button" class="chip" data-att-filter="<?= e($m) ?>" aria-pressed="false"><?= e(t('ov.m.' . $m)) ?> <b><?= $n ?></b></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <ul class="attention-list">
            <?php foreach ($attention as $i => $a): ?>
                <li class="att-<?= $tone[$a['severity']] ?>" data-module="<?= e($a['module']) ?>"<?= $i >= 8 ? ' hidden' : '' ?>>
                    <span class="att-icon"><?= icon($a['icon']) ?></span>
                    <span class="att-text"><strong><?= e($a['title']) ?></strong><small><?= e(t('severity.' . $a['severity'])) ?></small></span>
                    <a class="btn btn-ghost btn-sm" href="<?= e($a['link']) ?>"><?= e($a['action']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($attention) > 8): ?><button type="button" class="btn btn-ghost btn-sm att-more" data-att-more><?= e(t('ov.show_all', ['n' => count($attention)])) ?></button><?php endif; ?>
    <?php endif; ?>
</div>
