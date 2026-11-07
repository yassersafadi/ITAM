<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Services\Consumables;
use ITAM\Services\Ledger;

final class ConsumableController extends Controller
{
    private const FIELDS = ['name', 'sku', 'kind', 'unit', 'min_quantity', 'reorder_qty', 'unit_cost', 'location_id', 'notes'];

    public function index(): void
    {
        $low = !empty($_GET['low']);
        $q = trim((string) ($_GET['q'] ?? ''));
        $where = ['1=1'];
        $params = [];
        if ($low) {
            $where[] = 'c.quantity <= c.min_quantity';
        }
        if ($q !== '') {
            $where[] = '(c.name LIKE ? OR c.sku LIKE ? OR c.barcode = ?)';
            array_push($params, "%$q%", "%$q%", $q);
        }
        $kind = in_array($_GET['kind'] ?? '', Consumables::KINDS, true) ? (string) $_GET['kind'] : '';
        if ($kind !== '') {
            $where[] = 'c.kind = ?';
            $params[] = $kind;
        }
        $rows = db()->all('SELECT c.*, l.name AS location_name FROM consumables c LEFT JOIN locations l ON l.id = c.location_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name', $params);
        $request = null;
        $this->view('consumables/index', [
            'rows' => $rows, 'low' => $low, 'q' => $q, 'kind' => $kind, 'request' => $request, 'usage' => Consumables::usage(),
            'total' => (int) db()->value('SELECT COUNT(*) FROM consumables'), 'lowCount' => count(Consumables::low()), 'value' => (float) db()->value('SELECT COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) FROM consumables'),
            'issued30' => (int) db()->value("SELECT COALESCE(-SUM(change_qty), 0) FROM consumable_moves WHERE reason = 'issue' AND created_at >= ?", [days_from_today(-30)]),
            'employees' => db()->all('SELECT id, name FROM employees WHERE active = 1 ORDER BY name'),
            'locations' => db()->all('SELECT id, name FROM locations ORDER BY name'),
        ]);
    }

    public function show(string $id): void
    {
        $c = db()->one('SELECT c.*, l.name AS location_name FROM consumables c LEFT JOIN locations l ON l.id = c.location_id WHERE c.id = ?', [(int) $id]) ?? abort(404);
        $moves = db()->all('SELECT m.*, e.name AS employee_name, u.name AS user_name FROM consumable_moves m LEFT JOIN employees e ON e.id = m.employee_id
            LEFT JOIN users u ON u.id = m.user_id WHERE m.consumable_id = ? ORDER BY m.id DESC LIMIT 100', [$c['id']]);
        $this->view('consumables/show', ['c' => $c, 'moves' => $moves, 'employees' => db()->all('SELECT id, name FROM employees WHERE active = 1 ORDER BY name'),
            'locations' => db()->all('SELECT id, name FROM locations ORDER BY name')]);
    }

    public function store(): void
    {
        $this->validate(['name' => 'required|max:160', 'quantity' => 'int', 'min_quantity' => 'int', 'unit_cost' => 'num']);
        $data = $this->payload() + ['quantity' => 0, 'created_at' => now(), 'updated_at' => now()];
        $id = db()->insert('consumables', $data);
        Ledger::record('create', 'consumable', $id, "Consumable added: {$data['name']}");
        if (($qty = max(0, (int) input('quantity'))) > 0) {
            Consumables::move($id, $qty, 'receive', null, null, t('parts.opening_stock'));
        }
        flash('success', t('common.saved'));
        redirect('/consumables/' . $id);
    }

    public function update(string $id): void
    {
        $before = db()->one('SELECT * FROM consumables WHERE id = ?', [(int) $id]) ?? abort(404);
        $this->validate(['name' => 'required|max:160', 'min_quantity' => 'int', 'unit_cost' => 'num']);
        $data = $this->payload() + ['updated_at' => now()];
        db()->update('consumables', (int) $id, $data);
        if ($diff = Ledger::diff($before, $data)) {
            Ledger::record('update', 'consumable', (int) $id, "Consumable updated: {$data['name']}", $diff);
        }
        flash('success', t('common.saved'));
        redirect('/consumables/' . $id);
    }

    /** Issue to an employee, receive stock or record a count. */
    public function move(string $id): void
    {
        $c = db()->one('SELECT * FROM consumables WHERE id = ?', [(int) $id]) ?? abort(404);
        $action = (string) input('action');
        $qty = (int) input('quantity');
        $note = input_or_null('note');
        $ok = match ($action) {
            'issue' => $qty > 0 && ($emp = (int) db()->value('SELECT id FROM employees WHERE id = ? AND active = 1', [(int) input('employee_id')]))
                && Consumables::move((int) $c['id'], -$qty, 'issue', $emp, null, $note),
            'receive' => $qty > 0 && Consumables::move((int) $c['id'], $qty, 'receive', null, null, $note),
            'count' => $qty >= 0 && input('quantity') !== null && input('quantity') !== '' && Consumables::move((int) $c['id'], $qty, 'count', null, null, $note),
            default => false,
        };
        if (!$ok) {
            flash('error', $action === 'issue' && $qty > (int) $c['quantity'] ? t('consumables.not_enough', ['n' => (int) $c['quantity']]) : t('consumables.move_failed'));
        } else {
            flash('success', t('consumables.moved.' . $action));
        }
        back();
    }

    private function payload(): array
    {
        $d = $this->only(self::FIELDS);
        $d['kind'] = in_array($d['kind'], Consumables::KINDS, true) ? $d['kind'] : 'accessory';
        $d['unit'] = in_array($d['unit'], Consumables::UNITS, true) ? $d['unit'] : 'pcs';
        $d['min_quantity'] = max(0, (int) ($d['min_quantity'] ?? 0));
        $d['reorder_qty'] = max(0, min(10000, (int) ($d['reorder_qty'] ?? 0)));
        $d['unit_cost'] = $d['unit_cost'] !== null && is_numeric($d['unit_cost']) ? round(max(0, (float) $d['unit_cost']), 2) : null;
        $d['location_id'] = $d['location_id'] ? (int) $d['location_id'] : null;
        return $d;
    }
}
