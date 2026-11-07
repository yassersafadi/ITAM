<?php
// Organisation structure: sectors > departments > sections, with levels that can be skipped, full paths in pickers,
// and asset filters that take in the units below.
use ITAM\Services\AssetQuery;
use ITAM\Services\OrgUnits;

return function (): void {
    $db = db();
    $ids = [];
    $add = function (string $name, string $level, ?int $parent = null) use ($db, &$ids): int {
        return $ids[] = $db->insert('departments', ['name' => $name, 'level' => $level, 'parent_id' => $parent]);
    };
    $asset = (int) $db->value("SELECT id FROM assets WHERE status NOT IN ('retired','lost') ORDER BY id LIMIT 1");
    $savedDept = $db->value('SELECT department_id FROM assets WHERE id = ?', [$asset]);
    try {
        $sector = $add('UT Tech sector', 'sector');
        $dept = $add('UT Infrastructure', 'department', $sector);
        $section = $add('UT Networks', 'section', $dept);
        $loose = $add('UT Reception', 'section');
        $direct = $add('UT Security office', 'section', $sector);
        OrgUnits::flush();
        check('a section shows its full path', OrgUnits::path($section) === 'UT Tech sector › UT Infrastructure › UT Networks');
        check('levels can be skipped: a section on its own, or straight under a sector', OrgUnits::path($loose) === 'UT Reception' && OrgUnits::path($direct) === 'UT Tech sector › UT Security office');
        check('a unit sits under a higher level only', OrgUnits::validParent('section', $dept) && OrgUnits::validParent('department', $sector)
            && !OrgUnits::validParent('sector', $dept) && !OrgUnits::validParent('department', $section) && !OrgUnits::validParent('department', $dept));
        $d = OrgUnits::descendants($sector);
        sort($d);
        $want = [$sector, $dept, $section, $direct];
        sort($want);
        check('a sector takes in everything under it', $d === $want);
        $tree = array_values(array_filter(OrgUnits::tree(), fn ($u) => in_array((int) $u['id'], $ids, true)));
        check('the tree lists each unit after the one it belongs to', array_column($tree, 'depth', 'id')[$section] === 2
            && array_search($section, array_column($tree, 'id')) > array_search($dept, array_column($tree, 'id')));
        check('pickers offer every unit with its path', in_array('UT Tech sector › UT Infrastructure › UT Networks', array_column(OrgUnits::options(), 'name'), true));
        $db->update('assets', $asset, ['department_id' => $section]);
        check('filtering assets by a sector finds those of its sections', in_array($asset, array_map('intval', array_column(AssetQuery::fetch(['department' => (string) $sector]), 'id')), true));
    } finally {
        $db->update('assets', $asset, ['department_id' => $savedDept]);
        foreach (array_reverse($ids) as $id) {
            $db->delete('departments', $id);
        }
        OrgUnits::flush();
    }
};
