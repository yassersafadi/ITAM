<?php
declare(strict_types=1);

namespace ITAM\Services;

use ITAM\Core\Auth;

/** One field of an asset changed from a cell of the list, under the same rules as the asset form and a hand-over. */
final class AssetQuickEdit
{
    /** List column => asset field. Health, risk, age, last seen and replacement are calculated, so they are not here. */
    public const FIELDS = [
        'tag' => 'tag', 'serial' => 'serial', 'name' => 'name', 'category' => 'category_id', 'manufacturer' => 'manufacturer', 'model' => 'model',
        'status' => 'status', 'assigned' => 'assigned_to', 'location' => 'location_id', 'department' => 'department_id', 'warranty' => 'warranty_until',
        'criticality' => 'criticality', 'ip' => 'ip_address', 'os' => 'os', 'value' => 'value', 'po' => 'po_number',
    ];
    private const LENGTHS = ['tag' => 60, 'serial' => 120, 'name' => 160, 'ip' => 45, 'os' => 120, 'po' => 60];
    private const CLOSES_CUSTODY = ['retired', 'lost', 'in_stock'];

    /** The attributes that make a cell editable, for someone who may change it. */
    public static function attrs(array $a, string $col): string
    {
        if (!isset(self::FIELDS[$col]) || !can('assets.edit') || ($col === 'assigned' && !can('assets.assign'))) {
            return '';
        }
        return ' data-edit="' . e($col) . '" data-value="' . e((string) ($a[self::FIELDS[$col]] ?? '')) . '"';
    }

    /** What the list cells offer. Loaded once, on the first edit. */
    public static function options(): array
    {
        $db = db();
        $named = fn (array $rows) => array_map(fn ($r) => ['v' => (string) $r['id'], 'l' => (string) $r['name']], $rows);
        $labels = fn (array $values, string $group) => array_map(fn ($v) => ['v' => (string) $v, 'l' => t($group . '.' . $v)], $values);
        $catalog = Catalog::forForm();
        return [
            'category' => $named($db->all('SELECT id, name FROM categories ORDER BY name')),
            'location' => $named(array_values(array_filter($db->all('SELECT id, name FROM locations ORDER BY name'), fn ($l) => in_scope($l['id'])))),
            'department' => $named(OrgUnits::options()),
            'assigned' => can('assets.assign') ? $named($db->all('SELECT id, name FROM employees WHERE active = 1 ORDER BY name')) : [],
            'status' => $labels(AssetQuery::STATUSES, 'status'),
            'criticality' => $labels([1, 2, 3, 4], 'criticality'),
            'manufacturer' => $catalog['manufacturers'],
            'model' => array_map(fn ($m) => ['m' => $m['m'], 'name' => $m['name']], $catalog['models']),
        ];
    }

    /**
     * Change one field. Returns ['confirm' => text] when closing a custody needs a yes first, otherwise ['ok' => true]
     * with an optional 'message', and a 'link' to the receipt.
     * @throws \InvalidArgumentException with the text to show under the field.
     */
    public static function save(array $a, string $col, string $value, bool $sure, ?int $by): array
    {
        $value = trim($value);
        if (!isset(self::FIELDS[$col])) {
            throw new \InvalidArgumentException(t('assets.quick.bad_value'));
        }
        if ($col === 'assigned') {
            return self::holder($a, $value, $by);
        }
        $field = self::FIELDS[$col];
        $note = null;
        $set = match ($col) {
            'manufacturer', 'model' => self::catalog($a, $col, $value, $note),
            default => [$field => self::clean($a, $col, $value)],
        };
        if ((string) ($a[$field] ?? '') === (string) ($set[$field] ?? '') && count($set) === 1) {
            return ['ok' => true];
        }
        $closes = $col === 'status' && in_array($set['status'], self::CLOSES_CUSTODY, true) && $a['assigned_to'];
        if ($closes && !$sure) {
            return ['confirm' => t('assets.quick.closes_custody', ['name' => (string) db()->value('SELECT name FROM employees WHERE id = ?', [$a['assigned_to']])])];
        }
        db()->transaction(function () use ($a, $col, $set, $closes): void {
            if ($closes) {
                self::closeAssignment((int) $a['id'], $a['condition_state']);
                $set['assigned_to'] = null;
            }
            if ($col === 'location') {
                db()->recordMove((int) $a['id'], $a['location_id'], $set['location_id']);
            }
            db()->update('assets', (int) $a['id'], $set + ['updated_at' => now()]);
            $diff = Ledger::diff($a, $set);
            if ($diff) {
                Ledger::record('update', 'asset', (int) $a['id'], "Updated {$a['tag']} from the list: " . implode(', ', array_keys($diff)), $diff);
            }
        });
        $note ??= self::duplicate((int) $a['id'], $field, $set[$field]);
        return ['ok' => true] + ($note !== null ? ['message' => $note] : []);
    }

