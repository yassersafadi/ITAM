<?php
return [
    'title' => 'Users, roles and permissions',
    'summary' => 'Create accounts for the IT team, choose what each role may do, and limit users to some locations.',
    'blocks' => [
        ['h', 'Users'],
        ['p', 'In [Users & Roles](/admin/users), someone with the **Manage users** permission can:'],
        ['list', [
            'Add a user with name, e-mail, password, role and language.',
            'Change a user’s role, deactivate the account, or set a new password.',
            'Limit a user to some **locations**: they then see and change only the assets, work orders, receipts and reports of those locations. Leave it empty for all locations.',
            'Reset a user’s two-factor authentication when they lose their phone.',
        ]],
        ['tip', 'You cannot deactivate yourself or change your own role, so you can never lock yourself out. Only an administrator can create or change an administrator.'],

        ['h', 'Built-in roles'],
        ['fields', [
            ['Administrator', 'Everything, always. Only administrators open [Roles & permissions](/admin/roles).'],
            ['IT Manager', 'Runs the register: assets, custody, work orders, licences, audits, reference data, the activity log and exports.'],
            ['Technician', 'Day-to-day work: assets, hand-overs, work orders, licences, audits and exports.'],
            ['Auditor', 'Reads everything, runs physical audits, sees the activity log and exports reports.'],
        ]],

        ['h', 'Permissions'],
        ['fields', [
            ['Add and edit assets', 'Create, edit, import and bulk-change assets; attach files.'],
            ['Hand out and take back devices', 'Check out and in, custody receipts, loaner bookings, accessories and consumables.'],
            ['Delete and dispose of assets', 'Delete assets and retire them.'],
            ['Work orders', 'Create, assign and complete work orders.'],
            ['Manage licences and software', 'Licences and seats.'],
            ['Run audits', 'Start, scan and close physical audits.'],
            ['Export reports', 'Export the asset list and reports.'],
            ['See the activity log', 'Open the activity log.'],
            ['Lists and categories', 'Locations, departments, categories, extra fields, manufacturers and models.'],
            ['Manage users', 'Users and staff access for employees.'],
            ['Settings', 'All the settings pages.'],
        ]],
        ['p', 'Every user can view the register; the permissions decide what they may change.'],

        ['h', 'Changing roles'],
        ['steps', [
            'Open [Roles & permissions](/admin/roles).',
            'Choose a role, rename it in English and Arabic if you like, tick its permissions and save.',
            'To add a role, give it a name and optionally start from a copy of an existing one.',
            'Change the order of the roles with the arrows. A role still given to users cannot be deleted.',
        ]],
        ['warn', 'The administrator role always keeps every permission.'],

        ['h', 'Signing in with Microsoft'],
        ['p', 'With single sign-on set up, users can press **Sign in with Microsoft**. ITAM matches them to their account by e-mail. If **Create accounts on first sign-in** is on, a new person gets an account with the role chosen in the settings. See [[settings]].'],
    ],
];
