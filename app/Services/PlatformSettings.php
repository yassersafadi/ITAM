<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Platform settings edited from Admin › Settings.
 * Values are stored in the settings table as "cfg.<config key>" and layered over config/config.php
 * at boot, so every config() call sees the administrator's choice without further changes.
 */
final class PlatformSettings
{
    /** Settings menu: tabs grouped by subject, in menu order. Every tab of TABS appears in exactly one group. */
    public const GROUPS = [
        'appearance' => ['identity', 'footer'],
        'platform'   => ['general', 'security', 'sso', 'backup', 'integrations'],
        'operations' => ['service', 'notifications'],
    ];
    public const TABS = ['identity', 'footer', 'general', 'security', 'sso', 'service', 'notifications', 'backup', 'integrations'];

    public const DATE_FORMATS = ['locale', 'Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y'];

    public const LOGOS = ['logo_mark', 'logo_full', 'logo_full_dark', 'favicon', 'login_bg'];

    /** Uploaded logos, relative to public/assets (each organisation has its own folder in multi-organisation mode). */
    private static function logoDir(): string
    {
        return 'uploads/brand';
    }
    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;
    /** The login background is a photo, so it may be larger than a logo. */
    private const BACKGROUND_MAX_BYTES = 5 * 1024 * 1024;
    private const LOGO_TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

