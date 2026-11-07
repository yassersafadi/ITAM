<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Ledger;

/**
 * Physical inventory audits. Technicians scan QR labels (camera or handheld scanner) and the platform
 * reconciles in real time: found, misplaced (registered elsewhere), unexpected (unknown tag) and — on close — missing.
 */
final class AuditController extends Controller
{
    public function index(): void
    {
        $audits = db()->all("SELECT au.*, l.name AS location_name, c.name AS category_name, u.name AS started_by_name,
            (SELECT COUNT(*) FROM audit_items i WHERE i.audit_id = au.id AND i.result = 'found') AS found,
            (SELECT COUNT(*) FROM audit_items i WHERE i.audit_id = au.id AND i.result = 'missing') AS missing,
            (SELECT COUNT(*) FROM audit_items i WHERE i.audit_id = au.id AND i.result IN ('misplaced','unexpected')) AS anomalies
            FROM audits au LEFT JOIN locations l ON l.id = au.location_id LEFT JOIN categories c ON c.id = au.category_id
            LEFT JOIN users u ON u.id = au.started_by ORDER BY au.id DESC");
        $locations = db()->all('SELECT id, name FROM locations ORDER BY name');
        $categories = db()->all('SELECT id, name FROM categories ORDER BY name');
        $this->view('audits/index', compact('audits', 'locations', 'categories'));
    }

    public function store(): void
    {
        $this->validate(['name' => 'required|max:160']);
        $id = db()->insert('audits', [
            'name' => input('name'), 'location_id' => input_or_null('location_id'), 'category_id' => input_or_null('category_id'), 'status' => 'open',
            'started_by' => Auth::id(), 'started_at' => now(),
        ]);
        Ledger::record('create', 'audit', $id, 'Started audit: ' . input('name'));
        redirect('/audits/' . $id);
    }

    public function show(string $id): void
    {
        $audit = db()->one('SELECT au.*, l.name AS location_name, c.name AS category_name FROM audits au LEFT JOIN locations l ON l.id = au.location_id
            LEFT JOIN categories c ON c.id = au.category_id WHERE au.id = ?', [(int) $id]) ?? abort(404);
        $items = $this->items((int) $id);
        $expected = self::expected($audit);
        $scannedIds = array_flip(array_filter(array_column($items, 'asset_id')));
        $pending = array_values(array_filter($expected, fn ($a) => !isset($scannedIds[$a['id']])));
        $this->view('audits/show', compact('audit', 'items', 'expected', 'pending'));
    }

    /** JSON endpoint called by the scanner. Accepts a tag, a serial, or a full label URL (…/a/{tag}). */
    public function scan(string $id): void
    {
        $audit = db()->one('SELECT * FROM audits WHERE id = ?', [(int) $id]);
        if (!$audit || $audit['status'] !== 'open') {
            json_response(['ok' => false, 'message' => t('audits.closed_msg')], 422);
        }
        $raw = trim((string) input('code', ''));
        if ($raw === '') {
            json_response(['ok' => false, 'message' => t('errors.invalid')], 422);
        }
        $code = preg_match('#/a/([^/?\#]+)#', $raw, $m) ? rawurldecode($m[1]) : $raw;
        $at = $this->scanTime($audit);
        $asset = db()->one('SELECT a.*, l.name AS location_name FROM assets a LEFT JOIN locations l ON l.id = a.location_id WHERE a.tag = ? OR a.serial = ? LIMIT 1', [$code, $code]);

        if ($asset && db()->value('SELECT id FROM audit_items WHERE audit_id = ? AND asset_id = ?', [(int) $id, $asset['id']])) {
            json_response(['ok' => true, 'duplicate' => true, 'result' => 'duplicate', 'message' => t('audits.already', ['tag' => $asset['tag']])]);
        }
        // An unknown label counts once per audit, whichever phone scans it and however long after.
        if (!$asset && db()->value('SELECT id FROM audit_items WHERE audit_id = ? AND asset_id IS NULL AND scanned_value = ?', [(int) $id, $code])) {
            json_response(['ok' => true, 'duplicate' => true, 'result' => 'duplicate', 'message' => t('audits.already', ['tag' => $code])]);
        }
        if (!$asset) {
            $result = 'unexpected';
            $message = t('audits.unknown', ['code' => $code]);
        } elseif ($audit['category_id'] && (int) $asset['category_id'] !== (int) $audit['category_id']) {
            // A real device outside the count's category: not expected here, so it is not moved by "apply moves" either.
            $result = 'unexpected';
            $cat = (string) db()->value('SELECT name FROM categories WHERE id = ?', [(int) $asset['category_id']]);
            $message = t('audits.wrong_category_msg', ['tag' => $asset['tag'], 'cat' => $cat !== '' ? $cat : '—']);
        } elseif ($audit['location_id'] && (int) $asset['location_id'] !== (int) $audit['location_id']) {
            $result = 'misplaced';
            $message = t('audits.misplaced_msg', ['tag' => $asset['tag'], 'loc' => $asset['location_name'] ?? '—']);
        } else {
            $result = 'found';
            $message = t('audits.found_msg', ['tag' => $asset['tag'], 'name' => $asset['name']]);
        }
        db()->insert('audit_items', [
            'audit_id' => (int) $id, 'asset_id' => $asset['id'] ?? null, 'scanned_value' => $code, 'result' => $result, 'scanned_at' => $at,
        ]);
        if ($asset && (string) $asset['last_seen_at'] < $at) {
            db()->update('assets', (int) $asset['id'], ['last_seen_at' => $at]);
        }
        json_response([
            'ok' => true, 'result' => $result, 'message' => $message, 'label' => t('audit_result.' . $result),
            'asset' => $asset ? ['id' => (int) $asset['id'], 'tag' => $asset['tag'], 'name' => $asset['name'], 'url' => url('assets/' . $asset['id'])] : null,
        ]);
    }

    public function close(string $id): void
    {
        $audit = db()->one('SELECT * FROM audits WHERE id = ?', [(int) $id]) ?? abort(404);
        if ($audit['status'] !== 'open') {
            redirect('/audits/' . $id);
        }
        $missing = 0;
        foreach (self::expected($audit) as $a) {
            if (!db()->value('SELECT id FROM audit_items WHERE audit_id = ? AND asset_id = ?', [(int) $id, $a['id']])) {
                db()->insert('audit_items', ['audit_id' => (int) $id, 'asset_id' => $a['id'], 'result' => 'missing', 'scanned_at' => now()]);
                $missing++;
            }
        }
        if (input('apply_moves') && $audit['location_id']) {
            foreach (db()->all("SELECT asset_id FROM audit_items WHERE audit_id = ? AND result = 'misplaced'", [(int) $id]) as $mi) {
                db()->update('assets', (int) $mi['asset_id'], ['location_id' => $audit['location_id'], 'updated_at' => now()]);
                Ledger::record('relocate', 'asset', (int) $mi['asset_id'], 'Location corrected by audit #' . $id);
            }
        }
        db()->update('audits', (int) $id, ['status' => 'closed', 'closed_at' => now()]);
        Ledger::record('close', 'audit', (int) $id, "Closed audit {$audit['name']} ($missing missing)");
        flash('success', t('audits.closed_flash', ['n' => $missing]));
        redirect('/audits/' . $id);
    }

    /** Scans synced from offline mode carry the time they were taken; accept it only within the audit window. */
    private function scanTime(array $audit): string
    {
        $ts = input('scanned_at') ? strtotime((string) input('scanned_at')) : false;
        if ($ts === false || $ts > time() + 300 || $ts < strtotime($audit['started_at'])) {
            return now();
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private function items(int $auditId): array
    {
        return db()->all('SELECT i.*, a.tag, a.name AS asset_name, l.name AS location_name FROM audit_items i
            LEFT JOIN assets a ON a.id = i.asset_id LEFT JOIN locations l ON l.id = a.location_id
            WHERE i.audit_id = ? ORDER BY i.id DESC', [$auditId]);
    }

    /** Assets an audit expects to find: by its location and/or category. Empty scope expects nothing. */
    public static function expected(array $audit): array
    {
        $where = [];
        $params = [];
        if (!empty($audit['location_id'])) {
            $where[] = 'a.location_id = ?';
            $params[] = (int) $audit['location_id'];
        }
        if (!empty($audit['category_id'])) {
            $where[] = 'a.category_id = ?';
            $params[] = (int) $audit['category_id'];
        }
        if (!$where) {
            return [];
        }
        return db()->all("SELECT a.id, a.tag, a.name FROM assets a WHERE " . implode(' AND ', $where) . " AND a.status NOT IN ('retired','lost','discovered') ORDER BY a.tag", $params);
    }
}
