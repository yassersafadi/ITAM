<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;
use ITAM\Core\View;

/**
 * Custody receipts: what was handed to (or returned by) an employee, the terms they accepted and
 * their handwritten signature. The content and signature are fingerprinted (SHA-256) and anchored
 * in the ledger, so a printed receipt can be verified later.
 */
final class Custody
{
    public const TYPES = ['issue', 'return'];

    private const MAX_SIGNATURE_BYTES = 400 * 1024;

    public static function dir(): string
    {
        return ITAM_STORAGE . '/signatures';
    }

    /**
     * Create a receipt for the given assets (and optional license names). Returns its id.
     * While the employee still has an unsigned receipt of the same type, the new items join it, so one
     * signature covers everything handed over; once it is signed, the next handover starts a new receipt.
     */
    public static function create(string $type, array $employee, array $assetIds, array $licenses = [], ?string $notes = null, array $conditions = []): int
    {
        $items = self::assetItems($assetIds, $conditions);
        foreach ($licenses as $l) {
            $items[] = ['kind' => 'license', 'name' => (string) $l];
        }
        $type = in_array($type, self::TYPES, true) ? $type : 'issue';
        $open = !empty($employee['id']) ? db()->one("SELECT id, receipt_no, items, notes, sign_token, token_expires FROM custody_receipts WHERE employee_id = ? AND type = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [(int) $employee['id'], $type]) : null;
        if ($open) {
            $merged = json_decode((string) $open['items'], true) ?: [];
            $have = array_flip(array_map([self::class, 'itemKey'], $merged));
            $added = array_values(array_filter($items, fn ($i) => !isset($have[self::itemKey($i)])));
            $notes = trim((string) $notes);
            $oldNotes = (string) $open['notes'];
            db()->update('custody_receipts', (int) $open['id'], [
                'items' => json_encode(array_merge($merged, $added), JSON_UNESCAPED_UNICODE),
                'notes' => $notes === '' || str_contains($oldNotes, $notes) ? ($oldNotes ?: null) : trim($oldNotes . "\n" . $notes),
            ]);
            Ledger::record('custody_' . $type, 'employee', (int) $employee['id'], "Custody receipt {$open['receipt_no']} for {$employee['name']}: " . count($added) . ' items added before signature');
            if ($added) {
                self::tell((int) $employee['id'], 'custody_added', ['ref' => $open['receipt_no'], 'n' => count($added)], self::signLink($open));
            }
            return (int) $open['id'];
        }
        $year = date('Y');
        $prefix = $type === 'return' ? 'RET' : 'CUS';
        $no = next_number('custody_receipts', 'receipt_no', "$prefix-$year");
        $id = db()->insert('custody_receipts', [
            'receipt_no' => $no, 'type' => $type,
            'employee_id' => $employee['id'] ?? null, 'employee_name' => $employee['name'],
            'items' => json_encode($items, JSON_UNESCAPED_UNICODE), 'terms' => self::terms($type), 'notes' => $notes,
            'status' => 'pending', 'sign_token' => bin2hex(random_bytes(24)),
            'token_expires' => date('Y-m-d H:i:s', time() + (int) config('custody.link_days', 7) * 86400),
            'staff_user_id' => Auth::id(), 'created_at' => now(),
        ]);
        Ledger::record('custody_' . $type, 'employee', $employee['id'] ?? null, "Custody receipt $no prepared for {$employee['name']} (" . count($items) . ' items)');
        return $id;
    }

    /** The receipt lines of these devices, with the condition given for each or the one on record. */
    private static function assetItems(array $assetIds, array $conditions = []): array
    {
        if (!$assetIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($assetIds), '?'));
        return array_map(fn ($a) => [
            'kind' => 'asset', 'id' => (int) $a['id'], 'tag' => $a['tag'], 'name' => $a['name'], 'serial' => $a['serial'],
            'category' => $a['category'], 'condition' => $conditions[$a['id']] ?? $a['condition_state'],
        ], db()->all("SELECT a.id, a.tag, a.name, a.serial, a.condition_state, c.name AS category FROM assets a
                      LEFT JOIN categories c ON c.id = a.category_id WHERE a.id IN ($in) ORDER BY a.tag", array_values($assetIds)));
    }

    /**
     * One receipt for a device replaced from a ticket: the device returned and the device received, under a single
     * signature. It never joins another receipt, because its wording names these two devices. Returns its id.
     */
    public static function swap(array $employee, int $oldId, int $newId, string $oldCondition, array $ticket): int
    {
        $old = self::assetItems([$oldId], [$oldId => $oldCondition])[0] + ['side' => 'out'];
        $new = self::assetItems([$newId])[0] + ['side' => 'in'];
        $no = next_number('custody_receipts', 'receipt_no', 'SWP-' . date('Y'));
        $id = db()->insert('custody_receipts', [
            'receipt_no' => $no, 'type' => 'swap', 'employee_id' => $employee['id'], 'employee_name' => $employee['name'],
            'items' => json_encode([$old, $new], JSON_UNESCAPED_UNICODE), 'terms' => t('custody.terms_swap', ['old' => $old['tag'], 'new' => $new['tag']]),
            'notes' => $ticket['number'] . ' · ' . $ticket['title'], 'ticket_id' => (int) $ticket['id'],
            'status' => 'pending', 'sign_token' => bin2hex(random_bytes(24)),
            'token_expires' => date('Y-m-d H:i:s', time() + (int) config('custody.link_days', 7) * 86400),
            'staff_user_id' => Auth::id(), 'created_at' => now(),
        ]);
        Ledger::record('custody_swap', 'employee', (int) $employee['id'], "Custody receipt $no prepared for {$employee['name']} (2 items)");
        return $id;
    }

    /** Identity of an item on a receipt: an asset by its id, a license by its name. */
    public static function itemKey(array $i): string
    {
        return $i['kind'] === 'asset' ? 'a' . $i['id'] : 'l' . $i['name'];
    }

    /** Path of the remote-signing page; a missing or expired link is renewed first, so reminders always work. */
    public static function signLink(array $r): string
    {
        $token = (string) ($r['sign_token'] ?? '');
        if ($token === '' || (string) ($r['token_expires'] ?? '') <= now()) {
            $token = bin2hex(random_bytes(24));
            db()->update('custody_receipts', (int) $r['id'], [
                'sign_token' => $token, 'token_expires' => date('Y-m-d H:i:s', time() + (int) config('custody.link_days', 7) * 86400),
            ]);
        }
        return '/sign/' . $token;
    }

    /**
     * Take an item added by mistake off an unsigned receipt. Only the document changes: a device stays with the
     * employee until it is checked in. The last item cannot go; cancel the receipt instead.
     * @throws \InvalidArgumentException
     */
    public static function removeItem(array $r, string $key): void
    {
        if ($r['status'] !== 'pending') {
            throw new \InvalidArgumentException(t('custody.already_signed'));
        }
        if ($r['type'] === 'swap') {
            throw new \InvalidArgumentException(t('custody.swap_fixed'));
        }
        $keep = array_values(array_filter($r['items'], fn ($i) => self::itemKey($i) !== $key));
        if (count($keep) === count($r['items'])) {
            throw new \InvalidArgumentException(t('errors.not_found'));
        }
        if (!$keep) {
            throw new \InvalidArgumentException(t('custody.last_item'));
        }
        $gone = array_values(array_filter($r['items'], fn ($i) => self::itemKey($i) === $key))[0];
        db()->update('custody_receipts', (int) $r['id'], ['items' => json_encode($keep, JSON_UNESCAPED_UNICODE)]);
        Ledger::record('custody_edit', 'employee', $r['employee_id'] ? (int) $r['employee_id'] : null,
            "Custody receipt {$r['receipt_no']}: " . ($gone['tag'] ?? $gone['name']) . ' removed before signature');
    }

    /** Days after which an unsigned receipt counts as late and its employee is reminded (then every as many days). */
    public static function lateDays(): int
    {
        return max(1, (int) config('custody.remind_days', 3));
    }

    /**
     * Nightly: remind employees of receipts still unsigned after lateDays() (by e-mail / WhatsApp as they chose,
     * with a fresh signing link), and tell the IT team once a day how many are late.
     */
    public static function remindPending(): int
    {
        $cut = days_from_today(-self::lateDays());
        $late = db()->all("SELECT * FROM custody_receipts WHERE status = 'pending' AND employee_id IS NOT NULL AND created_at < ?", [$cut]);
        $n = 0;
        foreach ($late as $r) {
            if ($r['reminded_at'] && substr((string) $r['reminded_at'], 0, 10) > $cut) {
                continue;
            }
            self::tell((int) $r['employee_id'], 'custody_reminder', ['ref' => $r['receipt_no'], 'days' => (int) days_between(substr((string) $r['created_at'], 0, 10))], self::signLink($r));
            db()->update('custody_receipts', (int) $r['id'], ['reminded_at' => now()]);
            $n++;
        }
        if ($late && !db()->value('SELECT 1 FROM notifications WHERE fingerprint = ?', ['custody_late:' . today()])) {
            db()->insert('notifications', ['type' => 'custody', 'title' => t('custody.notify_late', ['n' => count($late), 'd' => self::lateDays()]),
                'body' => null, 'link' => url('custody', ['status' => 'late']), 'fingerprint' => 'custody_late:' . today(), 'created_at' => now()]);
        }
        return $n;
    }

    /** Terms shown on the receipt: the administrator's text, or the default for the current language. */
    public static function terms(string $type): string
    {
        $custom = trim((string) config('custody.terms', ''));
        return $type === 'issue' && $custom !== '' ? $custom : t('custody.terms_' . $type);
    }

    public static function find(int $id): ?array
    {
        $r = db()->one('SELECT r.*, e.employee_no, e.email, d.name AS department_name, u.name AS staff_name
            FROM custody_receipts r LEFT JOIN employees e ON e.id = r.employee_id LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u ON u.id = r.staff_user_id WHERE r.id = ?', [$id]);
        if ($r) {
            $r['items'] = json_decode((string) $r['items'], true) ?: [];
        }
        return $r;
    }

    public static function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $id = db()->value("SELECT id FROM custody_receipts WHERE sign_token = ? AND status = 'pending' AND token_expires > ?", [$token, now()]);
        return $id ? self::find((int) $id) : null;
    }

    /**
     * Store the signatures and seal the receipt.
     * @throws \InvalidArgumentException when a signature is missing or not a real drawing
     */
    public static function sign(array $receipt, string $employeeSig, ?string $staffSig, string $signerName, string $via, ?string $seen = null): void
    {
        if ($receipt['status'] !== 'pending') {
            throw new \InvalidArgumentException(t('custody.already_signed'));
        }
        // Items may join an unsigned receipt while the signing page is open: never seal what the employee did not see.
        if ($seen !== null && !hash_equals(self::contentKey($receipt), $seen)) {
            throw new \InvalidArgumentException(t('custody.changed'));
        }
        $empFile = self::storeSignature($employeeSig);
        $staffFile = $staffSig ? self::storeSignature($staffSig, false) : null;
        $signedAt = now();
        $data = [
            'status' => 'signed', 'employee_signature' => $empFile, 'staff_signature' => $staffFile,
            'signer_name' => mb_substr(trim($signerName) ?: $receipt['employee_name'], 0, 120), 'signed_at' => $signedAt,
            'signed_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'signed_via' => $via, 'sign_token' => null,
        ];
        $data['doc_hash'] = self::hash(array_merge($receipt, $data));
        db()->update('custody_receipts', (int) $receipt['id'], $data);
        Ledger::record('custody_signed', 'employee', $receipt['employee_id'] ? (int) $receipt['employee_id'] : null,
            "Custody receipt {$receipt['receipt_no']} signed by {$data['signer_name']} ($via)", ['receipt' => $receipt['receipt_no'], 'doc_hash' => $data['doc_hash']]);
        db()->update('custody_receipts', (int) $receipt['id'], ['ledger_hash' => db()->value('SELECT hash FROM ledger ORDER BY id DESC LIMIT 1')]);
    }

    /** Short key of the items and notes shown on the signing page, sent back with the signature. */
    public static function contentKey(array $r): string
    {
        return hash('sha256', json_encode([is_array($r['items']) ? $r['items'] : (json_decode((string) $r['items'], true) ?: []), (string) $r['notes']], JSON_UNESCAPED_UNICODE));
    }

    /** Fingerprint of everything that was agreed, including the signature images themselves. */
    public static function hash(array $r): string
    {
        $items = is_array($r['items']) ? $r['items'] : (json_decode((string) $r['items'], true) ?: []);
        $sig = static fn (?string $f) => $f && is_file(self::dir() . '/' . basename($f)) ? hash_file('sha256', self::dir() . '/' . basename($f)) : '';
        return hash('sha256', json_encode([
            $r['receipt_no'], $r['type'], $r['employee_name'], $items, $r['terms'], $r['notes'], $r['signer_name'], $r['signed_at'],
            $sig($r['employee_signature'] ?? null), $sig($r['staff_signature'] ?? null),
        ], JSON_UNESCAPED_UNICODE));
    }

    /** True when the stored fingerprint still matches the content and signature files. */
    public static function intact(array $r): bool
    {
        if ($r['status'] !== 'signed' || !$r['doc_hash']) {
            return false;
        }
        $entry = db()->one('SELECT * FROM ledger WHERE hash = ?', [(string) $r['ledger_hash']]);
        return hash_equals((string) $r['doc_hash'], self::hash($r)) && $entry !== null && Ledger::hash($entry) === $entry['hash'];
    }

    /** Render a receipt as a PDF file (null when no browser is available on the server). Logo and signatures travel inside the page. */
    public static function pdf(array $r): ?string
    {
        if (!Pdf::available()) {
            return null;
        }
        $embed = static function (string $file): string {
            return is_file($file) ? 'data:' . (mime_content_type($file) ?: 'image/png') . ';base64,' . base64_encode((string) file_get_contents($file)) : '';
        };
        $path = (string) config('brand.logo_full', '');
        $logo = $embed(ITAM_ROOT . '/public/assets/' . ($path !== '' && is_file(ITAM_ROOT . '/public/assets/' . $path) ? $path : 'img/logo-full.png'));
        $sigUrl = fn (string $who) => $embed(self::dir() . '/' . basename((string) ($who === 'staff' ? $r['staff_signature'] : $r['employee_signature'])));
        page_title((string) $r['receipt_no']);
        $html = View::render('custody/receipt', ['r' => $r, 'intact' => self::intact($r), 'verifyUrl' => '', 'link' => null, 'logo' => $logo, 'sigUrl' => $sigUrl, 'forPdf' => true],
            'procurement/_pdf_layout');
        return Pdf::fromHtml($html, ITAM_STORAGE . '/reports/' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r['receipt_no']) . '-' . bin2hex(random_bytes(3)) . '.pdf');
    }

    /** Validate a PNG data URL drawn on the signature pad and save it. Returns the file name. */
    private static function storeSignature(string $dataUrl, bool $required = true): ?string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            if ($required) {
                throw new \InvalidArgumentException(t('custody.sig_missing'));
            }
            return null;
        }
        $png = base64_decode($m[1], true);
        if ($png === false || strlen($png) > self::MAX_SIGNATURE_BYTES || !($img = @imagecreatefromstring($png))) {
            throw new \InvalidArgumentException(t('custody.sig_invalid'));
        }
        // Count drawn pixels on a coarse grid: an empty or single-dot pad is not a signature.
        [$w, $h] = [imagesx($img), imagesy($img)];
        if ($w > 3000 || $h > 1500) {
            throw new \InvalidArgumentException(t('custody.sig_invalid'));
        }
        $ink = 0;
        for ($x = 0; $x < $w; $x += 3) {
            for ($y = 0; $y < $h; $y += 3) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 100) {
                    $ink++;
                }
            }
        }
        unset($img);       // freed here; imagedestroy() is deprecated since PHP 8.5
        if ($ink < 25) {
            if ($required) {
                throw new \InvalidArgumentException(t('custody.sig_missing'));
            }
            return null;
        }
        if (!is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        $name = bin2hex(random_bytes(16)) . '.png';
        file_put_contents(self::dir() . '/' . $name, $png);
        return $name;
    }

    /** E-mail an employee about their receipt (title and body from notice.<kind>), in the language they last used. */
    private static function tell(int $employeeId, string $kind, array $params, string $link): void
    {
        $e = db()->one('SELECT id, email, locale FROM employees WHERE id = ? AND active = 1', [$employeeId]);
        if (!$e || !$e['email'] || !Notifier::canEmail()) {
            return;
        }
        $locale = isset(config('i18n.locales')[(string) $e['locale']]) ? (string) $e['locale'] : (string) config('i18n.default', 'en');
        \ITAM\Core\Lang::with($locale, fn () => Notifier::sendTo((string) $e['email'], t('notice.' . $kind, $params), t('notice.' . $kind . '_body', $params), Notifier::absolute($link)));
    }
}
