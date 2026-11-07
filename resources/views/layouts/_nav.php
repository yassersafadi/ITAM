<?php
// The side menu for the signed-in person, read by NavSections::menu(): daily work first, then reports and set-up.
$settings = can('settings.manage');
$nav = array_map(fn (array $items) => array_values(array_filter($items)), [
    'nav.g.overview' => [
        ['/', 'dashboard', 'nav.dashboard'],
        ['/portal', 'user', 'nav.employee_portal'],
    ],
    'nav.g.assets' => [
        ['/assets', 'box', 'nav.assets'],
        ['/licenses', 'key', 'nav.licenses'],
        ['/consumables', 'layers', 'nav.consumables'],
        ['/audits', 'clipboard', 'nav.audits'],
        ['/disposals', 'trash', 'nav.disposals'],
    ],
    'nav.g.people' => [
        ['/directory/employees', 'users', 'nav.employees'],
        ['/custody', 'edit', 'nav.custody'],
        ['/loans', 'swap', 'nav.loans'],
    ],
    'nav.g.maintenance' => [
        ['/maintenance', 'wrench', 'nav.maintenance'],
    ],
    'nav.g.reports' => [
        ['/reports', 'chart', 'nav.reports'],
        can('ledger.view') ? ['/ledger', 'history', 'nav.ledger'] : null,
    ],
    'nav.g.reference' => [
        ['/directory/locations', 'pin', 'nav.locations'],
        ['/directory/departments', 'building', 'nav.departments'],
        ['/directory/categories', 'layers', 'nav.categories'],
        ['/directory/manufacturers', 'globe', 'nav.manufacturers'],
        ['/directory/models', 'laptop', 'nav.models'],
    ],
    'nav.g.system' => [
        can('users.manage') ? ['/admin/users', 'user', 'nav.users'] : null,
        \ITAM\Core\Auth::isAdmin() ? ['/admin/roles', 'shield', 'nav.roles'] : null,
        $settings ? ['/admin/settings', 'settings', 'nav.settings'] : null,
        ['/help', 'info', 'nav.help'],
        ['/about', 'star', 'nav.about'],
    ],
]);
return $nav;
