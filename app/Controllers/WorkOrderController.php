<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Ledger;
use ITAM\Services\Sla;

/** Work order page: take it, add notes and before/after photos. */
final class WorkOrderController extends Controller
{
    private const PRIORITY_ORDER = "CASE m.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END";

    public function show(string $id): void
    {
        $m = db()->one(
            "SELECT m.*, a.tag, a.name AS asset_name, a.model, a.serial, a.location_id, a.assigned_to, a.status AS asset_status, c.icon,
                    l.name AS location_name, e.name AS employee_name, u.name AS technician
             FROM maintenance m JOIN assets a ON a.id = m.asset_id LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN locations l ON l.id = a.location_id LEFT JOIN employees e ON e.id = a.assigned_to LEFT JOIN users u ON u.id = m.technician_id
             WHERE m.id = ?", [(int) $id]
        ) ?? abort(404);
        if (!in_scope($m['location_id'])) {
            abort(403);
        }
        $photos = ['before' => [], 'after' => []];
        foreach (db()->all("SELECT * FROM attachments WHERE maintenance_id = ? AND stored_name NOT LIKE 'chat-%' ORDER BY id", [(int) $id]) as $p) {
            $photos[self::photoStage($p)][] = $p;
        }
        $history = db()->all("SELECT created_at, user_name, summary FROM ledger WHERE entity = 'maintenance' AND entity_id = ? ORDER BY id DESC LIMIT 15", [(int) $id]);
        $sla = Sla::state($m);
        $this->view('workorders/show', compact('m', 'photos', 'history', 'sla'));
    }

    /** Assign the work order to myself. */
    public function take(string $id): void
    {
        $this->find($id);
        db()->update('maintenance', (int) $id, ['technician_id' => Auth::id()]);
        Ledger::record('assign', 'maintenance', (int) $id, 'Work order #' . $id . ' taken by ' . (Auth::user()['name'] ?? ''));
        $this->done(t('field.taken'), '/work-orders/' . $id);
    }

    public function photo(string $id): void
    {
        $m = $this->find($id);
        $file = $_FILES['photo'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 10 * 1024 * 1024) {
            flash('error', t('files.failed'));
            redirect('/work-orders/' . $id);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'][$mime] ?? null;
        if (!$ext) {
            flash('error', t('files.type'));
            redirect('/work-orders/' . $id);
        }
        $dir = ITAM_STORAGE . '/attachments';
        is_dir($dir) || mkdir($dir, 0775, true);
        $stage = input('stage') === 'after' ? 'after' : 'before';
        // The stage lives in the stored name (language-independent); the display name is translated.
        $stored = $stage . '-' . bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], "$dir/$stored");
        $fid = db()->insert('attachments', [
            'asset_id' => $m['asset_id'], 'maintenance_id' => (int) $id, 'kind' => 'photo', 'name' => t('field.photo_' . $stage) . " WO-$id.$ext",
            'stored_name' => $stored, 'mime' => $mime, 'size' => (int) $file['size'], 'user_id' => Auth::id(), 'created_at' => now(),
        ]);
        Ledger::record('attach', 'asset', (int) $m['asset_id'], "Photo ($stage) added to work order #$id", ['file_id' => $fid, 'sha256' => hash_file('sha256', "$dir/$stored")]);
        flash('success', t('field.photo_saved'));
        redirect('/work-orders/' . $id . '#photos');
    }

    /**
     * Before or after: from the stored-name prefix; older photos only carry it in their display name,
     * written in the uploader's language at the time (English or Arabic).
     */
    private static function photoStage(array $p): string
    {
        $stored = (string) $p['stored_name'];
        if (str_starts_with($stored, 'after-') || str_starts_with($stored, 'before-')) {
            return strstr($stored, '-', true);
        }
        foreach (['After ', 'Après ', 'بعد '] as $word) {
            if (str_starts_with((string) $p['name'], $word)) {
                return 'after';
            }
        }
        return 'before';
    }

    private function find(string $id): array
    {
        $m = db()->one('SELECT m.*, a.location_id FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE m.id = ?', [(int) $id]) ?? abort(404);
        return in_scope($m['location_id']) ? $m : abort(403);
    }

    /** JSON for queued offline actions, a redirect with a message otherwise. */
    private function done(string $message, string $to): never
    {
        if (wants_json()) {
            json_response(['ok' => true]);
        }
        flash('success', $message);
        redirect($to);
    }
}
