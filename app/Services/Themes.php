<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Themes engine. A theme is a small set of colours for light and dark mode plus a font, a corner shape and a
 * density; everything else (soft tints, borders, gradients, sidebar ink, button ink) is derived from them so any
 * combination stays readable. The administrator picks one theme for the whole platform: a built-in preset or a
 * custom theme, which can be exported to a JSON file and imported on another installation.
 */
final class Themes
{
    /** Colour roles each theme defines, per mode. */
    public const ROLES = ['primary', 'accent', 'sidebar', 'bg', 'surface', 'text'];

    /** key => [Google Fonts family, CSS stack]. Every font covers Arabic and Latin. */
    public const FONTS = [
        'alexandria' => ['Alexandria', '"Alexandria", system-ui, "Segoe UI", Tahoma, sans-serif'],
        'ibm-plex'   => ['IBM Plex Sans Arabic', '"IBM Plex Sans Arabic", system-ui, "Segoe UI", Tahoma, sans-serif'],
        'tajawal'    => ['Tajawal', '"Tajawal", system-ui, "Segoe UI", Tahoma, sans-serif'],
        'cairo'      => ['Cairo', '"Cairo", system-ui, "Segoe UI", Tahoma, sans-serif'],
        'almarai'    => ['Almarai', '"Almarai", system-ui, "Segoe UI", Tahoma, sans-serif'],
        'readex'     => ['Readex Pro', '"Readex Pro", system-ui, "Segoe UI", Tahoma, sans-serif'],
        'noto-kufi'  => ['Noto Kufi Arabic', '"Noto Kufi Arabic", system-ui, "Segoe UI", Tahoma, sans-serif'],
        'system'     => [null, 'system-ui, -apple-system, "Segoe UI", Tahoma, Roboto, sans-serif'],
    ];

    /** Corner shapes: [cards, small items, controls]. */
    public const SHAPES = ['sharp' => ['6px', '4px', '5px'], 'medium' => ['14px', '9px', '10px'], 'round' => ['22px', '14px', '999px']];

    /** Densities: [control height, card padding]. */
    public const DENSITIES = ['comfortable' => ['38px', '18px'], 'compact' => ['32px', '13px']];

    public const DEFAULT = 'itam';

