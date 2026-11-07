<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Database;

/**
 * First-run installation: language, requirements, database, organisation, first administrator and demo data.
 * The wizard writes config/local.php, builds the schema and finally storage/installed.lock, after which it
 * is closed for good. Installations made before the wizard existed are recognised and locked automatically.
 */
final class Installer
{
    public const EXTENSIONS = ['pdo_sqlite', 'openssl', 'curl', 'zip', 'mbstring', 'fileinfo', 'sodium'];
    public const OPTIONAL = ['pdo_mysql', 'intl', 'gd'];

    public static function lockFile(): string
    {
        return ITAM_STORAGE . '/installed.lock';
    }

    /** Present while the wizard is installing; left behind only when an installation failed half-way. */
    public static function runningFile(): string
    {
        return ITAM_STORAGE . '/installing.flag';
    }

    public static function installed(): bool
    {
        if (is_file(self::lockFile())) {
            return true;
        }
        // An installation that started and did not finish is not installed (the wizard reopens to retry).
        if (is_file(self::runningFile())) {
            return false;
        }
        // Existing database from before the wizard: recognise it and write the lock.
        $cfg = config('db');
        try {
            if ($cfg['driver'] === 'sqlite' && (!is_file($cfg['path']) || filesize($cfg['path']) === 0)) {
                return false;
            }
            $pdo = $cfg['driver'] === 'mysql'
                ? new \PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']), $cfg['username'], $cfg['password'])
                : new \PDO('sqlite:' . $cfg['path']);
            $exists = $cfg['driver'] === 'mysql'
                ? (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn()
                : (bool) $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
        if ($exists) {
            @file_put_contents(self::lockFile(), json_encode(['installed_at' => date('c'), 'via' => 'existing database']));
        }
        return $exists;
    }

    /** @return list<array{name: string, ok: bool, required: bool, detail: string}> */
    public static function requirements(): array
    {
        $out = [['name' => 'PHP ≥ 8.2', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'required' => true, 'detail' => PHP_VERSION]];
        foreach (self::EXTENSIONS as $ext) {
            $out[] = ['name' => $ext, 'ok' => extension_loaded($ext), 'required' => true, 'detail' => ''];
        }
        foreach (self::OPTIONAL as $ext) {
            $out[] = ['name' => $ext, 'ok' => extension_loaded($ext), 'required' => false, 'detail' => ''];
        }
        // Only where the platform writes: data, config/local.php and uploaded logos. Static assets stay read-only.
        foreach (['storage/' => ITAM_STORAGE, 'config/' => ITAM_ROOT . '/config', 'public/assets/uploads/' => ITAM_ROOT . '/public/assets/uploads'] as $name => $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $out[] = ['name' => $name, 'ok' => is_writable($dir), 'required' => true, 'detail' => t('install.writable')];
        }
        return $out;
    }

    public static function requirementsMet(): bool
    {
        return !array_filter(self::requirements(), fn ($r) => $r['required'] && !$r['ok']);
    }

    /** Try a database configuration. Returns an error message or null. MySQL databases are created when missing. */
    public static function testDatabase(array $db): ?string
    {
        try {
            if ($db['driver'] === 'mysql') {
                $pdo = new \PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']), $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo->exec('USE `' . str_replace('`', '', $db['database']) . '`');
                if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() && !self::failedEarlierOn($db)) {
                    return t('install.db_not_empty');
                }
            } else {
                $dir = dirname($db['path']);
                if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                    return t('install.db_dir');
                }
                if (is_file($db['path']) && filesize($db['path']) > 0 && !self::failedEarlierOn($db)) {
                    return t('install.db_not_empty');
                }
                new \PDO('sqlite:' . $db['path']);
            }
        } catch (\Throwable $e) {
            return mb_substr($e->getMessage(), 0, 200);
        }
        return null;
    }

    /**
     * Run the installation from the wizard's answers.
     * @param array{db: array, org: array, admin: array, demo: bool} $a
     */
    /** Same database as an installation that failed half-way (recorded in the running flag). */
    private static function failedEarlierOn(array $db): bool
    {
        $flag = is_file(self::runningFile()) ? json_decode((string) file_get_contents(self::runningFile()), true) : null;
        return is_array($flag) && ($flag['target'] ?? null) === self::target($db);
    }

    private static function target(array $db): string
    {
        return $db['driver'] === 'mysql' ? "mysql://{$db['host']}:{$db['port']}/{$db['database']}" : 'sqlite://' . $db['path'];
    }

    /** Remove what a failed attempt left in its own database, so the retry starts clean. */
    private static function resetFailed(array $db): void
    {
        if (!self::failedEarlierOn($db)) {
            return;
        }
        if ($db['driver'] === 'sqlite') {
            @unlink($db['path']);
            return;
        }
        $pdo = new \PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '', $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public static function run(array $a): void
    {
        self::resetFailed($a['db']);
        file_put_contents(self::runningFile(), json_encode(['target' => self::target($a['db']), 'started' => date('c')]));
        self::writeLocalConfig($a['db'], $a['org']);
        $GLOBALS['itam_config'] = array_replace_recursive($GLOBALS['itam_config'], ['db' => $a['db'], 'app' => ['timezone' => $a['org']['timezone']]]);
        date_default_timezone_set($a['org']['timezone']);

        $db = Database::instance();
        $db->migrate($a['demo']);
        // Replace the seeded default account with the administrator chosen in the wizard.
        $adminId = (int) $db->value('SELECT id FROM users WHERE email = ?', [Seeder::DEFAULT_ADMIN_EMAIL]);
        $db->update('users', $adminId, [
            'name' => $a['admin']['name'], 'email' => strtolower($a['admin']['email']), 'locale' => $a['org']['locale'],
            'password_hash' => password_hash($a['admin']['password'], PASSWORD_DEFAULT),
        ]);
        foreach (['app.name' => $a['org']['name'], 'app.name_ar' => $a['org']['name_ar'], 'app.vendor' => $a['org']['vendor'],
                  'i18n.default' => $a['org']['locale'], 'app.timezone' => $a['org']['timezone'], 'app.currency' => $a['org']['currency'] ?? 'SAR'] as $k => $v) {
            $db->setSetting('cfg.' . $k, (string) $v);
        }
        @file_put_contents(self::lockFile(), json_encode(['installed_at' => date('c'), 'via' => 'wizard', 'version' => ITAM_VERSION]));
        @unlink(self::runningFile());
        $GLOBALS['itam_installed'] = true;
        // The welcome card on the first dashboard (it also says where ITAM comes from).
        $db->setSetting('welcome.pending', '1');
        Ledger::record('install', 'system', null, 'Installed with the setup wizard for ' . $a['org']['vendor'] . ($a['demo'] ? ' (demo data)' : ''));
    }

    /** Database and time zone go to config/local.php (kept out of version control); the rest lives in settings. */
    private static function writeLocalConfig(array $db, array $org): void
    {
        $file = ITAM_ROOT . '/config/local.php';
        $local = is_file($file) ? (require $file) : [];
        $local = array_replace_recursive(is_array($local) ? $local : [], ['db' => $db, 'app' => ['timezone' => $org['timezone']]]);
        $php = "<?php\n// Written by the installation wizard on " . date('Y-m-d H:i') . ". Holds this server's database settings.\nreturn "
            . var_export($local, true) . ";\n";
        if (file_put_contents($file, $php) === false) {
            throw new \RuntimeException(t('install.config_write'));
        }
        @chmod($file, 0640);
    }
}
