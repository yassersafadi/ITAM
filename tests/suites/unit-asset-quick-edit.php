<?php
// One cell of the asset list: the rules of the asset form and of a hand-over still apply.
use ITAM\Core\Auth;
use ITAM\Services\AssetQuickEdit;

return function (): void {
    $db = db();
    $session = $_SESSION ?? [];
    $cfg = &$GLOBALS['itam_config'];
    $saved = $cfg;
    $as = function (?array $user): void {
        (new ReflectionProperty(Auth::class, 'user'))->setValue(null, $user);
        (new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, true);
        $_SESSION = $user ? ['user_id' => $user['id']] : [];
    };
    try {
        $cfg['mail'] = ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'from' => 'desk@aim.test'] + $cfg['mail'];
        $admin = $db->one("SELECT * FROM users WHERE role = 'admin' AND active = 1 ORDER BY id LIMIT 1");
        $as($admin);
        $by = (int) $admin['id'];
        $emps = $db->all('SELECT * FROM employees WHERE active = 1 ORDER BY id LIMIT 2');
        $make = fn (string $tag, array $over = []) => $db->insert('assets', $over + ['tag' => $tag, 'name' => "Laptop $tag", 'status' => 'in_stock', 'condition_state' => 'good', 'created_at' => now(), 'updated_at' => now()]);
        $get = fn (int $id) => $db->one('SELECT * FROM assets WHERE id = ?', [$id]);
        $put = fn (int $id, string $col, string $value, bool $sure = false) => AssetQuickEdit::save($get($id), $col, $value, $sure, $by);
        $refused = function (int $id, string $col, string $value) use ($put): bool {
            try {
                $put($id, $col, $value);
                return false;
            } catch (\InvalidArgumentException) {
                return true;
            }
        };

        // Plain fields.
        $id = $make('QE-1');
        $make('QE-TAKEN', ['serial' => 'QE-SN-TAKEN']);
        $put($id, 'name', 'Finance laptop');
        check('a name changes', $get($id)['name'] === 'Finance laptop');
        check('a change is in the audit trail', (bool) $db->value("SELECT 1 FROM ledger WHERE entity = 'asset' AND entity_id = ? AND action = 'update'", [$id]));
        check('a name cannot be empty', $refused($id, 'name', ''));
        $put($id, 'tag', 'QE-1B');
        check('a tag changes', $get($id)['tag'] === 'QE-1B');
        check('a tag in use is refused', $refused($id, 'tag', 'QE-TAKEN'));
        check('a serial in use only warns', isset($put($id, 'serial', 'QE-SN-TAKEN')['message']) && $get($id)['serial'] === 'QE-SN-TAKEN');
        check('a bad date is refused', $refused($id, 'warranty', '2026-13-40'));
        $put($id, 'warranty', '2028-01-31');
        check('a date is kept', $get($id)['warranty_until'] === '2028-01-31');
        $put($id, 'warranty', '');
        check('a date can be cleared', $get($id)['warranty_until'] === null);
        check('a negative value is refused', $refused($id, 'value', '-5'));
        check('a bad address is refused', $refused($id, 'ip', 'not an ip'));
        check('an unknown criticality is refused', $refused($id, 'criticality', '9'));
        check('an unknown status is refused', $refused($id, 'status', 'gone'));
        check('a calculated field is refused', $refused($id, 'health', '100'));

        // Location.
        $loc = (int) $db->value('SELECT id FROM locations ORDER BY id LIMIT 1');
        $put($id, 'location', (string) $loc);
        check('a move of location is recorded', (int) $get($id)['location_id'] === $loc && (bool) $db->value('SELECT 1 FROM asset_moves WHERE asset_id = ?', [$id]));

        // Category with required fields.
        $cat = $db->insert('categories', ['name' => 'QE required', 'code' => 'QER']);
        $db->insert('custom_fields', ['category_id' => $cat, 'label' => 'Needed', 'type' => 'text', 'required' => 1]);
        check('a category with required fields is left to the form', $refused($id, 'category', (string) $cat));
        $free = $db->insert('categories', ['name' => 'QE free', 'code' => 'QEF']);
        $put($id, 'category', (string) $free);
        check('another category changes', (int) $get($id)['category_id'] === $free);

        // Catalog.
        [$m1] = \ITAM\Services\Catalog::learn('QE Maker', 'QE One', null);
        \ITAM\Services\Catalog::learn('QE Other', 'QE Two', null);
        check('a model needs a manufacturer', $refused($id, 'model', 'QE One'));
        $put($id, 'manufacturer', 'QE Maker');
        $put($id, 'model', 'QE One');
        $moved = $put($id, 'manufacturer', 'QE Other');
        check('a model of another manufacturer is cleared, with a note', $get($id)['model'] === null && isset($moved['message']));
        $as(['id' => 0, 'role' => 'technician', 'active' => 1]);
        if (!can('catalog.edit')) {
            check('someone without the catalog picks from the lists', $refused($id, 'manufacturer', 'QE Unknown'));
        }
        $as($admin);

        // Status and custody.
        $held = $make('QE-HELD');
        $put($held, 'assigned', (string) $emps[0]['id']);
        $a = $get($held);
        check('a new holder is a hand-over', (int) $a['assigned_to'] === (int) $emps[0]['id'] && $a['status'] === 'deployed');
        check('the hand-over is recorded', (int) $db->value('SELECT COUNT(*) FROM assignments WHERE asset_id = ? AND checked_in_at IS NULL', [$held]) === 1);
        $tag = $a['tag'];
        $issued = fn (int $emp, string $type) => (bool) $db->value('SELECT 1 FROM custody_receipts WHERE employee_id = ? AND type = ? AND items LIKE ?', [$emp, $type, "%$tag%"]);
        check('a receipt goes out for signature', $issued((int) $emps[0]['id'], 'issue'));

        $asked = $put($held, 'status', 'in_stock');
        check('closing a custody asks first', isset($asked['confirm']) && (int) $get($held)['assigned_to'] === (int) $emps[0]['id']);
        $put($held, 'assigned', (string) $emps[1]['id']);
        check('changing the holder closes the old custody', (int) $db->value('SELECT COUNT(*) FROM assignments WHERE asset_id = ? AND checked_in_at IS NULL', [$held]) === 1 && (int) $get($held)['assigned_to'] === (int) $emps[1]['id']);
        check('the previous holder gets a return receipt', $issued((int) $emps[0]['id'], 'return'));
        check('and the new holder a receipt', $issued((int) $emps[1]['id'], 'issue'));
        $put($held, 'status', 'in_stock', true);
        $a = $get($held);
        check('confirmed, the status closes the custody', $a['status'] === 'in_stock' && $a['assigned_to'] === null && !$db->value('SELECT 1 FROM assignments WHERE asset_id = ? AND checked_in_at IS NULL', [$held]));

        $back = $make('QE-BACK');
        $put($back, 'assigned', (string) $emps[0]['id']);
        $res = $put($back, 'assigned', '');
        check('clearing the holder returns the device', $get($back)['status'] === 'in_stock' && $get($back)['assigned_to'] === null && !empty($res['link']));
        $lost = $make('QE-LOST', ['status' => 'lost']);
        check('a lost device cannot be handed over', $refused($lost, 'assigned', (string) $emps[0]['id']));
        $gone = $db->insert('employees', ['name' => 'QE Left', 'email' => 'qe-left@aim.test', 'active' => 0]);
        check('an inactive employee is refused', $refused($back, 'assigned', (string) $gone));

        // Who sees what.
        $row = $get($id);
        check('an editable cell carries its value', str_contains(AssetQuickEdit::attrs($row, 'tag'), 'data-edit="tag"'));
        check('a calculated cell is not editable', AssetQuickEdit::attrs($row, 'health') === '');
        $as(['id' => 0, 'role' => 'nobody', 'active' => 1]);
        check('without the permission no cell is editable', AssetQuickEdit::attrs($row, 'tag') === '');
        $as(null);
    } finally {
        $cfg = $saved;
        (new ReflectionProperty(Auth::class, 'user'))->setValue(null, null);
        (new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, false);
        $_SESSION = $session;
    }
};
