<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/**
 * Temporary loaner pool: employees book a device type for dates from the portal (or staff book for them),
 * staff approve, hand over and take back by scanning the QR label, and late returns raise an alert.
 * Hand-over creates a normal assignment with an expected return date, so the asset history stays complete.
 */
final class Loans
{
    public const STATUSES = ['requested', 'approved', 'checked_out', 'returned', 'cancelled', 'rejected', 'expired'];
    public const ACTIVE = ['requested', 'approved', 'checked_out'];
    public const TONE = ['requested' => 'warning', 'approved' => 'info', 'checked_out' => 'success', 'returned' => 'neutral', 'cancelled' => 'neutral', 'rejected' => 'danger', 'expired' => 'neutral'];
    /** Days after the start an approved booking waits for hand-over before it expires and frees the device. */
    public const COLLECT_DAYS = 3;

    /** Categories that have loanable devices, with how many. */
    public static function pool(): array
    {
        return db()->all("SELECT c.id, c.name, c.icon, COUNT(*) AS n FROM assets a JOIN categories c ON c.id = a.category_id
            WHERE a.loanable = 1 AND a.status NOT IN ('retired','lost','in_repair') GROUP BY c.id, c.name, c.icon ORDER BY c.name");
    }

    /** Loanable devices free for the whole period (no overlapping booking, not held by someone else). A device still out past its date stays taken. */
    public static function available(string $from, string $to, ?int $categoryId = null, ?int $excludeBooking = null): array
    {
        $params = [$to, $from];
        $sql = "SELECT a.id, a.tag, a.name, a.category_id, a.location_id, l.name AS location_name FROM assets a LEFT JOIN locations l ON l.id = a.location_id
            WHERE a.loanable = 1 AND a.status NOT IN ('retired','lost','in_repair','discovered')
              AND (a.assigned_to IS NULL OR EXISTS (SELECT 1 FROM loan_bookings x WHERE x.asset_id = a.id AND x.status = 'checked_out'))
              AND NOT EXISTS (SELECT 1 FROM loan_bookings b WHERE b.asset_id = a.id AND b.status IN ('requested','approved','checked_out')
                              AND b.starts_on <= ? AND (b.ends_on >= ? OR b.status = 'checked_out')" . ($excludeBooking ? ' AND b.id <> ' . (int) $excludeBooking : '') . ')';
        if ($categoryId) {
            $sql .= ' AND a.category_id = ' . (int) $categoryId;
        }
        return db()->all($sql . ' ORDER BY COALESCE(a.health_score, 0) DESC, a.tag', $params);
    }

    /** Checks dates against the pool rules; returns an error message or null. */
    public static function invalidDates(string $from, string $to, bool $staff = false): ?string
    {
        $ok = fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        if (!$ok($from) || !$ok($to) || $to < $from) {
            return t('loans.bad_dates');
        }
        if (!$staff && $from < today()) {
            return t('loans.past');
        }
        if ((int) days_between($from, $to) + 1 > (int) config('loans.max_days', 14)) {
            return t('loans.too_long', ['n' => (int) config('loans.max_days', 14)]);
        }
        if (!$staff && $from > days_from_today((int) config('loans.ahead_days', 60))) {
            return t('loans.too_far', ['n' => (int) config('loans.ahead_days', 60)]);
        }
        return null;
    }

    /** Book the first free device of a category (or a given device). Portal requests wait for approval. */
    public static function book(int $employeeId, ?int $categoryId, ?int $assetId, string $from, string $to, ?string $purpose, string $via): ?int
    {
        $free = self::available($from, $to, $categoryId);
        if ($assetId) {
            $free = array_values(array_filter($free, fn ($a) => (int) $a['id'] === $assetId));
        }
        if (!$free) {
            return null;
        }
        $asset = $free[0];
        $id = db()->insert('loan_bookings', [
            'number' => next_number('loan_bookings', 'number', 'LN-' . date('Y')), 'asset_id' => $asset['id'], 'employee_id' => $employeeId,
            'starts_on' => $from, 'ends_on' => $to, 'purpose' => $purpose !== null ? mb_substr($purpose, 0, 255) : null,
            'status' => $via === 'portal' ? 'requested' : 'approved', 'via' => $via, 'user_id' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $emp = (string) db()->value('SELECT name FROM employees WHERE id = ?', [$employeeId]);
        Ledger::record('loan_book', 'loan', $id, "Loan booking for $emp: {$asset['tag']} $from → $to ($via)");
        if ($via === 'portal') {
            $title = t('loans.notify_request', ['name' => $emp]);
            db()->insert('notifications', ['type' => 'loan', 'title' => $title, 'body' => "{$asset['tag']} · $from → $to", 'link' => url('loans', ['view' => 'requests']),
                'fingerprint' => 'loan:' . $id, 'created_at' => now()]);
            Notifier::send($title, "{$asset['tag']} · $from → $to" . ($purpose ? "\n$purpose" : ''), Notifier::absolute('/loans?view=requests'));
        }
        return $id;
    }

    public static function find(int $id): ?array
    {
        return db()->one('SELECT b.*, a.tag, a.name AS asset_name, a.status AS asset_status, a.assigned_to, a.condition_state, c.icon,
            e.name AS employee_name, e.email AS employee_email FROM loan_bookings b JOIN assets a ON a.id = b.asset_id
            LEFT JOIN categories c ON c.id = a.category_id JOIN employees e ON e.id = b.employee_id WHERE b.id = ?', [$id]);
    }

    /** Status change by staff (approve, reject, cancel). Returns false when the move is not allowed. */
    public static function decide(array $b, string $to): bool
    {
        $allowed = ['requested' => ['approved', 'rejected', 'cancelled'], 'approved' => ['cancelled']][$b['status']] ?? [];
        if (!in_array($to, $allowed, true)) {
            return false;
        }
        db()->update('loan_bookings', (int) $b['id'], ['status' => $to, 'updated_at' => now()]);
        Ledger::record('loan_' . $to, 'loan', (int) $b['id'], "{$b['number']} ({$b['tag']}, {$b['employee_name']}) → $to");
        if ($b['employee_email'] && in_array($to, ['approved', 'rejected'], true)) {
            Notifier::sendTo($b['employee_email'], t('loans.mail_' . $to, ['n' => $b['number']]), t('loans.mail_body', ['tag' => $b['tag'], 'from' => $b['starts_on'], 'to' => $b['ends_on']]), Notifier::absolute('/portal'));
        }
        return true;
    }

    /** Hand the device over: an assignment with the booking's end as expected return. */
    public static function checkout(array $b): void
    {
        $now = now();
        db()->transaction(function () use ($b, $now) {
            $emp = db()->one('SELECT location_id FROM employees WHERE id = ?', [$b['employee_id']]);
            db()->insert('assignments', ['asset_id' => $b['asset_id'], 'employee_id' => $b['employee_id'], 'checked_out_at' => $now, 'expected_return' => $b['ends_on'],
                'condition_out' => $b['condition_state'], 'notes' => t('loans.assignment_note', ['n' => $b['number']]), 'user_id' => Auth::id()]);
            db()->update('assets', (int) $b['asset_id'], ['assigned_to' => $b['employee_id'], 'status' => 'deployed', 'location_id' => $emp['location_id'] ?? null, 'last_seen_at' => $now, 'updated_at' => $now]);
            db()->update('loan_bookings', (int) $b['id'], ['status' => 'checked_out', 'checked_out_at' => $now, 'updated_at' => $now]);
        });
        Ledger::record('checkout', 'asset', (int) $b['asset_id'], "Loaner {$b['tag']} handed to {$b['employee_name']} until {$b['ends_on']} ({$b['number']})");
    }

    public static function checkin(array $b): void
    {
        $now = now();
        db()->transaction(function () use ($b, $now) {
            db()->run('UPDATE assignments SET checked_in_at = ?, condition_in = ? WHERE asset_id = ? AND checked_in_at IS NULL', [$now, $b['condition_state'], $b['asset_id']]);
            db()->update('assets', (int) $b['asset_id'], ['assigned_to' => null, 'status' => 'in_stock', 'last_seen_at' => $now, 'updated_at' => $now]);
            db()->update('loan_bookings', (int) $b['id'], ['status' => 'returned', 'returned_at' => $now, 'updated_at' => $now]);
        });
        Ledger::record('checkin', 'asset', (int) $b['asset_id'], "Loaner {$b['tag']} returned by {$b['employee_name']} ({$b['number']})" . ($b['ends_on'] < today() ? ', ' . days_between($b['ends_on']) . ' days late' : ''));
    }

    /**
     * QR hand-over desk: a scanned label returns a device that is out on loan, or hands over the booking that
     * starts today (or earlier and is still open) for that device.
     * @return array{ok: bool, message: string, booking?: int}
     */
    public static function scan(string $code): array
    {
        $code = trim($code);
        if (preg_match('#/a/([^/?\#]+)#', $code, $m)) {
            $code = rawurldecode($m[1]);
        }
        $asset = db()->one('SELECT id, tag FROM assets WHERE tag = ? OR serial = ? LIMIT 1', [$code, $code]);
        if (!$asset) {
            return ['ok' => false, 'message' => t('loans.scan_unknown', ['code' => mb_substr($code, 0, 40)])];
        }
        $out = db()->value("SELECT id FROM loan_bookings WHERE asset_id = ? AND status = 'checked_out' ORDER BY id DESC LIMIT 1", [$asset['id']]);
        if ($out) {
            $b = self::find((int) $out);
            self::checkin($b);
            return ['ok' => true, 'message' => t('loans.scan_returned', ['tag' => $b['tag'], 'name' => $b['employee_name']]), 'booking' => (int) $b['id']];
        }
        $due = db()->value("SELECT id FROM loan_bookings WHERE asset_id = ? AND status = 'approved' AND starts_on <= ? AND ends_on >= ? ORDER BY starts_on LIMIT 1", [$asset['id'], today(), today()]);
        if ($due) {
            $b = self::find((int) $due);
            self::checkout($b);
            return ['ok' => true, 'message' => t('loans.scan_out', ['tag' => $b['tag'], 'name' => $b['employee_name'], 'date' => fmt_date($b['ends_on'])]), 'booking' => (int) $b['id']];
        }
        return ['ok' => false, 'message' => t('loans.scan_nothing', ['tag' => $asset['tag']])];
    }

    public static function forEmployee(int $employeeId): array
    {
        return db()->all("SELECT b.*, a.tag, a.name AS asset_name, c.icon FROM loan_bookings b JOIN assets a ON a.id = b.asset_id
            LEFT JOIN categories c ON c.id = a.category_id WHERE b.employee_id = ? AND (b.status IN ('requested','approved','checked_out') OR b.updated_at >= ?)
            ORDER BY b.starts_on DESC LIMIT 10", [$employeeId, days_from_today(-30)]);
    }

    /** Loans past their return date. */
    public static function overdue(): array
    {
        return db()->all("SELECT b.*, a.tag, e.name AS employee_name, e.email AS employee_email FROM loan_bookings b JOIN assets a ON a.id = b.asset_id
            JOIN employees e ON e.id = b.employee_id WHERE b.status = 'checked_out' AND b.ends_on < ? ORDER BY b.ends_on", [today()]);
    }

    /** Nightly: one alert per late loan (staff notification, e-mail to the borrower, ledger entry and webhook). */
    public static function remindOverdue(): int
    {
        $n = 0;
        foreach (self::overdue() as $b) {
            // Every 3 days, not every night: compare calendar days, since reminded_at carries a time.
            if ($b['reminded_at'] && substr((string) $b['reminded_at'], 0, 10) > days_from_today(-3)) {
                continue;
            }
            $late = (int) days_between($b['ends_on']);
            db()->insert('notifications', ['type' => 'loan', 'title' => t('loans.notify_overdue', ['tag' => $b['tag'], 'name' => $b['employee_name'], 'n' => $late]),
                'body' => $b['number'], 'link' => url('loans', ['view' => 'active']), 'fingerprint' => 'loan_late:' . $b['id'] . ':' . today(), 'created_at' => now()]);
            if ($b['employee_email']) {
                Notifier::sendTo($b['employee_email'], t('loans.mail_overdue_subject', ['tag' => $b['tag']]), t('loans.mail_overdue_body', ['name' => $b['employee_name'], 'tag' => $b['tag'], 'date' => $b['ends_on']]));
            }
            Ledger::record('loan_overdue', 'loan', (int) $b['id'], "{$b['number']}: {$b['tag']} not returned by {$b['employee_name']}, $late days late");
            db()->update('loan_bookings', (int) $b['id'], ['reminded_at' => now()]);
            $n++;
        }
        return $n;
    }

    /**
     * Nightly: bookings nobody collected stop holding the device. An approved booking expires when its end date
     * has passed or COLLECT_DAYS days after its start without hand-over; a request never decided expires after its
     * end date. The employee is told by e-mail, as for approval and rejection.
     */
    public static function expireUncollected(): int
    {
        $n = 0;
        $rows = db()->all("SELECT b.id FROM loan_bookings b WHERE (b.status = 'approved' AND (b.ends_on < ? OR b.starts_on < ?))
            OR (b.status = 'requested' AND b.ends_on < ?)", [today(), days_from_today(-self::COLLECT_DAYS), today()]);
        foreach ($rows as $r) {
            $b = self::find((int) $r['id']);
            if (!$b) {
                continue;
            }
            db()->update('loan_bookings', (int) $b['id'], ['status' => 'expired', 'updated_at' => now()]);
            Ledger::record('loan_expired', 'loan', (int) $b['id'], "{$b['number']} ({$b['tag']}, {$b['employee_name']}) expired: {$b['status']} and never handed over");
            if ($b['employee_email']) {
                Notifier::sendTo($b['employee_email'], t('loans.mail_expired', ['n' => $b['number']]), t('loans.mail_expired_body', ['tag' => $b['tag'], 'from' => $b['starts_on'], 'to' => $b['ends_on']]), Notifier::absolute('/portal'));
            }
            $n++;
        }
        return $n;
    }

    /** Booking calendar: every loanable device with its bookings over $days days from $start. */
    public static function calendar(string $start, int $days = 21): array
    {
        $end = date('Y-m-d', strtotime($start . ' +' . ($days - 1) . ' days'));
        $assets = db()->all("SELECT a.id, a.tag, a.name, c.icon FROM assets a LEFT JOIN categories c ON c.id = a.category_id
            WHERE a.loanable = 1 AND a.status NOT IN ('retired','lost') ORDER BY c.name, a.tag");
        $bookings = db()->all("SELECT b.id, b.asset_id, b.number, b.starts_on, b.ends_on, b.status, e.name AS employee_name FROM loan_bookings b
            JOIN employees e ON e.id = b.employee_id WHERE b.status IN ('requested','approved','checked_out') AND b.starts_on <= ? AND b.ends_on >= ?", [$end, $start]);
        $by = [];
        foreach ($bookings as $b) {
            $by[(int) $b['asset_id']][] = $b;
        }
        foreach ($assets as &$a) {
            $a['bookings'] = $by[(int) $a['id']] ?? [];
        }
        unset($a);
        return ['start' => $start, 'end' => $end, 'days' => $days, 'assets' => $assets];
    }
}
