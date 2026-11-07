<?php
return [
    'title' => 'Dashboard',
    'summary' => 'Your starting page: assets by status, what needs your attention, and charts of warranties, work orders and licence use.',
    'blocks' => [
        ['h', 'What the dashboard shows'],
        ['fields', [
            ['Assets summary', 'How many assets are in each status (deployed, in stock, in repair and so on). Press a status to open the asset list filtered on it.'],
            ['Needs your attention', 'Things waiting for action, most urgent first. It refreshes by itself every minute.'],
            ['Analytics', 'Charts: assets by status and by category, warranties ending in the next 12 months, work orders opened against closed over 12 weeks, mean time to repair, and seat use per licence.'],
        ]],

        ['h', 'Needs your attention'],
        ['p', 'Each line says how many items are concerned and links to the list that shows them:'],
        ['list', [
            'Work orders past their SLA time.',
            'Licences with more seats assigned than bought.',
            'Urgent (high or critical) work orders with no technician.',
            'Loaner requests from employees waiting for approval.',
            'Devices past their expected return date.',
            'High and mission-critical assets whose warranty ends within 30 days.',
            'Accessories and consumables at or below their minimum.',
        ]],
        ['tip', 'An empty list means nothing is waiting for you.'],

        ['h', 'Choosing a location'],
        ['p', 'Use the location list at the top to see the dashboard for one location, or **All my locations**. A user limited to some branches only sees those.'],

        ['h', 'Customising'],
        ['steps', [
            'Press **Customize**.',
            'Change the order of the sections, or press **Hide** on the ones you do not need.',
            'Press **Done**. **Reset** brings back the default layout.',
        ]],
        ['p', 'Your layout is saved for your account only.'],
    ],
];
