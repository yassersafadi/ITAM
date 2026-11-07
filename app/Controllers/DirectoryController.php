<?php
declare(strict_types=1);

namespace ITAM\Controllers;

use ITAM\Core\Icons;
use ITAM\Services\Catalog;
use ITAM\Services\Ledger;

/** Generic CRUD for reference data: employees, locations, departments, categories, manufacturers, models. */
final class DirectoryController extends Controller
{
    private function schema(string $type): array
    {
        return match ($type) {
            'employees' => [
                'icon' => 'users', 'table' => 'employees', 'asset_fk' => 'assigned_to',
                'fields' => [
                    'name' => ['text', 'required|max:120'], 'employee_no' => ['text', 'max:40'], 'email' => ['email', 'email'],
                    'mobile' => ['tel', 'max:20'], 'title' => ['text', 'max:120'], 'title_ar' => ['text', 'max:120'], 'department_id' => ['select:departments', 'int'], 'location_id' => ['select:locations', 'int'],
                    'manager_id' => ['select:employees', 'int'], 'active' => ['checkbox', ''],
                ],
                'columns' => ['name', 'employee_no', 'email', 'department_id', 'location_id'],
            ],
            'locations' => [
                'icon' => 'pin', 'table' => 'locations', 'asset_fk' => 'location_id',
                'fields' => ['name' => ['text', 'required|max:120'], 'city' => ['text', 'max:80'], 'building' => ['text', 'max:80'], 'floor' => ['text', 'max:20'], 'room' => ['text', 'max:40']],
                'columns' => ['name', 'city', 'building', 'floor', 'room'],
            ],
            'departments' => [
                'icon' => 'building', 'table' => 'departments', 'asset_fk' => 'department_id',
                // Sectors, departments and sections (see OrgUnits): the level, and the unit above if any.
                'fields' => ['name' => ['text', 'required|max:120'], 'level' => ['org_level', 'required'], 'parent_id' => ['org_parent', 'int'], 'code' => ['text', 'max:20']],
                'columns' => ['name', 'level', 'code'],
            ],
            'categories' => [
                'icon' => 'layers', 'table' => 'categories', 'asset_fk' => 'category_id',
                'fields' => [
                    'name' => ['text', 'required|max:120'], 'code' => ['text', 'required|max:10'], 'icon' => ['icon', ''],
                    'lifespan_months' => ['number', 'required|int'], 'power_watts' => ['number', 'int'], 'hours_per_day' => ['number', 'int'],
                    'maintenance_interval_days' => ['number', 'int'],
                ],
                'columns' => ['icon', 'name', 'code', 'lifespan_months', 'power_watts', 'maintenance_interval_days'],
            ],
            // Assets carry the manufacturer and model as text (see Catalog), so they are counted by name.
            'manufacturers' => [
                'icon' => 'globe', 'table' => 'manufacturers',
                'count' => "(SELECT COUNT(*) FROM assets a WHERE LOWER(a.manufacturer) = LOWER(t.name) AND a.status NOT IN ('retired','lost'))",
                'extra' => ', (SELECT COUNT(*) FROM asset_models x WHERE x.manufacturer_id = t.id) AS model_count',
                'fields' => ['name' => ['text', 'required|max:80'], 'website' => ['url', 'max:255'], 'support_phone' => ['text', 'max:40']],
                'columns' => ['name', 'model_count', 'website', 'support_phone'],
            ],
            'models' => [
                'icon' => 'laptop', 'table' => 'asset_models',
                'count' => "(SELECT COUNT(*) FROM assets a WHERE LOWER(a.manufacturer) = LOWER(mf.name) AND LOWER(a.model) = LOWER(t.name) AND a.status NOT IN ('retired','lost'))",
                'extra' => ', mf.name AS manufacturer_name',
                'join' => ' JOIN manufacturers mf ON mf.id = t.manufacturer_id',
                'order' => 'mf.name, t.name',
                'fields' => ['manufacturer_id' => ['select:manufacturers', 'required|int'], 'name' => ['text', 'required|max:120'], 'category_id' => ['select:categories', 'int'],
                    'specs' => ['text', 'max:255'], 'image_url' => ['url', 'max:500']],
                'columns' => ['name', 'manufacturer_id', 'category_id'],
            ],
            default => abort(404),
        };
    }

