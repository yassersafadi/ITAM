<?php
declare(strict_types=1);

namespace ITAM\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    public static function partial(string $view, array $data = []): string
    {
        $file = ITAM_ROOT . '/resources/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view");
        }
        // Isolated scope so view variables (even one called $data) never collide with locals here.
        $render = static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            include $__file;
            return (string) ob_get_clean();
        };
        return $render($file, $data);
    }

    public static function show(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        // What was typed before a failed save stays for this one page, so the form comes back filled in.
        $html = self::render($view, $data, $layout);
        unset($_SESSION['_old']);
        echo $html;
    }
}
