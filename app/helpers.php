<?php
declare(strict_types=1);

use ITAM\Core\Auth;
use ITAM\Core\Database;
use ITAM\Core\Icons;
use ITAM\Core\Lang;

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['itam_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): Database
{
    return Database::instance();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function t(string $key, array $params = []): string
{
    return Lang::get($key, $params);
}

function base_path(): string
{
    // The built-in server reports SCRIPT_NAME relative to existing folders (e.g. /assets/…), so it is
    // only trusted when it really points at the front controller.
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server' || !str_ends_with($script, '.php')) {
        return '';
    }
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $base === '/' ? '' : $base;
}

function url(string $path = '', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query(array_filter($query, fn ($v) => $v !== null && $v !== ''));
    }
    return $u;
}

/** URL of a static file with a version that changes with each release and each edit, so browsers never keep an old copy. */
function asset(string $path): string
{
    $file = ITAM_ROOT . '/public/assets/' . ltrim($path, '/');
    $v = ITAM_VERSION . (is_file($file) ? '.' . substr((string) filemtime($file), -6) : '');
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/**
 * Import map giving every ES module its versioned URL: app.js imports './ui.js' and the others without a version,
 * and the map makes those imports fetch the current file after a release instead of a cached one.
 */
function import_map(): string
{
    static $html = null;
    if ($html === null) {
        $map = [];
        foreach (glob(ITAM_ROOT . '/public/assets/js/*.js') ?: [] as $file) {
            $name = basename($file);
            if ($name !== 'sw.js') {
                $map[url('assets/js/' . $name)] = asset('js/' . $name);
            }
        }
        $html = '<script type="importmap">' . json_encode(['imports' => $map], JSON_UNESCAPED_SLASHES) . '</script>';
    }
    return $html;
}

/** Next document number like DSP-2026-0007: highest existing number + 1, so cancelled numbers are never reused. */
function next_number(string $table, string $column, string $prefix): string
{
    $last = (string) db()->value("SELECT MAX($column) FROM $table WHERE $column LIKE ?", [$prefix . '-%']);
    $n = $last !== '' ? (int) substr($last, strrpos($last, '-') + 1) : 0;
    return sprintf('%s-%04d', $prefix, $n + 1);
}

/** Tiny inline trend line (SVG) for a series of numbers, e.g. battery health over time. */
function sparkline(array $values, int $w = 120, int $h = 28, ?float $min = null, ?float $max = null): string
{
    $values = array_values(array_map('floatval', $values));
    if (count($values) < 2) {
        return '';
    }
    $min ??= min($values);
    $max ??= max($values);
    $span = max(0.0001, $max - $min);
    $step = $w / (count($values) - 1);
    $pts = [];
    foreach ($values as $i => $v) {
        $pts[] = round($i * $step, 1) . ',' . round($h - 2 - ($v - $min) / $span * ($h - 4), 1);
    }
    $last = end($pts);
    [$lx, $ly] = explode(',', $last);
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true">'
        . '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/>'
        . '<circle cx="' . $lx . '" cy="' . $ly . '" r="2.4" fill="currentColor"/></svg>';
}

/** Platform name in the given (or current) language, as set in Settings › Identity. */
function app_name(?string $locale = null): string
{
    $locale ??= Lang::locale();
    return match ($locale) {
        'ar'    => (string) config('app.name_ar'),
        default => (string) config('app.name'),
    };
}

/** The organisation's name in a language: its own spelling when one is set (Settings › Identity), otherwise the main name. */
function vendor_name(?string $locale = null): string
{
    $locale ??= Lang::locale();
    // A Latin-script interface without its own spelling takes the English one before the main name.
    return (string) (config('app.vendor_' . $locale) ?: ($locale !== 'ar' ? config('app.vendor_en') : null) ?: config('app.vendor'));
}

/** Uploaded logo if one is set, otherwise the bundled default. The favicon falls back to the uploaded mark. */
function brand_asset(string $which): string
{
    $path = (string) config('brand.' . $which, '');
    if ($path === '' && $which === 'favicon') {
        $path = (string) config('brand.logo_mark', '');
    }
    if ($path === '' && $which === 'logo_full_dark') {
        return brand_asset('logo_full');
    }
    if ($which === 'login_bg') {
        return $path !== '' && is_file(ITAM_ROOT . '/public/assets/' . $path) ? asset($path) : '';
    }
    if ($path !== '' && is_file(ITAM_ROOT . '/public/assets/' . $path)) {
        return asset($path);
    }
    return asset(match ($which) {
        'logo_full' => 'img/logo-full.png',
        'favicon'   => 'img/favicon.png',
        default     => 'img/logo-mark.png',
    });
}

/** Inline CSS overriding the brand colour when it differs from the default. */
function brand_style(): string
{
    return \ITAM\Services\Themes::style();
}

/** Font stylesheet for the active theme. */
function theme_fonts(): string
{
    return \ITAM\Services\Themes::fontLink();
}

/**
 * Sidebar colours for a chosen background. Text, separators and scrollbar flip to dark ink on light
 * backgrounds so the menu stays readable whatever colour is picked.
 * @return array<string, string>
 */
function sidebar_colors(string $bg): array
{
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $bg)) {
        return [];
    }
    return \ITAM\Services\Themes::luminance($bg) > 0.4
        ? ['--sidebar-bg' => $bg, '--sidebar-fg' => '#0f1b33', '--sidebar-text' => '#3d4b63', '--sidebar-muted' => '#7a8aa3',
           '--sidebar-active' => 'rgba(15, 27, 51, .07)', '--sidebar-line' => 'rgba(15, 27, 51, .08)',
           '--sidebar-thumb' => 'rgba(15, 27, 51, .2)', '--sidebar-thumb-hover' => 'rgba(15, 27, 51, .35)']
        : ['--sidebar-bg' => $bg, '--sidebar-fg' => '#ffffff', '--sidebar-text' => 'rgba(255, 255, 255, .72)', '--sidebar-muted' => 'rgba(255, 255, 255, .45)',
           '--sidebar-active' => 'rgba(255, 255, 255, .09)', '--sidebar-line' => 'rgba(255, 255, 255, .08)',
           '--sidebar-thumb' => 'rgba(255, 255, 255, .18)', '--sidebar-thumb-hover' => 'rgba(255, 255, 255, .32)'];
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function days_from_today(int $days): string
{
    return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
}

