<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Ledger;
use ITAM\Services\Notifier;
use ITAM\Services\Sla;

/**
 * Public fault reporting: anyone who scans an asset's QR label can report a problem without an account.
 * The report becomes a corrective work order on exactly that asset, with an SLA target, or with the service desk
 * (ITSM module) an incident on that asset that the desk works and opens the work order from.
 */
final class ReportIssueController extends Controller
{
    public const URGENCY = ['blocked' => 'high', 'degraded' => 'medium', 'minor' => 'low'];

    private const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** QR labels point here: staff go to the asset, everyone else gets the report form. */
    public function scan(string $tag): void
    {
        if (Auth::check()) {
            $id = db()->value('SELECT id FROM assets WHERE tag = ?', [$tag]) ?? abort(404, t('errors.not_found'));
            redirect('/assets/' . $id);
        }
        redirect('/report/' . rawurlencode($tag));
    }

    public function form(string $tag): void
    {
        $asset = $this->asset($tag);
        $this->view('report/form', ['asset' => $asset, 'enabled' => (bool) config('reports.enabled', true)], 'layouts/guest');
    }

    public function submit(string $tag): void
    {
        $asset = $this->asset($tag);
        if (!config('reports.enabled', true)) {
            abort(403, t('report.disabled'));
        }
        // Bots fill every field; people never see this one.
        if ((string) input('website', '') !== '') {
            redirect('/report/' . rawurlencode($tag) . '/thanks');
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $hour = date('Y-m-d H:i:s', time() - 3600);
        $recent = (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE source = 'report' AND reporter_ip = ? AND created_at >= ?", [$ip, $hour]);
        if ($recent >= (int) config('reports.per_hour', 5)) {
            flash('error', t('report.too_many'));
            redirect('/report/' . rawurlencode($tag));
        }
        $this->validate(['reporter_name' => 'required|max:120', 'reporter_contact' => 'required|max:160', 'description' => 'required|max:2000']);
        $priority = self::URGENCY[(string) input('urgency')] ?? 'medium';
        $description = trim((string) input('description'));
        $title = mb_strimwidth(preg_replace('/\s+/u', ' ', $description), 0, 80, '…');
        $now = now();
        $id = db()->insert('maintenance', [
            'asset_id' => $asset['id'], 'type' => 'corrective', 'title' => $title, 'description' => $description,
            'status' => 'open', 'priority' => $priority, 'source' => 'report',
            // "emp:<id>" marks a report made by a signed-in employee; an anonymous form may not claim it.
            'reporter_name' => input('reporter_name'), 'reporter_contact' => preg_replace('/^\s*emp\s*:/i', '', (string) input('reporter_contact')), 'reporter_ip' => $ip,
            'due_at' => Sla::dueAt($priority, $now), 'created_at' => $now,
        ]);
        $this->storePhoto((int) $asset['id'], $id);
        Ledger::record('report', 'asset', (int) $asset['id'], "Fault reported on {$asset['tag']} by " . input('reporter_name') . " (work order #$id)");
        $notice = t('report.notify_title', ['tag' => $asset['tag']]);
        db()->insert('notifications', [
            'user_id' => null, 'type' => 'report', 'title' => $notice, 'body' => $title,
            'link' => url('maintenance', ['source' => 'report']), 'fingerprint' => 'report:' . $id, 'created_at' => $now,
        ]);
        Notifier::send($notice, input('reporter_name') . ' — ' . $description, Notifier::absolute('/maintenance?source=report'));
        $_SESSION['_report_ref'] = 'WO-' . $id;
        redirect('/report/' . rawurlencode($tag) . '/thanks');
    }

    public function thanks(string $tag): void
    {
        $asset = $this->asset($tag);
        $ref = $_SESSION['_report_ref'] ?? null;
        unset($_SESSION['_report_ref']);
        $this->view('report/thanks', ['asset' => $asset, 'ref' => $ref], 'layouts/guest');
    }

    private function asset(string $tag): array
    {
        // Only what the reporter needs to recognise the device: no owner, network or value details.
        return db()->one(
            "SELECT a.id, a.tag, a.name, c.icon, l.name AS location_name FROM assets a
             LEFT JOIN categories c ON c.id = a.category_id LEFT JOIN locations l ON l.id = a.location_id
             WHERE a.tag = ? AND a.status NOT IN ('retired','lost')",
            [$tag]
        ) ?? abort(404, t('errors.not_found'));
    }

    private function storePhoto(int $assetId, int $workOrder): void
    {
        $file = $_FILES['photo'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > AttachmentController::maxBytes()) {
            return;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = self::PHOTO_TYPES[$mime] ?? null;
        if (!$ext || !@getimagesize($file['tmp_name'])) {
            return;
        }
        if (!is_dir(AttachmentController::dir())) {
            mkdir(AttachmentController::dir(), 0775, true);
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], AttachmentController::dir() . '/' . $stored)) {
            db()->insert('attachments', [
                'asset_id' => $assetId, 'kind' => 'photo', 'name' => "report-$workOrder.$ext", 'stored_name' => $stored,
                'mime' => $mime, 'size' => (int) $file['size'], 'user_id' => null, 'created_at' => now(),
            ]);
        }
    }
}
