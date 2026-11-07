<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Services\Ledger;
use ITAM\Services\Sla;

final class MaintenanceController extends Controller
{
    public const TYPES = ['corrective', 'preventive', 'upgrade', 'inspection'];
    public const STATUSES = ['open', 'in_progress', 'done', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function index(): void
    {
        Sla::escalate();
        $w = ['1=1', scope_sql('a')];
        $p = [];
        $status = $_GET['status'] ?? 'active';
        if ($status === 'active') {
            $w[] = "m.status IN ('open','in_progress')";
        } elseif (in_array($status, self::STATUSES, true)) {
            $w[] = 'm.status = ?';
            $p[] = $status;
        }
        if (in_array($_GET['type'] ?? '', self::TYPES, true)) {
            $w[] = 'm.type = ?';
            $p[] = $_GET['type'];
        }
        if (!empty($_GET['overdue'])) {
            $w[] = "m.status IN ('open','in_progress') AND m.scheduled_for < ?";
            $p[] = today();
        }
        if (!empty($_GET['breached'])) {
            $w[] = "m.status IN ('open','in_progress') AND m.due_at < ?";
            $p[] = now();
        }
        if (($_GET['source'] ?? '') === 'report') {
            $w[] = "m.source IN ('report','portal','whatsapp')";
        }
        if (!empty($_GET['technician'])) {
            $w[] = 'm.technician_id = ?';
            $p[] = (int) $_GET['technician'];
        }
        if (!empty($_GET['unassigned'])) {
            $w[] = "m.technician_id IS NULL AND m.status IN ('open','in_progress')";
        }
        $items = db()->all(
            'SELECT m.*, a.tag, a.name AS asset_name, a.model, a.criticality, u.name AS technician, c.icon
             FROM maintenance m JOIN assets a ON a.id = m.asset_id LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = m.technician_id WHERE ' . implode(' AND ', $w) . "
             ORDER BY CASE m.status WHEN 'in_progress' THEN 0 WHEN 'open' THEN 1 ELSE 2 END,
                      CASE m.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
                      COALESCE(m.due_at, m.scheduled_for, m.created_at) ASC LIMIT 300",
            $p
        );
        // Tiles count the same branches as the list.
        $from = 'FROM maintenance m JOIN assets a ON a.id = m.asset_id WHERE ' . scope_sql('a');
        $stats = [
            'open'      => (int) db()->value("SELECT COUNT(*) $from AND m.status = 'open'"),
            'progress'  => (int) db()->value("SELECT COUNT(*) $from AND m.status = 'in_progress'"),
            'overdue'   => (int) db()->value("SELECT COUNT(*) $from AND m.status IN ('open','in_progress') AND m.scheduled_for < ?", [today()]),
            'downtime'  => (float) db()->value("SELECT COALESCE(SUM(m.downtime_hours),0) $from AND m.created_at >= ?", [days_from_today(-90)]),
            'mttr'      => (float) db()->value("SELECT AVG(m.downtime_hours) $from AND m.type = 'corrective' AND m.status = 'done' AND m.created_at >= ?", [days_from_today(-365)]),
            'breached'  => (int) db()->value("SELECT COUNT(*) $from AND m.status IN ('open','in_progress') AND m.due_at < ?", [now()]),
            'reports'   => (int) db()->value("SELECT COUNT(*) $from AND m.source IN ('report','portal','whatsapp') AND m.status IN ('open','in_progress')"),
            'sla'       => Sla::compliance(30),
        ];
        $assets = db()->all("SELECT a.id, a.tag, a.name FROM assets a WHERE a.status NOT IN ('retired','lost') AND " . scope_sql('a') . ' ORDER BY a.tag');
        $technicians = db()->all("SELECT id, name FROM users WHERE active = 1 AND role IN ('admin','manager','technician') ORDER BY name");
        $this->view('maintenance/index', compact('items', 'stats', 'assets', 'technicians', 'status'));
    }

    public function store(): void
    {
        $this->validate(['asset_id' => 'required|int', 'title' => 'required|max:200', 'scheduled_for' => 'date', 'recurrence_days' => 'int']);
        $data = $this->only(['asset_id', 'type', 'title', 'description', 'priority', 'scheduled_for', 'technician_id', 'recurrence_days']);
        $data['type'] = in_array($data['type'], self::TYPES, true) ? $data['type'] : 'corrective';
        $data['priority'] = in_array($data['priority'], self::PRIORITIES, true) ? $data['priority'] : 'medium';
        $data['asset_id'] = (int) $data['asset_id'];
        if (!in_scope(db()->value('SELECT location_id FROM assets WHERE id = ?', [$data['asset_id']]))) {
            abort(403);
        }
        // "—" in the technician list means unassigned; forms without the field (asset page) assign the creator.
        $data['technician_id'] = array_key_exists('technician_id', $_POST) ? ($data['technician_id'] ? (int) $data['technician_id'] : null) : Auth::id();
        $data['recurrence_days'] = (int) ($data['recurrence_days'] ?? 0);
        $data['status'] = 'open';
        $data['created_at'] = now();
        if (Sla::appliesTo($data['type'])) {
            $data['due_at'] = Sla::dueAt($data['priority']);
        }
        $id = db()->insert('maintenance', $data);
        if (input('mark_in_repair')) {
            db()->update('assets', $data['asset_id'], ['status' => 'in_repair', 'updated_at' => now()]);
        }
        Ledger::record('create', 'maintenance', $id, "Work order: {$data['title']}", ['asset_id' => $data['asset_id']]);
        Ledger::record('maintenance', 'asset', $data['asset_id'], "Work order opened: {$data['title']}");
        flash('success', t('maintenance.created'));
        back();
    }

    /** Hand a work order to another technician. */
    public function assign(string $id): void
    {
        $m = db()->one('SELECT * FROM maintenance WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!in_scope(db()->value('SELECT location_id FROM assets WHERE id = ?', [$m['asset_id']]))) {
            abort(403);
        }
        $tech = input('technician_id') ? (int) input('technician_id') : null;
        db()->update('maintenance', (int) $id, ['technician_id' => $tech]);
        Ledger::record('assign', 'maintenance', (int) $id, "Work order #{$id} assigned to user #" . ($tech ?? '—'));
        flash('success', t('common.saved'));
        back();
    }

    public function transition(string $id): void
    {
        $m = db()->one('SELECT * FROM maintenance WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!in_scope(db()->value('SELECT location_id FROM assets WHERE id = ?', [$m['asset_id']]))) {
            abort(403);
        }
        $to = (string) input('status');
        if (!in_array($to, self::STATUSES, true)) {
            abort(422);
        }
        $upd = ['status' => $to];
        if ($to === 'done') {
            $upd['completed_at'] = now();
            $upd['downtime_hours'] = (float) (input('downtime_hours') ?: $m['downtime_hours']);
            $upd['resolution'] = input_or_null('resolution');
            if (is_numeric(input('cost')) && (float) input('cost') >= 0) {
                $upd['cost'] = round((float) input('cost'), 2);
            }
            if ($m['recurrence_days'] > 0) {
                $next = days_from_today((int) $m['recurrence_days']);
                db()->insert('maintenance', [
                    'asset_id' => $m['asset_id'], 'type' => $m['type'], 'title' => $m['title'], 'description' => $m['description'],
                    'status' => 'open', 'priority' => $m['priority'], 'technician_id' => $m['technician_id'],
                    'scheduled_for' => $next, 'recurrence_days' => $m['recurrence_days'], 'created_at' => now(),
                    // Corrective work keeps its response target, counted from the day it is due to start.
                    'due_at' => Sla::appliesTo($m['type']) ? Sla::dueAt($m['priority'], $next . ' 00:00:00') : null,
                ]);
            }
            $asset = db()->one('SELECT status, assigned_to FROM assets WHERE id = ?', [$m['asset_id']]);
            $stillOpen = (int) db()->value("SELECT COUNT(*) FROM maintenance WHERE asset_id = ? AND id <> ? AND status IN ('open','in_progress') AND type = 'corrective'", [$m['asset_id'], $m['id']]);
            if ($asset && $asset['status'] === 'in_repair' && !$stillOpen) {
                db()->update('assets', (int) $m['asset_id'], ['status' => $asset['assigned_to'] ? 'deployed' : 'in_stock', 'updated_at' => now()]);
            }
            db()->update('assets', (int) $m['asset_id'], ['last_seen_at' => now()]);
        }
        db()->update('maintenance', (int) $id, $upd);
        Ledger::record('transition', 'maintenance', (int) $id, "Work order #{$id} → {$to}");
        Ledger::record('maintenance', 'asset', (int) $m['asset_id'], "Work order “{$m['title']}” → " . t('maintenance.status.' . $to));
        if (wants_json()) {
            json_response(['ok' => true]);       // replayed from the offline queue
        }
        flash('success', t('common.saved'));
        back();
    }
}
