<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/**
 * Everything the dashboard shows, for the whole estate or one location (always within the user's branch scope):
 * the assets by status, what needs attention and the chart tabs.
 */
final class DashboardData
{
    public const WIDGETS = ['attention', 'charts'];
    private const SEVERITY = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    private string $where;        // condition on assets alias "a"
    private string $active = "a.status NOT IN ('retired','lost')";

    public function __construct(private ?int $location = null)
    {
        $this->where = scope_sql('a') . ($location ? ' AND a.location_id = ' . (int) $location : '');
    }

    /** Assets by status for the summary card: status => count, in the usual order. */
    public function summary(): array
    {
        $rows = array_column(db()->all("SELECT a.status, COUNT(*) AS n FROM assets a WHERE {$this->where} GROUP BY a.status"), 'n', 'status');
        $out = [];
        foreach (['deployed', 'in_stock', 'reserved', 'in_repair', 'retired'] as $s) {
            $out[$s] = (int) ($rows[$s] ?? 0);
        }
        return $out;
    }

    /** What needs someone: a prioritised list of counts, each with a link to the matching list. */
    public function attention(): array
    {
        $db = db();
        $w = $this->where;
        $items = [];
        $add = function (string $key, string $sev, string $icon, int $n, string $link) use (&$items) {
            if ($n > 0) {
                $items[] = ['key' => $key, 'module' => 'assets', 'severity' => $sev, 'icon' => $icon, 'n' => $n, 'link' => $link,
                    'title' => t("dash.att.$key", ['n' => $n]), 'action' => t("dash.act.$key")];
            }
        };
        $add('breached', 'high', 'clock', (int) $db->value("SELECT COUNT(*) FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE $w AND m.status IN ('open','in_progress') AND m.due_at < ?", [now()]), url('maintenance', ['breached' => 1]));
        // Licences and consumables are organisation-wide: only counts are shown, never asset details.
        $add('licenses', 'high', 'key', count(array_filter($db->all('SELECT l.seats, (SELECT COUNT(*) FROM license_seats s WHERE s.license_id = l.id) AS used FROM licenses l'), fn ($l) => (int) $l['used'] > (int) $l['seats'])), url('licenses', ['filter' => 'over']));
        $add('unassigned', 'medium', 'user', (int) $db->value("SELECT COUNT(*) FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE $w AND m.status = 'open' AND m.technician_id IS NULL AND m.priority IN ('critical','high')"), url('maintenance', ['unassigned' => 1]));
        $add('loan_requests', 'medium', 'swap', (int) $db->value("SELECT COUNT(*) FROM loan_bookings b WHERE b.status = 'requested'"), url('loans', ['view' => 'requests']));
        $add('returns', 'medium', 'swap', (int) $db->value("SELECT COUNT(*) FROM assignments x JOIN assets a ON a.id = x.asset_id WHERE $w AND x.checked_in_at IS NULL AND x.expected_return IS NOT NULL AND x.expected_return < ?", [today()]), url('assets', ['overdue' => 1]));
        $add('warranty', 'low', 'shield', (int) $db->value("SELECT COUNT(*) FROM assets a WHERE {$this->active} AND $w AND a.criticality >= 3 AND a.warranty_until BETWEEN ? AND ?", [today(), days_from_today(30)]), url('assets', ['warranty' => 'expiring', 'criticality' => 3]));
        $add('consumables', 'low', 'layers', count(Consumables::low()), url('consumables', ['low' => 1]));
        usort($items, fn ($x, $y) => self::SEVERITY[$x['severity']] <=> self::SEVERITY[$y['severity']]);
        return $items;
    }

    /** Monday-to-Monday windows of the last $n weeks, oldest first. */
    private function weeks(int $n): array
    {
        $out = [];
        $monday = strtotime('monday this week');
        for ($i = $n - 1; $i >= 0; $i--) {
            $a = $monday - $i * 7 * 86400;
            $out[] = [date('Y-m-d 00:00:00', $a), date('Y-m-d 00:00:00', $a + 7 * 86400)];
        }
        return $out;
    }

