<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/** The sections of the side menu (resources/views/layouts/_nav.php) for the signed-in person. */
final class NavSections
{
    private static array $menus = [];

    /** The menu for this person: section key => items [path, icon, label key]. */
    public static function menu(): array
    {
        return self::$menus[(int) Auth::id()] ??= require ITAM_ROOT . '/resources/views/layouts/_nav.php';
    }

    /** The menu without empty sections. */
    public static function arrange(array $menu): array
    {
        return array_filter($menu);
    }

    /** A section's name in the reader's language. */
    public static function label(string $key): string
    {
        return t($key);
    }
}