    /** Built-in presets. 'itam' reproduces the stylesheet exactly, so choosing it adds no CSS. */
    public const PRESETS = [
        'itam' => [
            'light' => ['primary' => '#1677e6', 'accent' => '#22c3a6', 'sidebar' => '#0b1b3f', 'bg' => '#f4f7fb', 'surface' => '#ffffff', 'text' => '#0f1b33'],
            'dark'  => ['primary' => '#3b8ef0', 'accent' => '#22c3a6', 'sidebar' => '#070e1d', 'bg' => '#0a1224', 'surface' => '#111b31', 'text' => '#e6ecf7'],
            'font' => 'alexandria', 'shape' => 'medium', 'density' => 'comfortable',
        ],
        'emerald' => [
            'light' => ['primary' => '#0f8a5f', 'accent' => '#84cc16', 'sidebar' => '#06281d', 'bg' => '#f3f8f5', 'surface' => '#ffffff', 'text' => '#0d2219'],
            'dark'  => ['primary' => '#2fbf86', 'accent' => '#a3e635', 'sidebar' => '#04160f', 'bg' => '#07140f', 'surface' => '#0e1f18', 'text' => '#e3f2ea'],
            'font' => 'ibm-plex', 'shape' => 'medium', 'density' => 'comfortable',
        ],
        'royal' => [
            'light' => ['primary' => '#6d3fe0', 'accent' => '#ec4899', 'sidebar' => '#1d1240', 'bg' => '#f6f4fc', 'surface' => '#ffffff', 'text' => '#1b1333'],
            'dark'  => ['primary' => '#9b7bf5', 'accent' => '#f472b6', 'sidebar' => '#0f0924', 'bg' => '#0d0a1c', 'surface' => '#171230', 'text' => '#ece8fb'],
            'font' => 'readex', 'shape' => 'round', 'density' => 'comfortable',
        ],
        'sunset' => [
            'light' => ['primary' => '#d9480f', 'accent' => '#f59f00', 'sidebar' => '#2b1308', 'bg' => '#fbf6f2', 'surface' => '#ffffff', 'text' => '#2a160d'],
            'dark'  => ['primary' => '#ff7a3d', 'accent' => '#fcc419', 'sidebar' => '#160903', 'bg' => '#140c07', 'surface' => '#21140c', 'text' => '#f8ece4'],
            'font' => 'cairo', 'shape' => 'medium', 'density' => 'comfortable',
        ],
        'desert' => [
            'light' => ['primary' => '#9a6a1f', 'accent' => '#c9a227', 'sidebar' => '#3a2a14', 'bg' => '#faf6ee', 'surface' => '#fffdf8', 'text' => '#2c2214'],
            'dark'  => ['primary' => '#d6a650', 'accent' => '#e9c46a', 'sidebar' => '#1c140a', 'bg' => '#15100a', 'surface' => '#211a10', 'text' => '#f4ecdc'],
            'font' => 'almarai', 'shape' => 'medium', 'density' => 'comfortable',
        ],
        'ocean' => [
            'light' => ['primary' => '#0e7490', 'accent' => '#06b6d4', 'sidebar' => '#0c2a36', 'bg' => '#f1f7f9', 'surface' => '#ffffff', 'text' => '#0b2530'],
            'dark'  => ['primary' => '#22b8d6', 'accent' => '#67e8f9', 'sidebar' => '#05161d', 'bg' => '#061319', 'surface' => '#0c1f27', 'text' => '#e1f3f8'],
            'font' => 'tajawal', 'shape' => 'round', 'density' => 'comfortable',
        ],
        'graphite' => [
            'light' => ['primary' => '#334155', 'accent' => '#64748b', 'sidebar' => '#111827', 'bg' => '#f5f6f8', 'surface' => '#ffffff', 'text' => '#111827'],
            'dark'  => ['primary' => '#94a3b8', 'accent' => '#cbd5e1', 'sidebar' => '#0b0f17', 'bg' => '#0d1117', 'surface' => '#161b22', 'text' => '#e6edf3'],
            'font' => 'system', 'shape' => 'sharp', 'density' => 'compact',
        ],
        'rose' => [
            'light' => ['primary' => '#be185d', 'accent' => '#fb7185', 'sidebar' => '#3b0d24', 'bg' => '#fcf5f8', 'surface' => '#ffffff', 'text' => '#2d0f1e'],
            'dark'  => ['primary' => '#f0609e', 'accent' => '#fda4af', 'sidebar' => '#1d0612', 'bg' => '#160810', 'surface' => '#22101a', 'text' => '#fbe7f0'],
            'font' => 'noto-kufi', 'shape' => 'round', 'density' => 'comfortable',
        ],
    ];

    private static ?array $active = null;

    // ---------- Stored state ----------

    /** Custom themes: id => ['name' => …, light, dark, font, shape, density]. */
    public static function custom(): array
    {
        $list = json_decode((string) db()->setting('theme.custom', '[]'), true);
        return is_array($list) ? $list : [];
    }

    /** "preset:itam" or "custom:<id>". */
    public static function activeKey(): string
    {
        $key = empty($GLOBALS['itam_installed']) ? '' : (string) db()->setting('theme.active', '');
        return $key !== '' && self::resolve($key) !== null ? $key : 'preset:' . self::DEFAULT;
    }

