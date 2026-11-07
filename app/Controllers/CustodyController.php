<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Custody;
use ITAM\Services\Ledger;
use ITAM\Services\Notifier;

final class CustodyController extends Controller
{
    public function index(): void
    {
        $status = in_array($_GET['status'] ?? '', ['pending', 'late', 'signed'], true) ? $_GET['status'] : null;
        // Late: still unsigned after the reminder delay, oldest first.
        $cut = days_from_today(-Custody::lateDays());
        [$where, $params] = match ($status) {
            'late' => [" AND r.status = 'pending' AND r.created_at < ?", [$cut]],
            null => ['', []],
            default => [' AND r.status = ?', [$status]],
        };
        // Users limited to some branches see the receipts of employees of those branches.
        $rows = db()->all('SELECT r.*, u.name AS staff_name FROM custody_receipts r LEFT JOIN users u ON u.id = r.staff_user_id LEFT JOIN employees e ON e.id = r.employee_id WHERE '
            . (scope_ids() === null ? '1=1' : scope_sql('e')) . $where . ' ORDER BY ' . ($status === 'late' ? 'r.id' : 'r.id DESC') . ' LIMIT 300', $params);
        $counts = array_column(db()->all('SELECT status, COUNT(*) AS n FROM custody_receipts GROUP BY status'), 'n', 'status');
        $counts['late'] = (int) db()->value("SELECT COUNT(*) FROM custody_receipts WHERE status = 'pending' AND created_at < ?", [$cut]);
        $this->view('custody/index', compact('rows', 'status', 'counts', 'cut'));
    }

    /** In-person signing: the employee signs on the technician's screen. */
    public function signForm(string $id): void
    {
        $r = $this->scoped(Custody::find((int) $id) ?? abort(404, t('errors.not_found')));
        if ($r['status'] === 'signed') {
            redirect('/custody/' . $r['id']);
        }
        $this->view('custody/sign', ['r' => $r, 'remote' => false, 'link' => $this->link($r)]);
    }

    public function sign(string $id): void
    {
        $r = $this->scoped(Custody::find((int) $id) ?? abort(404));
        try {
            Custody::sign($r, (string) input('employee_signature', ''), null, (string) input('signer_name', ''), 'in_person', input_or_null('seen'));
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/custody/' . $r['id'] . '/sign');
        }
        flash('success', t('custody.signed_ok'));
        redirect('/custody/' . $r['id']);
    }

    /** Printable receipt with signatures and the verification fingerprint. */
    public function show(string $id): void
    {
        $r = $this->scoped(Custody::find((int) $id) ?? abort(404, t('errors.not_found')));
        $origin = rtrim(Notifier::absolute('/') ?? '', '/');
        // The remote-signing link lets anyone sign as the employee: only people who hand out devices see it.
        $this->view('custody/receipt', ['r' => $r, 'intact' => Custody::intact($r), 'verifyUrl' => $origin . url('custody/' . $r['id']), 'link' => can('assets.assign') ? $this->link($r) : null,
            'removable' => $r['status'] === 'pending' && can('assets.assign')], 'layouts/print');
    }

    public function signature(string $id, string $who): void
    {
        $r = db()->one('SELECT employee_id, employee_signature, staff_signature FROM custody_receipts WHERE id = ?', [(int) $id]) ?? abort(404);
        scope_guard(employee_location($r['employee_id']));
        $file = $who === 'staff' ? $r['staff_signature'] : $r['employee_signature'];
        $path = $file ? Custody::dir() . '/' . basename($file) : '';
        if (!$path || !is_file($path)) {
            abort(404);
        }
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    /** New remote-signing link (the old one stops working); opens the signing screen with its QR code and link. */
    public function relink(string $id): void
    {
        $r = $this->scoped(Custody::find((int) $id) ?? abort(404));
        if ($r['status'] !== 'pending') {
            redirect('/custody/' . $r['id']);
        }
        db()->update('custody_receipts', (int) $r['id'], [
            'sign_token' => bin2hex(random_bytes(24)), 'token_expires' => date('Y-m-d H:i:s', time() + (int) config('custody.link_days', 7) * 86400),
        ]);
        flash('success', t('custody.link_renewed'));
        redirect('/custody/' . $r['id'] . '/sign');
    }

    /** Take an item added by mistake off an unsigned receipt. */
    public function removeItem(string $id): void
    {
        $r = $this->scoped(Custody::find((int) $id) ?? abort(404));
        try {
            Custody::removeItem($r, (string) input('item', ''));
            flash('success', t('custody.item_removed'));
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/custody/' . $r['id']);
    }

    public function destroy(string $id): void
    {
        $r = $this->scoped(Custody::find((int) $id) ?? abort(404));
        if ($r['status'] !== 'pending') {
            abort(422, t('custody.already_signed'));
        }
        db()->delete('custody_receipts', (int) $r['id']);
        Ledger::record('custody_cancel', 'employee', $r['employee_id'] ? (int) $r['employee_id'] : null, "Custody receipt {$r['receipt_no']} cancelled before signature");
        flash('success', t('common.deleted'));
        redirect('/custody');
    }

    // ---------- Remote signing (public link / QR, no account needed) ----------
    public function remoteForm(string $token): void
    {
        $r = Custody::byToken($token);
        if (!$r) {
            http_response_code(404);
        }
        $this->view($r ? 'custody/sign' : 'custody/link_invalid', ['r' => $r, 'remote' => true, 'token' => $token, 'link' => null], 'layouts/guest');
    }

    public function remoteSign(string $token): void
    {
        $r = Custody::byToken($token) ?? abort(404, t('custody.link_invalid'));
        try {
            Custody::sign($r, (string) input('employee_signature', ''), null, (string) input('signer_name', ''), 'link', input_or_null('seen'));
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/sign/' . $token);
        }
        $this->view('custody/thanks', ['r' => Custody::find((int) $r['id'])], 'layouts/guest');
    }

    private function link(array $r): ?string
    {
        return $r['status'] === 'pending' && $r['sign_token'] && $r['token_expires'] > now()
            ? rtrim(Notifier::absolute('/') ?? '', '/') . url('sign/' . $r['sign_token']) : null;
    }

    /** A receipt of an employee of the user's branches, or 403. */
    private function scoped(array $r): array
    {
        scope_guard(employee_location($r['employee_id'] ?? null));
        return $r;
    }
}
