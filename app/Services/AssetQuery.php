<?php
declare(strict_types=1);

namespace ITAM\Services;

/** Builds the filtered asset listing shared by the asset grid, CSV export, labels and the smart query bar. */
final class AssetQuery
{
    public const FILTER_KEYS = [
        'q', 'status', 'category', 'location', 'department', 'assigned', 'manufacturer',
        'warranty', 'criticality', 'seen', 'overdue', 'age_min', 'unassigned', 'sort', 'dir',
        'age_max', 'warranty_from', 'warranty_to', 'seen_days', 'assigned_state', 'condition', 'cf', 'cfv', 'ids',
    ];

    /** Keys that narrow the list (everything except ordering). */
    public static function active(array $f): array
    {
        return array_diff_key($f, ['sort' => 1, 'dir' => 1]);
    }

    public const STATUSES = ['deployed', 'in_stock', 'reserved', 'in_repair', 'discovered', 'retired', 'lost'];
    public const CONDITIONS = ['excellent', 'good', 'fair', 'poor'];
    /** Width of plain numeric tags, used when no tag prefix is set. */
    public const NUMERIC_TAG_DIGITS = 6;

    public static function filtersFromRequest(): array
    {
        $f = [];
        foreach (self::FILTER_KEYS as $k) {
            $v = $_GET[$k] ?? null;
            if ($v !== null && $v !== '') {
                $f[$k] = is_string($v) ? trim($v) : $v;
            }
        }
        return $f;
    }

    /** @return array{0: string, 1: array} WHERE clause and params */
    public static function where(array $f): array
    {
        // Branch-scoped users only ever see assets at their own locations.
        $w = ['1=1', scope_sql('a')];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = '(a.tag LIKE ? OR a.name LIKE ? OR a.serial LIKE ? OR a.hostname LIKE ? OR a.model LIKE ? OR a.ip_address LIKE ? OR a.mac_address LIKE ? OR e.name LIKE ? OR a.po_number LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($p, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        if (!empty($f['status'])) {
            $statuses = array_intersect(explode(',', (string) $f['status']), self::STATUSES);
            if ($statuses) {
                $w[] = 'a.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
                array_push($p, ...array_values($statuses));
            }
        } else {
            $w[] = "a.status NOT IN ('retired','lost')";
        }
        foreach (['category' => 'a.category_id', 'location' => 'a.location_id', 'department' => 'a.department_id', 'assigned' => 'a.assigned_to'] as $k => $col) {
            if (!empty($f[$k])) {
                $ids = array_map('intval', explode(',', (string) $f[$k]));
                if ($k === 'department') {
                    $ids = array_values(array_unique(array_merge(...array_map([OrgUnits::class, 'descendants'], $ids))));
                }
                $w[] = "$col IN (" . implode(',', array_fill(0, count($ids), '?')) . ')';
                array_push($p, ...$ids);
            }
        }
        if (!empty($f['manufacturer'])) {
            $w[] = 'LOWER(a.manufacturer) = LOWER(?)';
            $p[] = $f['manufacturer'];
        }
        if (!empty($f['criticality'])) { $w[] = 'a.criticality >= ?'; $p[] = (int) $f['criticality']; }
        if (!empty($f['unassigned']) || ($f['assigned_state'] ?? '') === 'none') { $w[] = 'a.assigned_to IS NULL'; }
        if (($f['assigned_state'] ?? '') === 'any') { $w[] = 'a.assigned_to IS NOT NULL'; }
        if (!empty($f['condition'])) {
            $conds = array_values(array_intersect(explode(',', (string) $f['condition']), self::CONDITIONS));
            if ($conds) {
                $w[] = 'a.condition_state IN (' . implode(',', array_fill(0, count($conds), '?')) . ')';
                array_push($p, ...$conds);
            }
        }
        if (!empty($f['warranty_from'])) { $w[] = 'a.warranty_until >= ?'; $p[] = (string) $f['warranty_from']; }
        if (!empty($f['warranty_to'])) { $w[] = 'a.warranty_until <= ?'; $p[] = (string) $f['warranty_to']; }
        if (!empty($f['seen_days'])) {
            $w[] = 'COALESCE(a.last_seen_at, a.created_at) < ?';
            $p[] = days_from_today(-(int) $f['seen_days']);
        }
        if (!empty($f['age_max'])) {
            $w[] = 'a.acquired_at >= ?';
            $p[] = date('Y-m-d', strtotime('-' . (int) round((float) $f['age_max'] * 12) . ' months'));
        }
        if (!empty($f['cf']) && isset($f['cfv']) && $f['cfv'] !== '') {
            $w[] = 'EXISTS (SELECT 1 FROM custom_values cv WHERE cv.asset_id = a.id AND cv.field_id = ? AND cv.value LIKE ?)';
            array_push($p, (int) $f['cf'], '%' . $f['cfv'] . '%');
        }
        if (!empty($f['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $f['ids']))));
            if ($ids) {
                $w[] = 'a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                array_push($p, ...$ids);
            }
        }
        if (!empty($f['warranty'])) {
            switch ($f['warranty']) {
                case 'expired':  $w[] = 'a.warranty_until < ?'; $p[] = today(); break;
                case 'expiring': $w[] = 'a.warranty_until BETWEEN ? AND ?'; array_push($p, today(), days_from_today((int) config('alerts.warranty_warn_days', 60))); break;
                case 'active':   $w[] = 'a.warranty_until >= ?'; $p[] = today(); break;
                case 'none':     $w[] = 'a.warranty_until IS NULL'; break;
            }
        }
        if (($f['seen'] ?? '') === 'stale') {
            $w[] = 'COALESCE(a.last_seen_at, a.created_at) < ?';
            $p[] = days_from_today(-(int) 180);
        }
        if (!empty($f['overdue'])) {
            $w[] = 'EXISTS (SELECT 1 FROM assignments x WHERE x.asset_id = a.id AND x.checked_in_at IS NULL AND x.expected_return < ?)';
            $p[] = today();
        }
        if (!empty($f['age_min'])) {
            $w[] = 'a.acquired_at <= ?';
            $p[] = date('Y-m-d', strtotime('-' . (int) round((float) $f['age_min'] * 12) . ' months'));
        }
        return [implode(' AND ', $w), $p];
    }

    public static function orderBy(array $f): string
    {
        $dir = strtolower($f['dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';
        return match ($f['sort'] ?? '') {
            'name'     => "a.name $dir",
            'updated'  => "a.updated_at $dir",
            'warranty' => "COALESCE(a.warranty_until, '9999') $dir",
            'age'      => "COALESCE(a.acquired_at, '9999') $dir",
            'status'   => "a.status $dir",
            'seen'     => "COALESCE(a.last_seen_at, '0') $dir",
            'value'    => "COALESCE(a.value, 0) $dir",
            'criticality' => "a.criticality $dir",
            'serial'   => "a.serial $dir",
            'model'    => "COALESCE(a.model, '') $dir",
            'category' => "COALESCE(c.name, '') $dir",
            'manufacturer' => "COALESCE(a.manufacturer, '') $dir",
            'po'       => "COALESCE(a.po_number, '') $dir",
            'location' => "l.name $dir",
            'assigned' => "e.name $dir",
            default    => "a.tag $dir",
        };
    }

    public const SELECT = "SELECT a.*, c.name AS category_name, c.icon AS category_icon, c.code AS category_code,
            l.name AS location_name, d.name AS department_name, e.name AS employee_name
        FROM assets a
        LEFT JOIN categories c ON c.id = a.category_id
        LEFT JOIN locations l ON l.id = a.location_id
        LEFT JOIN departments d ON d.id = a.department_id
        LEFT JOIN employees e ON e.id = a.assigned_to";

    /** Rows per value of one column, with every other filter applied (counts shown next to filter options). */
    public static function facet(array $f, string $key, string $column): array
    {
        unset($f[$key]);
        if ($key === 'status') {
            $f['status'] = implode(',', self::STATUSES);
        }
        [$where, $p] = self::where($f);
        $rows = db()->all("SELECT $column AS v, COUNT(*) AS n FROM assets a LEFT JOIN employees e ON e.id = a.assigned_to WHERE $where GROUP BY $column", $p);
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['v']] = $r['n'];       // a NULL group (e.g. no location) counts under ''
        }
        return $out;
    }

    /** Headline figures for the current selection. */
    public static function summary(array $f): array
    {
        [$where, $p] = self::where($f);
        $warn = days_from_today((int) config('alerts.warranty_warn_days', 60));
        $stale = days_from_today(-(int) 180);
        $row = db()->one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN a.warranty_until BETWEEN ? AND ? THEN 1 ELSE 0 END) AS expiring,
                    SUM(CASE WHEN a.warranty_until < ? THEN 1 ELSE 0 END) AS expired,
                    SUM(CASE WHEN COALESCE(a.last_seen_at, a.created_at) < ? THEN 1 ELSE 0 END) AS stale,
                    SUM(CASE WHEN a.assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned,
                    SUM(COALESCE(a.value, 0)) AS value
             FROM assets a LEFT JOIN employees e ON e.id = a.assigned_to WHERE $where",
            array_merge([today(), $warn, today(), $stale], $p)
        );
        return array_map(fn ($v) => $v === null ? null : (float) $v, $row ?? []);
    }