    /**
     * Editable keys: tab, type and limits. Types: text, textarea, color, select, bool, int, float, percent, url.
     * Percent values are stored as a fraction (0.6) and edited as a percentage (60).
     */
    public const FIELDS = [
        'app.name'          => ['tab' => 'identity', 'type' => 'text', 'required' => true, 'max' => 80],
        'app.name_ar'       => ['tab' => 'identity', 'type' => 'text', 'required' => true, 'max' => 80],
        'app.vendor'        => ['tab' => 'identity', 'type' => 'text', 'required' => true, 'max' => 80],
        'app.vendor_en'     => ['tab' => 'identity', 'type' => 'text', 'max' => 80],
        'app.vendor_ar'     => ['tab' => 'identity', 'type' => 'text', 'max' => 80],
        'brand.login_message' => ['tab' => 'identity', 'type' => 'textarea', 'max' => 300],
        // Brand colours now live in the themes engine (Themes); old brand.color / brand.sidebar_color values still load.
        'login.layout'        => ['tab' => 'identity', 'type' => 'select'],
        'login.background'    => ['tab' => 'identity', 'type' => 'select'],
        'login.overlay'       => ['tab' => 'identity', 'type' => 'percent', 'min' => 0, 'max' => 85],
        'login.headline'      => ['tab' => 'identity', 'type' => 'text', 'max' => 120],
        'login.points'        => ['tab' => 'identity', 'type' => 'bool'],
        'mail.branded'        => ['tab' => 'identity', 'type' => 'bool'],

        'brand.footer_text'   => ['tab' => 'footer', 'type' => 'textarea', 'max' => 300],
        'social.website'      => ['tab' => 'footer', 'type' => 'url', 'max' => 300],
        'social.email'        => ['tab' => 'footer', 'type' => 'email', 'max' => 190],
        'social.whatsapp'     => ['tab' => 'footer', 'type' => 'text', 'max' => 20, 'pattern' => '/^\+?[0-9 ]{7,20}$/'],
        'social.x'            => ['tab' => 'footer', 'type' => 'url', 'max' => 300],
        'social.linkedin'     => ['tab' => 'footer', 'type' => 'url', 'max' => 300],
        'social.instagram'    => ['tab' => 'footer', 'type' => 'url', 'max' => 300],
        'social.youtube'      => ['tab' => 'footer', 'type' => 'url', 'max' => 300],
        'social.tiktok'       => ['tab' => 'footer', 'type' => 'url', 'max' => 300],
        'social.facebook'     => ['tab' => 'footer', 'type' => 'url', 'max' => 300],

        'i18n.default'        => ['tab' => 'general', 'type' => 'select'],
        'i18n.detect_browser' => ['tab' => 'general', 'type' => 'bool'],
        'app.timezone'        => ['tab' => 'general', 'type' => 'select'],
        'app.date_format'     => ['tab' => 'general', 'type' => 'select'],
        'app.tag_prefix'      => ['tab' => 'general', 'type' => 'text', 'pattern' => '/^[A-Z0-9]{2,8}$/'],
        'app.url'             => ['tab' => 'general', 'type' => 'url', 'max' => 200],
        'app.currency'        => ['tab' => 'general', 'type' => 'select'],

        'security.password_min'    => ['tab' => 'security', 'type' => 'int', 'min' => 8, 'max' => 64],
        'security.session_idle'    => ['tab' => 'security', 'type' => 'int', 'min' => 0, 'max' => 1440],
        'security.max_attempts'    => ['tab' => 'security', 'type' => 'int', 'min' => 3, 'max' => 20],
        'security.lockout_minutes' => ['tab' => 'security', 'type' => 'int', 'min' => 1, 'max' => 240],
        'security.require_2fa_admin' => ['tab' => 'security', 'type' => 'bool'],

        'sso.enabled'        => ['tab' => 'sso', 'type' => 'bool'],
        'sso.tenant'         => ['tab' => 'sso', 'type' => 'text', 'max' => 120],
        'sso.client_id'      => ['tab' => 'sso', 'type' => 'text', 'max' => 120],
        'sso.client_secret'  => ['tab' => 'sso', 'type' => 'secret', 'max' => 300],
        'sso.authority'      => ['tab' => 'sso', 'type' => 'url', 'max' => 300],
        'sso.allowed_tenants' => ['tab' => 'sso', 'type' => 'text', 'max' => 400],
        'sso.domains'        => ['tab' => 'sso', 'type' => 'text', 'max' => 300],
        'sso.auto_provision' => ['tab' => 'sso', 'type' => 'bool'],
        'sso.default_role'   => ['tab' => 'sso', 'type' => 'select'],



        'reports.enabled'  => ['tab' => 'service', 'type' => 'bool'],
        'custody.terms'     => ['tab' => 'service', 'type' => 'textarea', 'max' => 3000],
        'custody.link_days' => ['tab' => 'service', 'type' => 'int', 'min' => 1, 'max' => 30],
        'reports.per_hour' => ['tab' => 'service', 'type' => 'int', 'min' => 1, 'max' => 50],
        'sla.critical'     => ['tab' => 'service', 'type' => 'int', 'min' => 1, 'max' => 720],
        'sla.high'         => ['tab' => 'service', 'type' => 'int', 'min' => 1, 'max' => 720],
        'sla.medium'       => ['tab' => 'service', 'type' => 'int', 'min' => 1, 'max' => 720],
        'sla.low'          => ['tab' => 'service', 'type' => 'int', 'min' => 1, 'max' => 720],
        'loans.max_days'   => ['tab' => 'service', 'type' => 'int', 'min' => 1, 'max' => 90],
        'loans.ahead_days' => ['tab' => 'service', 'type' => 'int', 'min' => 7, 'max' => 365],






        'notify.email'          => ['tab' => 'notifications', 'type' => 'bool'],
        'mail.from'             => ['tab' => 'notifications', 'type' => 'email', 'max' => 190],
        'mail.host'             => ['tab' => 'notifications', 'type' => 'text', 'max' => 190],
        'mail.port'             => ['tab' => 'notifications', 'type' => 'int', 'min' => 1, 'max' => 65535],
        'mail.encryption'       => ['tab' => 'notifications', 'type' => 'select'],
        'mail.username'         => ['tab' => 'notifications', 'type' => 'text', 'max' => 190],
        'mail.password'         => ['tab' => 'notifications', 'type' => 'secret', 'max' => 190],
        'notify.recipients'     => ['tab' => 'notifications', 'type' => 'emails', 'max' => 1000],
        'notify.weekly_digest'  => ['tab' => 'notifications', 'type' => 'bool'],


        'backup.daily' => ['tab' => 'backup', 'type' => 'bool'],
        'backup.keep'  => ['tab' => 'backup', 'type' => 'int', 'min' => 1, 'max' => 365],
    ];

    /** Layer stored values over the file configuration. Called once from bootstrap. */
    public static function apply(): void
    {
        $GLOBALS['itam_config_base'] ??= $GLOBALS['itam_config'];
        $rows = db()->all("SELECT skey, svalue FROM settings WHERE skey LIKE 'cfg.%'");
        foreach ($rows as $row) {
            $key = substr($row['skey'], 4);
            $type = self::FIELDS[$key]['type'] ?? (str_starts_with($key, 'brand.') ? 'text' : null);
            if ($type !== null) {
                self::put($key, self::cast($type, $type === 'secret' ? self::unseal((string) $row['svalue']) : (string) $row['svalue']));
            }
        }
    }

