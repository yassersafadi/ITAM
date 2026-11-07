<?php
declare(strict_types=1);

namespace ITAM\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. All date math happens in PHP so queries stay portable between SQLite and MySQL.
 */
final class Database
{
    private static ?Database $instance = null;
    public readonly PDO $pdo;
    public readonly string $driver;

    private function __construct()
    {
        $cfg = config('db');
        $this->driver = $cfg['driver'];
        if ($this->driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']);
            $this->pdo = new PDO($dsn, $cfg['username'], $cfg['password']);
        } else {
            $dir = dirname($cfg['path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $this->pdo = new PDO('sqlite:' . $cfg['path']);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Create the schema on an empty database (seeding demo data when $demo), then apply pending migrations. */
    public function migrate(?bool $demo = null): void
    {
        $exists = $this->driver === 'mysql'
            ? (bool) $this->value("SHOW TABLES LIKE 'users'")
            : (bool) $this->value("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (!$exists) {
            $this->execFile(ITAM_ROOT . '/database/schema.sql');
        }
        $this->applyMigrations();
        if (!$exists) {
            (new \ITAM\Services\Seeder($this))->run($demo ?? (bool) (getenv('ITAM_SEED_DEMO') ?: true));
        }
    }

    /** Run database/migrations/*.sql files not applied yet, in name order. */
    private function applyMigrations(): void
    {
        $files = glob(ITAM_ROOT . '/database/migrations/*.sql') ?: [];
        if (!$files) {
            return;
        }
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, applied_at VARCHAR(20) NOT NULL)');
        sort($files);
        $pending = fn () => array_values(array_diff(array_map('basename', $files), array_column($this->all('SELECT name FROM migrations'), 'name')));
        if (!$pending()) {
            return;
        }
        // Right after an update several requests can arrive at once. On MySQL every CREATE/ALTER commits by itself, so two
        // of them applying the same file would both run its INSERTs: one applies the migrations while the others wait,
        // then they see what is left (nothing).
        $lock = $this->driver === 'mysql' ? 'itam_migrate_' . substr(md5((string) config('db.database')), 0, 16) : null;
        if ($lock !== null) {
            $this->value('SELECT GET_LOCK(?, 120)', [$lock]);
        }
        $applied = [];
        try {
            foreach ($pending() as $name) {
                $file = ITAM_ROOT . '/database/migrations/' . $name;
                $apply = function () use ($file, $name) {
                    $this->execFile($file);
                    $this->insert('migrations', ['name' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
                };
                // SQLite DDL is transactional, so a failed migration leaves the schema untouched.
                // MySQL commits implicitly on every CREATE/ALTER, so a transaction there would only fail.
                $this->driver === 'mysql' ? $apply() : $this->transaction($apply);
                $applied[] = $name;
            }
        } finally {
            if ($lock !== null) {
                $this->value('SELECT RELEASE_LOCK(?)', [$lock]);
            }
        }
    }

    private function execFile(string $file): void
    {
        $sql = (string) file_get_contents($file);
        if ($this->driver === 'mysql') {
            $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT AUTO_INCREMENT PRIMARY KEY', $sql);
            $sql = preg_replace('/CREATE INDEX IF NOT EXISTS/', 'CREATE INDEX', $sql);
        }
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
            // A statement starting with /*mysql*/ only applies to MySQL/MariaDB (SQLite does not enforce lengths).
            if ($this->driver !== 'mysql' && str_starts_with($stmt, '/*mysql*/')) {
                continue;
            }
            // And one starting with /*sqlite*/ only to SQLite (where the two dialects differ, e.g. DROP INDEX).
            if ($this->driver === 'mysql' && str_starts_with($stmt, '/*sqlite*/')) {
                continue;
            }
            $this->pdo->exec($stmt);
        }
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        // ITAM_SLOW_QUERY_MS (environment) logs queries slower than that, for performance work (tests/perf.php).
        static $slow = null;
        $slow ??= (int) getenv('ITAM_SLOW_QUERY_MS');
        $start = $slow ? microtime(true) : 0.0;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        if ($slow && ($ms = (microtime(true) - $start) * 1000) >= $slow) {
            error_log(sprintf('Slow query %.0f ms: %s', $ms, preg_replace('/\s+/', ' ', mb_substr($sql, 0, 400))));
        }
        return $st;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols))
        );
        $this->run($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, int $id, array $data): void
    {
        if (!$data) {
            return;
        }
        // Location history feeds the anomaly rules; every screen that moves an asset goes through here.
        $from = $table === 'assets' && array_key_exists('location_id', $data)
            ? $this->value('SELECT location_id FROM assets WHERE id = ?', [$id]) : false;
        $set = implode(', ', array_map(fn ($c) => "$c = :$c", array_keys($data)));
        $data['__id'] = $id;
        $this->run("UPDATE $table SET $set WHERE id = :__id", $data);
        if ($from !== false) {
            $this->recordMove($id, $from, $data['location_id']);
        }
    }

    /** Append to the location history when an asset really changed place. */
    public function recordMove(int $assetId, mixed $from, mixed $to): void
    {
        if ((string) $from === (string) $to) {
            return;
        }
        $via = PHP_SAPI === 'cli' ? 'system' : (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $this->insert('asset_moves', [
            'asset_id' => $assetId, 'from_location_id' => $from ?: null, 'to_location_id' => $to ?: null,
            'via' => mb_substr($via, 0, 60), 'user_id' => \ITAM\Core\Auth::id(), 'moved_at' => now(),
        ]);
    }

    public function delete(string $table, int $id): void
    {
        $this->run("DELETE FROM $table WHERE id = ?", [$id]);
    }

    public function transaction(callable $fn): mixed
    {
        // Nested call (e.g. a ticket opened while the installer seeds demo data): part of the outer transaction.
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->pdo->beginTransaction();
        try {
            $r = $fn($this);
            // A MySQL DDL statement inside $fn ends the transaction by itself.
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $r;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;               // the original error, not a rollback failure that would hide it
        }
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $v = $this->value('SELECT svalue FROM settings WHERE skey = ?', [$key]);
        return $v ?? $default;
    }

    public function setSetting(string $key, string $value): void
    {
        // One atomic upsert: a delete-then-insert let two overlapping scheduler runs (--monitor every minute and
        // --quick every 15) both insert the key, and the second died on MySQL's duplicate primary key.
        $this->run($this->driver === 'mysql'
            ? 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
            : 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue',
            [$key, $value]);
    }
}
