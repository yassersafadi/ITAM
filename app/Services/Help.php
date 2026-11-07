<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Lang;

/**
 * In-app user guide. Each section is a PHP file per language in resources/help/{locale}/{slug}.php returning
 * ['title', 'summary', 'blocks' => [[type, content], ...]]. Block types: h, p, steps, list, fields, tip, warn, faq.
 * Inline text supports **bold**, `code`, [[slug]] (link to another section) and [label](/path) (link into the platform).
 * A missing translation falls back to English, then Arabic, with a notice.
 */
final class Help
{
    /** Display groups: the sections of the side menu, in its order, between the opening and closing guides. */
    public const GROUPS = ['start', 'nav.g.overview', 'nav.g.assets', 'nav.g.people', 'nav.g.maintenance', 'nav.g.reports', 'nav.g.reference', 'nav.g.system'];

    /**
     * slug => [group, icon, platform path the section explains (also used for the "?" button), permission or null].
     * Order is the reading order.
     */
    public const SECTIONS = [
        // start
        'getting-started' => ['start', 'sparkles', null, null],
        'search' => ['start', 'search', null, null],
        'faq' => ['start', 'info', null, null],
        // nav.g.overview
        'dashboard' => ['nav.g.overview', 'dashboard', '/', null],
        'portal' => ['nav.g.overview', 'user', '/portal', null],
        // nav.g.assets
        'assets' => ['nav.g.assets', 'box', '/assets', null],
        'asset-import' => ['nav.g.assets', 'upload', '/assets/import', 'assets.edit'],
        'qr-labels' => ['nav.g.assets', 'qr', '/assets/labels', null],
        'licenses' => ['nav.g.assets', 'key', '/licenses', null],
        'consumables' => ['nav.g.assets', 'layers', '/consumables', null],
        'audits' => ['nav.g.assets', 'clipboard', '/audits', null],
        'disposals' => ['nav.g.assets', 'trash', '/disposals', null],
        // nav.g.people
        'employees' => ['nav.g.people', 'users', '/directory/employees', null],
        'custody' => ['nav.g.people', 'edit', '/custody', null],
        'loans' => ['nav.g.people', 'swap', '/loans', null],
        // nav.g.maintenance
        'maintenance' => ['nav.g.maintenance', 'wrench', '/maintenance', null],
        // nav.g.reports
        'reports' => ['nav.g.reports', 'chart', '/reports', null],
        'ledger' => ['nav.g.reports', 'history', '/ledger', 'ledger.view'],
        // nav.g.reference
        'reference-data' => ['nav.g.reference', 'layers', '/directory/categories', null],
        // nav.g.system
        'roles' => ['nav.g.system', 'shield', '/admin/roles', null],
        'settings' => ['nav.g.system', 'settings', '/admin/settings', 'settings.manage'],
        'server' => ['nav.g.system', 'server', null, 'settings.manage'],
    ];

    private static array $cache = [];

    /** Sections readable without a staff sign-in, on /portal/help (the employee guide only). */
    public const PUBLIC = [];

    /** True while rendering for employees: links to staff sections and screens become plain text. */
    public static bool $public = false;

    /** Sections the signed-in user may read (administrator topics need their permission). */
    public static function visible(): array
    {
        return array_filter(self::SECTIONS, fn ($s) => $s[3] === null || can($s[3]));
    }

    /** A group's name: a menu section reads as it does in the menu, renamed or not. */
    public static function groupName(string $group): string
    {
        return str_starts_with($group, 'nav.g.') ? NavSections::label($group) : t('help.group.' . $group);
    }

    /** One section in the current language: title, summary, blocks, plus lang (the language actually used). */
    public static function load(string $slug, ?string $locale = null): ?array
    {
        if (!isset(self::SECTIONS[$slug])) {
            return null;
        }
        $locale ??= Lang::locale();
        $key = "$locale/$slug";
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = null;
            foreach (array_unique([$locale, 'en', 'ar']) as $try) {
                $file = ITAM_ROOT . "/resources/help/$try/$slug.php";
                if (is_file($file)) {
                    $data = require $file;
                    if (is_array($data) && isset($data['title'], $data['blocks'])) {
                        self::$cache[$key] = $data + ['lang' => $try, 'summary' => ''];
                        break;
                    }
                }
            }
        }
        return self::$cache[$key];
    }

    /** Section explaining the page at $path (longest matching platform path), for the "?" button. */
    public static function forPath(string $path): string
    {
        $best = 'getting-started';
        $len = 0;
        foreach (self::visible() as $slug => [, , $route]) {
            if ($route === null) {
                continue;
            }
            $match = $route === '/' ? $path === '/' : ($path === $route || str_starts_with($path, rtrim($route, '/') . '/'));
            if ($match && strlen($route) > $len) {
                [$best, $len] = [$slug, strlen($route)];
            }
        }
        // Detail pages without their own section.
        if ($len === 0 && preg_match('#^/assets/\d+#', $path)) {
            return 'assets';
        }
        if ($len === 0 && preg_match('#^/(work-orders)/#', $path)) {
            return 'maintenance';
        }
        if ($len === 0 && str_starts_with($path, '/directory/models')) {
            return 'reference-data';
        }
        if ($len === 0 && preg_match('#^/(employees|clearances)/#', $path)) {
            return 'employees';
        }
        return $best;
    }

    /** Plain text of a section, for the search index. */
    public static function plain(array $section): string
    {
        $out = [$section['title'], $section['summary']];
        foreach ($section['blocks'] as [, $content]) {
            if (is_array($content)) {
                array_walk_recursive($content, function ($v) use (&$out) {
                    $out[] = $v;
                });
            } else {
                $out[] = $content;
            }
        }
        return preg_replace('/\s+/u', ' ', strip_tags(implode(' ', array_filter($out, 'is_string')))) ?? '';
    }

    /** Escape then apply the small inline syntax. */
    public static function inline(string $text): string
    {
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/`([^`]+)`/u', '<code dir="auto">$1</code>', (string) $html);
        $html = preg_replace_callback('/\[\[([a-z0-9-]+)\]\]/', function ($m) {
            $s = self::load($m[1]);
            if (!$s) {
                return e($m[1]);
            }
            if (self::$public) {
                return in_array($m[1], self::PUBLIC, true) ? '<a href="' . url('portal/help') . '">' . e($s['title']) . '</a>' : e($s['title']);
            }
            return '<a href="' . url('help/' . $m[1]) . '">' . e($s['title']) . '</a>';
        }, (string) $html);
        // Links into the platform only (relative paths), never to other sites. Employees only get links into the portal.
        return (string) preg_replace_callback('#\[([^\]]+)\]\((/[A-Za-z0-9/_?=&;.-]*)\)#u', fn ($m) => self::$public && !str_starts_with($m[2], '/portal')
            ? $m[1] : '<a href="' . url(html_entity_decode($m[2])) . '">' . $m[1] . '</a>', (string) $html);
    }
}