    public static function resolve(string $key): ?array
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, '');
        if ($kind === 'preset' && isset(self::PRESETS[$id])) {
            return self::PRESETS[$id];
        }
        return $kind === 'custom' ? (self::custom()[$id] ?? null) : null;
    }

    /**
     * The theme in use. Before the engine existed the identity page stored a brand colour and a sidebar colour;
     * those still apply on top of the default preset until a theme is chosen.
     */
    public static function active(): array
    {
        if (self::$active !== null) {
            return self::$active;
        }
        // Before installation (the wizard) there is no database yet: the default look.
        if (empty($GLOBALS['itam_installed']) || db()->setting('theme.active') === null) {
            $t = self::PRESETS[self::DEFAULT];
            foreach (['color' => 'primary', 'sidebar_color' => 'sidebar'] as $old => $role) {
                $c = (string) config('brand.' . $old, '');
                if (self::isColor($c)) {
                    $t['light'][$role] = strtolower($c);
                }
            }
            return self::$active = $t;
        }
        return self::$active = self::resolve(self::activeKey()) ?? self::PRESETS[self::DEFAULT];
    }

    public static function activate(string $key): bool
    {
        if (self::resolve($key) === null) {
            return false;
        }
        db()->setSetting('theme.active', $key);
        self::$active = null;
        return true;
    }

    /** Create or replace a custom theme; returns its id. */
    public static function saveCustom(?string $id, array $theme): string
    {
        $all = self::custom();
        $id = $id !== null && isset($all[$id]) ? $id : bin2hex(random_bytes(4));
        $all[$id] = $theme;
        db()->setSetting('theme.custom', json_encode($all, JSON_UNESCAPED_UNICODE));
        self::$active = null;
        return $id;
    }

    public static function deleteCustom(string $id): void
    {
        $all = self::custom();
        unset($all[$id]);
        db()->setSetting('theme.custom', json_encode($all, JSON_UNESCAPED_UNICODE));
        if (self::activeKey() === 'custom:' . $id || db()->setting('theme.active') === 'custom:' . $id) {
            db()->setSetting('theme.active', 'preset:' . self::DEFAULT);
        }
        self::$active = null;
    }

    // ---------- Validation, import and export ----------

    /**
     * A clean theme from untrusted input (form or imported file), or null with $error set.
     * Colours must be #rrggbb; font, shape and density must be known keys.
     */
    public static function sanitize(array $in, ?string &$error = null): ?array
    {
        $name = trim(mb_substr((string) ($in['name'] ?? ''), 0, 60));
        if ($name === '') {
            $error = 'name';
            return null;
        }
        $out = ['name' => strip_tags($name)];
        foreach (['light', 'dark'] as $mode) {
            foreach (self::ROLES as $role) {
                $c = strtolower(trim((string) ($in[$mode][$role] ?? '')));
                if (!self::isColor($c)) {
                    $error = "$mode.$role";
                    return null;
                }
                $out[$mode][$role] = $c;
            }
        }
        foreach (['font' => self::FONTS, 'shape' => self::SHAPES, 'density' => self::DENSITIES] as $k => $allowed) {
            $v = (string) ($in[$k] ?? '');
            if (!isset($allowed[$v])) {
                $error = $k;
                return null;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /** Portable JSON for a theme. */
    public static function export(array $theme, string $name): string
    {
        return (string) json_encode(['format' => 'itam-theme', 'version' => 1, 'name' => $name] + array_intersect_key($theme, array_flip(['light', 'dark', 'font', 'shape', 'density'])),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Parse an exported file; null when it is not a valid theme. */
    public static function import(string $json, ?string &$error = null): ?array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? '') !== 'itam-theme' || (int) ($data['version'] ?? 0) !== 1) {
            $error = 'format';
            return null;
        }
        return self::sanitize($data, $error);
    }

    // ---------- Output ----------

    /** Style block for the active theme (empty for the untouched default, whose values are in the stylesheet). */
    public static function style(?array $theme = null): string
    {
        $theme ??= self::active();
        if ($theme === self::PRESETS[self::DEFAULT]) {
            return '';
        }
        $vars = fn (array $v) => implode('; ', array_map(fn ($k, $x) => "$k: $x", array_keys($v), $v));
        $light = self::vars($theme['light'], false) + self::shapeVars($theme);
        $dark = self::vars($theme['dark'], true);
        // The :not(#id) part outranks the stylesheet's own light/dark rules; dark adds one more class-level selector.
        return '<style id="itam-theme">:root:not(#itam-theme) { ' . $vars($light) . '; }'
            . ' :root[data-theme="dark"]:not(#itam-theme) { ' . $vars($dark) . '; }'
            . ' @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]):not(#itam-theme) { ' . $vars($dark) . '; } }</style>';
    }

    /** Google Fonts stylesheet link for the active font (none for the system font). */
    public static function fontLink(?array $theme = null): string
    {
        $family = self::FONTS[($theme ?? self::active())['font']][0] ?? null;
        if ($family === null) {
            return '';
        }
        $href = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $family) . ':wght@400;500;600;700&display=swap';
        return '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="' . e($href) . '" rel="stylesheet">';
    }

    /** Main colour for places that cannot use CSS variables (e-mail, browser theme-color). */
    public static function primary(): string
    {
        return self::active()['light']['primary'];
    }

    /** CSS custom properties derived from one mode's six colours. */
    public static function vars(array $c, bool $dark): array
    {
        $p = $c['primary'];
        $mix = fn (string $a, string $b, int $pct) => "color-mix(in srgb, $a, $b $pct%)";
        return [
            '--primary' => $p, '--brand-blue' => $p, '--brand-teal' => $c['accent'], '--brand-navy' => $c['sidebar'],
            '--primary-ink' => self::ink($p),
            '--primary-soft' => $mix($c['surface'], $p, $dark ? 18 : 12),
            '--grad' => "linear-gradient(135deg, $p 0%, {$c['accent']} 100%)",
            '--hero-a' => "color-mix(in srgb, $p 50%, transparent)", '--hero-b' => "color-mix(in srgb, {$c['accent']} 42%, transparent)",
            '--bg' => $c['bg'], '--surface' => $c['surface'],
            '--surface-2' => $mix($c['surface'], $c['bg'], 60),
            '--surface-3' => $mix($c['bg'], $c['text'], $dark ? 7 : 4),
            '--border' => $mix($c['bg'], $c['text'], $dark ? 13 : 9),
            '--border-strong' => $mix($c['bg'], $c['text'], $dark ? 20 : 16),
            '--text' => $c['text'],
            '--text-2' => $mix($c['text'], $c['surface'], 30),
            '--muted' => $mix($c['text'], $c['surface'], 48),
        ] + sidebar_colors($c['sidebar']);
    }

    public static function shapeVars(array $theme): array
    {
        [$r, $rs, $rc] = self::SHAPES[$theme['shape']] ?? self::SHAPES['medium'];
        [$h, $pad] = self::DENSITIES[$theme['density']] ?? self::DENSITIES['comfortable'];
        return ['--radius' => $r, '--radius-sm' => $rs, '--radius-ctl' => $rc, '--ctl-h' => $h, '--card-pad' => $pad,
            '--font' => self::FONTS[$theme['font']][1] ?? self::FONTS['alexandria'][1], '--font-ar' => self::FONTS[$theme['font']][1] ?? self::FONTS['alexandria'][1]];
    }

    // ---------- Colour maths ----------

    public static function isColor(string $c): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c);
    }

    /** WCAG relative luminance of #rrggbb. */
    public static function luminance(string $hex): float
    {
        $lin = static fn (int $v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

    /** Text colour on a filled button: whichever of white or dark ink reads better. */
    public static function ink(string $bg): string
    {
        return self::contrast('#ffffff', $bg) >= self::contrast('#0f1b33', $bg) ? '#ffffff' : '#0f1b33';
    }

    public static function contrast(string $a, string $b): float
    {
        [$hi, $lo] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];
        return round(($hi + 0.05) / ($lo + 0.05), 2);
    }

    /**
     * Readability checks the builder shows: [mode, check key, ratio, minimum]. Text needs 4.5:1, buttons and
     * links 3:1 (WCAG AA for large text and interface parts).
     */
    public static function checks(array $theme): array
    {
        $out = [];
        foreach (['light', 'dark'] as $mode) {
            $c = $theme[$mode];
            $ink = self::ink($c['primary']);
            $out[] = [$mode, 'text', self::contrast($c['text'], $c['surface']), 4.5];
            $out[] = [$mode, 'button', self::contrast($ink, $c['primary']), 3.0];
            $out[] = [$mode, 'link', self::contrast($c['primary'], $c['surface']), 3.0];
        }
        return $out;
    }
}