    /** Check a value and put it in the form the asset keeps. */
    private static function clean(array $a, string $col, string $value): mixed
    {
        $bad = fn () => new \InvalidArgumentException(t('assets.quick.bad_value'));
        if (isset(self::LENGTHS[$col]) && mb_strlen($value) > self::LENGTHS[$col]) {
            throw $bad();
        }
        switch ($col) {
            case 'tag':
            case 'name':
                if ($value === '') {
                    throw new \InvalidArgumentException(t('validation.required', ['field' => t('field.' . $col)]));
                }
                if ($col === 'tag' && db()->value('SELECT id FROM assets WHERE tag = ? AND id <> ?', [$value, $a['id']])) {
                    throw new \InvalidArgumentException(t('assets.tag_taken', ['tag' => $value]));
                }
                return $value;
            case 'status':
                return in_array($value, AssetQuery::STATUSES, true) ? $value : throw $bad();
            case 'criticality':
                return in_array((int) $value, [1, 2, 3, 4], true) ? (int) $value : throw $bad();
            case 'category':
                if (!db()->value('SELECT id FROM categories WHERE id = ?', [(int) $value])) {
                    throw $bad();
                }
                if (CustomFields::validate((int) $value, [])) {
                    throw new \InvalidArgumentException(t('assets.quick.category_fields'));
                }
                return (int) $value;
            case 'location':
                if ($value === '' ? scope_ids() !== null : !db()->value('SELECT id FROM locations WHERE id = ?', [(int) $value])) {
                    throw $bad();
                }
                if ($value !== '' && !in_scope((int) $value)) {
                    throw new \InvalidArgumentException(t('errors.forbidden'));
                }
                return $value === '' ? null : (int) $value;
            case 'department':
                if ($value !== '' && !in_array((int) $value, array_map('intval', array_column(OrgUnits::options(), 'id')), true)) {
                    throw $bad();
                }
                return $value === '' ? null : (int) $value;
            case 'warranty':
                if ($value === '') {
                    return null;
                }
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                return $d && $d->format('Y-m-d') === $value ? $value : throw $bad();
            case 'value':
                if ($value === '') {
                    return null;
                }
                return is_numeric($value) && (float) $value >= 0 ? $value : throw $bad();
            case 'ip':
                return $value === '' ? null : (preg_match('/^[0-9a-fA-F:.]+$/', $value) ? $value : throw $bad());
        }
        return $value === '' ? null : $value;
    }

