<?php use ITAM\Core\Lang;
// Sign-in page design (Settings › Identity): layout, background (theme gradient, photo or plain colour), overlay, headline.
$layout = in_array(config('login.layout'), ['split', 'split_reverse', 'centered'], true) ? config('login.layout') : 'split';
$bgImage = brand_asset('login_bg');
$bg = config('login.background') === 'image' && $bgImage !== '' ? 'image' : (config('login.background') === 'solid' ? 'solid' : 'theme');
$bgStyle = $bg === 'image' ? ' style="--login-image:url(\'' . e($bgImage) . '\'); --login-overlay:' . (float) config('login.overlay', 0.45) . '"' : '';
$headline = trim((string) config('login.headline', ''));
?>
<div class="login login-<?= e($layout) ?> login-bg-<?= e($bg) ?>"<?= $layout === 'centered' ? $bgStyle : '' ?>>
    <section class="login-hero"<?= $layout !== 'centered' ? $bgStyle : '' ?>>
        <div class="login-hero-inner">
            <img src="<?= brand_asset('logo_mark') ?>" alt="" class="login-mark">
            <h1><?= e(app_name(Lang::locale() === 'ar' ? 'en' : Lang::locale())) ?></h1>
            <p class="ar-name" dir="rtl" lang="ar"><?= e(app_name('ar')) ?></p>
            <?php if ($headline !== ''): ?><p class="login-headline" dir="auto"><?= e($headline) ?></p><?php endif; ?>
            <?php if (config('login.points', true)): ?>
            <ul class="login-points">
                <li><?= icon('heart') ?> <?= e(t('login.point1')) ?></li>
                <li><?= icon('share') ?> <?= e(t('login.point2')) ?></li>
                <li><?= icon('sparkles') ?> <?= e(t('login.point3')) ?></li>
                <li><?= icon('shield') ?> <?= e(t('login.point4')) ?></li>
            </ul>
            <?php endif; ?>
        </div>
    </section>
    <section class="login-form-wrap">
        <div class="login-langs">
            <?php foreach (config('i18n.locales') as $code => $l): ?>
                <a href="<?= url('lang/' . $code) ?>" class="<?= $code === Lang::locale() ? 'active' : '' ?>"><?= e($l['label']) ?></a>
            <?php endforeach; ?>
        </div>
        <form class="login-card" method="post" action="<?= url('login') ?>">
            <?= csrf_field() ?>
            <?php if ((string) config('brand.logo_full_dark') !== ''): ?>
                <img src="<?= brand_asset('logo_full') ?>" alt="<?= e(vendor_name()) ?>" class="login-logo theme-light has-dark">
                <img src="<?= brand_asset('logo_full_dark') ?>" alt="<?= e(vendor_name()) ?>" class="login-logo theme-dark has-dark">
            <?php else: ?>
                <img src="<?= brand_asset('logo_full') ?>" alt="<?= e(vendor_name()) ?>" class="login-logo">
            <?php endif; ?>
            <h2><?= e(t('login.title')) ?></h2>
            <p class="muted"><?= e(t('login.subtitle')) ?></p>
            <?php if ($msg = trim((string) config('brand.login_message'))): ?>
                <div class="login-message"><?= icon('info') ?><span><?= e($msg) ?></span></div>
            <?php endif; ?>
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>"><?= icon('alert') ?><span><?= e($f['message']) ?></span></div>
            <?php endforeach; ?>
            <label class="field">
                <span><?= e(t('field.email')) ?></span>
                <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username">
            </label>
            <label class="field">
                <span><?= e(t('field.password')) ?></span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <a class="link small login-forgot" href="<?= url('forgot-password') ?>"><?= e(t('reset.forgot')) ?></a>
            <button class="btn btn-primary btn-block" type="submit"><?= e(t('login.submit')) ?></button>
            <?php if (\ITAM\Services\Oidc::enabled()): ?>
                <div class="or-divider"><span><?= e(t('sso.or')) ?></span></div>
                <a class="btn btn-ghost btn-block sso-btn" href="<?= url('auth/sso') ?>"><svg class="icon" viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg> <?= e(t('sso.button')) ?></a>
            <?php endif; ?>
        </form>
        <?= partial('partials/footer', ['variant' => 'login']) ?>
    </section>
</div>
<?php unset($_SESSION['_old']); ?>
