<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\AssetQuery;
use ITAM\Services\Ledger;

/**
 * Retiring an asset: the reason, how it left (recycled, donated, destroyed, returned to the vendor), when and to whom.
 * Retiring closes the custody, frees the licence seats and cancels open work orders.
 */
final class DisposalController extends Controller
{
    public const REASONS = ['end_of_life', 'beyond_repair', 'obsolete', 'damaged', 'other'];
    public const METHODS = ['recycle', 'donate', 'destroy', 'return_vendor'];

    public function index(): void
    {
        $rows = db()->all('SELECT d.*, u.name AS user_name FROM disposals d LEFT JOIN users u ON u.id = d.user_id ORDER BY d.disposed_at DESC, d.id DESC');
        $stats = [
            'total' => count($rows),
            'year'  => count(array_filter($rows, fn ($r) => str_starts_with($r['disposed_at'], date('Y')))),
        ];
        $this->view('disposals/index', compact('rows', 'stats'));
    }

    public function form(string $id): void
    {
        $asset = AssetQuery::find((int) $id) ?? abort(404, t('errors.not_found'));
        if ($asset['status'] === 'retired') {
            flash('warning', t('disposal.already'));
            redirect('/assets/' . $asset['id']);
        }
        $this->view('disposals/form', ['asset' => $asset, 'checks' => $this->checks($asset)]);
    }

    public function store(string $id): void
    {
        $asset = AssetQuery::find((int) $id) ?? abort(404, t('errors.not_found'));
        if ($asset['status'] === 'retired') {
            flash('warning', t('disposal.already'));
            redirect('/assets/' . $asset['id']);
        }
        $this->validate(['reason' => 'required', 'method' => 'required', 'disposed_at' => 'required|date', 'recipient' => 'max:160']);
        $error = match (true) {
            !in_array(input('reason'), self::REASONS, true), !in_array(input('method'), self::METHODS, true) => t('errors.invalid'),
            input('disposed_at') > today() => t('disposal.err_future'),
            default => null,
        };
        if ($error) {
            $_SESSION['_old'] = $_POST;
            flash('error', $error);
            back();
        }
        $aid = (int) $asset['id'];
        $db = db();
        $checks = $this->checks($asset);
        $db->transaction(function () use ($db, $asset, $aid, $checks) {
            $db->run('UPDATE assignments SET checked_in_at = ?, condition_in = ? WHERE asset_id = ? AND checked_in_at IS NULL', [now(), $asset['condition_state'], $aid]);
            $db->run('DELETE FROM license_seats WHERE asset_id = ?', [$aid]);
            $db->run("UPDATE maintenance SET status = 'cancelled' WHERE asset_id = ? AND status IN ('open','in_progress')", [$aid]);
            $db->update('assets', $aid, ['status' => 'retired', 'assigned_to' => null, 'updated_at' => now()]);
            $year = substr((string) input('disposed_at'), 0, 4);
            $data = [
                'certificate_no' => next_number('disposals', 'certificate_no', "RET-$year"),
                'asset_id' => $aid, 'asset_tag' => $asset['tag'], 'asset_name' => $asset['name'], 'serial' => $asset['serial'],
                'category_name' => $asset['category_name'],
                'reason' => input('reason'), 'method' => input('method'), 'sanitization' => 'not_applicable',
                'recipient' => input_or_null('recipient'), 'notes' => input_or_null('notes'),
                'disposed_at' => input('disposed_at'), 'user_id' => Auth::id(), 'created_at' => now(),
            ];
            $db->insert('disposals', $data);
            Ledger::record('dispose', 'asset', $aid, "Retired {$asset['tag']} ({$data['certificate_no']})", array_intersect_key($data, array_flip(
                ['certificate_no', 'reason', 'method', 'recipient', 'disposed_at']
            )) + ['released_seats' => $checks['seats'], 'cancelled_work_orders' => $checks['open_work']]);
        });
        flash('success', t('disposal.done'));
        redirect('/assets/' . $aid);
    }

    /** What retiring the asset will change, shown before confirming. */
    private function checks(array $asset): array
    {
        $aid = (int) $asset['id'];
        return [
            'assigned'  => $asset['employee_name'],
            'open_work' => (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE asset_id = ? AND status IN ('open','in_progress')", [$aid]),
            'seats'     => (int) db()->value('SELECT COUNT(*) FROM license_seats WHERE asset_id = ?', [$aid]),
        ];
    }
}
