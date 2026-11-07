<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/**
 * Presentation state of the asset list: columns, ready-made views and each user's preferences
 * (layout, visible columns, density, page size) and saved filter sets.
 */
final class AssetGrid
{
    /** Optional columns: key => [label key, sort key or null]. Tag and name are always shown. */
    public const COLUMNS = [
        'serial' => ['field.serial', 'serial'],
        'category' => ['field.category_id', 'category'],
        'manufacturer' => ['field.manufacturer', 'manufacturer'],
        'model' => ['field.model', 'model'],
        'status' => ['field.status', 'status'],
        'assigned' => ['field.assigned_to', 'assigned'],
        'location' => ['field.location_id', 'location'],
        'department' => ['field.department_id', null],
        'warranty' => ['field.warranty_until', 'warranty'],
        'criticality' => ['field.criticality', 'criticality'],
        'ip' => ['field.ip_address', null],
        'os' => ['field.os', null],
        'seen' => ['grid.col.last_seen', 'seen'],
        'age' => ['grid.col.age', 'age'],
        'value' => ['field.value', 'value'],
        'po' => ['field.po_number', 'po'],
    ];
    public const DEFAULT_COLUMNS = ['serial', 'category', 'manufacturer', 'model', 'status', 'assigned', 'location', 'warranty'];
    /** Columns placed right after the tag, before the name, whenever they are shown. */
    public const LEADING = ['serial'];
    /** Bumped when new default columns should reach people who already saved a choice. */
    private const PREFS_VERSION = 1;
    public const VIEWS = ['table', 'cards', 'groups'];
    public const GROUPS = ['category', 'location', 'status', 'department', 'criticality'];
    public const PER_PAGE = [25, 50, 100, 200];
    public const GROUP_LIMIT = 600;

    /** Ready-made views: key => [icon, filters]. */
    public static function presets(): array
    {
        return [
            'all' => ['layers', []],
            'critical' => ['alert', ['criticality' => '3']],
            'expiring' => ['shield', ['warranty' => 'expiring']],
            'stale' => ['radar', ['seen' => 'stale']],
            'unassigned' => ['user-minus', ['assigned_state' => 'none', 'status' => 'in_stock']],
            'repair' => ['wrench', ['status' => 'in_repair']],
        ];
    }

    public static function prefs(): array
    {
        $saved = json_decode((string) db()->setting(self::key('prefs')), true) ?: [];
        if ($saved && ($saved['v'] ?? 1) < self::PREFS_VERSION) {
            // Version 2 brought serial and model into the default table, version 3 category and manufacturer
            // (until then shown under the name and the model): add them to saved choices once, before the model.
            $cols = array_values(array_unique(array_merge(['serial', 'model'], (array) ($saved['columns'] ?? self::DEFAULT_COLUMNS))));
            $at = (int) array_search('model', $cols, true);
            array_splice($cols, $at, 0, array_values(array_diff(['category', 'manufacturer'], $cols)));
            $saved['columns'] = $cols;
            $saved['v'] = self::PREFS_VERSION;
            db()->setSetting(self::key('prefs'), json_encode($saved));
        }
        $p = $saved + ['view' => 'table', 'columns' => self::DEFAULT_COLUMNS, 'density' => 'comfortable', 'per_page' => 25, 'group' => 'category'];
        $p['columns'] = array_values(array_intersect((array) $p['columns'], array_keys(self::COLUMNS)));
        return $p;
    }

    /** Apply view/column/density/page-size choices from the request; persist them unless asked not to. */
    public static function prefsFromRequest(): array
    {
        $p = self::prefs();
        $changed = false;
        $set = function (string $k, mixed $v) use (&$p, &$changed) {
            if ($p[$k] !== $v) {
                $p[$k] = $v;
                $changed = true;
            }
        };
        if (in_array($_GET['view'] ?? '', self::VIEWS, true)) {
            $set('view', $_GET['view']);
        }
        if (in_array($_GET['group'] ?? '', self::GROUPS, true)) {
            $set('group', $_GET['group']);
        }
        if (in_array($_GET['density'] ?? '', ['comfortable', 'compact'], true)) {
            $set('density', $_GET['density']);
        }
        if (in_array((int) ($_GET['per_page'] ?? 0), self::PER_PAGE, true)) {
            $set('per_page', (int) $_GET['per_page']);
        }
        if (isset($_GET['cols'])) {
            $cols = array_values(array_intersect(explode(',', (string) $_GET['cols']), array_keys(self::COLUMNS)));
            $set('columns', $cols);
        }
        if ($changed && empty($_GET['transient'])) {
            db()->setSetting(self::key('prefs'), json_encode($p + ['v' => self::PREFS_VERSION]));
        }
        return $p;
    }

    /** @return array<int, array{name: string, query: array}> */
    public static function savedViews(): array
    {
        return json_decode((string) db()->setting(self::key('views')), true) ?: [];
    }

    public static function saveView(string $name, array $query): void
    {
        $views = array_values(array_filter(self::savedViews(), fn ($v) => mb_strtolower($v['name']) !== mb_strtolower($name)));
        $views[] = ['name' => mb_substr($name, 0, 40), 'query' => $query];
        db()->setSetting(self::key('views'), json_encode(array_slice($views, -12), JSON_UNESCAPED_UNICODE));
    }

    public static function deleteView(int $index): void
    {
        $views = self::savedViews();
        unset($views[$index]);
        db()->setSetting(self::key('views'), json_encode(array_values($views), JSON_UNESCAPED_UNICODE));
    }

    /** Whether the current filters are exactly a preset's (ignoring order). */
    public static function matches(array $filters, array $preset): bool
    {
        $a = AssetQuery::active($filters);
        ksort($a);
        ksort($preset);
        return array_map('strval', $a) === array_map('strval', $preset);
    }

    private static function key(string $what): string
    {
        return "grid.assets.$what." . (int) Auth::id();
    }
}
