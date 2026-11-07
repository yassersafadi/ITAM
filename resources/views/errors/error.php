<div class="empty-state error-page">
    <div class="error-code"><?= (int) $code ?></div>
    <h2><?= e($message ?: t('errors.generic')) ?></h2>
    <a class="btn btn-primary" href="<?= url('/') ?>"><?= e(t('errors.home')) ?></a>
</div>
