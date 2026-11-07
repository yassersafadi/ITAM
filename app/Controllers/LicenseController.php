<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Ledger;

final class LicenseController extends Controller
{
    public const TYPES = ['per_user', 'per_device', 'subscription', 'site', 'concurrent'];

    public function index(): void
    {
        $rows = db()->all('SELECT l.*, (SELECT COUNT(*) FROM license_seats s WHERE s.license_id = l.id) AS used
            FROM licenses l ORDER BY l.name');
        $filter = $_GET['filter'] ?? '';
        $rows = array_values(array_filter($rows, function ($l) use ($filter) {
            return match ($filter) {
                'over'      => (int) $l['used'] > (int) $l['seats'],
                'expiring'  => $l['expires_at'] && $l['expires_at'] >= today() && $l['expires_at'] <= days_from_today(45),
                'expired'   => $l['expires_at'] && $l['expires_at'] < today(),
                default     => true,
            };
        }));
        $this->view('licenses/index', ['licenses' => $rows, 'filter' => $filter]);
    }

    public function show(string $id): void
    {
        $license = db()->one('SELECT * FROM licenses WHERE id = ?', [(int) $id]) ?? abort(404);
        $seats = db()->all('SELECT s.*, a.tag, a.name AS asset_name, e.name AS employee_name
            FROM license_seats s JOIN licenses l ON l.id = s.license_id
            LEFT JOIN assets a ON a.id = s.asset_id LEFT JOIN employees e ON e.id = s.employee_id
            WHERE s.license_id = ? ORDER BY s.assigned_at DESC', [(int) $id]);
        $assets = db()->all("SELECT id, tag, name FROM assets WHERE status NOT IN ('retired','lost') ORDER BY tag");
        $employees = db()->all('SELECT id, name FROM employees WHERE active = 1 ORDER BY name');
        $this->view('licenses/show', compact('license', 'seats', 'assets', 'employees'));
    }

    public function store(): void
    {
        $this->validate(['name' => 'required|max:160', 'seats' => 'required|int', 'expires_at' => 'date']);
        $data = $this->payload() + ['created_at' => now()];
        $id = db()->insert('licenses', $data);
        Ledger::record('create', 'license', $id, "License {$data['name']} ({$data['seats']} seats)");
        flash('success', t('common.saved'));
        redirect('/licenses/' . $id);
    }

    public function update(string $id): void
    {
        $before = db()->one('SELECT * FROM licenses WHERE id = ?', [(int) $id]) ?? abort(404);
        $this->validate(['name' => 'required|max:160', 'seats' => 'required|int', 'expires_at' => 'date']);
        $data = $this->payload();
        db()->update('licenses', (int) $id, $data);
        Ledger::record('update', 'license', (int) $id, "Updated license {$before['name']}", Ledger::diff($before, $data));
        flash('success', t('common.saved'));
        redirect('/licenses/' . $id);
    }

    public function destroy(string $id): void
    {
        $l = db()->one('SELECT * FROM licenses WHERE id = ?', [(int) $id]) ?? abort(404);
        db()->delete('licenses', (int) $id);
        Ledger::record('delete', 'license', (int) $id, "Deleted license {$l['name']}");
        flash('success', t('common.deleted'));
        redirect('/licenses');
    }

    public function assign(string $id): void
    {
        $l = db()->one('SELECT * FROM licenses WHERE id = ?', [(int) $id]) ?? abort(404);
        $asset = input_or_null('asset_id');
        $emp = input_or_null('employee_id');
        if (!$asset && !$emp) {
            flash('error', t('licenses.pick_target'));
            back();
        }
        $used = (int) db()->value('SELECT COUNT(*) FROM license_seats WHERE license_id = ?', [(int) $id]);
        if ($used >= (int) $l['seats']) {
            flash('warning', t('licenses.over_allocated', ['name' => $l['name']]));
        }
        db()->insert('license_seats', [
            'license_id' => (int) $id, 'asset_id' => $asset ? (int) $asset : null, 'employee_id' => $emp ? (int) $emp : null,
            'assigned_at' => now(), 'last_used_at' => now(),
        ]);
        Ledger::record('assign_seat', 'license', (int) $id, "Seat assigned on {$l['name']}", ['asset_id' => $asset, 'employee_id' => $emp]);
        flash('success', t('common.saved'));
        back();
    }

    public function release(string $id, string $seat): void
    {
        db()->run('DELETE FROM license_seats WHERE id = ? AND license_id = ?', [(int) $seat, (int) $id]);
        Ledger::record('release_seat', 'license', (int) $id, "Seat #{$seat} released");
        flash('success', t('licenses.released'));
        back();
    }

    private function payload(): array
    {
        $data = $this->only(['name', 'publisher', 'edition', 'license_type', 'license_key', 'seats', 'expires_at', 'notes']);
        $data['license_type'] = in_array($data['license_type'], self::TYPES, true) ? $data['license_type'] : 'per_device';
        $data['seats'] = max(1, (int) $data['seats']);
        return $data;
    }
}
