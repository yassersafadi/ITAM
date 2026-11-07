<?php
use ITAM\Core\Lang;

$user = auth();
$locale = Lang::locale();
$path = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', strlen(base_path())), '/');
$seen = (int) db()->setting('notif_seen_' . $user['id'], '0');
$unread = (int) db()->value('SELECT COUNT(*) FROM notifications WHERE id > ? AND (user_id IS NULL OR user_id = ?)', [$seen, $user['id']]);
$nav = \ITAM\Services\NavSections::arrange(\ITAM\Services\NavSections::menu());
$clientI18n = Lang::group('js.');
$pageTitle = page_title() ? page_title() . ' · ' : '';
?><!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e(Lang::dir()) ?>" <?= $user['theme'] !== 'system' ? 'data-theme="' . e($user['theme']) . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle . app_name()) ?></title>
    <link rel="icon" href="<?= brand_asset('favicon') ?>">
    <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
    <meta name="theme-color" content="<?= e(\ITAM\Services\Themes::primary()) ?>">
    <link rel="apple-touch-icon" href="<?= brand_asset('logo_mark') ?>">
    <?= theme_fonts() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <?= import_map() ?>
    <?= brand_style() ?>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('itam-theme');
                if (t && !document.documentElement.hasAttribute('data-theme')) document.documentElement.setAttribute('data-theme', t);
                if (localStorage.getItem('itam-sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed');
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <?php if ((string) config('app.env') !== ''): ?><div class="env-strip env-<?= e((string) config('app.env')) ?>"><strong><?= e(strtoupper((string) config('app.env'))) ?></strong><?php if (!\ITAM\Services\Notifier::outbound()): ?> · <?= e(t('env.outbound_off')) ?><?php endif; ?></div><?php endif; ?>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= url('/') ?>">
            <img src="<?= brand_asset('logo_mark') ?>" alt="" class="brand-mark">
            <span class="brand-text"><strong><?= e(app_name()) ?></strong></span>
        </a>
        <?php
        // Only the entry closest to the page lights up: /requests/catalog marks the service catalogue, not the requests above it.
        $current = '';
        foreach ($nav as $items) {
            foreach ($items as [$href]) {
                if (($href === '/' ? $path === '/' : $path === $href || str_starts_with($path, $href . '/')) && strlen($href) > strlen($current)) {
                    $current = $href;
                }
            }
        }
        $active = fn (string $href) => $href === $current ? ' active' : '';
        ?>
        <nav class="nav">
            <?php foreach ($nav as $group => $items): if (!$items) continue; ?>
                <div class="nav-group">
                    <div class="nav-label"><?= e(\ITAM\Services\NavSections::label($group)) ?></div>
                    <?php foreach ($items as [$href, $ic, $label]): ?>
                        <a href="<?= url($href) ?>" class="nav-item<?= $active($href) ?>" title="<?= e(t($label)) ?>"<?= $href === '/portal' ? ' target="_blank" rel="noopener"' : '' ?>>
                            <?= icon($ic) ?><span><?= e(t($label)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
        <?= partial('partials/promo') ?>
        <div class="sidebar-foot">
            <span class="muted small">ITAM <span dir="ltr">v<?= ITAM_VERSION ?></span> · <?= e(t('about.open_source')) ?></span>
        </div>
    </aside>
    <script>
        // Keep the sidebar scroll position between pages (restored before paint to avoid a jump).
        (function () {
            var sb = document.getElementById('sidebar');
            try {
                var y = sessionStorage.getItem('itam-sidebar-scroll');
                if (y !== null) sb.scrollTop = +y;
                addEventListener('pagehide', function () { sessionStorage.setItem('itam-sidebar-scroll', String(sb.scrollTop)); });
            } catch (e) {}
            var active = sb.querySelector('.nav-item.active');
            if (active) {
                var a = active.getBoundingClientRect(), s = sb.getBoundingClientRect();
                if (a.top < s.top || a.bottom > s.bottom) active.scrollIntoView({ block: 'center' });
            }
        })();
    </script>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn" data-action="toggle-sidebar" aria-label="<?= e(t('common.menu')) ?>"><?= icon('menu') ?></button>
            <button class="smart-search" data-action="open-palette" type="button">
                <?= icon('search') ?>
                <span><?= e(t('search.placeholder')) ?></span>
                <kbd>Ctrl K</kbd>
            </button>
            <div class="topbar-actions">
                <div class="dropdown">
                    <button class="icon-btn" data-dropdown aria-label="Language"><?= icon('globe') ?><span class="hide-sm"><?= strtoupper($locale) ?></span></button>
                    <div class="dropdown-menu">
                        <?php foreach (config('i18n.locales') as $code => $l): ?>
                            <a href="<?= url('lang/' . $code) ?>" class="<?= $code === $locale ? 'active' : '' ?>"><?= e($l['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="icon-btn" data-action="toggle-theme" aria-label="<?= e(t('common.theme')) ?>">
                    <span class="theme-light"><?= icon('moon') ?></span><span class="theme-dark"><?= icon('sun') ?></span>
                </button>
                <a class="icon-btn" href="<?= url('help/' . \ITAM\Services\Help::forPath($path)) ?>" aria-label="<?= e(t('help.this_page')) ?>" title="<?= e(t('help.this_page')) ?>"><span class="help-q">?</span></a>
                <a class="icon-btn bell" href="<?= url('notifications') ?>" aria-label="<?= e(t('nav.notifications')) ?>">
                    <?= icon('bell') ?>
                    <?php if ($unread): ?><span class="dot" data-unread><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
                </a>
                <div class="dropdown">
                    <button class="user-chip" data-dropdown>
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <span class="hide-sm"><?= e($user['name']) ?><small><?= e(role_name($user['role'])) ?></small></span>
                    </button>
                    <div class="dropdown-menu end">
                        <a href="<?= url('profile') ?>"><?= icon('user') ?> <?= e(t('nav.profile')) ?></a>
                        <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?>
                            <button type="submit"><?= icon('logout') ?> <?= e(t('nav.logout')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            <?php // Messages float over the top of the page (they take no room) and hide by themselves. ?>
            <div class="toasts" aria-live="polite">
                <?php foreach (take_flashes() as $f): ?>
                    <div class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
                        <?= icon($f['type'] === 'success' ? 'check' : ($f['type'] === 'error' ? 'alert' : 'info')) ?>
                        <span><?= e($f['message']) ?></span>
                        <button class="flash-close" data-action="dismiss" aria-label="close"><?= icon('x') ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <?= $content ?>
        </main>
        <?= partial('partials/footer', ['variant' => 'app']) ?>
    </div>
</div>
<script>
    // On wide screens the page scrolls inside .main (see app.css), which the browser does not restore on Back:
    // keep its position per address, restored before paint when returning to a page.
    (function () {
        var main = document.querySelector('.main'), key = 'itam-main-scroll:' + location.pathname + location.search;
        try {
            var nav = performance.getEntriesByType('navigation')[0], y = sessionStorage.getItem(key);
            if (nav && nav.type === 'back_forward' && y !== null) main.scrollTop = +y;
            addEventListener('pagehide', function () { sessionStorage.setItem(key, String(main.scrollTop)); });
        } catch (e) {}
    })();
</script>

<div class="palette" id="palette" hidden>
    <div class="palette-backdrop" data-action="close-palette"></div>
    <div class="palette-panel" role="dialog" aria-modal="true" aria-label="<?= e(t('search.title')) ?>">
        <div class="palette-input">
            <?= icon('search') ?>
            <input type="text" id="palette-input" autocomplete="off" placeholder="<?= e(t('search.hint')) ?>">
            <kbd>Esc</kbd>
        </div>
        <div class="palette-body" id="palette-body"></div>
        <div class="palette-foot muted small"><?= e(t('search.examples')) ?></div>
    </div>
</div>

<script>window.ITAM = <?= json_encode([
    'base' => base_path(), 'csrf' => csrf_token(), 'locale' => $locale, 'dir' => Lang::dir(),
    'i18n' => $clientI18n,
    'commands' => array_merge(...array_map(fn ($items) => array_map(fn ($i) => ['url' => url($i[0]), 'icon' => $i[1], 'label' => t($i[2])], $items), array_values($nav))),
    // What the signed-in user may do, so client-side actions (command palette) only offer what will be allowed.
    // The server still checks every request.
    'perms' => array_combine($clientPerms = ['assets.edit', 'assets.assign', 'maintenance.edit', 'audits.run', 'licenses.edit', 'reports.export'], array_map('can', $clientPerms)),
    'readOnly' => false,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= asset('vendor/qrcode.js') ?>"></script>
<script type="module" src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
