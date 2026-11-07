<?php
// Manufacturers and models: filled from the register, new names added once whatever their case, renames carried to
// the devices, and a rename to a listed name merges the two entries.
use ITAM\Services\Catalog;

return function (): void {
    $db = db();
    $missing = $db->value("SELECT COUNT(*) FROM (SELECT DISTINCT LOWER(manufacturer) AS m FROM assets WHERE manufacturer IS NOT NULL AND manufacturer <> '') x
        WHERE NOT EXISTS (SELECT 1 FROM manufacturers f WHERE LOWER(f.name) = x.m)");
    check('every manufacturer of the register is listed', (int) $missing === 0 && (int) $db->value('SELECT COUNT(*) FROM manufacturers') > 0, (string) $missing);
    $missing = $db->value("SELECT COUNT(*) FROM assets a WHERE a.manufacturer <> '' AND a.model <> '' AND NOT EXISTS (
        SELECT 1 FROM asset_models x JOIN manufacturers f ON f.id = x.manufacturer_id WHERE LOWER(f.name) = LOWER(a.manufacturer) AND LOWER(x.name) = LOWER(a.model))");
    check('every model of the register is listed under its manufacturer', (int) $missing === 0, (string) $missing);

    try {
        [$m, $x] = Catalog::learn('  Zeta   Test ', 'ZT 100', null, false);
        check('a new manufacturer and model are added, spaces tidied', $m === 'Zeta Test' && $x === 'ZT 100' && Catalog::known('Zeta Test', 'ZT 100'));
        [$m, $x] = Catalog::learn('zeta test', 'zt 100', null, false);
        check('another case reuses the listed spelling', $m === 'Zeta Test' && $x === 'ZT 100'
            && (int) $db->value("SELECT COUNT(*) FROM manufacturers WHERE LOWER(name) = 'zeta test'") === 1
            && (int) $db->value("SELECT COUNT(*) FROM asset_models WHERE LOWER(name) = 'zt 100'") === 1);
        check('unknown names are reported as not listed', !Catalog::known('Zeta Test', 'ZT 999') && !Catalog::known('No Such Maker') && !Catalog::known(null, 'ZT 100'));
        check('a model without a manufacturer is not listed', Catalog::learn(null, 'Orphan 1', null, false) === [null, 'Orphan 1']
            && !$db->value("SELECT id FROM asset_models WHERE name = 'Orphan 1'"));

        // Rename to a listed name: the two manufacturers become one, with the devices and models.
        Catalog::learn('Zeta Test Inc.', 'ZT 100', null, false);
        Catalog::learn('Zeta Test Inc.', 'ZT 200', null, false);
        $a1 = $db->insert('assets', ['tag' => 'UT-CAT-1', 'name' => 'Cat test 1', 'status' => 'in_stock', 'manufacturer' => 'zeta test inc.', 'model' => 'ZT 200', 'created_at' => now(), 'updated_at' => now()]);
        $old = $db->one("SELECT * FROM manufacturers WHERE name = 'Zeta Test Inc.'");
        $db->update('manufacturers', (int) $old['id'], ['name' => 'Zeta Test']);
        [$devices, $into] = Catalog::renameManufacturer((int) $old['id'], 'Zeta Test Inc.', 'Zeta Test');
        $keep = (int) $db->value("SELECT id FROM manufacturers WHERE name = 'Zeta Test'");
        check('a rename moves the devices and merges into the listed entry', $devices === 1 && $into === $keep
            && $db->value('SELECT manufacturer FROM assets WHERE id = ?', [$a1]) === 'Zeta Test'
            && !$db->value('SELECT id FROM manufacturers WHERE id = ?', [$old['id']]));
        check('merged models are kept once', array_column($db->all('SELECT name FROM asset_models WHERE manufacturer_id = ? ORDER BY name', [$keep]), 'name') === ['ZT 100', 'ZT 200']);

        // Model rename follows on the devices.
        $model = $db->one("SELECT * FROM asset_models WHERE manufacturer_id = ? AND name = 'ZT 200'", [$keep]);
        $after = ['manufacturer_id' => $keep, 'name' => 'ZT 210'];
        $db->update('asset_models', (int) $model['id'], $after);
        [$devices] = Catalog::renameModel((int) $model['id'], $model, $after);
        check('a model rename is carried to the devices', $devices === 1 && $db->value('SELECT model FROM assets WHERE id = ?', [$a1]) === 'ZT 210');
        check('devices are counted per entry', Catalog::devices('ZETA TEST') === 1 && Catalog::devices('Zeta Test', 'zt 210') === 1 && Catalog::devices('Zeta Test', 'ZT 100') === 0);

        $form = Catalog::forForm();
        check('the form gets both lists', in_array('Zeta Test', $form['manufacturers'], true)
            && in_array(['m' => 'Zeta Test', 'name' => 'ZT 100', 'category' => null], $form['models'], true));
    } finally {
        $db->run("DELETE FROM assets WHERE tag = 'UT-CAT-1'");
        $db->run("DELETE FROM asset_models WHERE manufacturer_id IN (SELECT id FROM manufacturers WHERE name LIKE 'Zeta Test%')");
        $db->run("DELETE FROM manufacturers WHERE name LIKE 'Zeta Test%'");
    }
};
