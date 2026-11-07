<?php
return [
    'title' => 'Employees',
    'summary' => 'The people who receive devices: their records, what each one holds, and giving an employee access to the staff screens.',
    'blocks' => [
        ['h', 'Employees and users'],
        ['p', 'An **employee** is anyone who can hold devices, sign custody receipts and use the employee portal. A **user** is an IT staff member who signs in to ITAM. One person can be both. See [[roles]] for users.'],

        ['h', 'Adding an employee'],
        ['steps', [
            'Open [Employees](/directory/employees).',
            'Fill in the name and, as far as you can, the employee number, work e-mail, mobile, job title (English and Arabic), department, location and manager.',
            'Save.',
        ]],
        ['tip', 'The work e-mail matters: the employee uses it to sign in to the employee portal. See [[portal]].'],

        ['h', 'The employee page'],
        ['p', 'Open an employee to see:'],
        ['list', [
            'The devices they hold now, and their total value.',
            'The history of every device they held, with dates and condition.',
            'Their licence seats, loaner bookings and custody receipts.',
        ]],

        ['h', 'Giving staff access'],
        ['p', 'Someone with the right to manage users can press **Grant staff access** on an employee page and choose a role. ITAM creates a user account with the employee’s e-mail. That account signs in with **Sign in with Microsoft**, so single sign-on must be set up. For a password account, create the user in [Users & Roles](/admin/users) instead.'],

        ['h', 'Leaving the organisation'],
        ['steps', [
            'Check in every device the employee holds, with a return receipt. See [[custody]].',
            'Free their licence seats. See [[licenses]].',
            'Edit the employee and untick **Active**.',
        ]],
        ['warn', 'ITAM does not let you delete an employee who still holds devices, or who has a custody or loan history. Deactivate them instead, so the history stays complete.'],
    ],
];