    /** Manufacturer and model come from the managed lists; a model that does not belong to a new manufacturer is cleared. */
    private static function catalog(array $a, string $col, string $value, ?string &$note): array
    {
        $manufacturer = $col === 'manufacturer' ? ($value === '' ? null : $value) : $a['manufacturer'];
        $model = $col === 'model' ? ($value === '' ? null : $value) : $a['model'];
        if ($col === 'model' && $model !== null && !$manufacturer) {
            throw new \InvalidArgumentException(t('assets.quick.pick_manufacturer'));
        }
        if ($col === 'manufacturer' && $model !== null && !Catalog::known($manufacturer, $model)) {
            $model = null;
            $note = t('assets.quick.model_cleared');
        }
        if (!can('catalog.edit') && !Catalog::known($manufacturer, $model)) {
            throw new \InvalidArgumentException(t('mfr.not_listed'));
        }
        [$manufacturer, $model] = Catalog::learn($manufacturer, $model, $a['category_id'] ? (int) $a['category_id'] : null);
        return ['manufacturer' => $manufacturer, 'model' => $model];
    }

    /** A new holder is a hand-over, and every change sends a receipt for signature. */
    private static function holder(array $a, string $value, ?int $by): array
    {
        if (!can('assets.assign')) {
            throw new \InvalidArgumentException(t('errors.forbidden'));
        }
        $newId = (int) $value;
        if ($newId === (int) $a['assigned_to']) {
            return ['ok' => true];
        }
        $emp = $newId ? db()->one('SELECT * FROM employees WHERE id = ? AND active = 1', [$newId]) : null;
        if ($newId && !$emp) {
            throw new \InvalidArgumentException(t('assets.quick.bad_value'));
        }
        if ($emp && in_array($a['status'], ['retired', 'lost'], true)) {
            throw new \InvalidArgumentException(t('assets.quick.not_assignable'));
        }
        $old = $a['assigned_to'] ? db()->one('SELECT * FROM employees WHERE id = ?', [$a['assigned_to']]) : null;
        $id = (int) $a['id'];
        $receipt = db()->transaction(function () use ($a, $id, $emp, $old, $by): ?int {
            $now = now();
            if ($a['assigned_to']) {
                self::closeAssignment($id, $a['condition_state']);
                Ledger::record('checkin', 'asset', $id, "Checked in {$a['tag']} from the list");
            }
            $receipt = $old ? Custody::create('return', $old, [$id], [], null, [$id => $a['condition_state']]) : null;
            if (!$emp) {
                db()->update('assets', $id, ['assigned_to' => null, 'status' => 'in_stock', 'updated_at' => $now, 'last_seen_at' => $now]);
                return $receipt;
            }
            db()->insert('assignments', ['asset_id' => $id, 'employee_id' => $emp['id'], 'checked_out_at' => $now, 'condition_out' => $a['condition_state'], 'user_id' => $by]);
            $location = $emp['location_id'] ?? $a['location_id'];
            db()->recordMove($id, $a['location_id'], $location);
            db()->update('assets', $id, ['assigned_to' => $emp['id'], 'status' => 'deployed', 'department_id' => $emp['department_id'] ?? $a['department_id'],
                'location_id' => $location, 'updated_at' => $now, 'last_seen_at' => $now]);
            Ledger::record('checkout', 'asset', $id, "Checked out {$a['tag']} to {$emp['name']} from the list", ['employee_id' => $emp['id']]);
            return Custody::create('issue', $emp, [$id]);
        });
        return ['ok' => true, 'message' => t('assets.quick.receipt', ['name' => (string) ($emp['name'] ?? $old['name'] ?? '')]), 'link' => url('custody/' . $receipt . '/sign')];
    }

    private static function duplicate(int $id, string $field, mixed $value): ?string
    {
        if (!in_array($field, ['serial', 'ip_address'], true) || !$value) {
            return null;
        }
        $tag = db()->value("SELECT tag FROM assets WHERE $field = ? AND id <> ? AND status NOT IN ('retired','lost') LIMIT 1", [$value, $id]);
        return $tag ? t('assets.duplicate', ['field' => t('field.' . $field), 'tag' => $tag]) : null;
    }

    public static function closeAssignment(int $assetId, ?string $condition): void
    {
        db()->run('UPDATE assignments SET checked_in_at = ?, condition_in = ? WHERE asset_id = ? AND checked_in_at IS NULL', [now(), $condition, $assetId]);
    }
}
