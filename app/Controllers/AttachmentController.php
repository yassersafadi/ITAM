<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Ledger;

/**
 * Files attached to assets (photos, manuals, warranty documents, reports).
 * Stored outside the web root and only served to signed-in users.
 */
final class AttachmentController extends Controller
{
    public const KINDS = ['photo', 'manual', 'warranty', 'report', 'other'];

    private const MAX_BYTES = 10 * 1024 * 1024;

    /** Extension → accepted MIME types (checked against the file's real content). */
    public const TYPES = [
        'pdf'  => ['application/pdf'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'txt'  => ['text/plain'],
        'log'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    public static function dir(): string
    {
        return ITAM_STORAGE . '/attachments';
    }

    /** Effective upload limit: the smaller of our cap and PHP's own limits. */
    public static function maxBytes(): int
    {
        $ini = static function (string $key): int {
            $v = trim((string) ini_get($key));
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        };
        return min(self::MAX_BYTES, $ini('upload_max_filesize') ?: self::MAX_BYTES, $ini('post_max_size') ?: self::MAX_BYTES);
    }

    public static function accept(): string
    {
        return implode(',', array_map(fn ($e) => '.' . $e, array_keys(self::TYPES)));
    }

    public function upload(string $id): void
    {
        $asset = db()->one('SELECT id, tag FROM assets WHERE id = ?', [(int) $id]) ?? abort(404);
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->fail(t('files.none'), (int) $id);
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $this->fail(t('files.too_big', ['n' => fmt_num(self::maxBytes() / 1048576, 1)]), (int) $id);
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $this->fail(t('files.failed'), (int) $id);
        }
        if ($file['size'] > self::maxBytes()) {
            $this->fail(t('files.too_big', ['n' => fmt_num(self::maxBytes() / 1048576, 1)]), (int) $id);
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
        if (!isset(self::TYPES[$ext]) || !in_array($mime, self::TYPES[$ext], true)) {
            $this->fail(t('files.type'), (int) $id);
        }
        if (!is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], self::dir() . '/' . $stored)) {
            $this->fail(t('files.failed'), (int) $id);
        }
        $name = mb_substr(trim(preg_replace('/[\x00-\x1F\/\\\\]+/u', '_', (string) $file['name'])), 0, 200) ?: 'file.' . $ext;
        $kind = in_array(input('kind'), self::KINDS, true) ? input('kind') : (str_starts_with($mime, 'image/') ? 'photo' : 'other');
        $fid = db()->insert('attachments', [
            'asset_id' => $asset['id'], 'kind' => $kind, 'name' => $name, 'stored_name' => $stored,
            'mime' => $mime, 'size' => (int) $file['size'], 'user_id' => Auth::id(), 'created_at' => now(),
        ]);
        Ledger::record('attach', 'asset', (int) $asset['id'], "File attached to {$asset['tag']}: {$name}", ['file_id' => $fid, 'sha256' => hash_file('sha256', self::dir() . '/' . $stored)]);
        flash('success', t('files.uploaded'));
        redirect('/assets/' . $asset['id'] . '#files');
    }

    public function download(string $id): void
    {
        $f = db()->one('SELECT * FROM attachments WHERE id = ?', [(int) $id]) ?? abort(404, t('errors.not_found'));
        scope_guard(db()->value('SELECT location_id FROM assets WHERE id = ?', [(int) $f['asset_id']]));
        $path = self::dir() . '/' . basename($f['stored_name']);
        if (!is_file($path)) {
            abort(404, t('errors.not_found'));
        }
        // Images and PDFs open in the browser; everything else downloads. Never sniff.
        $inline = isset($_GET['view']) && (str_starts_with((string) $f['mime'], 'image/') || $f['mime'] === 'application/pdf');
        header('Content-Type: ' . ($inline ? $f['mime'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f['name']));
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }

    public function destroy(string $id): void
    {
        $f = db()->one('SELECT a.*, s.tag, s.location_id FROM attachments a JOIN assets s ON s.id = a.asset_id WHERE a.id = ?', [(int) $id]) ?? abort(404);
        scope_guard($f['location_id']);
        @unlink(self::dir() . '/' . basename($f['stored_name']));
        db()->delete('attachments', (int) $f['id']);
        Ledger::record('detach', 'asset', (int) $f['asset_id'], "File removed from {$f['tag']}: {$f['name']}");
        flash('success', t('common.deleted'));
        redirect('/assets/' . $f['asset_id'] . '#files');
    }

    private function fail(string $message, int $assetId): never
    {
        flash('error', $message);
        redirect('/assets/' . $assetId . '#files');
    }
}
