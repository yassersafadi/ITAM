<?php
declare(strict_types=1);

namespace ITAM\Core;

/**
 * Minimal router: METHOD + path pattern with {param} placeholders.
 * Options: 'auth' => bool (default true), 'perm' => permission string, 'csrf' => bool (default true for POST),
 * 'rate' => [bucket, max, seconds]: at most max POSTs per user (or address) in that many seconds, else 429.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $opts = []): void
    {
        $this->add('GET', $path, $handler, $opts);
    }

    public function post(string $path, array $handler, array $opts = []): void
    {
        $this->add('POST', $path, $handler, $opts);
    }

    private function add(string $method, string $path, array $handler, array $opts): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/') . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'opts');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = rtrim($path, '/') ?: '/';

        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $allowed = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $opts = $route['opts'];
            if (($opts['auth'] ?? true) && !Auth::check()) {
                if (str_starts_with($path, '/api/')) {
                    json_response(['error' => 'Unauthenticated'], 401);
                }
                $_SESSION['_intended'] = $uri;
                redirect('/login');
            }
            // Administrators must enrol in two-factor authentication before doing anything else, when required.
            if (($opts['auth'] ?? true) && Auth::mustEnrol2fa() && !preg_match('#^/(profile|logout|lang/)#', $path) && !str_starts_with($path, '/api/')) {
                redirect('/profile?require2fa=1');
            }
            if (isset($opts['perm']) && !Auth::can($opts['perm'])) {
                abort(403, t('errors.forbidden'));
            }
            if ($method === 'POST' && ($opts['csrf'] ?? true)) {
                $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
                if (!hash_equals(csrf_token(), (string) $token)) {
                    abort(419, t('errors.csrf'));
                }
            }
            if ($method === 'POST' && isset($opts['rate'])) {
                [$bucket, $max, $seconds] = $opts['rate'] + ['', 30, 60];
                if (!\ITAM\Services\RateLimit::hit($bucket . ':' . (Auth::id() ?? 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? '')), (int) $max, (int) $seconds)) {
                    abort(429, t('errors.rate_limited'));
                }
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            (new $class())->$action(...array_values($params));
            return;
        }
        abort($allowed ? 405 : 404, $allowed ? 'Method not allowed' : t('errors.not_found'));
    }
}
