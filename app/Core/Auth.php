<?php
declare(strict_types=1);

namespace ITAM\Core;

final class Auth
{
    /** The built-in roles. The roles in use, with their names and permissions, live in the roles table (Users › Roles). */
    public const ROLES = ['admin', 'manager', 'technician', 'auditor'];

    /** Every permission a role can be given, grouped for the Roles page. */
    public const PERMISSION_GROUPS = [
        'assets'      => ['assets.edit', 'assets.assign', 'assets.delete'],
        'maintenance' => ['maintenance.edit'],
        'inventory'   => ['licenses.edit', 'audits.run'],
        'reports'     => ['reports.export', 'ledger.view'],
        'admin'       => ['catalog.edit', 'users.manage', 'settings.manage'],
    ];

    /** Built-in permissions, used until the roles table exists. '*' grants everything. */
    private const PERMISSIONS = [
        'admin'      => ['*'],
        'manager'    => ['assets.edit', 'assets.delete', 'assets.assign', 'maintenance.edit', 'licenses.edit', 'audits.run', 'catalog.edit', 'ledger.view', 'reports.export'],
        'technician' => ['assets.edit', 'assets.assign', 'maintenance.edit', 'licenses.edit', 'audits.run', 'reports.export'],
        'auditor'    => ['audits.run', 'ledger.view', 'reports.export'],
    ];

    private static ?array $user = null;
    private static bool $loaded = false;
    /** @var array<string, array>|null slug => role row, in order */
    private static ?array $roles = null;

    /** Roles in order (the order is also the priority for Entra role groups): slug => row with decoded permissions. */
    public static function roleRows(): array
    {
        if (self::$roles === null) {
            try {
                $rows = db()->all('SELECT * FROM roles ORDER BY sort, slug');
            } catch (\Throwable) {
                $rows = [];        // before the migration: the built-in roles
            }
            self::$roles = [];
            foreach ($rows as $r) {
                $r['permissions'] = json_decode((string) $r['permissions'], true) ?: [];
                self::$roles[$r['slug']] = $r;
            }
            if (!self::$roles) {
                foreach (self::PERMISSIONS as $slug => $p) {
                    self::$roles[$slug] = ['slug' => $slug, 'permissions' => $p, 'entra_group' => null, 'name_en' => null, 'name_ar' => null];
                }
            }
        }
        return self::$roles;
    }

    /** @return list<string> role slugs, in order */
    public static function roles(): array
    {
        return array_keys(self::roleRows());
    }

    /** The signed-in user is an administrator (the only role that may create or change administrators and roles). */
    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /**
     * Someone who manages users without being an administrator may not hand out the administrator role or change
     * an administrator's account: that would let them make themselves administrator.
     */
    public static function mayManage(?array $target, ?string $newRole = null): bool
    {
        return self::isAdmin() || (($target['role'] ?? null) !== 'admin' && $newRole !== 'admin');
    }

    /** The role for new accounts when none is chosen: Technician, or the last role if that one was deleted. */
    public static function defaultRole(): string
    {
        $roles = self::roles();
        return in_array('technician', $roles, true) ? 'technician' : (string) end($roles);
    }

    /** After the Roles page saves. */
    public static function forgetRoles(): void
    {
        self::$roles = null;
    }

