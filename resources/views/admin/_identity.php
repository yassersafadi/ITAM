<?php
/**
 * Identity tab: themes engine (gallery, builder with live preview, import/export), names, logos,
 * sign-in page design and e-mail branding. $field renders one PlatformSettings field.
 * @var callable $field
 */
use ITAM\Services\PlatformSettings as PS;
use ITAM\Services\Themes;

$activeKey = Themes::activeKey();
$cards = [];
foreach (Themes::PRESETS as $id => $t) {
    $cards['preset:' . $id] = $t + ['name' => t('theme.preset.' . $id)];
}
foreach (Themes::custom() as $id => $t) {
    $cards['custom:' . $id] = $t;
}
$fontName = fn (string $k) => Themes::FONTS[$k][0] ?? t('theme.font_system');
$base = Themes::active() + ['name' => ''];
?>
<section class="card theme-section">
    <div class="card-head">
        <h3><?= icon('sparkles') ?> <?= e(t('theme.title')) ?></h3>
        <div class="inline-actions">
            <form method="post" action="<?= url('admin/themes/import') ?>" enctype="multipart/form-data" data-theme-import>
                <?= csrf_field() ?>
                <label class="btn btn-ghost btn-sm file-btn"><?= icon('upload') ?> <?= e(t('theme.import')) ?>
                    <input type="file" name="theme" accept="application/json,.json"></label>
            </form>
            <button type="button" class="btn btn-primary btn-sm" data-theme-new data-theme='<?= e(json_encode($base, JSON_UNESCAPED_UNICODE)) ?>'><?= icon('plus') ?> <?= e(t('theme.new')) ?></button>
        </div>
    </div>
    <p class="small muted"><?= e(t('theme.intro')) ?></p>
    <div class="theme-gallery">
        <?php foreach ($cards as $key => $t): [$kind, $id] = explode(':', $key, 2); $on = $key === $activeKey; ?>
            <article class="theme-card<?= $on ? ' active' : '' ?>">
                <div class="theme-swatches" aria-hidden="true">
                    <span style="background:<?= e($t['light']['sidebar']) ?>"></span>
                    <span style="background:<?= e($t['light']['bg']) ?>"><i style="background:<?= e($t['light']['primary']) ?>"></i><i style="background:<?= e($t['light']['accent']) ?>"></i></span>
                    <span style="background:<?= e($t['dark']['bg']) ?>"><i style="background:<?= e($t['dark']['primary']) ?>"></i><i style="background:<?= e($t['dark']['accent']) ?>"></i></span>
                </div>
                <div class="theme-meta">
                    <strong dir="auto"><?= e($t['name']) ?></strong>
                    <small class="muted"><?= e($fontName($t['font'])) ?> · <?= e(t('theme.shape.' . $t['shape'])) ?> · <?= e(t('theme.density.' . $t['density'])) ?></small>
                    <?php if ($on): ?><span class="badge badge-success"><?= icon('check') ?> <?= e(t('theme.active')) ?></span>
                    <?php elseif ($kind === 'custom'): ?><span class="badge badge-neutral"><?= e(t('theme.custom')) ?></span><?php endif; ?>
                </div>
                <div class="theme-actions">
                    <?php if (!$on): ?>
                        <form method="post" action="<?= url('admin/themes/activate') ?>"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
                            <button class="btn btn-ghost btn-sm"><?= icon('check') ?> <?= e(t('theme.apply')) ?></button></form>
                    <?php endif; ?>
                    <button type="button" class="icon-btn sm" title="<?= e(t($kind === 'custom' ? 'common.edit' : 'theme.customize')) ?>" aria-label="<?= e(t($kind === 'custom' ? 'common.edit' : 'theme.customize')) ?>"
                        data-theme-edit="<?= $kind === 'custom' ? e($id) : '' ?>" data-theme='<?= e(json_encode($kind === 'custom' ? $t : ['name' => $t['name'] . ' — ' . t('theme.copy')] + $t, JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit') ?></button>
                    <a class="icon-btn sm" href="<?= url('admin/themes/export', ['key' => $key]) ?>" title="<?= e(t('theme.export')) ?>" aria-label="<?= e(t('theme.export')) ?>"><?= icon('download') ?></a>
                    <?php if ($kind === 'custom'): ?>
                        <form method="post" action="<?= url('admin/themes/' . $id . '/delete') ?>" data-confirm="<?= e(t('theme.delete_confirm', ['name' => $t['name']])) ?>"><?= csrf_field() ?>
                            <button class="icon-btn sm" aria-label="<?= e(t('common.delete')) ?>"><?= icon('trash') ?></button></form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="card theme-builder" data-theme-builder hidden>
    <form method="post" action="<?= url('admin/themes') ?>" class="theme-builder-grid">
        <?= csrf_field() ?><input type="hidden" name="id" value="" data-tb-id>
        <div class="stack">
            <h3 class="card-title"><?= icon('sliders') ?> <span data-tb-title><?= e(t('theme.builder')) ?></span></h3>
            <label class="field"><span><?= e(t('theme.name')) ?> *</span><input name="name" required maxlength="60" data-tb-name></label>
            <div class="seg seg-sm" role="tablist">
                <button type="button" class="active" data-tb-mode="light"><?= icon('sun') ?> <?= e(t('theme.light')) ?></button>
                <button type="button" data-tb-mode="dark"><?= icon('moon') ?> <?= e(t('theme.dark')) ?></button>
            </div>
            <?php foreach (['light', 'dark'] as $mode): ?>
                <div class="theme-colors" data-tb-colors="<?= $mode ?>"<?= $mode === 'dark' ? ' hidden' : '' ?>>
                    <?php foreach (Themes::ROLES as $role): ?>
                        <label class="field"><span><?= e(t('theme.role.' . $role)) ?></span>
                            <span class="color-pick"><input type="color" name="<?= $mode ?>[<?= $role ?>]" data-tb-color="<?= $mode ?>.<?= $role ?>"><code dir="ltr" data-tb-hex="<?= $mode ?>.<?= $role ?>"></code></span>
                            <small class="field-help"><?= e(t('theme.role.' . $role . '_help')) ?></small></label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="fields">
                <label class="field"><span><?= e(t('theme.font')) ?></span><select name="font" data-tb-font>
                    <?php foreach (Themes::FONTS as $k => [$family]): ?><option value="<?= $k ?>"><?= e($family ?? t('theme.font_system')) ?></option><?php endforeach; ?>
                </select></label>
                <label class="field"><span><?= e(t('theme.shape')) ?></span><select name="shape" data-tb-shape>
                    <?php foreach (array_keys(Themes::SHAPES) as $k): ?><option value="<?= $k ?>"><?= e(t('theme.shape.' . $k)) ?></option><?php endforeach; ?>
                </select></label>
                <label class="field"><span><?= e(t('theme.density')) ?></span><select name="density" data-tb-density>
                    <?php foreach (array_keys(Themes::DENSITIES) as $k): ?><option value="<?= $k ?>"><?= e(t('theme.density.' . $k)) ?></option><?php endforeach; ?>
                </select></label>
            </div>
            <div class="theme-checks" data-tb-checks aria-live="polite"></div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" data-tb-cancel><?= e(t('common.cancel')) ?></button>
                <button class="btn btn-ghost"><?= icon('check') ?> <?= e(t('common.save')) ?></button>
                <button class="btn btn-primary" name="apply" value="1"><?= icon('sparkles') ?> <?= e(t('theme.save_apply')) ?></button>
            </div>
        </div>

        <div class="theme-preview-wrap">
            <div class="theme-preview-head"><strong><?= e(t('theme.preview')) ?></strong> <small class="muted" data-tb-preview-mode><?= e(t('theme.light')) ?></small></div>
            <div class="theme-preview" data-tb-preview>
                <aside class="tp-side">
                    <img src="<?= brand_asset('logo_mark') ?>" alt="">
                    <span class="tp-nav active"><?= icon('dashboard') ?> <?= e(t('nav.dashboard')) ?></span>
                    <span class="tp-nav"><?= icon('box') ?> <?= e(t('nav.assets')) ?></span>
                    <span class="tp-nav"><?= icon('wrench') ?> <?= e(t('nav.maintenance')) ?></span>
                    <span class="tp-nav"><?= icon('chart') ?> <?= e(t('nav.reports')) ?></span>
                </aside>
                <div class="tp-main">
                    <div class="tp-top"><span class="tp-search"><?= icon('search') ?> <?= e(t('theme.preview_search')) ?></span></div>
                    <div class="tp-hero"><strong><?= e(t('theme.preview_hero')) ?></strong><span>92</span></div>
                    <div class="tp-card">
                        <div class="tp-row"><strong><?= e(t('nav.assets')) ?></strong><span class="tp-badge"><?= e(t('status.deployed')) ?></span></div>
                        <input class="tp-input" value="IT-LAP-0042" readonly tabindex="-1">
                        <p class="tp-text"><?= e(t('theme.preview_text')) ?> <a href="#" tabindex="-1" onclick="return false"><?= e(t('theme.preview_link')) ?></a></p>
                        <div class="tp-row"><span class="tp-btn"><?= icon('plus') ?> <?= e(t('assets.new')) ?></span><span class="tp-btn ghost"><?= e(t('common.cancel')) ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<form method="post" action="<?= url('admin/settings') ?>" enctype="multipart/form-data" class="grid grid-2">
    <?= csrf_field() ?><input type="hidden" name="tab" value="identity">
    <section class="card">
        <h3 class="card-title"><?= icon('tag') ?> <?= e(t('settings.names')) ?></h3>
        <div class="stack">
            <?= $field('app.name') ?>
            <?= $field('app.name_ar') ?>
            <?= $field('app.vendor') ?>
            <?= $field('app.vendor_en') ?>
            <?= $field('app.vendor_ar') ?>
        </div>
    </section>

    <section class="card">
        <h3 class="card-title"><?= icon('mail') ?> <?= e(t('theme.mail_pdf')) ?></h3>
        <?= $field('mail.branded') ?>
        <div class="mail-preview" aria-hidden="true">
            <div class="mail-preview-head"><img src="<?= brand_asset('logo_full') ?>" alt=""><span><?= e(app_name()) ?></span></div>
            <div class="mail-preview-body"><strong><?= e(t('theme.mail_sample_title')) ?></strong><p><?= e(t('theme.mail_sample_body')) ?></p><span class="mail-preview-btn"><?= e(t('theme.mail_sample_btn')) ?></span></div>
        </div>
        <p class="small muted"><?= e(t('theme.pdf_note')) ?></p>
    </section>

    <section class="card span-2">
        <h3 class="card-title"><?= icon('upload') ?> <?= e(t('settings.logos')) ?></h3>
        <div class="logo-grid">
            <?php foreach (array_diff(PS::LOGOS, ['login_bg']) as $logo): $custom = (string) config('brand.' . $logo) !== ''; ?>
                <div class="logo-slot">
                    <div class="logo-box logo-<?= $logo ?>"><img src="<?= brand_asset($logo) ?>" alt="" data-logo-preview="<?= $logo ?>"></div>
                    <strong><?= e(t('settings.f.brand.' . $logo)) ?></strong>
                    <small class="muted"><?= e(t('settings.h.brand.' . $logo)) ?></small>
                    <label class="btn btn-ghost btn-sm file-btn"><?= icon('upload') ?> <?= e(t('settings.choose_file')) ?>
                        <input type="file" name="<?= $logo ?>" accept="image/png,image/jpeg,image/webp" data-logo-input="<?= $logo ?>"></label>
                    <?php if ($custom): ?>
                        <label class="check"><input type="checkbox" name="remove_<?= $logo ?>" value="1"> <?= e(t('settings.use_default')) ?></label>
                    <?php else: ?>
                        <small class="muted"><?= e(t('settings.is_default')) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="small muted"><?= e(t('settings.logo_rules')) ?></p>
    </section>

    <section class="card span-2">
        <h3 class="card-title"><?= icon('lock') ?> <?= e(t('settings.login_page')) ?></h3>
        <div class="login-design">
            <div class="stack">
                <div class="fields">
                    <?= $field('login.layout') ?>
                    <?= $field('login.background') ?>
                </div>
                <div class="logo-slot login-bg-slot">
                    <strong><?= e(t('settings.f.brand.login_bg')) ?></strong>
                    <small class="muted"><?= e(t('settings.h.brand.login_bg')) ?></small>
                    <label class="btn btn-ghost btn-sm file-btn"><?= icon('upload') ?> <?= e(t('settings.choose_file')) ?>
                        <input type="file" name="login_bg" accept="image/png,image/jpeg,image/webp" data-logo-input="login_bg" data-max-bytes="<?= 5 * 1024 * 1024 ?>"></label>
                    <?php if ((string) config('brand.login_bg') !== ''): ?>
                        <label class="check"><input type="checkbox" name="remove_login_bg" value="1"> <?= e(t('theme.remove_image')) ?></label>
                    <?php endif; ?>
                </div>
                <?= $field('login.overlay') ?>
                <?= $field('login.headline') ?>
                <?= $field('login.points') ?>
                <?= $field('brand.login_message') ?>
            </div>
            <div class="login-mini" data-login-mini data-layout="<?= e((string) config('login.layout', 'split')) ?>" data-bg="<?= e((string) config('login.background', 'theme')) ?>"
                 style="--mini-image:url('<?= e(brand_asset('login_bg')) ?>'); --mini-overlay:<?= (float) config('login.overlay', 0.45) ?>">
                <div class="login-mini-hero"><img src="<?= brand_asset('logo_mark') ?>" alt=""><i></i><i></i><i></i></div>
                <div class="login-mini-form"><img src="<?= brand_asset('logo_full') ?>" alt=""><i></i><i></i><b></b></div>
            </div>
        </div>
    </section>

    <div class="form-actions span-2"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('common.save')) ?></button></div>
</form>
