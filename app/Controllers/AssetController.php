<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Auth;
use ITAM\Core\View;
use ITAM\Services\AssetGrid;
use ITAM\Services\AssetQuery;
use ITAM\Services\Catalog;
use ITAM\Services\CustomFields;
use ITAM\Services\Ledger;
use ITAM\Services\XlsxReader;

final class AssetController extends Controller
{
    private const FIELDS = [
        'name', 'category_id', 'status', 'condition_state', 'criticality', 'manufacturer', 'model', 'serial', 'hostname',
        'ip_address', 'mac_address', 'os', 'cpu', 'ram_gb', 'storage_gb', 'location_id', 'department_id',
        'acquired_at', 'warranty_until', 'value', 'po_number', 'power_watts', 'notes',
    ];
    public function index(): void
    {
        $filters = AssetQuery::filtersFromRequest();
        $data = $this->grid($filters, AssetGrid::prefsFromRequest());
        if (!empty($_GET['partial'])) {
            // Live updates: the page swaps these parts in place instead of reloading.
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode([
                'toolbar' => View::partial('assets/_toolbar', $data),
                'results' => View::partial('assets/_results', $data),
                'drawer' => View::partial('assets/_drawer', $data),
                'count' => t('assets.count', ['n' => fmt_num($data['total'])]),
                'show' => t('grid.show_n', ['n' => fmt_num($data['total'])]),
                'url' => url('assets', $filters + ($data['page'] > 1 ? ['page' => $data['page']] : [])),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        $this->view('assets/index', $data);
    }

    /** Everything the list needs for one set of filters and display preferences. */
    private function grid(array $filters, array $prefs): array
    {
        $grouped = $prefs['view'] === 'groups';
        $per = $grouped ? AssetGrid::GROUP_LIMIT : (int) $prefs['per_page'];
        $total = AssetQuery::count($filters);
        $pages = (int) max(1, ceil($total / $per));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        $presets = [];
        foreach (AssetGrid::presets() as $key => [$icon, $query]) {
            $presets[$key] = ['icon' => $icon, 'query' => $query, 'count' => AssetQuery::count($query), 'active' => AssetGrid::matches($filters, $query)];
        }
        return [
            'filters' => $filters, 'prefs' => $prefs, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'assets' => AssetQuery::fetch($filters, $per, ($page - 1) * $per),
            'summary' => AssetQuery::summary($filters),
            'facets' => [
                'status' => AssetQuery::facet($filters, 'status', 'a.status'),
                'category' => AssetQuery::facet($filters, 'category', 'a.category_id'),
                'location' => AssetQuery::facet($filters, 'location', 'a.location_id'),
                'department' => AssetQuery::facet($filters, 'department', 'a.department_id'),
                'condition' => AssetQuery::facet($filters, 'condition', 'a.condition_state'),
            ],
            'presets' => $presets, 'savedViews' => AssetGrid::savedViews(), 'lookups' => $this->lookups(),
            'customFields' => db()->all('SELECT f.id, f.label, c.name AS category FROM custom_fields f JOIN categories c ON c.id = f.category_id ORDER BY c.name, f.sort, f.label'),
        ];
    }

    /** Side panel with the essentials of one asset, opened from the list. */
    public function preview(string $id): void
    {
        $asset = AssetQuery::find((int) $id) ?? abort(404);
        $aid = (int) $asset['id'];
        $db = db();
        echo View::partial('assets/_preview', [
            'a' => $asset,
            'timeline' => array_slice($this->timeline($aid), 0, 6),
            'openWork' => $db->all("SELECT id, title, priority, status FROM maintenance WHERE asset_id = ? AND status IN ('open','in_progress') ORDER BY id DESC LIMIT 3", [$aid]),
        ]);
    }

    public function saveView(): void
    {
        $name = trim((string) input('name'));
        parse_str((string) input('query'), $query);
        $query = AssetQuery::active(array_intersect_key($query, array_flip(AssetQuery::FILTER_KEYS)));
        if ($name === '' || !$query) {
            flash('error', t('grid.view_empty'));
            back();
        }
        AssetGrid::saveView($name, $query);
        flash('success', t('grid.view_saved', ['name' => $name]));
        redirect('/assets', $query);
    }

    public function deleteView(string $index): void
    {
        AssetGrid::deleteView((int) $index);
        back();
    }

    public function create(): void
    {
        $asset = ['status' => 'in_stock', 'condition_state' => 'good', 'criticality' => 2];
        if ($clone = (int) ($_GET['clone'] ?? 0)) {
            $src = db()->one('SELECT * FROM assets WHERE id = ?', [$clone]);
            if ($src) {
                $asset = array_intersect_key($src, array_flip(self::FIELDS));
                $asset['serial'] = $asset['hostname'] = $asset['ip_address'] = $asset['mac_address'] = null;
            }
        }
        $this->view('assets/form', ['asset' => $asset, 'lookups' => $this->lookups(), 'mode' => 'create']);
    }

    public function store(): void
    {
        $this->validate(['name' => 'required|max:160', 'acquired_at' => 'date', 'warranty_until' => 'date', 'value' => 'num', 'ram_gb' => 'int', 'storage_gb' => 'int', 'po_number' => 'max:60']);
        $data = $this->payload();
        // A user limited to some branches adds devices to those branches only.
        if (scope_ids() !== null) {
            scope_guard($data['location_id']);
        }
        $this->validateCustom($data['category_id']);
        $this->catalog($data);
        $data['tag'] = input_or_null('tag') ?? AssetQuery::nextTag($data['category_id'] ? (int) $data['category_id'] : null);
        if (db()->value('SELECT id FROM assets WHERE tag = ?', [$data['tag']])) {
            flash('error', t('assets.tag_taken', ['tag' => $data['tag']]));
            $_SESSION['_old'] = $_POST;
            back();
        }
        $data['created_at'] = $data['updated_at'] = now();
        $data['last_seen_at'] = now();
        $id = db()->insert('assets', $data);
        CustomFields::save($id, $data['category_id'], (array) ($_POST['cf'] ?? []));
        Ledger::record('create', 'asset', $id, "Created {$data['tag']} — {$data['name']}", $data);
        $this->duplicateWarning($id, $data);
        flash('success', t('assets.created', ['tag' => $data['tag']]));
        redirect('/assets/' . $id);
    }

    public function show(string $id): void
    {
        $asset = AssetQuery::find((int) $id) ?? abort(404, t('errors.not_found'));
        $aid = (int) $asset['id'];
        $db = db();
        $maintenance = $db->all('SELECT m.*, u.name AS technician FROM maintenance m LEFT JOIN users u ON u.id = m.technician_id WHERE m.asset_id = ? ORDER BY m.created_at DESC', [$aid]);
        $assignments = $db->all('SELECT x.*, e.name AS employee_name FROM assignments x JOIN employees e ON e.id = x.employee_id WHERE x.asset_id = ? ORDER BY x.checked_out_at DESC', [$aid]);
        $licenses = $db->all('SELECT s.id AS seat_id, l.id, l.name, l.edition, l.expires_at FROM license_seats s JOIN licenses l ON l.id = s.license_id WHERE s.asset_id = ?', [$aid]);
        $timeline = $this->timeline($aid);
        $files = $db->all('SELECT f.*, u.name AS user_name FROM attachments f LEFT JOIN users u ON u.id = f.user_id WHERE f.asset_id = ? ORDER BY f.id DESC', [$aid]);
        $custom = CustomFields::display($aid, $asset['category_id'] ? (int) $asset['category_id'] : null);
        $disposal = $db->one('SELECT id, disposed_at, method FROM disposals WHERE asset_id = ? ORDER BY id DESC LIMIT 1', [$aid]);
        $openAssignment = $db->one('SELECT * FROM assignments WHERE asset_id = ? AND checked_in_at IS NULL ORDER BY id DESC LIMIT 1', [$aid]);
        $this->view('assets/show', compact('asset', 'maintenance', 'assignments', 'licenses', 'timeline', 'openAssignment', 'files', 'disposal', 'custom') + [
            'lookups' => $this->lookups(),
        ]);
    }

    public function edit(string $id): void
    {
        $this->guard($id);
        $asset = db()->one('SELECT * FROM assets WHERE id = ?', [(int) $id]) ?? abort(404, t('errors.not_found'));
        $this->view('assets/form', ['asset' => $asset, 'lookups' => $this->lookups(), 'mode' => 'edit']);
    }

    public function update(string $id): void
    {
        $this->guard($id);
        $before = db()->one('SELECT * FROM assets WHERE id = ?', [(int) $id]) ?? abort(404);
        $this->validate(['name' => 'required|max:160', 'acquired_at' => 'date', 'warranty_until' => 'date', 'value' => 'num', 'ram_gb' => 'int', 'storage_gb' => 'int', 'po_number' => 'max:60']);
        $data = $this->payload();
        // …and cannot move one out of them.
        if (scope_ids() !== null) {
            scope_guard($data['location_id']);
        }
        $this->validateCustom($data['category_id']);
        $this->catalog($data, $before);
        $tag = input_or_null('tag');
        if ($tag && $tag !== $before['tag']) {
            if (db()->value('SELECT id FROM assets WHERE tag = ? AND id <> ?', [$tag, $before['id']])) {
                flash('error', t('assets.tag_taken', ['tag' => $tag]));
                back();
            }
            $data['tag'] = $tag;
        }
        if (in_array($data['status'], ['retired', 'lost', 'in_stock'], true) && $before['assigned_to']) {
            $this->closeAssignment((int) $before['id'], $data['condition_state']);
            $data['assigned_to'] = null;
        }
        $data['updated_at'] = now();
        db()->update('assets', (int) $id, $data);
        $diff = Ledger::diff($before, $data) + CustomFields::save((int) $id, $data['category_id'], (array) ($_POST['cf'] ?? []));
        if ($diff) {
            Ledger::record('update', 'asset', (int) $id, "Updated {$before['tag']}: " . implode(', ', array_keys($diff)), $diff);
        }
        $this->duplicateWarning((int) $id, $data);
        flash('success', t('common.saved'));
        redirect('/assets/' . $id);
    }

    /** What the cells of the list offer. */
    public function quickOptions(): void
    {
        json_response(\ITAM\Services\AssetQuickEdit::options());
    }

    /** One cell of the list. */
    public function quickEdit(string $id): void
    {
        $this->guard($id);
        $asset = db()->one('SELECT * FROM assets WHERE id = ?', [(int) $id]) ?? abort(404);
        try {
            json_response(\ITAM\Services\AssetQuickEdit::save($asset, (string) input('col'), (string) input('value'), (bool) input('sure'), Auth::id()));
        } catch (\InvalidArgumentException $e) {
            json_response(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(string $id): void
    {
        $this->guard($id);
        $asset = db()->one('SELECT * FROM assets WHERE id = ?', [(int) $id]) ?? abort(404);
        db()->delete('assets', (int) $id);
        Ledger::record('delete', 'asset', (int) $id, "Deleted {$asset['tag']} — {$asset['name']}", $asset);
        flash('success', t('assets.deleted', ['tag' => $asset['tag']]));
        redirect('/assets');
    }

    public function checkout(string $id): void
    {
        $this->guard($id);
        $this->validate(['employee_id' => 'required|int', 'expected_return' => 'date']);
        $asset = db()->one('SELECT * FROM assets WHERE id = ?', [(int) $id]) ?? abort(404);
        $emp = db()->one('SELECT * FROM employees WHERE id = ?', [(int) input('employee_id')]) ?? abort(404);
        if ($asset['assigned_to']) {
            $this->closeAssignment((int) $asset['id'], $asset['condition_state']);
        }
        db()->insert('assignments', [
            'asset_id' => $asset['id'], 'employee_id' => $emp['id'], 'checked_out_at' => now(),
            'expected_return' => input_or_null('expected_return'), 'condition_out' => $asset['condition_state'],
            'notes' => input_or_null('notes'), 'user_id' => Auth::id(),
        ]);
        db()->update('assets', (int) $asset['id'], [
            'assigned_to' => $emp['id'], 'status' => 'deployed', 'department_id' => $emp['department_id'] ?? $asset['department_id'],
            'location_id' => $emp['location_id'] ?? $asset['location_id'], 'updated_at' => now(), 'last_seen_at' => now(),
        ]);
        Ledger::record('checkout', 'asset', (int) $asset['id'], "Checked out {$asset['tag']} to {$emp['name']}", ['employee_id' => $emp['id']]);
        flash('success', t('assets.checked_out', ['name' => $emp['name']]));
        if (input('receipt')) {
            $rid = \ITAM\Services\Custody::create('issue', $emp, [(int) $asset['id']], [], input_or_null('notes'));
            redirect('/custody/' . $rid . '/sign');
        }
        redirect('/assets/' . $id);
    }

    public function checkin(string $id): void
    {
        $this->guard($id);
        $asset = db()->one('SELECT * FROM assets WHERE id = ?', [(int) $id]) ?? abort(404);
        $cond = in_array(input('condition_in'), AssetQuery::CONDITIONS, true) ? input('condition_in') : $asset['condition_state'];
        $this->closeAssignment((int) $asset['id'], $cond);
        db()->update('assets', (int) $asset['id'], ['assigned_to' => null, 'status' => 'in_stock', 'condition_state' => $cond, 'updated_at' => now(), 'last_seen_at' => now()]);
        Ledger::record('checkin', 'asset', (int) $asset['id'], "Checked in {$asset['tag']} (condition: $cond)");
        flash('success', t('assets.checked_in'));
        $holder = $asset['assigned_to'] ? db()->one('SELECT id, name FROM employees WHERE id = ?', [$asset['assigned_to']]) : null;
        if ($holder && input('receipt')) {
            $rid = \ITAM\Services\Custody::create('return', $holder, [(int) $asset['id']], [], null, [(int) $asset['id'] => $cond]);
            redirect('/custody/' . $rid . '/sign');
        }
        redirect('/assets/' . $id);
    }

    public function bulk(): void
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        if (!$ids) {
            flash('error', t('assets.bulk_none'));
            back();
        }
        $action = (string) input('bulk_action');
        // Silently drop selections outside the user's locations.
        $ids = array_values(array_map('intval', array_column(db()->all('SELECT id FROM assets a WHERE id IN (' . implode(',', $ids) . ') AND ' . scope_sql('a')), 'id')));
        if (!$ids) {
            abort(403);
        }
        if ($action === 'labels') {
            redirect('/assets/labels', ['ids' => implode(',', $ids)]);
        }
        if ($action === 'export') {
            redirect('/assets/export', ['ids' => implode(',', $ids), 'format' => \ITAM\Services\Exporter::format(input('bulk_format'))]);
        }
        if (in_array($action, ['status', 'location', 'department', 'criticality', 'work_order'], true) && !can('assets.edit')) {
            abort(403);
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'status' && in_array(input('bulk_status'), AssetQuery::STATUSES, true)) {
            db()->run("UPDATE assets SET status = ?, updated_at = ? WHERE id IN ($in)", array_merge([input('bulk_status'), now()], $ids));
        } elseif ($action === 'location' && input('bulk_location')) {
            foreach (db()->all("SELECT id, location_id FROM assets WHERE id IN ($in)", $ids) as $row) {
                db()->recordMove((int) $row['id'], $row['location_id'], (int) input('bulk_location'));
            }
            db()->run("UPDATE assets SET location_id = ?, updated_at = ? WHERE id IN ($in)", array_merge([(int) input('bulk_location'), now()], $ids));
        } elseif ($action === 'department' && input('bulk_department')) {
            db()->run("UPDATE assets SET department_id = ?, updated_at = ? WHERE id IN ($in)", array_merge([(int) input('bulk_department'), now()], $ids));
        } elseif ($action === 'criticality' && in_array((int) input('bulk_criticality'), [1, 2, 3, 4], true)) {
            db()->run("UPDATE assets SET criticality = ?, updated_at = ? WHERE id IN ($in)", array_merge([(int) input('bulk_criticality'), now()], $ids));
        } elseif ($action === 'work_order' && trim((string) input('bulk_title')) !== '' && can('maintenance.edit')) {
            $type = in_array(input('bulk_type'), MaintenanceController::TYPES, true) ? input('bulk_type') : 'inspection';
            $priority = in_array(input('bulk_priority'), MaintenanceController::PRIORITIES, true) ? input('bulk_priority') : 'medium';
            $title = mb_substr(trim((string) input('bulk_title')), 0, 200);
            foreach ($ids as $aid) {
                $row = ['asset_id' => $aid, 'type' => $type, 'title' => $title, 'priority' => $priority, 'status' => 'open',
                    'technician_id' => Auth::id(), 'created_at' => now()];
                if (\ITAM\Services\Sla::appliesTo($type)) {
                    $row['due_at'] = \ITAM\Services\Sla::dueAt($priority);
                }
                $wo = db()->insert('maintenance', $row);
                Ledger::record('maintenance', 'asset', $aid, "Work order opened: $title", ['work_order' => $wo]);
            }
        } else {
            flash('error', t('grid.bulk_incomplete'));
            back();
        }
        foreach ($ids as $aid) {
        }
        Ledger::record('bulk_' . $action, 'asset', null, 'Bulk ' . $action . ' on ' . count($ids) . ' assets', ['ids' => $ids]);
        flash('success', t('assets.bulk_done', ['n' => count($ids)]));
        back();
    }

    public function labels(): void
    {
        if (!empty($_GET['ids'])) {
            $ids = array_map('intval', explode(',', (string) $_GET['ids']));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $assets = db()->all(AssetQuery::SELECT . " WHERE a.id IN ($in) AND " . scope_sql('a') . ' ORDER BY a.tag', $ids);
        } else {
            $assets = AssetQuery::fetch(AssetQuery::filtersFromRequest(), 300);
        }
        $origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $this->view('assets/labels', ['assets' => $assets, 'origin' => config('app.url') ?: $origin], 'layouts/print');
    }

    public function export(): void
    {
        $format = \ITAM\Services\Exporter::format($_GET['format'] ?? null);
        $rows = AssetQuery::fetch(AssetQuery::filtersFromRequest());
        Ledger::record('export', 'asset', null, 'Exported ' . count($rows) . ' assets (' . strtoupper($format) . ')');
        // CSV and JSON keep field names and raw values, so they round-trip with the import.
        $cols = ['tag', 'name', 'category_name', 'status', 'condition_state', 'criticality', 'manufacturer', 'model', 'serial', 'hostname',
                 'ip_address', 'mac_address', 'os', 'cpu', 'ram_gb', 'storage_gb', 'location_name', 'department_name', 'employee_name',
                 'acquired_at', 'warranty_until', 'value', 'po_number', 'last_seen_at', 'notes'];
        if (in_array($format, ['csv', 'json'], true)) {
            \ITAM\Services\Exporter::download($format, 'itam-assets', t('nav.assets'), $cols, array_map(fn ($r) => array_map(fn ($c) => $r[$c] ?? null, $cols), $rows));
        }
        // Excel and PDF are for people: translated headings and values; PDF keeps the columns that fit a page.
        if ($format === 'pdf') {
            $cols = ['tag', 'serial', 'name', 'model', 'status', 'employee_name', 'location_name', 'warranty_until'];
        }
        $label = ['category_name' => 'field.category_id', 'location_name' => 'field.location_id', 'department_name' => 'field.department_id',
            'employee_name' => 'field.assigned_to', 'last_seen_at' => 'grid.col.last_seen'];
        $value = fn (array $r, string $c) => match ($c) {
            'status' => t('status.' . $r['status']),
            'condition_state' => t('condition.' . $r['condition_state']),
            'criticality' => t('criticality.' . (int) $r['criticality']),
            'acquired_at', 'warranty_until' => $r[$c] ? fmt_date($r[$c]) : null,
            'last_seen_at' => $r[$c] ? fmt_date($r[$c], true) : null,
            default => $r[$c] ?? null,
        };
        \ITAM\Services\Exporter::download($format, 'itam-assets', t('nav.assets'), array_map(fn ($c) => t($label[$c] ?? 'field.' . $c), $cols),
            array_map(fn ($r) => array_map(fn ($c) => $value($r, $c), $cols), $rows));
    }

    public function importForm(): void
    {
        $this->view('assets/import', ['result' => $_SESSION['_import_result'] ?? null]);
        unset($_SESSION['_import_result']);
    }

    public function import(): void
    {
        $file = $_FILES['csv']['tmp_name'] ?? null;
        if (!$file || !is_uploaded_file($file)) {
            flash('error', t('import.no_file'));
            back();
        }
        // Excel workbooks are recognised by content (a ZIP), whatever the file name says.
        $isXlsx = str_starts_with((string) file_get_contents($file, false, null, 0, 4), "PK\x03\x04");
        if ($isXlsx) {
            try {
                $table = XlsxReader::rows($file);
            } catch (\RuntimeException) {
                flash('error', t('import.bad_xlsx'));
                back();
            }
        } else {
            $fh = fopen($file, 'r');
            $first = fgets($fh);
            $delim = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
            rewind($fh);
            $table = [];
            while (($row = fgetcsv($fh, 0, $delim, escape: '')) !== false) {
                $table[] = $row;
            }
            fclose($fh);
        }
        // Skip blank lines above the header (common in hand-made sheets).
        while ($table && !array_filter($table[0], fn ($v) => trim((string) $v) !== '')) {
            array_shift($table);
        }
        $header = array_shift($table);
        if (!$header) {
            flash('error', t('import.empty'));
            back();
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $alias = ['category_name' => 'category', 'location_name' => 'location', 'department_name' => 'department', 'employee_name' => 'assigned_to', 'asset tag' => 'tag', 'serial number' => 'serial'];
        $header = array_map(fn ($h) => $alias[$h] ?? $h, $header);

        $created = $updated = $skipped = 0;
        $errors = [];
        $line = 1;
        $lookup = function (string $table, ?string $name, array $extra = []): ?int {
            if (!$name) return null;
            $id = db()->value("SELECT id FROM $table WHERE LOWER(name) = LOWER(?)" . ($table === 'categories' ? ' OR LOWER(code) = LOWER(?)' : ''), $table === 'categories' ? [$name, $name] : [$name]);
            if ($id) return (int) $id;
            // Employees are never created here, nor locations by a user limited to some branches.
            if ($table === 'employees' || ($table === 'locations' && scope_ids() !== null)) return null;
            return db()->insert($table, ['name' => $name] + $extra);
        };
        db()->transaction(function () use ($table, $header, &$created, &$updated, &$skipped, &$errors, &$line, $lookup) {
            foreach ($table as $row) {
                $line++;
                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $r = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
                $r = array_map(fn ($v) => ($v = trim((string) $v)) === '' ? null : $v, $r);
                $existing = null;
                if (!empty($r['tag'])) $existing = db()->one('SELECT id, location_id, assigned_to, condition_state FROM assets WHERE tag = ?', [$r['tag']]);
                if (!$existing && !empty($r['serial'])) $existing = db()->one('SELECT id, tag, location_id, assigned_to, condition_state FROM assets WHERE serial = ?', [$r['serial']]);
                if (!$existing && empty($r['name'])) {
                    $errors[] = t('import.row_missing_name', ['line' => $line]);
                    $skipped++;
                    continue;
                }
                // Updates change only the cells the file fills in; a missing column or empty cell keeps the current value.
                $given = fn (string $c) => ($r[$c] ?? null) !== null;
                $data = ['updated_at' => now()];
                if ($given('name')) $data['name'] = $r['name'];
                if ($given('category') || !$existing) {
                    $data['category_id'] = $lookup('categories', $r['category'] ?? null, ['code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($r['category'] ?? 'GEN')), 0, 3)) ?: 'GEN']);
                }
                if (in_array($r['status'] ?? '', AssetQuery::STATUSES, true)) $data['status'] = $r['status'];
                elseif (!$existing) $data['status'] = 'in_stock';
                if (in_array($r['condition_state'] ?? '', AssetQuery::CONDITIONS, true)) $data['condition_state'] = $r['condition_state'];
                elseif (!$existing) $data['condition_state'] = 'good';
                if ($given('criticality') || !$existing) $data['criticality'] = max(1, min(4, (int) ($r['criticality'] ?? 2)));
                if ($given('location') || !$existing) $data['location_id'] = $lookup('locations', $r['location'] ?? null);
                if ($given('department') || !$existing) $data['department_id'] = $lookup('departments', $r['department'] ?? null);
                if ($given('assigned_to') || !$existing) $data['assigned_to'] = $lookup('employees', $r['assigned_to'] ?? null);
                foreach (['manufacturer', 'model', 'serial', 'hostname', 'ip_address', 'mac_address', 'os', 'cpu', 'ram_gb', 'storage_gb', 'acquired_at', 'warranty_until', 'value', 'po_number', 'notes'] as $c) {
                    if ($given($c) || (!$existing && array_key_exists($c, $r))) $data[$c] = $r[$c];
                }
                // Manufacturers and models not listed yet are added to the lists; the cells keep the lists' spelling.
                if (array_key_exists('manufacturer', $data) || array_key_exists('model', $data)) {
                    $cur = $existing ? db()->one('SELECT manufacturer, model, category_id FROM assets WHERE id = ?', [$existing['id']]) : [];
                    [$man, $mod] = Catalog::learn($data['manufacturer'] ?? $cur['manufacturer'] ?? null, $data['model'] ?? $cur['model'] ?? null, $data['category_id'] ?? $cur['category_id'] ?? null);
                    if (array_key_exists('manufacturer', $data)) $data['manufacturer'] = $man;
                    if (array_key_exists('model', $data)) $data['model'] = $mod;
                }
                foreach (['acquired_at', 'warranty_until'] as $dc) {
                    if (!empty($data[$dc]) && ($ts = strtotime($data[$dc])) !== false) $data[$dc] = date('Y-m-d', $ts);
                }
                // Users limited to some branches may neither touch other branches' devices nor move devices out of their own.
                if (($existing && !in_scope($existing['location_id'])) || (scope_ids() !== null && array_key_exists('location_id', $data) && !in_scope($data['location_id']))) {
                    $errors[] = t('import.row_out_of_scope', ['line' => $line]);
                    $skipped++;
                    continue;
                }
                if ($existing) {
                    // An earlier import may have dropped the tag's leading zeros (Excel number cells); put them back.
                    if (!empty($r['tag']) && isset($existing['tag']) && $existing['tag'] !== $r['tag'] && $existing['tag'] === ltrim($r['tag'], '0')) {
                        $data['tag'] = $r['tag'];
                    }
                    $assetId = (int) $existing['id'];
                    db()->update('assets', $assetId, $data);
                    $updated++;
                } else {
                    $data['tag'] = $r['tag'] ?? AssetQuery::nextTag($data['category_id']);
                    $data['created_at'] = now();
                    $data['source'] = 'import';
                    $assetId = db()->insert('assets', $data);
                    $created++;
                }
                // A new holder gets the same hand-over rows as a check-out / check-in, so the timeline stays right.
                $holder = $existing['assigned_to'] ?? null;
                if (array_key_exists('assigned_to', $data) && (int) $holder !== (int) $data['assigned_to']) {
                    $condition = $data['condition_state'] ?? $existing['condition_state'] ?? null;
                    if ($holder) {
                        $this->closeAssignment($assetId, $condition);
                    }
                    if ($data['assigned_to']) {
                        db()->insert('assignments', [
                            'asset_id' => $assetId, 'employee_id' => $data['assigned_to'], 'checked_out_at' => now(),
                            'condition_out' => $condition, 'notes' => 'Import', 'user_id' => Auth::id(),
                        ]);
                    }
                }
            }
        });
        Ledger::record('import', 'asset', null, ($isXlsx ? 'Excel' : 'CSV') . " import: $created created, $updated updated, $skipped skipped");
        $_SESSION['_import_result'] = compact('created', 'updated', 'skipped', 'errors');
        redirect('/assets/import');
    }

    public function template(): void
    {
        $headers = ['tag', 'name', 'category', 'status', 'condition_state', 'criticality', 'manufacturer', 'model', 'serial', 'hostname', 'ip_address', 'os',
            'location', 'department', 'assigned_to', 'acquired_at', 'warranty_until', 'value', 'po_number', 'notes'];
        $sample = ['', 'Dell Latitude 5440', 'Laptop', 'in_stock', 'good', '2', 'Dell', 'Latitude 5440', 'ABC1234', 'LT-0999', '', 'Windows 11 Pro',
            'Riyadh HQ', 'Finance', '', '2025-01-15', '2028-01-15', '5200', '', ''];
        if (($_GET['format'] ?? '') === 'xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="itam-import-template.xlsx"');
            echo \ITAM\Services\Exporter::xlsx('Assets', $headers, [$sample]);
            exit;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="itam-import-template.csv"');
        echo \ITAM\Services\Exporter::csv($headers, [$sample]);
        exit;
    }

    // ------------------------------------------------------------------

    private function payload(): array
    {
        $data = $this->only(self::FIELDS);
        $data['status'] = in_array($data['status'], AssetQuery::STATUSES, true) ? $data['status'] : 'in_stock';
        $data['condition_state'] = in_array($data['condition_state'], AssetQuery::CONDITIONS, true) ? $data['condition_state'] : 'good';
        $data['criticality'] = max(1, min(4, (int) ($data['criticality'] ?? 2)));
        foreach (['category_id', 'location_id', 'department_id', 'ram_gb', 'storage_gb', 'power_watts'] as $k) {
            $data[$k] = $data[$k] === null ? null : (int) $data[$k];
        }
        if ($data['mac_address']) {
            $data['mac_address'] = strtoupper(str_replace('-', ':', $data['mac_address']));
        }
        return $data;
    }

    /**
     * The manufacturer and model come from the managed lists. A user who may edit the catalog can enter a new one
     * (added to the lists here); others pick from the lists, though an asset keeps the value it already had.
     */
    private function catalog(array &$data, ?array $before = null): void
    {
        if (!can('catalog.edit')) {
            $same = fn (string $k) => $before !== null && mb_strtolower(trim((string) $before[$k])) === mb_strtolower(trim((string) $data[$k]));
            $bad = (!$same('manufacturer') && !Catalog::known($data['manufacturer']))
                || (!($same('manufacturer') && $same('model')) && $data['model'] !== null && !Catalog::known($data['manufacturer'], $data['model']));
            if ($bad) {
                $_SESSION['_old'] = $_POST;
                flash('error', t('mfr.not_listed'));
                back();
            }
        }
        [$data['manufacturer'], $data['model']] = Catalog::learn($data['manufacturer'], $data['model'], $data['category_id']);
    }

    private function validateCustom(?int $categoryId): void
    {
        $errors = CustomFields::validate($categoryId, (array) ($_POST['cf'] ?? []));
        if ($errors) {
            $_SESSION['_old'] = $_POST;
            foreach ($errors as $msg) {
                flash('error', $msg);
            }
            back();
        }
    }

    private function closeAssignment(int $assetId, ?string $condition): void
    {
        \ITAM\Services\AssetQuickEdit::closeAssignment($assetId, $condition);
    }

    private function duplicateWarning(int $id, array $data): void
    {
        foreach (['serial', 'mac_address', 'ip_address', 'hostname'] as $f) {
            if (!empty($data[$f])) {
                $dup = db()->one("SELECT id, tag FROM assets WHERE $f = ? AND id <> ? AND status NOT IN ('retired','lost') LIMIT 1", [$data[$f], $id]);
                if ($dup) {
                    flash('warning', t('assets.duplicate', ['field' => t('field.' . $f), 'tag' => $dup['tag']]));
                }
            }
        }
    }

    private function timeline(int $id): array
    {
        $events = [];
        foreach (db()->all("SELECT * FROM ledger WHERE entity = 'asset' AND entity_id = ? ORDER BY id DESC LIMIT 60", [$id]) as $l) {
            $events[] = ['at' => $l['created_at'], 'icon' => match ($l['action']) { 'create' => 'plus', 'checkout' => 'user', 'checkin' => 'box', 'relate', 'unrelate' => 'link', 'heartbeat' => 'agent', 'dispose' => 'trash', 'part_used' => 'cpu', 'attach', 'detach' => 'upload', default => 'edit' }, 'title' => $l['summary'], 'by' => $l['user_name'], 'tone' => $l['action'] === 'dispose' ? 'danger' : 'info'];
        }
        foreach (db()->all('SELECT * FROM maintenance WHERE asset_id = ?', [$id]) as $m) {
            $events[] = ['at' => $m['completed_at'] ?? $m['created_at'], 'icon' => 'wrench', 'title' => t('maintenance.type.' . $m['type']) . ': ' . $m['title'], 'by' => t('maintenance.status.' . $m['status']), 'tone' => $m['type'] === 'corrective' ? 'warning' : 'success'];
        }
        foreach (db()->all('SELECT i.*, a.name AS audit_name FROM audit_items i JOIN audits a ON a.id = i.audit_id WHERE i.asset_id = ?', [$id]) as $ai) {
            $events[] = ['at' => $ai['scanned_at'], 'icon' => 'clipboard', 'title' => t('audits.timeline', ['audit' => $ai['audit_name'], 'result' => t('audit_result.' . $ai['result'])]), 'by' => '', 'tone' => $ai['result'] === 'found' ? 'success' : 'danger'];
        }
        usort($events, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return array_slice($events, 0, 50);
    }

    /** Refuse assets outside the signed-in user's locations. */
    private function guard(string $id): void
    {
        $loc = db()->one('SELECT location_id FROM assets WHERE id = ?', [(int) $id]) ?? abort(404);
        if (!in_scope($loc['location_id'])) {
            abort(403);
        }
    }

    private function lookups(): array
    {
        $db = db();
        return [
            'categories'  => $db->all('SELECT id, name, code, icon FROM categories ORDER BY name'),
            'locations'   => $db->all('SELECT id, name FROM locations ORDER BY name'),
            'departments' => \ITAM\Services\OrgUnits::options(),
            'employees'   => $db->all('SELECT id, name, employee_no FROM employees WHERE active = 1 ORDER BY name'),
        ];
    }
}
