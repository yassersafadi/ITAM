<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\View;
use ITAM\Services\Custody;
use ITAM\Services\Ledger;
use ITAM\Services\Loans;
use ITAM\Services\Notifier;
use ITAM\Services\Oidc;
use ITAM\Services\Pdf;
use ITAM\Services\Sla;

/**
 * Employee self-service portal: people see the devices and licences they hold, sign and download their custody
 * receipts, report a fault on a device and book a loaner. Sign-in is with their Microsoft account (SSO) or a one-time
 * link sent by e-mail; portal sessions are separate from staff accounts and grant no access to the management screens.
 */
final class PortalController extends Controller
{
    private const LINK_MINUTES = 15;

    /** The signed-in employee, or null. Applies the same idle timeout as staff sessions. */
    public static function employee(): ?array
    {
        $id = $_SESSION['portal_emp'] ?? null;
        if (!$id) {
            return null;
        }
        $idle = (int) config('security.session_idle', 0);
        if ($idle > 0 && time() - (int) ($_SESSION['portal_at'] ?? 0) > $idle * 60) {
            unset($_SESSION['portal_emp'], $_SESSION['portal_at']);
            return null;
        }
        $_SESSION['portal_at'] = time();
        return db()->one('SELECT e.*, d.name AS department_name, l.name AS location_name FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN locations l ON l.id = e.location_id
            WHERE e.id = ? AND e.active = 1', [(int) $id]);
    }

    public static function signIn(int $employeeId, string $via): void
    {
        session_regenerate_id(true);
        $_SESSION['portal_emp'] = $employeeId;
        $_SESSION['portal_at'] = time();
        Ledger::record('portal_login', 'employee', $employeeId, "Employee signed in to the self-service portal ($via)");
    }

    // ---------- Sign-in ----------
    public function login(): void
    {
        if (self::employee()) {
            redirect('/portal');
        }
        $this->view('portal/login', ['sso' => Oidc::enabled(), 'email' => Notifier::canEmail()], 'layouts/guest');
    }

    public function sendLink(): void
    {
        $email = strtolower(trim((string) input('email', '')));
        // Requests are counted in the database per address and per e-mail (a session counter resets without cookies).
        $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
        $hour = date('Y-m-d H:i:s', time() - 3600);
        $byIp = (int) db()->value("SELECT COUNT(*) FROM login_failures WHERE ip = ? AND email LIKE 'portal:%' AND created_at > ?", [$ip, $hour]);
        $byEmail = (int) db()->value('SELECT COUNT(*) FROM login_failures WHERE email = ? AND created_at > ?', ['portal:' . $email, $hour]);
        db()->insert('login_failures', ['email' => mb_substr('portal:' . $email, 0, 190), 'ip' => $ip, 'created_at' => now()]);
        if ($byIp < 10 && $byEmail < 3 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emp = db()->one('SELECT id, name FROM employees WHERE LOWER(email) = ? AND active = 1', [$email]);
            if ($emp) {
                $token = bin2hex(random_bytes(32));
                db()->insert('portal_tokens', [
                    'employee_id' => $emp['id'], 'token_hash' => hash('sha256', $token),
                    'expires_at' => date('Y-m-d H:i:s', time() + self::LINK_MINUTES * 60), 'created_at' => now(),
                ]);
                $link = Notifier::absolute('/portal/auth/' . $token);
                Notifier::sendTo($email, t('portal.mail_subject', ['app' => app_name()]), t('portal.mail_body', ['name' => $emp['name'], 'n' => self::LINK_MINUTES]), $link);
            }
        }
        // Same answer whether or not the address exists, so the form cannot be used to list employees.
        flash('success', t('portal.link_sent'));
        redirect('/portal/login');
    }

    public function magic(string $token): void
    {
        $row = preg_match('/^[a-f0-9]{64}$/', $token)
            ? db()->one('SELECT * FROM portal_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [hash('sha256', $token), now()])
            : null;
        if (!$row) {
            flash('error', t('portal.link_invalid'));
            redirect('/portal/login');
        }
        db()->update('portal_tokens', (int) $row['id'], ['used_at' => now()]);
        self::signIn((int) $row['employee_id'], 'link');
        redirect('/portal');
    }

    public function sso(): void
    {
        if (!Oidc::enabled()) {
            redirect('/portal/login');
        }
        $_SESSION['sso_target'] = 'portal';
        redirect(Oidc::authorizeUrl());
    }

    public function logout(): void
    {
        unset($_SESSION['portal_emp'], $_SESSION['portal_at']);
        redirect('/portal/login');
    }

    // ---------- Pages ----------
    /** Home: receipts waiting for a signature, the employee's open fault reports and loaner bookings. */
    public function home(): void
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $id = (int) $emp['id'];
        $pending = $this->pendingReceipts($id);
        $tickets = db()->all("SELECT m.id, m.title, m.status, m.created_at, a.tag FROM maintenance m JOIN assets a ON a.id = m.asset_id
            WHERE m.source = 'portal' AND m.reporter_contact = ? ORDER BY m.id DESC LIMIT 5", ['emp:' . $id]);
        $loans = db()->all("SELECT b.id, b.number, b.status, b.starts_on, b.ends_on, c.name AS category_name FROM loan_bookings b
            JOIN assets la ON la.id = b.asset_id LEFT JOIN categories c ON c.id = la.category_id WHERE b.employee_id = ? AND b.status IN ('requested','approved','checked_out') ORDER BY b.starts_on", [$id]);
        $warrantySoon = (int) db()->value('SELECT COUNT(*) FROM assets WHERE assigned_to = ? AND warranty_until IS NOT NULL AND warranty_until BETWEEN ? AND ?',
            [$id, today(), days_from_today(60)]);
        $openTickets = (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE source = 'portal' AND reporter_contact = ? AND status IN ('open','in_progress')", ['emp:' . $id]);
        $this->page('home', compact('pending', 'tickets', 'loans', 'warrantySoon', 'openTickets'));
    }

    public function devices(): void
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $licenses = db()->all('SELECT l.name, l.edition FROM license_seats s JOIN licenses l ON l.id = s.license_id WHERE s.employee_id = ? ORDER BY l.name', [$emp['id']]);
        $this->page('devices', compact('licenses'));
    }

    /** One device: its facts, warranty and the fault history since the employee got it. */
    public function device(string $id): void
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $a = db()->one('SELECT a.*, c.name AS category_name, c.icon, l.name AS location_name,
                (SELECT MAX(x.checked_out_at) FROM assignments x WHERE x.asset_id = a.id AND x.employee_id = a.assigned_to) AS since
            FROM assets a LEFT JOIN categories c ON c.id = a.category_id LEFT JOIN locations l ON l.id = a.location_id
            WHERE a.id = ? AND a.assigned_to = ?', [(int) $id, $emp['id']]) ?? abort(404, t('errors.not_found'));
        $history = db()->all("SELECT id, title, status, created_at, completed_at FROM maintenance WHERE asset_id = ? AND created_at >= ? AND type <> 'preventive'
            ORDER BY id DESC LIMIT 20", [$a['id'], $a['since'] ?: '0000']);
        $this->page('device', compact('a', 'history'));
    }

    /** Custody receipts: waiting for a signature and signed, with the licences the employee holds. */
    public function documents(): void
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $pending = $this->pendingReceipts((int) $emp['id']);
        $signed = db()->all("SELECT id, receipt_no, type, signed_at FROM custody_receipts WHERE employee_id = ? AND status = 'signed' ORDER BY id DESC", [$emp['id']]);
        $licenses = db()->all('SELECT l.name, l.edition FROM license_seats s JOIN licenses l ON l.id = s.license_id WHERE s.employee_id = ? ORDER BY l.name', [$emp['id']]);
        $this->page('documents', compact('pending', 'signed', 'licenses'));
    }

    /** The employee's own signed receipt, printable. */
    public function receipt(string $id): void
    {
        $r = $this->ownReceipt($id);
        $this->view('custody/receipt', [
            'r' => $r, 'intact' => Custody::intact($r), 'verifyUrl' => rtrim(Notifier::absolute('/') ?? '', '/') . url('custody/' . $r['id']), 'link' => null,
            'portal' => true, 'pdf' => Pdf::available(), 'sigUrl' => fn (string $who) => url('portal/receipts/' . $r['id'] . '/signature/' . $who),
        ], 'layouts/print');
    }

    /** The receipt as a PDF file (or the printable page when the server cannot render PDFs). */
    public function receiptPdf(string $id): void
    {
        $r = $this->ownReceipt($id);
        $file = Custody::pdf($r);
        if (!$file) {
            redirect('/portal/receipts/' . $r['id']);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r['receipt_no']) . '.pdf"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        @unlink($file);
        exit;
    }

    public function receiptSignature(string $id, string $who): void
    {
        $r = $this->ownReceipt($id);
        $file = $who === 'staff' ? $r['staff_signature'] : $r['employee_signature'];
        $path = $file ? Custody::dir() . '/' . basename((string) $file) : '';
        if (!$path || !is_file($path)) {
            abort(404);
        }
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    /** Render a portal page inside the portal shell; the dialogs on every page need the devices and the loaner pool. */
    public function page(string $view, array $data): void
    {
        $emp = self::employee();
        $assets = db()->all('SELECT a.id, a.tag, a.name, a.serial, a.model, a.status, a.warranty_until, a.acquired_at, c.icon, c.name AS category_name,
                (SELECT MAX(x.checked_out_at) FROM assignments x WHERE x.asset_id = a.id AND x.employee_id = a.assigned_to) AS since
            FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.assigned_to = ? ORDER BY a.tag', [$emp['id']]);
        // E-mails to the employee follow the language they last used here.
        if ($emp['locale'] !== \ITAM\Core\Lang::locale()) {
            db()->update('employees', (int) $emp['id'], ['locale' => \ITAM\Core\Lang::locale()]);
        }
        $data = [
            'emp' => $emp, 'assets' => $assets, 'page' => $view,
            'categories' => db()->all('SELECT id, name FROM categories ORDER BY name'),
            'loanPool' => Loans::pool(),
            'toSign' => (int) db()->value("SELECT COUNT(*) FROM custody_receipts WHERE employee_id = ? AND status = 'pending'", [$emp['id']]),
        ] + $data;
        unset($_SESSION['_old']);
        echo View::partial('layouts/guest', $data + ['content' => View::render('portal/' . $view, $data, 'portal/_shell')]);
    }

    /** Pending receipts get a fresh signing link so the employee can always sign from the portal. */
    private function pendingReceipts(int $employeeId): array
    {
        $pending = db()->all("SELECT * FROM custody_receipts WHERE employee_id = ? AND status = 'pending' ORDER BY id DESC", [$employeeId]);
        foreach ($pending as &$p) {
            if (!$p['sign_token'] || $p['token_expires'] <= now()) {
                $p['sign_token'] = bin2hex(random_bytes(24));
                db()->update('custody_receipts', (int) $p['id'], ['sign_token' => $p['sign_token'], 'token_expires' => date('Y-m-d H:i:s', time() + 86400)]);
            }
        }
        unset($p);
        return $pending;
    }

    /** A signed receipt that belongs to the signed-in employee, or 404. */
    private function ownReceipt(string $id): array
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $r = Custody::find((int) $id);
        if (!$r || (int) $r['employee_id'] !== (int) $emp['id'] || $r['status'] !== 'signed') {
            abort(404, t('errors.not_found'));
        }
        return $r;
    }

    private static function returnTo(): never
    {
        $path = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
        $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        $local = $base !== '' && str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
        redirect(preg_match('#^/portal(/(devices|documents)(/\d+)?)?$#', $local) ? $local : '/portal');
    }

    // ---------- Actions ----------
    /** A fault on one of the employee's devices becomes a work order on it. */
    public function report(): void
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $this->validate(['asset_id' => 'required', 'description' => 'required|max:2000']);
        $asset = db()->one('SELECT id, tag FROM assets WHERE id = ? AND assigned_to = ?', [(int) input('asset_id'), $emp['id']]) ?? abort(403);
        $priority = ReportIssueController::URGENCY[(string) input('urgency')] ?? 'medium';
        $desc = trim((string) input('description'));
        $title = mb_strimwidth(preg_replace('/\s+/u', ' ', $desc), 0, 80, '…');
        $now = now();
        $id = db()->insert('maintenance', [
            'asset_id' => $asset['id'], 'type' => 'corrective', 'title' => $title,
            'description' => $desc, 'status' => 'open', 'priority' => $priority, 'source' => 'portal',
            'reporter_name' => $emp['name'], 'reporter_contact' => 'emp:' . $emp['id'], 'reporter_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'due_at' => Sla::dueAt($priority, $now), 'created_at' => $now,
        ]);
        Ledger::record('report', 'asset', (int) $asset['id'], "Fault reported on {$asset['tag']} by {$emp['name']} via portal (work order #$id)");
        $title = t('report.notify_title', ['tag' => $asset['tag']]);
        $body = $emp['name'] . ' — ' . $desc;
        db()->insert('notifications', ['user_id' => null, 'type' => 'report', 'title' => $title, 'body' => $body,
            'link' => url('maintenance', ['source' => 'report']), 'fingerprint' => 'report:' . $id, 'created_at' => $now]);
        Notifier::send($title, $body, Notifier::absolute('/maintenance'));
        flash('success', t('portal.reported', ['ref' => 'WO-' . $id]));
        self::returnTo();
    }

    /** Book a loaner of a given type for dates; staff approve it before hand-over. */
    public function loan(): void
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $from = (string) input('starts_on');
        $to = (string) input('ends_on');
        if ($err = Loans::invalidDates($from, $to)) {
            flash('error', $err);
            self::returnTo();
        }
        $id = Loans::book((int) $emp['id'], (int) input('category_id') ?: null, null, $from, $to, input_or_null('purpose'), 'portal');
        $id ? flash('success', t('loans.requested', ['n' => db()->value('SELECT number FROM loan_bookings WHERE id = ?', [$id])]))
            : flash('error', t('loans.none_free'));
        self::returnTo();
    }

    public function loanCancel(string $id): void
    {
        $emp = self::employee() ?? redirect('/portal/login');
        $b = Loans::find((int) $id);
        if (!$b || (int) $b['employee_id'] !== (int) $emp['id'] || !Loans::decide($b, 'cancelled')) {
            abort(403);
        }
        flash('success', t('loans.cancelled'));
        self::returnTo();
    }
}
