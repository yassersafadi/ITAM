<?php
return [
    'title' => 'Search',
    'summary' => 'Find an asset, employee, licence or page quickly, and narrow the asset list with filters.',
    'blocks' => [
        ['h', 'Quick search (Ctrl K)'],
        ['p', 'Press the search bar at the top of any page, or `Ctrl K` (`⌘ K` on a Mac). Type at least two letters. ITAM looks in:'],
        ['list', [
            '**Assets:** tag, name, serial number, host name and IP address.',
            '**Employees:** name, e-mail and employee number.',
            '**Licences:** name and publisher.',
            '**Menu pages:** type a page name, such as “loans”, to jump to it.',
        ]],
        ['p', 'Move with ↑ and ↓, press `Enter` to open, `Esc` to close. Only assets in the locations you may see appear.'],
        ['tip', 'Quick search matches text. To ask for “laptops in Riyadh whose warranty ends soon”, use the filters of the asset list instead.'],

        ['h', 'Searching the asset list'],
        ['p', 'The search box on [Assets](/assets) matches the same fields as quick search. Combine it with the filters on the side: status, category, location, department, condition, criticality, warranty dates, holder and the extra fields of a category. See [[assets]].'],
        ['list', [
            'Every filter you choose appears as a chip above the list; press × on a chip to remove it.',
            'Save a combination you use often with **Save view**; it appears under **Views**.',
            'Press `/` on the asset list to jump to its search box.',
        ]],

        ['h', 'Other lists'],
        ['p', 'Accessories and consumables and the activity log have their own search box; licences, work orders and custody receipts have filters at the top of the page.'],

        ['h', 'Frequently asked questions'],
        ['faq', [
            ['An asset I know exists does not appear', 'Check that you have no filter left on, and that the asset is in one of your locations. A user limited to some branches only sees their assets.'],
            ['Can I search in Arabic?', 'Yes. Search matches the text as it is stored, in either language.'],
        ]],
    ],
];
