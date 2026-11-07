<?php
declare(strict_types=1);

/**
 * Automated checks, run before every release:  php tests/run.php
 *
 * Builds a fresh database with the demo data in a temporary MariaDB database (dropped at the end), so nothing touches
 * the real one, then:
 *  - unit checks of the core services (roles and permissions, directory sync, device confirmation, notices…);
 *  - consistency checks (every route's controller method exists, translations complete in the three languages,
 *    the user guide);
 *  - an HTTP pass on a separate PHP server: every page as an administrator in three languages, the employee portal,
 *    permissions (a technician is refused the admin pages), CSRF, password reset.
 * Exit code 0 when everything passes. Options: --unit (skip HTTP), --filter=word, --sqlite (a temporary SQLite file
 * instead, the database of small installations).
 *
 * MariaDB is what production runs, so it is the default: behaviour SQLite hides (duplicate keys between concurrent
 * writers, || as OR) shows up here. The server comes from ITAM_TEST_MYSQL_HOST, _PORT, _USER, _PASS (default root
 * with no password on 127.0.0.1:3306); a throwaway container is enough:
 *   docker run -d --name itam-test-mariadb --restart unless-stopped -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 -p 3306:3306 mariadb:10.11
 */

$root = dirname(__DIR__);
$opts = getopt('', ['unit', 'filter:', 'sqlite']);
$mysql = !isset($opts['sqlite']);
// pdo_mysql not enabled in php.ini: run again with it loaded, and the HTTP servers too (t_php()).
if ($mysql && !extension_loaded('pdo_mysql')) {
    putenv('ITAM_TEST_PHP_EXT=pdo_mysql');
    passthru(implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-d', 'extension=pdo_mysql', ...$argv])), $code);
    exit($code);
}

/** The PHP command for a child process, with the extension this run had to load itself. */
function t_php(): array
{
    return getenv('ITAM_TEST_PHP_EXT') ? [PHP_BINARY, '-d', 'extension=' . getenv('ITAM_TEST_PHP_EXT')] : [PHP_BINARY];
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itam-tests-' . bin2hex(random_bytes(4));
mkdir($tmp);
$dbFile = $tmp . DIRECTORY_SEPARATOR . 'itam.sqlite';
putenv('ITAM_DB_PATH=' . $dbFile);
// The temporary folder is the storage of the test copy: installed (the wizard is tested on its own), files and logs there.
putenv('ITAM_STORAGE=' . $tmp);
file_put_contents($tmp . DIRECTORY_SEPARATOR . 'installed.lock', json_encode(['installed_at' => date('c'), 'via' => 'tests']));
putenv('ITAM_SEED_DEMO=1');
putenv('ITAM_DB_DRIVER=sqlite');
$server = null;
if ($mysql) {
    $dbName = 'itam_test_' . bin2hex(random_bytes(4));
    $my = ['host' => getenv('ITAM_TEST_MYSQL_HOST') ?: '127.0.0.1', 'port' => (int) (getenv('ITAM_TEST_MYSQL_PORT') ?: 3306),
        'user' => getenv('ITAM_TEST_MYSQL_USER') ?: 'root', 'pass' => (string) getenv('ITAM_TEST_MYSQL_PASS')];
    try {
        $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $my['host'], $my['port']), $my['user'], $my['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        @rmdir($tmp);
        fwrite(STDERR, "No MariaDB test server on {$my['host']}:{$my['port']}: {$e->getMessage()}\n"
            . "Start one (see the top of tests/run.php) or set ITAM_TEST_MYSQL_HOST/_PORT/_USER/_PASS; --sqlite runs on SQLite.\n");
        exit(1);
    }
    $server->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['DRIVER' => 'mysql', 'HOST' => $my['host'], 'PORT' => $my['port'], 'NAME' => $dbName, 'USER' => $my['user'], 'PASS' => $my['pass']] as $k => $v) {
        putenv("ITAM_DB_$k=$v");
    }
    echo 'MySQL ' . $server->query('SELECT VERSION()')->fetchColumn() . ", database $dbName\n";
}
define('ITAM_CLI', true);
require $root . '/app/bootstrap.php';

$filter = $opts['filter'] ?? null;
$GLOBALS['t_pass'] = $GLOBALS['t_fail'] = 0;
$GLOBALS['t_errors'] = [];

/** Record one check. */
function check(string $name, bool $ok, string $detail = ''): void
{
    if ($ok) {
        $GLOBALS['t_pass']++;
        return;
    }
    $GLOBALS['t_fail']++;
    $GLOBALS['t_errors'][] = $name . ($detail !== '' ? " — $detail" : '');
    echo "  ✗ $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$suites = [];
foreach (glob(__DIR__ . '/suites/*.php') as $file) {
    $suites[basename($file, '.php')] = $file;
}
$started = microtime(true);
foreach ($suites as $name => $file) {
    if ($filter && !str_contains($name, $filter)) {
        continue;
    }
    if (isset($opts['unit']) && str_starts_with($name, 'http')) {
        continue;
    }
    echo "• $name\n";
    $before = $GLOBALS['t_fail'];
    try {
        (require $file)($root, $tmp);
    } catch (\Throwable $e) {
        check("$name crashed", false, $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    }
    if ($GLOBALS['t_fail'] === $before) {
        echo "  ✓ ok\n";
    }
}

// Mail queued during the run is handled (held: the test copy never sends) before the database goes.
\ITAM\Services\Notifier::flush();
$GLOBALS['itam_db'] = null;
if ($server) {
    $server->exec("DROP DATABASE `$dbName`");
}
$clean = function (string $dir) use (&$clean): void {
    foreach (glob($dir . '/*') ?: [] as $f) {
        is_dir($f) ? $clean($f) : @unlink($f);
    }
    @rmdir($dir);
};
foreach (glob($tmp . '/*') ?: [] as $f) {
    is_dir($f) ? $clean($f) : @unlink($f);
}
@rmdir($tmp);
printf("\n%d passed, %d failed in %.1fs\n", $GLOBALS['t_pass'], $GLOBALS['t_fail'], microtime(true) - $started);
exit($GLOBALS['t_fail'] ? 1 : 0);
