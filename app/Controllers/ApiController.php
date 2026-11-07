<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\AssetQuery;

final class ApiController extends Controller
{
    /** Quick search of the command palette (signed-in staff): assets, employees and licences. */
    public function search(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            json_response(['results' => []]);
        }
        $like = '%' . $q . '%';
        $res = [];
        foreach (db()->all('SELECT a.id, a.tag, a.name, a.status FROM assets a WHERE (a.tag LIKE ? OR a.name LIKE ? OR a.serial LIKE ? OR a.hostname LIKE ? OR a.ip_address LIKE ?) AND ' . scope_sql('a') . ' LIMIT 6', [$like, $like, $like, $like, $like]) as $a) {
            $res[] = ['type' => 'asset', 'icon' => 'box', 'title' => $a['tag'] . ' — ' . $a['name'], 'sub' => t('status.' . $a['status']), 'url' => url('assets/' . $a['id'])];
        }
        foreach (db()->all('SELECT id, name, email FROM employees WHERE name LIKE ? OR email LIKE ? OR employee_no LIKE ? LIMIT 4', [$like, $like, $like]) as $e) {
            $res[] = ['type' => 'employee', 'icon' => 'user', 'title' => $e['name'], 'sub' => $e['email'], 'url' => url('employees/' . $e['id'])];
        }
        foreach (db()->all('SELECT id, name, edition FROM licenses WHERE name LIKE ? OR publisher LIKE ? LIMIT 3', [$like, $like]) as $l) {
            $res[] = ['type' => 'license', 'icon' => 'key', 'title' => $l['name'], 'sub' => $l['edition'], 'url' => url('licenses/' . $l['id'])];
        }
        json_response(['results' => $res]);
    }

    public function notificationsCount(): void
    {
        $seen = (int) db()->setting('notif_seen_' . \ITAM\Core\Auth::id(), '0');
        json_response(['unread' => (int) db()->value('SELECT COUNT(*) FROM notifications WHERE id > ? AND (user_id IS NULL OR user_id = ?)', [$seen, \ITAM\Core\Auth::id()])]);
    }

    // ---------------- Token-authenticated REST API ----------------

    /** Scopes a token may carry. */
    public const SCOPES = ['read'];

    public static function requireToken(string $scope): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $token = preg_match('/Bearer\s+(\S+)/i', $header, $m) ? $m[1] : ($_SERVER['HTTP_X_ITAM_TOKEN'] ?? '');
        $row = $token ? db()->one('SELECT * FROM api_tokens WHERE token_hash = ?', [hash('sha256', $token)]) : null;
        if (!$row) {
            json_response(['error' => 'Invalid API token'], 401);
        }
        if ($row['scopes'] !== null && !in_array($scope, explode(',', (string) $row['scopes']), true)) {
            json_response(['error' => "This token may not use '$scope'"], 403);
        }
        db()->update('api_tokens', (int) $row['id'], ['last_used_at' => now()]);
        return $row;
    }

    /** GET /api/v1/assets: the register with the list filters, paged with offset (a full page means there may be more). */
    public function assets(): void
    {
        self::requireToken('read');
        $limit = max(1, min(1000, (int) ($_GET['limit'] ?? 500)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $rows = AssetQuery::fetch(AssetQuery::filtersFromRequest(), $limit, $offset);
        json_response(['data' => array_map([$this, 'publicAsset'], $rows), 'count' => count($rows), 'offset' => $offset, 'limit' => $limit]);
    }

    /** GET /api/v1/assets/{tag}: one asset. */
    public function asset(string $tag): void
    {
        self::requireToken('read');
        $id = db()->value('SELECT id FROM assets WHERE tag = ?', [$tag]) ?? json_response(['error' => 'Not found'], 404);
        json_response(['data' => $this->publicAsset(AssetQuery::find((int) $id))]);
    }

    private function publicAsset(array $a): array
    {
        return [
            'tag' => $a['tag'], 'name' => $a['name'], 'category' => $a['category_name'], 'status' => $a['status'],
            'manufacturer' => $a['manufacturer'], 'model' => $a['model'], 'serial' => $a['serial'], 'hostname' => $a['hostname'],
            'ip_address' => $a['ip_address'], 'location' => $a['location_name'], 'department' => $a['department_name'],
            'assigned_to' => $a['employee_name'], 'warranty_until' => $a['warranty_until'], 'criticality' => (int) $a['criticality'],
            'acquired_at' => $a['acquired_at'], 'value' => $a['value'] !== null ? (float) $a['value'] : null,
        ];
    }
}