    public function charts(): array
    {
        $db = db();
        $w = "{$this->active} AND {$this->where}";
        $status = $db->all("SELECT a.status, COUNT(*) AS n FROM assets a WHERE {$this->where} GROUP BY a.status ORDER BY n DESC");
        $cats = $db->all("SELECT c.name, COUNT(a.id) AS n FROM assets a JOIN categories c ON c.id = a.category_id WHERE $w GROUP BY c.id, c.name ORDER BY n DESC");
        $warranty = [];
        for ($m = 0; $m < 12; $m++) {
            $warranty[date('Y-m', strtotime("first day of +$m month"))] = 0;
        }
        $endYm = date('Y-m-t', strtotime('first day of +11 month'));
        foreach ($db->all("SELECT a.warranty_until AS d FROM assets a WHERE $w AND a.warranty_until BETWEEN ? AND ?", [today(), $endYm]) as $r) {
            $warranty[substr($r['d'], 0, 7)]++;
        }
        // Work orders opened vs closed per week (12 weeks) and repair time per month (6 months).
        $labels = $opened = $closed = [];
        foreach ($this->weeks(12) as [$a, $b]) {
            $labels[] = date('j/n', strtotime($a));
            $opened[] = (int) $db->value("SELECT COUNT(*) FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE {$this->where} AND m.created_at >= ? AND m.created_at < ?", [$a, $b]);
            $closed[] = (int) $db->value("SELECT COUNT(*) FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE {$this->where} AND m.status = 'done' AND m.completed_at >= ? AND m.completed_at < ?", [$a, $b]);
        }
        $mttr = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = date('Y-m-01 00:00:00', strtotime("first day of -$i month"));
            $end = date('Y-m-01 00:00:00', strtotime($start . ' +1 month'));
            $rows = $db->all("SELECT m.created_at, m.completed_at FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE {$this->where} AND m.type = 'corrective' AND m.status = 'done' AND m.completed_at >= ? AND m.completed_at < ?", [$start, $end]);
            $mttr[] = ['label' => fmt_month(substr($start, 0, 7)), 'value' => $rows ? round(array_sum(array_map(fn ($r) => max(0, strtotime($r['completed_at']) - strtotime($r['created_at'])), $rows)) / count($rows) / 3600, 1) : 0];
        }
        $lic = $db->all('SELECT l.name, l.seats, (SELECT COUNT(*) FROM license_seats s WHERE s.license_id = l.id) AS used FROM licenses l WHERE l.seats > 0');
        usort($lic, fn ($x, $y) => ($y['used'] / max(1, $y['seats'])) <=> ($x['used'] / max(1, $x['seats'])));
        return [
            'status' => array_map(fn ($s) => ['label' => t('status.' . $s['status']), 'value' => (int) $s['n'], 'key' => $s['status']], $status),
            'categories' => array_map(fn ($c) => ['label' => $c['name'], 'value' => (int) $c['n']], $cats),
            'warranty' => array_map(fn ($k, $n) => ['label' => fmt_month($k), 'value' => $n], array_keys($warranty), $warranty),
            'workload' => ['labels' => $labels, 'series' => [['label' => t('dash.ch.opened'), 'values' => $opened], ['label' => t('dash.ch.closed'), 'values' => $closed]]],
            'mttr' => $mttr,
            'licenses' => array_map(fn ($l) => ['label' => $l['name'], 'value' => (int) round($l['used'] / max(1, $l['seats']) * 100)], array_slice($lic, 0, 8)),
        ];
    }

    /** Widget order and hidden widgets for the signed-in user. */
    public static function layout(): array
    {
        $saved = json_decode((string) db()->setting('dash.layout.' . (int) Auth::id()), true) ?: [];
        $order = array_values(array_intersect((array) ($saved['order'] ?? []), self::WIDGETS));
        $order = array_merge($order, array_diff(self::WIDGETS, $order));
        return ['order' => $order, 'hidden' => array_values(array_intersect((array) ($saved['hidden'] ?? []), self::WIDGETS)), 'custom' => (bool) $saved];
    }

    public static function saveLayout(?array $order, ?array $hidden): void
    {
        $key = 'dash.layout.' . (int) Auth::id();
        if ($order === null) {
            db()->run('DELETE FROM settings WHERE skey = ?', [$key]);
            return;
        }
        db()->setSetting($key, json_encode(['order' => array_values(array_intersect($order, self::WIDGETS)), 'hidden' => array_values(array_intersect($hidden ?? [], self::WIDGETS))]));
    }
}
