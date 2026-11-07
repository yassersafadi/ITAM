<?php
use ITAM\Services\Comments;

/** Discussion thread with @mentions. Expects $entity ('asset' | 'maintenance' | 'employee') and $entityId. */
$comments = Comments::thread($entity, $entityId);
$people = array_map(fn ($u) => ['id' => (int) $u['id'], 'name' => $u['name']], Comments::people());
usort($people, fn ($a, $b) => strcmp($a['name'], $b['name']));
?>
<section class="card comments" id="comments">
    <h3 class="card-title"><?= icon('users') ?> <?= e(t('comments.title')) ?> <span class="count"><?= count($comments) ?></span></h3>
    <ol class="comment-list">
        <?php foreach ($comments as $c): ?>
            <li>
                <span class="avatar sm"><?= e(mb_strtoupper(mb_substr((string) ($c['author'] ?? '?'), 0, 1))) ?></span>
                <div>
                    <div class="comment-meta"><strong dir="auto"><?= e($c['author'] ?? t('comments.deleted_user')) ?></strong> <small class="muted"><?= e(time_ago($c['created_at'])) ?></small>
                        <?php if ((int) $c['user_id'] === (int) (auth()['id'] ?? 0) || can('settings.manage')): ?>
                            <form method="post" action="<?= url('comments/' . $c['id'] . '/delete') ?>" class="inline" data-confirm="<?= e(t('comments.delete_confirm')) ?>"><?= csrf_field() ?><button class="link small muted"><?= e(t('common.delete')) ?></button></form>
                        <?php endif; ?></div>
                    <p dir="auto"><?= Comments::render($c['body']) ?></p>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (!$comments): ?><li class="muted small"><?= e(t('comments.none')) ?></li><?php endif; ?>
    </ol>
    <form method="post" action="<?= url('comments') ?>" class="comment-form">
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="<?= e($entity) ?>"><input type="hidden" name="entity_id" value="<?= (int) $entityId ?>">
        <div class="mention-wrap">
            <textarea name="body" rows="2" maxlength="4000" required dir="auto" placeholder="<?= e(t('comments.placeholder')) ?>" data-mention='<?= e(json_encode($people, JSON_UNESCAPED_UNICODE)) ?>'></textarea>
            <ul class="mention-menu" hidden></ul>
        </div>
        <button class="btn btn-primary btn-sm"><?= icon('check') ?> <?= e(t('comments.post')) ?></button>
    </form>
</section>
