<?php
/**
 * ITAM - configuration.
 * Every value can be overridden with an environment variable (ITAM_*) or a config/local.php file returning a partial
 * array (the installation wizard writes the database settings there). Most values are editable in Settings.
 */
declare(strict_types=1);

$config = [
    'app' => [
        'name'       => 'ITAM',
        'name_ar'    => 'إدارة الأصول',
        'short'      => 'ITAM',
        'vendor'     => '',
        // A copy that is not the live platform (set in local.php): 'dev' shows a strip on every page.
        'env'        => '',
        // false holds every outgoing e-mail: a test copy of live data never writes to real people.
        'outbound'   => true,
        'url'        => getenv('ITAM_URL') ?: '',
        'debug'      => (bool) (getenv('ITAM_DEBUG') ?: false),
        'timezone'   => getenv('ITAM_TIMEZONE') ?: 'Asia/Riyadh',
        'tag_prefix' => 'IT',
        'date_format' => 'locale',   // locale | Y-m-d | d/m/Y | m/d/Y | d.m.Y
        'currency'   => 'SAR',
    ],
    // The project ITAM comes from. Shown on the About page, in the footer and once after installation; never contacted.
    'promo' => [
        'name' => 'AIM',
        'url'  => 'https://demo.unifiedsofthub.com',
        'vendor' => 'Unified Software Hub',
    ],
    // Visual identity. Uploaded logos are stored under public/assets/uploads/brand.
    'brand' => [
        'color'         => '#1677e6',
        'sidebar_color' => '#0b1b3f',
        'login_message' => '',
        'footer_text'   => '',
        'logo_mark'     => '',
        'logo_full'     => '',
        'logo_full_dark' => '',
        'favicon'       => '',
        'login_bg'      => '',
    ],
    // Sign-in page design: layout split | split_reverse | centered, background theme | image | solid.
    'login' => ['layout' => 'split', 'background' => 'theme', 'overlay' => 0.45, 'headline' => '', 'points' => true],
    // Footer links (empty = hidden).
    'social' => ['website' => '', 'email' => '', 'x' => '', 'linkedin' => '', 'instagram' => '', 'youtube' => '', 'tiktok' => '', 'facebook' => ''],
    // Single sign-on with Microsoft Entra ID (or any OpenID Connect provider via 'authority').
    'sso' => [
        'enabled' => false, 'tenant' => '', 'client_id' => '', 'client_secret' => '', 'authority' => '',
        'auto_provision' => false, 'default_role' => 'technician', 'domains' => '', 'allowed_tenants' => '',
    ],
    'backup' => [
        'daily' => true,   // nightly copy from scripts/scheduler.php
        'keep'  => 14,     // number of backups retained
    ],
    'security' => [
        'password_min'    => 8,
        'session_idle'    => 120,   // minutes of inactivity before sign-out, 0 = never
        'max_attempts'    => 5,
        'lockout_minutes' => 5,
        'require_2fa_admin' => false,
    ],
    // Public fault reports from QR labels.
    'reports' => ['enabled' => true, 'per_hour' => 5],
    // Custody receipts: terms printed above the signature (empty = built-in text per language), remote link validity.
    'custody' => ['terms' => '', 'link_days' => 7],
    // Work order resolution targets in hours per priority.
    'sla' => ['critical' => 4, 'high' => 8, 'medium' => 24, 'low' => 72],
    // Loaner pool: longest booking and how far ahead employees may book.
    'loans' => ['max_days' => 14, 'ahead_days' => 60],
    // Outgoing e-mail (SMTP) and the IT distribution list.
    'mail' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => '', 'from' => '', 'branded' => true],
    'notify' => ['email' => false, 'recipients' => '', 'weekly_digest' => true],
    'db' => [
        // sqlite (default, zero setup) or mysql (MySQL / MariaDB, requires the pdo_mysql extension)
        'driver'   => getenv('ITAM_DB_DRIVER') ?: 'sqlite',
        'path'     => getenv('ITAM_DB_PATH') ?: (defined('ITAM_STORAGE') ? ITAM_STORAGE : dirname(__DIR__) . '/storage') . '/itam.sqlite',
        'host'     => getenv('ITAM_DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('ITAM_DB_PORT') ?: 3306),
        'database' => getenv('ITAM_DB_NAME') ?: 'itam',
        'username' => getenv('ITAM_DB_USER') ?: 'root',
        'password' => getenv('ITAM_DB_PASS') ?: '',
    ],
    'i18n' => [
        'default' => 'en',
        'detect_browser' => true,
        'locales' => [
            'en' => ['label' => 'English',  'dir' => 'ltr'],
            'ar' => ['label' => 'العربية',  'dir' => 'rtl'],
        ],
    ],
    // Warranty and stock alerts on the dashboard.
    'alerts' => ['warranty_warn_days' => 60],
];

$local = __DIR__ . '/local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
