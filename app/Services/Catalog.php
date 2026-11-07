<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * The managed lists of manufacturers and models. Assets keep both as text (assets.manufacturer, assets.model);
 * every path that writes them (form, import, agent, Intune, discovery, procurement) calls learn(), so a value
 * not in the lists yet is added and the lists always cover the register. Names match without regard to case.
 */
final class Catalog
{
    /**
     * Make sure the manufacturer (and its model) are in the lists, adding what is missing. Returns the names as the
     * lists spell them, so a device typed "dell" is stored as "Dell". A model without a manufacturer is not listed.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function learn(?string $manufacturer, ?string $model, ?int $categoryId = null, bool $record = true): array
    {
        $manufacturer = self::clean($manufacturer, 80);
        $model = self::clean($model, 120);
        if ($manufacturer === null) {
            return [null, $model];
        }
        $db = db();
        $m = $db->one('SELECT id, name FROM manufacturers WHERE LOWER(name) = LOWER(?) ORDER BY id LIMIT 1', [$manufacturer]);
        if (!$m) {
            $m = ['id' => $db->insert('manufacturers', ['name' => $manufacturer]), 'name' => $manufacturer];
            if ($record) {
                Ledger::record('create', 'manufacturer', $m['id'], 'Manufacturer added to the list: ' . $manufacturer);
            }
        }
        if ($model === null) {
            return [$m['name'], null];
        }
        $name = $db->value('SELECT name FROM asset_models WHERE manufacturer_id = ? AND LOWER(name) = LOWER(?) ORDER BY id LIMIT 1', [$m['id'], $model]);
        if ($name === null) {
            $id = $db->insert('asset_models', ['manufacturer_id' => $m['id'], 'name' => $model, 'category_id' => $categoryId ?: null]);
            if ($record) {
                Ledger::record('create', 'model', $id, "Model added to the list: {$m['name']} $model");
            }
            $name = $model;
        }
        return [$m['name'], (string) $name];
    }

    /** True when the manufacturer (and the model, if given) are already in the lists. */
    public static function known(?string $manufacturer, ?string $model = null): bool
    {
        $manufacturer = self::clean($manufacturer, 80);
        $model = self::clean($model, 120);
        if ($manufacturer === null) {
            return $model === null;
        }
        $mid = db()->value('SELECT id FROM manufacturers WHERE LOWER(name) = LOWER(?) ORDER BY id LIMIT 1', [$manufacturer]);
        if (!$mid) {
            return false;
        }
        return $model === null || db()->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND LOWER(name) = LOWER(?)', [$mid, $model]) !== null;
    }

    /** Add every manufacturer and model used in the register (after the demo data is seeded). */
    public static function syncFromAssets(): void
    {
        $rows = db()->all("SELECT manufacturer, model, MIN(category_id) AS category_id, COUNT(DISTINCT category_id) AS cats FROM assets
            WHERE manufacturer IS NOT NULL AND manufacturer <> '' GROUP BY manufacturer, model ORDER BY manufacturer, model");
        foreach ($rows as $r) {
            self::learn($r['manufacturer'], $r['model'], (int) $r['cats'] === 1 ? (int) $r['category_id'] : null, false);
        }
    }

    /**
     * A manufacturer was renamed: the devices follow, and when the new name is already listed the two entries become
     * one (its models move over, a model listed under both keeps the existing entry). Returns [devices, merged into id].
     */
    public static function renameManufacturer(int $id, string $old, string $new): array
    {
        $db = db();
        $devices = $old === $new ? 0 : $db->run('UPDATE assets SET manufacturer = ?, updated_at = ? WHERE LOWER(manufacturer) = LOWER(?)', [$new, now(), $old])->rowCount();
        $into = $db->value('SELECT id FROM manufacturers WHERE LOWER(name) = LOWER(?) AND id <> ? ORDER BY id LIMIT 1', [$new, $id]);
        if ($into) {
            foreach ($db->all('SELECT id, name FROM asset_models WHERE manufacturer_id = ?', [$id]) as $x) {
                $dup = $db->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND LOWER(name) = LOWER(?)', [$into, $x['name']]);
                $dup ? $db->delete('asset_models', (int) $x['id']) : $db->update('asset_models', (int) $x['id'], ['manufacturer_id' => (int) $into]);
            }
            $db->delete('manufacturers', $id);
        }
        return [$devices, $into ? (int) $into : null];
    }

    /** A model was renamed or moved to another manufacturer: the devices follow, and a duplicate entry is merged. */
    public static function renameModel(int $id, array $before, array $after): array
    {
        $db = db();
        $man = fn ($mid) => (string) $db->value('SELECT name FROM manufacturers WHERE id = ?', [(int) $mid]);
        $oldMan = $man($before['manufacturer_id']);
        $newMan = $man($after['manufacturer_id']);
        $devices = $oldMan === $newMan && $before['name'] === $after['name'] ? 0 : $db->run(
            'UPDATE assets SET manufacturer = ?, model = ?, updated_at = ? WHERE LOWER(manufacturer) = LOWER(?) AND LOWER(model) = LOWER(?)',
            [$newMan, $after['name'], now(), $oldMan, $before['name']]
        )->rowCount();
        $into = $db->value('SELECT id FROM asset_models WHERE manufacturer_id = ? AND LOWER(name) = LOWER(?) AND id <> ? ORDER BY id LIMIT 1', [(int) $after['manufacturer_id'], $after['name'], $id]);
        if ($into) {
            $db->delete('asset_models', $id);
        }
        return [$devices, $into ? (int) $into : null];
    }

    /** Devices that still carry this manufacturer (or this model of it), in any status. */
    public static function devices(string $manufacturer, ?string $model = null): int
    {
        return (int) ($model === null
            ? db()->value('SELECT COUNT(*) FROM assets WHERE LOWER(manufacturer) = LOWER(?)', [$manufacturer])
            : db()->value('SELECT COUNT(*) FROM assets WHERE LOWER(manufacturer) = LOWER(?) AND LOWER(model) = LOWER(?)', [$manufacturer, $model]));
    }

    /** Both lists for the asset form: manufacturers by name, models with their manufacturer and category. */
    public static function forForm(): array
    {
        $db = db();
        return [
            'manufacturers' => array_column($db->all('SELECT name FROM manufacturers ORDER BY name'), 'name'),
            'models' => array_map(fn ($r) => ['m' => $r['manufacturer'], 'name' => $r['name'], 'category' => $r['category_id'] ? (int) $r['category_id'] : null],
                $db->all('SELECT m.name AS manufacturer, x.name, x.category_id FROM asset_models x JOIN manufacturers m ON m.id = x.manufacturer_id ORDER BY m.name, x.name')),
        ];
    }

    private static function clean(?string $v, int $max): ?string
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
