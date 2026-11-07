<?php
declare(strict_types=1);

// Built-in server: let static files through.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    // Behind a proxy that ends TLS, PHP sees plain HTTP: the forwarded protocol says the browser used https.
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https',
]);
session_name('ITAMSESSID');
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(self)');

require dirname(__DIR__) . '/app/bootstrap.php';

if (empty($GLOBALS['itam_installed'])) {
    $path = substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base_path()));
    if (!preg_match('#^/(install|lang/)#', $path)) {
        redirect('/install');
    }
}

try {
    $router = require ITAM_ROOT . '/app/routes.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    if (config('app.debug')) {
        echo '<pre>' . e((string) $e) . '</pre>';
    } else {
        echo \ITAM\Core\View::render('errors/error', ['code' => 500, 'message' => t('errors.server')], 'layouts/guest');
    }
}