function days_between(?string $from, ?string $to = null): ?int
{
    if (!$from) {
        return null;
    }
    $a = new DateTimeImmutable(substr($from, 0, 10));
    $b = new DateTimeImmutable($to ? substr($to, 0, 10) : 'today');
    return (int) $a->diff($b)->format('%r%a');
}

function fmt_date(?string $date, bool $time = false): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return e($date);
    }
    $format = (string) config('app.date_format', 'locale');
    if ($format !== 'locale') {
        return date($time ? $format . ' H:i' : $format, $ts);
    }
    $locale = Lang::locale();
    if (class_exists(IntlDateFormatter::class)) {
        $fmt = new IntlDateFormatter(
            $locale === 'ar' ? 'ar-SA-u-nu-latn-ca-gregory' : $locale,
            IntlDateFormatter::MEDIUM,
            $time ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE
        );
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date($time ? 'Y-m-d H:i' : 'Y-m-d', $ts);
}

function fmt_month(string $ym): string
{
    $ts = strtotime($ym . '-01');
    if (class_exists(IntlDateFormatter::class)) {
        $locale = Lang::locale();
        $f = new IntlDateFormatter($locale === 'ar' ? 'ar-SA-u-nu-latn-ca-gregory' : $locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM yy');
        $out = $f->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date('M y', $ts);
}

function fmt_num(float|int|null $n, int $decimals = 0): string
{
    return number_format((float) ($n ?? 0), $decimals);
}

function time_ago(?string $date): string
{
    if (!$date) {
        return t('common.never');
    }
    $diff = time() - strtotime($date);
    if ($diff < 60) return t('time.just_now');
    if ($diff < 3600) return t('time.minutes_ago', ['n' => intdiv($diff, 60)]);
    if ($diff < 86400) return t('time.hours_ago', ['n' => intdiv($diff, 3600)]);
    return t('time.days_ago', ['n' => intdiv($diff, 86400)]);
}

function redirect(string $path, array $query = []): never
{
    $target = str_starts_with($path, 'http') ? $path : url($path, $query);
    header('Location: ' . $target);
    exit;
}

function back(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref !== '' ? $ref : '/');
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        json_response(['error' => $message ?: 'Error ' . $code], $code);
    }
    echo \ITAM\Core\View::render('errors/error', ['code' => $code, 'message' => $message], Auth::check() ? 'layouts/app' : 'layouts/guest');
    exit;
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

/** A row's text in the interface language: its `<field>_ar` (etc.) when filled in, else the main value. */
function localized(array $row, string $field): string
{
    $own = trim((string) ($row[$field . '_' . Lang::locale()] ?? ''));
    return $own !== '' ? $own : (string) ($row[$field] ?? '');
}

function input_or_null(string $key): ?string
{
    $v = input($key);
    return ($v === null || $v === '') ? null : (string) $v;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function auth(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

/** A role's name in the current language (Users › Roles may rename it). */
function role_name(?string $slug): string
{
    return Auth::roleName($slug);
}

function icon(string $name, string $class = 'icon'): string
{
    return Icons::svg($name, $class);
}

function status_badge(string $status, string $group = 'status'): string
{
    $tone = match ($status) {
        'deployed', 'done', 'found', 'active', 'closed', 'ok' => 'success',
        'in_stock', 'open', 'reserved', 'low' => 'info',
        'in_repair', 'in_progress', 'misplaced', 'medium', 'discovered' => 'warning',
        'lost', 'missing', 'critical', 'high', 'unexpected' => 'danger',
        default => 'neutral',
    };
    return '<span class="badge badge-' . $tone . '">' . e(t($group . '.' . $status)) . '</span>';
}

function health_class(?int $score): string
{
    if ($score === null) return 'neutral';
    return $score >= 75 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
}

function select_options(array $rows, mixed $selected, string $valueKey = 'id', string $labelKey = 'name', bool $blank = true): string
{
    $html = $blank ? '<option value="">—</option>' : '';
    foreach ($rows as $row) {
        $v = (string) $row[$valueKey];
        $sel = ((string) $selected === $v) ? ' selected' : '';
        $html .= '<option value="' . e($v) . '"' . $sel . '>' . e($row[$labelKey]) . '</option>';
    }
    return $html;
}

function enum_options(array $values, mixed $selected, string $group, bool $blank = false): string
{
    $html = $blank ? '<option value="">' . e(t('common.any')) . '</option>' : '';
    foreach ($values as $v) {
        $sel = ((string) $selected === (string) $v) ? ' selected' : '';
        $html .= '<option value="' . e($v) . '"' . $sel . '>' . e(t($group . '.' . $v)) . '</option>';
    }
    return $html;
}

/** Sets (or reads) the current page title used by the layout. */
function page_title(?string $title = null): ?string
{
    static $current = null;
    if ($title !== null) {
        $current = $title;
    }
    return $current;
}

function partial(string $view, array $data = []): string
{
    return \ITAM\Core\View::partial($view, $data);
}

/** Start–end of a time span as two isolated dates, so it reads correctly in right-to-left text. */
function window_range(string $start, string $end): string
{
    return '<bdi>' . e(fmt_date($start, true)) . '</bdi> – <bdi>' . e(fmt_date($end, true)) . '</bdi>';
}

/** Same as window_range() for whole days (bookings), without times. */
function date_range(string $start, string $end): string
{
    return '<bdi>' . e(fmt_date($start)) . '</bdi> – <bdi>' . e(fmt_date($end)) . '</bdi>';
}


/** Locations the signed-in user is limited to, or null for every location (no limit, or no user, e.g. the scheduler). */
function scope_ids(): ?array
{
    $raw = (string) (\ITAM\Core\Auth::user()['location_scope'] ?? '');
    $ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
    return $ids ?: null;
}

/** SQL condition restricting assets (by table alias) to the user's locations. */
function scope_sql(string $alias = 'a'): string
{
    $ids = scope_ids();
    return $ids === null ? '1=1' : "$alias.location_id IN (" . implode(',', $ids) . ')';
}

function in_scope(mixed $locationId): bool
{
    $ids = scope_ids();
    return $ids === null || in_array((int) $locationId, $ids, true);
}

/**
 * A CSV cell that a spreadsheet will not run as a formula: text starting with = + - @ (or a tab or carriage return)
 * gets a leading apostrophe. Numbers, negative ones included, stay numbers.
 */
function csv_safe(mixed $v): string
{
    $s = (string) ($v ?? '');
    return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false && !is_numeric($s) ? "'" . $s : $s;
}

/** Stop a user limited to some branches from opening something of another branch. */
function scope_guard(mixed $locationId): void
{
    if (!in_scope($locationId)) {
        abort(403, t('errors.forbidden'));
    }
}

/** Branch of an employee (for receipts, clearances and the employee page). */
function employee_location(mixed $employeeId): mixed
{
    return $employeeId ? db()->value('SELECT location_id FROM employees WHERE id = ?', [(int) $employeeId]) : null;
}

/** Requests sent by scripts (offline sync, live updates) want JSON rather than a redirect. */
function wants_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** Social networks shown in the footer, in display order. */
const SOCIAL_NETWORKS = ['website', 'email', 'whatsapp', 'x', 'linkedin', 'instagram', 'youtube', 'tiktok', 'facebook'];

/** Configured social links as [key, label, href] (empty ones are left out). */
function social_links(): array
{
    $out = [];
    foreach (SOCIAL_NETWORKS as $key) {
        $v = trim((string) config('social.' . $key));
        if ($v === '') {
            continue;
        }
        $href = match ($key) {
            'email' => 'mailto:' . $v,
            'whatsapp' => 'https://wa.me/' . preg_replace('/\D+/', '', $v),
            default => $v,
        };
        $out[] = [$key, t('social.' . $key), $href];
    }
    return $out;
}

/** Filled brand marks for the footer (24×24). */
function social_icon(string $key): string
{
    $paths = [
        'x' => '<path d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.4l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.6l5.24 6.93zM17.6 20.64h2.04L6.49 3.24H4.3z"/>',
        'linkedin' => '<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zM7.12 20.45H3.56V9h3.56zM22.23 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/>',
        'youtube' => '<path d="M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.07 0 12 0 12s0 3.93.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.93 24 12 24 12s0-3.93-.5-5.81zM9.55 15.57V8.43L15.82 12z"/>',
        'facebook' => '<path d="M9.1 23.69v-7.98H6.63v-3.67H9.1v-1.58c0-4.09 1.85-5.98 5.86-5.98.4 0 .96.04 1.47.1.39.04.78.11 1.14.2v3.32a8.6 8.6 0 0 0-1.39-.05c-.7 0-1.26.1-1.67.31-.3.15-.52.36-.68.62-.26.42-.38 1-.38 1.75v1.3h3.92l-.67 3.67h-3.25v8.25C19.4 23.24 24 18.18 24 12.04 24 5.42 18.63.04 12 .04S0 5.42 0 12.04c0 5.63 3.87 10.35 9.1 11.65z"/>',
        'tiktok' => '<path d="M12.53.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03a10.4 10.4 0 0 1-5.82-1.9c-.01 2.92.01 5.84-.02 8.75a7.6 7.6 0 0 1-1.35 3.94 7.43 7.43 0 0 1-5.91 3.21 7.25 7.25 0 0 1-4.08-1.03A7.53 7.53 0 0 1 1.6 17.25c-.02-.5-.03-1-.01-1.49a7.5 7.5 0 0 1 2.58-4.96 7.45 7.45 0 0 1 6.15-1.72c.02 1.48-.04 2.96-.04 4.44a3.4 3.4 0 0 0-3.02.37 3.47 3.47 0 0 0-1.36 1.75c-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87a3.33 3.33 0 0 0 2.77-1.61c.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
        'instagram' => '<path d="M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85C2.38 3.92 3.9 2.38 7.15 2.23 8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 2.7.27.27 2.69.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.2 4.36 2.62 6.78 6.98 6.98C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c4.35-.2 6.78-2.62 6.98-6.98.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95C23.73 2.7 21.31.27 16.95.07 15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.4-11.85a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z"/>',
        'whatsapp' => '<path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.28-.1-.48-.15-.68.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.41-.07-.13-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88m8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41z"/>',
        'email' => '<path d="M2 5.5A2.5 2.5 0 0 1 4.5 3h15A2.5 2.5 0 0 1 22 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-15A2.5 2.5 0 0 1 2 18.5zm2.3-.5L12 11l7.7-6zM20 6.9l-7.39 5.75a1 1 0 0 1-1.22 0L4 6.9v11.6c0 .28.22.5.5.5h15a.5.5 0 0 0 .5-.5z"/>',
        'website' => '<path d="M12 0a12 12 0 1 0 0 24 12 12 0 0 0 0-24zm7.93 7h-3.2a17.6 17.6 0 0 0-1.38-4.32A10.03 10.03 0 0 1 19.93 7zM12 2.04c.9 1.27 1.62 2.94 2.06 4.96H9.94c.44-2.02 1.16-3.69 2.06-4.96zM2.26 14a10.1 10.1 0 0 1 0-4h3.6a20.7 20.7 0 0 0 0 4zm.81 3h3.2c.33 1.6.8 3.06 1.38 4.32A10.03 10.03 0 0 1 3.07 17zm3.2-10h-3.2a10.03 10.03 0 0 1 4.58-4.32A17.6 17.6 0 0 0 6.27 7zM12 21.96c-.9-1.27-1.62-2.94-2.06-4.96h4.12c-.44 2.02-1.16 3.69-2.06 4.96zM14.4 14H9.6a18.5 18.5 0 0 1 0-4h4.8a18.5 18.5 0 0 1 0 4zm.95 7.32c.58-1.26 1.05-2.72 1.38-4.32h3.2a10.03 10.03 0 0 1-4.58 4.32zM18.14 14a20.7 20.7 0 0 0 0-4h3.6a10.1 10.1 0 0 1 0 4z"/>',
    ];
    return '<svg class="social-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . ($paths[$key] ?? $paths['website']) . '</svg>';
}

/** A label => value map as the list the charts read: [['label' => ..., 'value' => ...], ...]. */
function chart_data(array $map): array
{
    return array_map(fn ($k, $v) => ['label' => (string) $k, 'value' => (float) $v], array_keys($map), array_values($map));
}