    /** The role's name in the current language: the one typed on the Roles page, else the built-in translation. */
    public static function roleName(?string $slug): string
    {
        $r = self::roleRows()[(string) $slug] ?? null;
        $custom = trim((string) ($r['name_' . \ITAM\Core\Lang::locale()] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $key = 'role.' . $slug;
        if (t($key) !== $key) {
            return t($key);
        }
        // A role created on the page but not named in this language: any name it has.
        foreach (['ar', 'en'] as $l) {
            if (trim((string) ($r['name_' . $l] ?? '')) !== '') {
                return trim((string) $r['name_' . $l]);
            }
        }
        return (string) $slug;
    }

    /** What a role may do, as the names of the permission groups it touches. */
    public static function roleSummary(string $slug): string
    {
        $perms = self::permissionsOf($slug);
        if (in_array('*', $perms, true)) {
            return t('roles.everything');
        }
        $groups = array_keys(array_filter(self::PERMISSION_GROUPS, fn ($g) => array_intersect($g, $perms)));
        return $groups ? implode(' · ', array_map(fn ($g) => t('permgroup.' . $g), $groups)) : t('roles.view_only');
    }

    /** The administrator always has every permission, whatever is stored. */
    public static function permissionsOf(string $slug): array
    {
        return $slug === 'admin' ? ['*'] : (self::roleRows()[$slug]['permissions'] ?? []);
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if ($id && self::idleExpired()) {
                self::logout();
                flash('warning', t('auth.session_expired'));
                $id = null;
            }
            if ($id) {
                self::$user = db()->one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
                // Background polling must not keep an idle session alive.
                if (!str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/notifications')) {
                    $_SESSION['last_activity'] = time();
                }
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = self::verifyCredentials($email, $password);
        if (!$user) {
            return false;
        }
        self::login($user);
        return true;
    }

    /** Check e-mail and password without signing in (the second factor may still be needed). */
    public static function verifyCredentials(string $email, string $password): ?array
    {
        $user = db()->one('SELECT * FROM users WHERE lower(email) = lower(?) AND active = 1', [$email]);
        return $user && password_verify($password, $user['password_hash']) ? $user : null;
    }

    /**
     * Sign-in throttling, kept in the database so a new browser session does not reset it.
     * An e-mail is locked after security.max_attempts failures within security.lockout_minutes;
     * an address (IP) after three times that many, which stops password spraying across accounts
     * without locking a whole office behind one NAT address after a few typos.
     */
    public static function throttled(string $email, string $ip): bool
    {
        $max = max(1, (int) config('security.max_attempts', 5));
        $since = date('Y-m-d H:i:s', time() - max(1, (int) config('security.lockout_minutes', 5)) * 60);
        try {
            return (int) db()->value('SELECT COUNT(*) FROM login_failures WHERE email = ? AND created_at > ?', [self::throttleEmail($email), $since]) >= $max
                || (int) db()->value('SELECT COUNT(*) FROM login_failures WHERE ip = ? AND created_at > ?', [mb_substr($ip, 0, 64), $since]) >= $max * 3;
        } catch (\PDOException) {
            return false; // table not migrated yet
        }
    }

    public static function recordFailure(string $email, string $ip): void
    {
        try {
            // Old rows are useless once outside the longest possible window.
            db()->run('DELETE FROM login_failures WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
            db()->insert('login_failures', ['email' => self::throttleEmail($email), 'ip' => mb_substr($ip, 0, 64), 'created_at' => now()]);
        } catch (\PDOException) {
        }
    }

    /** A successful sign-in clears that e-mail's failures (not the address's, so one valid account cannot reset a spray). */
    public static function clearFailures(string $email): void
    {
        try {
            db()->run('DELETE FROM login_failures WHERE email = ?', [self::throttleEmail($email)]);
        } catch (\PDOException) {
        }
    }

    private static function throttleEmail(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 190);
    }

    public static function mustEnrol2fa(): bool
    {
        $u = self::user();
        return $u !== null && config('security.require_2fa_admin') && $u['role'] === 'admin' && !(int) ($u['totp_enabled'] ?? 0);
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['locale'] = $user['locale'];
        db()->update('users', (int) $user['id'], ['last_login_at' => now()]);
        self::$user = $user;
        self::$loaded = true;
    }

    private static function idleExpired(): bool
    {
        $minutes = (int) config('security.session_idle', 0);
        return $minutes > 0 && isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $minutes * 60;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($permission === 'view') {
            return true;
        }
        $perms = self::permissionsOf((string) $user['role']);
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }
}
