<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Consistent online copies of the database, kept in storage/backups with simple retention.
 * SQLite: VACUUM INTO. MySQL/MariaDB: mysqldump (single transaction, no locks), gzip-compressed.
 */
final class Backup
{
    private const PATTERN = '/^itam-\d{8}-\d{6}(-auto|-before-update)?\.(sqlite|sql\.gz)$/';

    public static function dir(): string
    {
        return ITAM_STORAGE . '/backups';
    }

    public static function supported(): bool
    {
        return db()->driver === 'sqlite' || (db()->driver === 'mysql' && self::mysqldump() !== null);
    }

    /** Create a backup and apply retention. Returns the file name. */
    public static function create(bool $automatic = false, string $suffix = ''): string
    {
        if (!self::supported()) {
            throw new \RuntimeException(t('backup.unsupported'));
        }
        if (!is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        $base = 'itam-' . date('Ymd-His') . ($automatic ? '-auto' : $suffix);
        if (db()->driver === 'sqlite') {
            $name = $base . '.sqlite';
            // VACUUM INTO writes a compact, transactionally consistent copy while the platform stays online.
            db()->pdo->exec('VACUUM INTO ' . db()->pdo->quote(self::dir() . '/' . $name));
        } else {
            $name = $base . '.sql.gz';
            self::dumpMysql(self::dir() . '/' . $name);
        }
        self::prune((int) config('backup.keep', 14));
        return $name;
    }

    /** @return list<array{name: string, size: int, time: int, auto: bool}> newest first */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/itam-*') ?: [] as $file) {
            $name = basename($file);
            if (preg_match(self::PATTERN, $name)) {
                $out[] = ['name' => $name, 'size' => (int) filesize($file), 'time' => (int) filemtime($file), 'auto' => str_contains($name, '-auto')];
            }
        }
        usort($out, fn ($a, $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    /** Absolute path for a valid backup name, or null. Names are matched strictly to prevent path traversal. */
    public static function path(string $name): ?string
    {
        $path = self::dir() . '/' . $name;
        return preg_match(self::PATTERN, $name) && is_file($path) ? $path : null;
    }

    public static function delete(string $name): bool
    {
        $path = self::path($name);
        return $path !== null && @unlink($path);
    }

    /** Retention applies to automatic (daily) backups only; manual ones stay until someone deletes them. */
    private static function prune(int $keep): void
    {
        $auto = array_values(array_filter(self::all(), fn ($b) => $b['auto']));
        foreach (array_slice($auto, max(1, $keep)) as $old) {
            @unlink(self::dir() . '/' . $old['name']);
        }
    }

    /** mysqldump on this server, or null (also when the host forbids exec()). */
    private static function mysqldump(): ?string
    {
        static $bin = false;
        if ($bin !== false) {
            return $bin;
        }
        if (!function_exists('exec')) {
            return $bin = null;
        }
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/bin/mariadb-dump', 'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe', 'C:/xampp/mysql/bin/mysqldump.exe'] as $c) {
            if (is_file($c)) {
                return $bin = $c;
            }
        }
        @exec((PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v') . ' mysqldump 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'), $out, $code);
        return $bin = ($code === 0 && !empty($out[0]) ? trim($out[0]) : null);
    }

    /** Dump through a private option file (the password never appears in the process list), then gzip. */
    private static function dumpMysql(string $target): void
    {
        $c = (array) config('db');
        $cnf = tempnam(sys_get_temp_dir(), 'itam');
        @chmod($cnf, 0600);
        file_put_contents($cnf, "[client]\nuser=\"" . addcslashes((string) ($c['username'] ?? ''), '"\\') . "\"\npassword=\"" . addcslashes((string) ($c['password'] ?? ''), '"\\')
            . "\"\nhost=\"" . addcslashes((string) ($c['host'] ?? '127.0.0.1'), '"\\') . "\"\nport=" . (int) ($c['port'] ?? 3306) . "\n");
        $sql = $target . '.tmp';
        $cmd = escapeshellarg((string) self::mysqldump()) . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' --single-transaction --quick --no-tablespaces --skip-lock-tables ' . escapeshellarg((string) ($c['database'] ?? 'itam'))
            . ' > ' . escapeshellarg($sql) . ' 2>&1';
        exec($cmd, $out, $code);
        @unlink($cnf);
        if ($code !== 0 || !is_file($sql) || filesize($sql) === 0) {
            $err = is_file($sql) ? mb_substr((string) file_get_contents($sql, false, null, 0, 300), 0, 300) : '';
            @unlink($sql);
            throw new \RuntimeException('mysqldump failed' . ($err !== '' ? ': ' . $err : ''));
        }
        $in = fopen($sql, 'rb');
        $gz = gzopen($target, 'wb6');
        while (!feof($in)) {
            gzwrite($gz, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($gz);
        @unlink($sql);
    }
}
