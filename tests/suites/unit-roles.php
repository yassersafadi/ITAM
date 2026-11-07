<?php
// Roles and permissions: the seeded roles keep the built-in permissions, the administrator always has everything,
// and a custom role grants exactly what was ticked.
use ITAM\Core\Auth;

return function (): void {
    check('roles seeded in order', Auth::roles() === ['admin', 'manager', 'technician', 'auditor'], json_encode(Auth::roles()));
    $unnamed = [];
    foreach (['en', 'ar'] as $l) {
        foreach (Auth::PERMISSION_GROUPS as $group => $list) {
            foreach (array_merge(['permgroup.' . $group], ...array_map(fn ($p) => ['perm.' . $p, 'perm.' . $p . '_help'], $list)) as $key) {
                if (\ITAM\Core\Lang::with($l, fn () => t($key)) === $key) {
                    $unnamed[] = "$l:$key";
                }
            }
        }
    }
    check('every permission has a name and a description in both languages', !$unnamed, implode(', ', $unnamed));
    $builtin = (new ReflectionClass(Auth::class))->getConstant('PERMISSIONS');
    foreach ($builtin as $role => $perms) {
        $db = Auth::permissionsOf($role);
        sort($perms);
        sort($db);
        check("seeded permissions of $role match the built-in ones", $perms === $db);
    }
    db()->run("UPDATE roles SET permissions = '[]' WHERE slug = 'admin'");
    Auth::forgetRoles();
    check('administrator keeps every permission even if emptied in the database', Auth::permissionsOf('admin') === ['*']);
    db()->run("UPDATE roles SET permissions = '[\"*\"]' WHERE slug = 'admin'");

    $known = array_merge(...array_values(Auth::PERMISSION_GROUPS));
    check('11 permissions are offered', count($known) === 11, (string) count($known));
    db()->insert('roles', ['slug' => 'rtest', 'name_en' => 'Store keeper', 'permissions' => json_encode(['catalog.edit']), 'sort' => 9,
        'created_at' => now(), 'updated_at' => now()]);
    Auth::forgetRoles();
    check('custom role listed', in_array('rtest', Auth::roles(), true));
    check('custom role name in English', \ITAM\Core\Lang::with('en', fn () => Auth::roleName('rtest')) === 'Store keeper');
    check('custom role falls back to any name it has', \ITAM\Core\Lang::with('ar', fn () => Auth::roleName('rtest')) === 'Store keeper');
    check('built-in role keeps its translation', \ITAM\Core\Lang::with('ar', fn () => Auth::roleName('technician')) === 'فني');
    check('custom role permissions', Auth::permissionsOf('rtest') === ['catalog.edit']);
    check('default role is technician', Auth::defaultRole() === 'technician');
    db()->run("DELETE FROM roles WHERE slug = 'rtest'");
    Auth::forgetRoles();
};
