<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Ledger;
use ITAM\Services\Loans;

final class LoanController extends Controller
{
    public const VIEWS = ['calendar', 'requests', 'active', 'history'];

    public function index(): void
    {
        $view = in_array($_GET['view'] ?? '', self::VIEWS, true) ? $_GET['view'] : 'calendar';
        $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['start'] ?? '')) ? $_GET['start'] : date('Y-m-d', strtotime('monday this week'));
        $where = match ($view) {
            'requests' => "b.status = 'requested'",
            'active' => "b.status IN ('approved','checked_out')",
            'history' => "b.status IN ('returned','cancelled','rejected','expired')",
            default => null,
        };
        $rows = $where ? db()->all("SELECT b.*, a.tag, a.name AS asset_name, c.icon, e.name AS employee_name FROM loan_bookings b JOIN assets a ON a.id = b.asset_id
            LEFT JOIN categories c ON c.id = a.category_id JOIN employees e ON e.id = b.employee_id WHERE $where
            ORDER BY " . ($view === 'history' ? 'b.updated_at DESC LIMIT 200' : 'b.starts_on, b.id')) : [];
        $counts = array_column(db()->all("SELECT status, COUNT(*) AS n FROM loan_bookings WHERE status IN ('requested','approved','checked_out') GROUP BY status"), 'n', 'status');
        $data = [
            'view' => $view, 'rows' => $rows, 'counts' => $counts, 'overdue' => count(Loans::overdue()),
            'cal' => $view === 'calendar' ? Loans::calendar($start) : null, 'pool' => Loans::pool(),
            'candidates' => db()->all("SELECT a.id, a.tag, a.name FROM assets a WHERE a.loanable = 0 AND a.status = 'in_stock' AND a.assigned_to IS NULL AND " . scope_sql('a') . ' ORDER BY a.tag'),
            'loanable' => db()->all("SELECT a.id, a.tag, a.name FROM assets a WHERE a.loanable = 1 AND a.status NOT IN ('retired','lost') ORDER BY a.tag"),
            'employees' => db()->all('SELECT id, name FROM employees WHERE active = 1 ORDER BY name'),
        ];
        $this->view('loans/index', $data);
    }

    public function store(): void
    {
        $this->validate(['employee_id' => 'required|int', 'starts_on' => 'required|date', 'ends_on' => 'required|date']);
        $from = (string) input('starts_on');
        $to = (string) input('ends_on');
        if ($err = Loans::invalidDates($from, $to, true)) {
            flash('error', $err);
            back();
        }
        $emp = (int) db()->value('SELECT id FROM employees WHERE id = ? AND active = 1', [(int) input('employee_id')]) ?: abort(422);
        $id = Loans::book($emp, input('category_id') ? (int) input('category_id') : null, input('asset_id') ? (int) input('asset_id') : null, $from, $to, input_or_null('purpose'), 'staff');
        flash($id ? 'success' : 'error', $id ? t('loans.booked') : t('loans.none_free'));
        redirect('/loans');
    }

    public function decide(string $id): void
    {
        $b = Loans::find((int) $id) ?? abort(404);
        Loans::decide($b, (string) input('status')) ? flash('success', t('common.saved')) : abort(422);
        back();
    }

    public function checkout(string $id): void
    {
        $b = Loans::find((int) $id) ?? abort(404);
        if ($b['status'] !== 'approved' || $b['starts_on'] > today() || $b['ends_on'] < today()) {
            flash('error', t('loans.not_ready'));
            back();
        }
        Loans::checkout($b);
        flash('success', t('loans.scan_out', ['tag' => $b['tag'], 'name' => $b['employee_name'], 'date' => fmt_date($b['ends_on'])]));
        back();
    }

    public function checkin(string $id): void
    {
        $b = Loans::find((int) $id) ?? abort(404);
        $b['status'] === 'checked_out' ? Loans::checkin($b) : abort(422);
        flash('success', t('loans.scan_returned', ['tag' => $b['tag'], 'name' => $b['employee_name']]));
        back();
    }

    /** Hand-over desk scan (JSON for the camera scanner, redirect for the plain form). */
    public function scan(): void
    {
        // A hand-over or return happens at the desk, now: a scan saved offline and replayed later would be
        // recorded at the wrong time (or hand over a booking that has since changed), so it is refused.
        if (input('offline')) {
            json_response(['ok' => false, 'result' => 'unexpected', 'asset' => null, 'message' => t('loans.scan_offline')], 422);
        }
        $res = Loans::scan((string) input('code', ''));
        if (wants_json()) {
            // Same shape as the audit scanner, which drives this desk too.
            json_response($res + ['result' => $res['ok'] ? 'found' : 'unexpected', 'asset' => null], $res['ok'] ? 200 : 422);
        }
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect('/loans', ['view' => 'active']);
    }

    /** Add a device to the loaner pool or take it out. */
    public function pool(): void
    {
        $id = (int) input('asset_id');
        $on = input('loanable') ? 1 : 0;
        $tag = db()->value('SELECT tag FROM assets WHERE id = ?', [$id]) ?? abort(404);
        if (!$on && db()->value("SELECT id FROM loan_bookings WHERE asset_id = ? AND status IN ('requested','approved','checked_out')", [$id])) {
            flash('error', t('loans.pool_busy'));
            back();
        }
        db()->update('assets', $id, ['loanable' => $on, 'updated_at' => now()]);
        Ledger::record('loan_pool', 'asset', $id, ($on ? 'Added to' : 'Removed from') . " the loaner pool: $tag");
        flash('success', t('common.saved'));
        back();
    }
}
