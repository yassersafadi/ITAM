<?php
declare(strict_types=1);

namespace ITAM\Controllers;

/** Installable app: service worker (served from the root so it can cover the whole site) and web manifest. */
final class PwaController extends Controller
{
    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name' => app_name(), 'short_name' => (string) config('app.short', 'ITAM'),
            'start_url' => url('/'), 'scope' => url('/'), 'display' => 'standalone',
            'background_color' => \ITAM\Services\Themes::active()['light']['sidebar'], 'theme_color' => \ITAM\Services\Themes::primary(),
            'dir' => \ITAM\Core\Lang::dir(), 'lang' => \ITAM\Core\Lang::locale(),
            'icons' => [['src' => brand_asset('logo_mark'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