    public static function fields(string $tab): array
    {
        return array_filter(self::FIELDS, fn ($f) => $f['tab'] === $tab);
    }

    /** Value as shown in the form (percentages as whole numbers). */
    public static function formValue(string $key): string
    {
        $v = config($key);
        return match (self::FIELDS[$key]['type']) {
            'percent' => (string) round((float) $v * 100),
            'bool'    => $v ? '1' : '0',
            'secret'  => '',
            default   => (string) $v,
        };
    }

    public static function default(string $key): mixed
    {
        $value = $GLOBALS['itam_config_base'] ?? $GLOBALS['itam_config'];
        foreach (explode('.', $key) as $part) {
            $value = $value[$part] ?? null;
        }
        return $value;
    }

    public static function options(string $key): array
    {
        return match ($key) {
            'i18n.default'    => array_map(fn ($l) => $l['label'], config('i18n.locales')),
            'app.currency'    => array_combine(\ITAM\Controllers\InstallController::CURRENCIES, \ITAM\Controllers\InstallController::CURRENCIES),
            'app.timezone'    => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()),
            'mail.encryption' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => t('settings.no_encryption')],
            'login.layout'    => array_combine(['split', 'split_reverse', 'centered'], array_map(fn ($k) => t('login.layout.' . $k), ['split', 'split_reverse', 'centered'])),
            'login.background' => array_combine(['theme', 'image', 'solid'], array_map(fn ($k) => t('login.bg.' . $k), ['theme', 'image', 'solid'])),
            'sso.default_role' => array_combine(\ITAM\Core\Auth::roles(), array_map(fn ($r) => role_name($r), \ITAM\Core\Auth::roles())),
            'app.date_format' => array_combine(self::DATE_FORMATS, array_map(
                fn ($f) => $f === 'locale' ? t('settings.date_locale') : date($f, strtotime('2026-03-14')) . '  (' . $f . ')',
                self::DATE_FORMATS
            )),
            default => [],
        };
    }

    /**
     * Validate and store one tab's fields from $input.
     * @return array{0: array<string,string>, 1: array<string,mixed>} [errors by key, changed values]
     */
    public static function save(string $tab, array $input): array
    {
        $errors = [];
        $values = [];
        foreach (self::fields($tab) as $key => $f) {
            $name = str_replace('.', '__', $key);
            $raw = $f['type'] === 'bool' ? (empty($input[$name]) ? '0' : '1') : trim((string) ($input[$name] ?? ''));
            if ($key === 'app.tag_prefix') {
                $raw = strtoupper($raw);
            }
            if ($f['type'] === 'secret' && $raw === '') {
                continue;       // blank keeps the stored secret
            }
            $error = self::check($key, $f, $raw);
            if ($error !== null) {
                $errors[$key] = $error;
                continue;
            }
            $values[$key] = $f['type'] === 'percent' ? (string) ((float) $raw / 100) : $raw;
        }
        if ($errors) {
            return [$errors, []];
        }
        $changed = [];
        foreach ($values as $key => $v) {
            $secret = self::FIELDS[$key]['type'] === 'secret';
            if ((string) self::formValueRaw($key) !== $v) {
                $changed[$key] = $secret ? '(changed)' : $v;   // never write secrets to the ledger
            }
            db()->setSetting('cfg.' . $key, $secret ? self::seal($v) : $v);
            self::put($key, self::cast(self::FIELDS[$key]['type'], $v));
        }
        return [[], $changed];
    }

    /** Remove stored values for a tab so the file defaults apply again. */
    public static function reset(string $tab): void
    {
        foreach (array_keys(self::fields($tab)) as $key) {
            db()->run('DELETE FROM settings WHERE skey = ?', ['cfg.' . $key]);
        }
        if ($tab === 'intelligence') {
            db()->run("DELETE FROM settings WHERE skey = 'grid_factor'");
        }
        if ($tab === 'identity') {
            foreach (self::LOGOS as $logo) {
                self::removeLogo($logo);
            }
        }
    }

    /** Store an uploaded logo. Returns an error message or null. */
    public static function storeLogo(string $which, array $file): ?string
    {
        if (!in_array($which, self::LOGOS, true) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return t('settings.logo_failed');
        }
        if ($file['size'] > ($which === 'login_bg' ? self::BACKGROUND_MAX_BYTES : self::LOGO_MAX_BYTES)) {
            return t('settings.logo_too_big');
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = $info ? (self::LOGO_TYPES[$info[2]] ?? null) : null;
        if ($ext === null) {
            return t('settings.logo_type');
        }
        $dir = ITAM_ROOT . '/public/assets/' . self::logoDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = $which . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            return t('settings.logo_failed');
        }
        self::removeLogo($which);
        db()->setSetting('cfg.brand.' . $which, self::logoDir() . '/' . $name);
        self::put('brand.' . $which, self::logoDir() . '/' . $name);
        return null;
    }

    public static function removeLogo(string $which): void
    {
        $current = (string) db()->setting('cfg.brand.' . $which, '');
        if ($current !== '' && str_starts_with($current, self::logoDir() . '/')) {
            @unlink(ITAM_ROOT . '/public/assets/' . $current);
        }
        db()->run('DELETE FROM settings WHERE skey = ?', ['cfg.brand.' . $which]);
        self::put('brand.' . $which, '');
    }

    private static function check(string $key, array $f, string $raw): ?string
    {
        $field = t('settings.f.' . $key);
        if ($raw === '') {
            return !empty($f['required']) || in_array($f['type'], ['int', 'float', 'percent', 'color'], true)
                || ($f['type'] === 'select' && !array_key_exists('', self::options($key)))
                ? t('validation.required', ['field' => $field]) : null;
        }
        $ok = match ($f['type']) {
            'int', 'percent' => filter_var($raw, FILTER_VALIDATE_INT) !== false && (int) $raw >= $f['min'] && (int) $raw <= $f['max'],
            'float'  => is_numeric($raw) && (float) $raw >= $f['min'] && (float) $raw <= $f['max'],
            'color'  => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $raw),
            'select' => array_key_exists($raw, self::options($key)),
            'url'    => (bool) filter_var($raw, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $raw),
            'email'  => (bool) filter_var($raw, FILTER_VALIDATE_EMAIL),
            'emails' => !array_filter(self::emailList($raw), fn ($m) => !filter_var($m, FILTER_VALIDATE_EMAIL)),
            default  => mb_strlen($raw) <= ($f['max'] ?? 255) && (!isset($f['pattern']) || preg_match($f['pattern'], $raw)),
        };
        if ($ok) {
            return null;
        }
        return isset($f['min']) ? t('settings.range', ['field' => $field, 'min' => $f['min'], 'max' => $f['max']])
            : t('settings.invalid', ['field' => $field]);
    }

    /** Encrypt a secret at rest (AES-256-GCM) with a key kept in storage/, outside the database. */
    public static function seal(string $plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'enc:' . base64_encode($iv . $tag . $cipher);
    }

    public static function unseal(string $stored): string
    {
        if (!str_starts_with($stored, 'enc:')) {
            return $stored;
        }
        $raw = base64_decode(substr($stored, 4), true) ?: '';
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }

    private static function key(): string
    {
        $file = ITAM_STORAGE . '/app.key';
        if (!is_file($file)) {
            file_put_contents($file, bin2hex(random_bytes(32)));
            @chmod($file, 0600);
        }
        return hex2bin(trim((string) file_get_contents($file))) ?: '';
    }

    /** Split a list of addresses separated by commas or white space. */
    public static function emailList(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $raw) ?: [])));
    }

    private static function formValueRaw(string $key): string
    {
        $v = config($key);
        return is_bool($v) ? ($v ? '1' : '0') : (string) $v;
    }

    private static function cast(string $type, string $v): mixed
    {
        return match ($type) {
            'int'  => (int) $v,
            'float', 'percent' => (float) $v,
            'bool' => $v === '1',
            default => $v,
        };
    }

    private static function put(string $key, mixed $value): void
    {
        $ref = &$GLOBALS['itam_config'];
        foreach (explode('.', $key) as $part) {
            if (!is_array($ref)) {
                $ref = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }
}
