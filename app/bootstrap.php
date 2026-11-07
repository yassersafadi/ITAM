<?php
declare(strict_types=1);

define('ITAM_ROOT', dirname(__DIR__));
define('ITAM_VERSION', '1.0.0');

spl_autoload_register(static function (string $class): void {
    $prefix = 'ITAM\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = ITAM_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require ITAM_ROOT . '/app/helpers.php';

define('ITAM_STORAGE', getenv('ITAM_STORAGE') ?: ITAM_ROOT . '/storage');

$GLOBALS['itam_config'] = require ITAM_ROOT . '/config/config.php';

if (!is_dir(ITAM_STORAGE . '/logs')) {
    @mkdir(ITAM_STORAGE . '/logs', 0775, true);
}
ini_set('log_errors', '1');
ini_set('error_log', ITAM_STORAGE . '/logs/itam.log');
ini_set('display_errors', config('app.debug') ? '1' : '0');
error_reporting(E_ALL);

date_default_timezone_set(config('app.timezone', 'UTC'));
// A new server goes through the installation wizard first; nothing touches the database until then.
$GLOBALS['itam_installed'] = \ITAM\Services\Installer::installed();
if ($GLOBALS['itam_installed']) {
    \ITAM\Core\Database::instance()->migrate();
    \ITAM\Services\PlatformSettings::apply();
    date_default_timezone_set(config('app.timezone', 'UTC'));
}