    public static function count(array $f): int
    {
        [$where, $p] = self::where($f);
        return (int) db()->value("SELECT COUNT(*) FROM assets a LEFT JOIN employees e ON e.id = a.assigned_to WHERE $where", $p);
    }

    public static function fetch(array $f, int $limit = 0, int $offset = 0): array
    {
        [$where, $p] = self::where($f);
        $sql = self::SELECT . " WHERE $where ORDER BY " . self::orderBy($f);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
        }
        return db()->all($sql, $p);
    }

    public static function find(int $id): ?array
    {
        return db()->one(self::SELECT . ' WHERE a.id = ? AND ' . scope_sql('a'), [$id]);
    }

    public static function nextTag(?int $categoryId): string
    {
        // Without a prefix, tags are plain numbers in one sequence across all categories (000001, 000002…).
        if (trim((string) config('app.tag_prefix', 'IT')) === '') {
            $last = 0;
            foreach (db()->all("SELECT tag FROM assets WHERE tag NOT LIKE '%-%'") as $r) {
                if (ctype_digit((string) $r['tag'])) {
                    $last = max($last, (int) $r['tag']);
                }
            }
            return str_pad((string) ($last + 1), self::NUMERIC_TAG_DIGITS, '0', STR_PAD_LEFT);
        }
        $code = $categoryId ? (db()->value('SELECT code FROM categories WHERE id = ?', [$categoryId]) ?: 'GEN') : 'GEN';
        $prefix = config('app.tag_prefix', 'IT') . '-' . strtoupper($code) . '-';
        $last = db()->value('SELECT tag FROM assets WHERE tag LIKE ? ORDER BY tag DESC LIMIT 1', [$prefix . '%']);
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