    public function index(string $type): void
    {
        $s = $this->schema($type);
        $count = $s['count'] ?? "(SELECT COUNT(*) FROM assets a WHERE a.{$s['asset_fk']} = t.id AND a.status NOT IN ('retired','lost'))";
        // The models page can show one manufacturer's models only.
        $only = $type === 'models' ? (int) ($_GET['manufacturer'] ?? 0) : 0;
        $rows = db()->all("SELECT t.*, $count AS asset_count" . ($s['extra'] ?? '') . " FROM {$s['table']} t" . ($s['join'] ?? '')
            . ($only ? ' WHERE t.manufacturer_id = ?' : '') . ' ORDER BY ' . ($s['order'] ?? 't.name'), $only ? [$only] : []);
        if ($type === 'departments') {
            $rows = $this->orgRows($rows);
        }
        $this->view('directory/index', ['type' => $type, 'schema' => $s, 'rows' => $rows, 'refs' => $this->refs(), 'icons' => Icons::names(), 'only' => $only]);
    }

    public function edit(string $type, string $id): void
    {
        $s = $this->schema($type);
        $row = db()->one("SELECT * FROM {$s['table']} WHERE id = ?", [(int) $id]) ?? abort(404);
        $this->view('directory/edit', ['type' => $type, 'schema' => $s, 'row' => $row, 'refs' => $this->refs(), 'icons' => Icons::names()]);
    }

    public function store(string $type): void
    {
        $s = $this->schema($type);
        $data = $this->payload($s);
        $this->guardDuplicate($type, $data);
        if ($type === 'departments') {
            $this->guardOrgUnit($data, null);
        }
        $id = db()->insert($s['table'], $data);
        Ledger::record('create', rtrim($type, 's'), $id, 'Created ' . rtrim($type, 's') . ': ' . $data['name']);
        flash('success', t('common.saved'));
        redirect('/directory/' . $type);
    }

    public function update(string $type, string $id): void
    {
        $s = $this->schema($type);
        $before = db()->one("SELECT * FROM {$s['table']} WHERE id = ?", [(int) $id]) ?? abort(404);
        $data = $this->payload($s);
        // An employee cannot be their own manager.
        if ($type === 'employees' && (int) ($data['manager_id'] ?? 0) === (int) $id) {
            $data['manager_id'] = null;
        }
        if ($type === 'departments') {
            $this->guardOrgUnit($data, (int) $id);
        }
        db()->update($s['table'], (int) $id, $data);
        Ledger::record('update', rtrim($type, 's'), (int) $id, 'Updated ' . rtrim($type, 's') . ': ' . $data['name'], Ledger::diff($before, $data));
        if (in_array($type, ['manufacturers', 'models'], true)) {
            $this->renamed($type, $before, $data);
        }
        flash('success', t('common.saved'));
        redirect('/directory/' . $type);
    }

    public function destroy(string $type, string $id): void
    {
        $s = $this->schema($type);
        $row = db()->one("SELECT * FROM {$s['table']} WHERE id = ?", [(int) $id]) ?? abort(404);
        if ($type === 'employees') {
            $this->guardEmployeeDelete($row);
        }
        if (in_array($type, ['manufacturers', 'models'], true)) {
            $this->guardCatalogDelete($type, $row);
        }
        if ($type === 'departments' && \ITAM\Services\OrgUnits::children((int) $id)) {
            flash('error', t('org.has_children'));
            redirect('/directory/departments/' . $id . '/edit');
        }
        db()->delete($s['table'], (int) $id);
        Ledger::record('delete', rtrim($type, 's'), (int) $id, 'Deleted ' . rtrim($type, 's') . ': ' . $row['name']);
        flash('success', t('common.deleted'));
        redirect('/directory/' . $type);
    }

