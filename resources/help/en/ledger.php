<?php
return [
    'title' => 'Activity log',
    'summary' => 'A record of every change in ITAM: who did what, to which record, and when.',
    'blocks' => [
        ['h', 'What is recorded'],
        ['p', 'ITAM writes an entry for every important action: creating, editing and deleting assets and reference data, hand-overs and returns, signed receipts, work orders, licence seats, audits, disposals, imports and exports, sign-ins, user and settings changes. Each entry shows the date and time, the user, the action, the record and a short summary, with the changed fields where useful.'],

        ['h', 'Opening the log'],
        ['p', 'Open [the activity log](/ledger) from the reports section of the menu. You need the permission to see it; by default managers and auditors have it.'],

        ['h', 'Finding an entry'],
        ['list', [
            'Type in the search box to match the summary or the user’s name.',
            'Choose a record type (asset, licence, user and so on) to narrow the list.',
            'The newest entries come first, 50 per page.',
        ]],
        ['tip', 'For the story of one device, the **Timeline** tab of the asset page is quicker: it shows the same entries for that asset together with its work orders and audit scans.'],

        ['h', 'Good to know'],
        ['list', [
            'Entries cannot be edited or deleted from the screens.',
            'Passwords and other secrets are never written to the log.',
            'The log lives in the ITAM database, so it is included in every backup. See [[settings]].',
        ]],
    ],
];
