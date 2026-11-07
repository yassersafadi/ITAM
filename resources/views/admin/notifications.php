<?php page_title(t('nav.notifications')); $seen = (int) db()->setting('notif_seen_' . auth()['id'], '0'); ?>
<div class="page-head">
    <div>
        <h1><?= icon('bell', 'icon icon-title') ?> <?= e(t('nav.notifications')) ?></h1>
        <p class="muted"><?= e(t('notifications.subtitle')) ?></p>
    </div>
    <form method="post" action="<?= url('notifications/read') ?>" class="page-actions"><?= csrf_field() ?>
        <button class="btn btn-ghost"><?= icon('check') ?> <?= e(t('notifications.mark_read')) ?></button></form>
</div>
<section class="card">
    <ul class="notif-list">
        <?php foreach ($rows as $n): ?>
            <li class="<?= (int) $n['id'] > $seen ? 'unread' : '' ?>">
                <span class="insight-icon"><?= icon(match ($n['type']) { 'drift' => 'swap', 'discovered' => 'agent', 'spof' => 'share', 'license_over', 'license_expiring' => 'key', 'pm_overdue' => 'wrench', 'high_risk' => 'activity', 'automation' => 'zap', default => 'bell' }) ?></span>
                <div>
                    <strong><?= e($n['title']) ?></strong>
                    <?php if ($n['body']): ?><p class="small muted"><?= e($n['body']) ?></p><?php endif; ?>
                    <small class="muted"><?= e(time_ago($n['created_at'])) ?></small>
                </div>
                <?php if ($n['link']): ?><a class="btn btn-ghost btn-sm" href="<?= e($n['link']) ?>"><?= e(t('common.review')) ?> ›</a><?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (!$rows): ?><li class="muted"><?= e(t('common.nothing')) ?></li><?php endif; ?>
    </ul>
</section>