    /**
     * Give an employee an account for the staff screens, with a role. They sign in with Microsoft (no password is
     * set); the account is made by hand, so a sync of the Entra role groups never changes it.
     */
    public function grantAccess(string $id): void
    {
        $emp = db()->one('SELECT * FROM employees WHERE id = ? AND active = 1', [(int) $id]) ?? abort(404);
        $email = strtolower(trim((string) $emp['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', t('access.no_email'));
            redirect('/employees/' . $emp['id']);
        }
        if (db()->value('SELECT id FROM users WHERE LOWER(email) = ?', [$email])) {
            flash('error', t('users.email_taken'));
            redirect('/employees/' . $emp['id']);
        }
        $role = in_array(input('role'), \ITAM\Core\Auth::roles(), true) ? (string) input('role') : \ITAM\Core\Auth::defaultRole();
        if (!\ITAM\Core\Auth::mayManage(null, $role)) {
            abort(403, t('users.admin_only'));
        }
        $uid = db()->insert('users', [
            'name' => $emp['name'], 'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'role' => $role, 'locale' => (string) config('i18n.default', 'en'), 'theme' => 'system', 'active' => 1,
            'auth_provider' => 'sso', 'directory_id' => $emp['external_id'], 'created_at' => now(),
        ]);
        Ledger::record('create', 'user', $uid, "Staff access granted to employee {$emp['name']} ($email, $role)");
        flash('success', t('access.granted', ['name' => $emp['name'], 'role' => role_name($role)]));
        redirect('/employees/' . $emp['id']);
    }

    public function employee(string $id): void
    {
        $emp = db()->one('SELECT e.*, d.name AS department_name, l.name AS location_name, m.name AS manager_name,
                (SELECT COUNT(*) FROM employees r WHERE r.manager_id = e.id AND r.active = 1) AS reports
            FROM employees e LEFT JOIN departments d ON d.id = e.department_id LEFT JOIN locations l ON l.id = e.location_id
            LEFT JOIN employees m ON m.id = e.manager_id WHERE e.id = ?', [(int) $id]) ?? abort(404);
        scope_guard($emp['location_id']);
        $assets = db()->all(\ITAM\Services\AssetQuery::SELECT . ' WHERE a.assigned_to = ? ORDER BY a.tag', [(int) $id]);
        $history = db()->all('SELECT x.*, a.tag, a.name AS asset_name FROM assignments x JOIN assets a ON a.id = x.asset_id WHERE x.employee_id = ? ORDER BY x.checked_out_at DESC', [(int) $id]);
        $licenses = db()->all('SELECT l.id, l.name, l.edition FROM license_seats s JOIN licenses l ON l.id = s.license_id WHERE s.employee_id = ?', [(int) $id]);
        $value = array_sum(array_map(fn ($a) => (float) $a['value'], $assets));
        // Their account on the staff screens, if any (by directory id, then e-mail).
        $account = db()->one('SELECT id, role, active, managed_by FROM users WHERE (directory_id IS NOT NULL AND directory_id = ?) OR (? <> \'\' AND LOWER(email) = LOWER(?)) LIMIT 1',
            [(string) $emp['external_id'], (string) $emp['email'], (string) $emp['email']]);
        $loans = db()->all("SELECT b.id, b.number, b.status, b.starts_on, b.ends_on, c.name AS category_name FROM loan_bookings b
            JOIN assets la ON la.id = b.asset_id LEFT JOIN categories c ON c.id = la.category_id WHERE b.employee_id = ? ORDER BY b.id DESC LIMIT 10", [(int) $id]);
        $receipts = db()->all('SELECT id, receipt_no, type, status, signed_at, created_at FROM custody_receipts WHERE employee_id = ? ORDER BY id DESC LIMIT 20', [(int) $id]);
        $this->view('directory/employee', compact('emp', 'assets', 'history', 'licenses', 'value', 'account', 'loans', 'receipts'));
    }

    /**
     * An employee is deleted only when nothing meaningful hangs on the record. Devices they hold would lose their
     * holder while staying "deployed", and the assignment history, license seats, requests and loan bookings are
     * deleted with them (ON DELETE CASCADE). So: refuse while devices are held (offboard first), and refuse when a
     * custody history exists (deactivate instead). Receipts and clearances keep the name and survive either way.
     */
    private function guardEmployeeDelete(array $emp): void
    {
        $id = (int) $emp['id'];
        $back = '/directory/employees/' . $id . '/edit';
        if ((int) db()->value('SELECT COUNT(*) FROM assets WHERE assigned_to = ?', [$id])) {
            flash('error', t('directory.employee_holds'));
            redirect($back);
        }
        $history = (int) db()->value('SELECT COUNT(*) FROM assignments WHERE employee_id = ?', [$id])
            + (int) db()->value("SELECT COUNT(*) FROM loan_bookings WHERE employee_id = ? AND status NOT IN ('returned','cancelled','rejected','expired')", [$id]);
        if ($history) {
            flash('error', t('directory.employee_has_history'));
            redirect($back);
        }
        // Seats are released by the cascade; say so in the trail.
        foreach (db()->all('SELECT l.id, l.name FROM license_seats s JOIN licenses l ON l.id = s.license_id WHERE s.employee_id = ?', [$id]) as $l) {
            Ledger::record('release_seat', 'license', (int) $l['id'], "Seat on {$l['name']} released: employee {$emp['name']} deleted");
        }
    }

    /** A manufacturer, or a model of the same manufacturer, is listed once (names compared without case). */
    private function guardDuplicate(string $type, array $data, int $self = 0): void
    {
        $taken = match ($type) {
            'manufacturers' => db()->value('SELECT id FROM manufacturers WHERE LOWER(name) = LOWER(?) AND id <> ?', [$data['name'], $self]),
            'models' => db()->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND LOWER(name) = LOWER(?) AND id <> ?', [$data['manufacturer_id'], $data['name'], $self]),
            default => null,
        };
        if ($taken) {
            $_SESSION['_old'] = $_POST;
            flash('error', t('mfr.duplicate', ['name' => $data['name']]));
            redirect('/directory/' . $type);
        }
    }

    /** Renaming an entry renames it on the devices too; renaming it to a listed name merges the two entries. */
    private function renamed(string $type, array $before, array $data): void
    {
        [$devices, $into] = $type === 'manufacturers'
            ? Catalog::renameManufacturer((int) $before['id'], (string) $before['name'], (string) $data['name'])
            : Catalog::renameModel((int) $before['id'], $before, $data);
        if ($devices || $into) {
            Ledger::record($into ? 'merge' : 'rename', rtrim($type, 's'), $into ?? (int) $before['id'],
                "Renamed {$before['name']} to {$data['name']} on $devices asset(s)" . ($into ? ', merged into the existing entry' : ''));
        }
        if ($into) {
            flash('success', t('mfr.merged', ['name' => $data['name'], 'n' => $devices]));
            redirect('/directory/' . $type);
        }
        if ($devices) {
            flash('success', t('mfr.renamed', ['n' => $devices]));
            redirect('/directory/' . $type);
        }
    }

    /** An entry still on devices is kept: deleting it would only bring it back on their next save or sync. */
    private function guardCatalogDelete(string $type, array $row): void
    {
        $n = $type === 'manufacturers'
            ? Catalog::devices((string) $row['name'])
            : Catalog::devices((string) db()->value('SELECT name FROM manufacturers WHERE id = ?', [$row['manufacturer_id']]), (string) $row['name']);
        if ($n) {
            flash('error', t('mfr.in_use', ['n' => $n]));
            redirect('/directory/' . $type . '/' . $row['id'] . '/edit');
        }
    }

    private function payload(array $s): array
    {
        $rules = [];
        foreach ($s['fields'] as $f => [$kind, $rule]) {
            if ($rule) {
                $rules[$f] = $rule;
            }
        }
        $this->validate($rules);
        $data = [];
        foreach ($s['fields'] as $f => [$kind]) {
            $v = input_or_null($f);
            $data[$f] = match (true) {
                $kind === 'checkbox' => $v ? 1 : 0,
                $kind === 'number', str_starts_with($kind, 'select:') => $v === null ? ($kind === 'number' ? 0 : null) : (int) $v,
                $kind === 'org_level' => in_array($v, \ITAM\Services\OrgUnits::LEVELS, true) ? $v : 'department',
                $kind === 'org_parent' => $v === null ? null : (int) $v,
                $f === 'code' => $v ? strtoupper($v) : $v,
                default => $v,
            };
        }
        return $data;
    }

    /**
     * A unit sits under one of a higher level only, and changing its level must not leave the units under it
     * at the same level or above.
     */
    private function guardOrgUnit(array $data, ?int $id): void
    {
        $back = $id ? '/directory/departments/' . $id . '/edit' : '/directory/departments';
        if ($id !== null && $data['parent_id'] === $id || !\ITAM\Services\OrgUnits::validParent($data['level'], $data['parent_id'])) {
            flash('error', t('org.bad_parent'));
            redirect($back);
        }
        foreach ($id ? \ITAM\Services\OrgUnits::children($id) : [] as $child) {
            if (\ITAM\Services\OrgUnits::rank(\ITAM\Services\OrgUnits::all()[$child]['level']) <= \ITAM\Services\OrgUnits::rank($data['level'])) {
                flash('error', t('org.children_level'));
                redirect($back);
            }
        }
        \ITAM\Services\OrgUnits::flush();
    }

    /** Units in tree order, with their depth and the assets of the unit and everything under it. */
    private function orgRows(array $rows): array
    {
        $byId = array_column($rows, null, 'id');
        $out = [];
        foreach (\ITAM\Services\OrgUnits::tree() as $u) {
            $r = $byId[$u['id']] ?? null;
            if ($r === null) {
                continue;
            }
            $r['depth'] = $u['depth'];
            $r['level'] = $u['level'];
            $r['total_count'] = array_sum(array_map(fn ($d) => (int) ($byId[$d]['asset_count'] ?? 0), \ITAM\Services\OrgUnits::descendants((int) $u['id'])));
            $out[] = $r;
        }
        return $out;
    }

    private function refs(): array
    {
        return [
            'departments' => \ITAM\Services\OrgUnits::options(),
            'locations'   => db()->all('SELECT id, name FROM locations ORDER BY name'),
            'employees'   => db()->all('SELECT id, name FROM employees WHERE active = 1 ORDER BY name'),
            'manufacturers' => db()->all('SELECT id, name FROM manufacturers ORDER BY name'),
            'categories'  => db()->all('SELECT id, name FROM categories ORDER BY name'),
            'users'       => db()->all('SELECT id, name FROM users WHERE active = 1 ORDER BY name'),
        ];
    }
}
