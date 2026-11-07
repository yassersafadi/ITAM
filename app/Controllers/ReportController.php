<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Exporter;
use ITAM\Services\Ledger;

/** Ready-made reports: warranties ending, utilization by category and department, and data quality. */
final class ReportController extends Controller
{
    public const TABS = ['warranty', 'utilization', 'quality'];

    public static function tabs(): array
    {
        return self::TABS;
    }

    public function index(): void
    {
        $tab = in_array($_GET['tab'] ?? '', self::TABS, true) ? $_GET['tab'] : 'warranty';
        $this->view('reports/index', ['tab' => $tab, 'data' => $this->data($tab)]);
    }

    public function export(string $tab): void
    {
        if (!in_array($tab, self::TABS, true)) {
            abort(404);
        }
        $rows = $this->data($tab)['rows'] ?? [];
        $format = Exporter::format($_GET['format'] ?? null);
        Ledger::record('export', 'report', null, "Exported $tab report (" . count($rows) . ' rows, ' . strtoupper($format) . ')');
        $keys = $rows ? array_keys($rows[0]) : [];
        // Machine formats keep the keys; Excel and PDF get readable headings.
        $headers = in_array($format, ['csv', 'json'], true) ? $keys : array_map(fn ($k) => ucfirst(str_replace('_', ' ', (string) $k)), $keys);
        Exporter::download($format, 'itam-' . $tab, t('reports.' . $tab), $headers,
            array_map(fn ($r) => array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v, JSON_UNESCAPED_UNICODE), array_values($r)), $rows));
    }

    private function data(string $tab): array
    {
        $db = db();
        switch ($tab) {
            case 'quality':
                $active = "status NOT IN ('retired','lost') AND " . scope_sql('assets');
                $total = max(1, (int) $db->value("SELECT COUNT(*) FROM assets WHERE $active"));
                $checks = [];
                foreach (['serial' => 'serial', 'location_id' => 'location', 'category_id' => 'category', 'warranty_until' => 'warranty', 'acquired_at' => 'acquired', 'manufacturer' => 'manufacturer'] as $col => $key) {
                    $missing = (int) $db->value("SELECT COUNT(*) FROM assets WHERE $active AND ($col IS NULL OR $col = '')");
                    $checks[] = ['check' => t('quality.' . $key), 'missing' => $missing, 'complete_pct' => (int) round(100 - $missing / $total * 100)];
                }
                $dups = [];
                foreach (['serial', 'ip_address', 'mac_address', 'hostname'] as $col) {
                    foreach ($db->all("SELECT $col AS v, COUNT(*) AS n FROM assets WHERE $active AND $col IS NOT NULL AND $col <> '' GROUP BY $col HAVING COUNT(*) > 1") as $d) {
                        $tags = array_column($db->all("SELECT tag, id FROM assets WHERE $col = ? AND $active", [$d['v']]), 'tag', 'id');
                        $dups[] = ['field' => t('field.' . $col), 'value' => $d['v'], 'count' => (int) $d['n'], 'assets' => $tags];
                    }
                }
                $score = (int) round(array_sum(array_column($checks, 'complete_pct')) / count($checks) - count($dups) * 2);
                return ['rows' => $checks, 'dups' => $dups, 'score' => max(0, $score)];

            case 'utilization':
                $rows = $db->all("SELECT c.name AS category,
                        SUM(CASE WHEN a.status = 'deployed' THEN 1 ELSE 0 END) AS deployed,
                        SUM(CASE WHEN a.status = 'in_stock' THEN 1 ELSE 0 END) AS in_stock,
                        SUM(CASE WHEN a.status = 'in_repair' THEN 1 ELSE 0 END) AS in_repair,
                        SUM(CASE WHEN a.status = 'reserved' THEN 1 ELSE 0 END) AS reserved,
                        COUNT(a.id) AS total
                    FROM categories c JOIN assets a ON a.category_id = c.id AND a.status NOT IN ('retired','lost') AND " . scope_sql('a') . "
                    GROUP BY c.id, c.name ORDER BY total DESC");
                foreach ($rows as &$r) {
                    $r['utilization_pct'] = (int) round((int) $r['deployed'] / max(1, (int) $r['total']) * 100);
                }
                unset($r);
                $byDept = $db->all("SELECT d.name AS department, COUNT(a.id) AS assets, COALESCE(SUM(a.value),0) AS value
                    FROM departments d LEFT JOIN assets a ON a.department_id = d.id AND a.status NOT IN ('retired','lost') AND " . scope_sql('a') . "
                    GROUP BY d.id, d.name ORDER BY assets DESC");
                return ['rows' => $rows, 'by_dept' => $byDept];

            default: // warranty: what runs out in the next twelve months, by quarter
                $rows = $db->all("SELECT a.id, a.tag, a.name, c.name AS category, e.name AS assigned_to, a.acquired_at, a.warranty_until, a.criticality, a.value
                    FROM assets a LEFT JOIN categories c ON c.id = a.category_id LEFT JOIN employees e ON e.id = a.assigned_to
                    WHERE a.status NOT IN ('retired','lost') AND a.warranty_until IS NOT NULL AND a.warranty_until >= ? AND a.warranty_until <= ? AND " . scope_sql('a') . "
                    ORDER BY a.warranty_until ASC", [today(), days_from_today(365)]);
                $quarters = [];
                foreach ($rows as $r) {
                    $ts = strtotime($r['warranty_until']);
                    $q = date('Y', $ts) . ' Q' . (int) ceil(date('n', $ts) / 3);
                    $quarters[$q] ??= ['count' => 0, 'value' => 0.0];
                    $quarters[$q]['count']++;
                    $quarters[$q]['value'] += (float) $r['value'];
                }
                ksort($quarters);
                return ['rows' => $rows, 'quarters' => $quarters];
        }
    }
}
