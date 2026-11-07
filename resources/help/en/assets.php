<?php
return [
    'title' => 'Assets',
    'summary' => 'The asset register: the list with filters and saved views, quick and bulk changes, the asset page, and adding assets.',
    'blocks' => [
        ['h', 'The asset list'],
        ['p', 'Open [Assets](/assets). At the top, tiles count the assets in the current list (total, value, warranty ending or expired, spare stock). Below them are ready views such as **All**, **Critical**, **Warranty ending**, **Spare stock** and **In repair**.'],
        ['fields', [
            ['Search', 'Matches tag, name, serial number, host name and IP address.'],
            ['Filters', 'Status, category, location, department, condition, criticality, holder, warranty dates, age and the extra fields of a category. Each choice shows as a chip you can remove.'],
            ['Views', '**Save view** keeps the current filters under a name of your choice. Saved views are personal.'],
            ['Layout', '**Table**, **Cards** or **Grouped** (by category, location, status, department or criticality). In the table, choose the **Columns**, the density and how many rows per page.'],
            ['Quick look', 'Opens a side panel with the essentials of an asset, its latest history and open work orders, without leaving the list.'],
            ['Export', 'Downloads the current list as CSV, Excel, PDF or JSON.'],
        ]],
        ['tip', 'Keyboard: `/` to search, `J`/`K` to move, `P` for quick look, `X` to select, `Enter` to open.'],

        ['h', 'Quick edit'],
        ['p', 'With the right to edit assets, click a cell in the table (status, holder, location, category, warranty and others) to change it on the spot. ITAM saves it and records it in the activity log. Setting the status to in stock, retired or lost ends the current custody.'],

        ['h', 'Bulk changes'],
        ['steps', [
            'Tick the assets, or use **Select all**.',
            'In the bar that appears, choose an action: change the status, location, department or criticality, open work orders on all of them, print their QR labels, or export them.',
            'Press **Apply**.',
        ]],

        ['h', 'Adding an asset'],
        ['steps', [
            'Press **New asset**.',
            'Fill in the name and category; the rest is optional: status, condition, criticality, manufacturer and model, serial, network details, specifications, location, department, purchase date, warranty end, value and purchase order.',
            'Leave the tag empty to get the next number automatically, or type your own.',
            'Press **Save**.',
        ]],
        ['list', [
            'The category’s extra fields appear on the form. See [[reference-data]].',
            'Manufacturer and model come from the lists in reference data. Users who may edit reference data can type a new one, which is added to the lists.',
            'ITAM warns you when another active asset has the same serial number, MAC address, IP address or host name.',
            '**Clone** on an asset page starts a new asset with the same details, without serial and network fields.',
        ]],

        ['h', 'The asset page'],
        ['p', 'The header shows the status, criticality, holder, warranty, purchase date and open work orders, with the actions **Label**, **Clone**, **Edit**, **Dispose** and **Check out** or **Check in**.'],
        ['fields', [
            ['Overview', 'Identity, technical details, extra fields and notes.'],
            ['Timeline', 'Everything that happened to the asset: creation, changes, hand-overs, work orders, audit scans and files.'],
            ['Maintenance', 'The asset’s work orders, and a form to open a new one. See [[maintenance]].'],
            ['Custody', 'Who held the asset and when, the condition out and back, and its custody receipts. See [[custody]].'],
            ['Software', 'Licence seats assigned to this device. See [[licenses]].'],
            ['Files', 'Invoices, photos, warranty papers and other documents.'],
            ['Discussion', 'Comments for the team. Write @ and a user’s full name to notify them.'],
        ]],

        ['h', 'Statuses'],
        ['fields', [
            ['Deployed', 'With an employee.'],
            ['In stock', 'Available.'],
            ['Reserved', 'Set aside for someone.'],
            ['In repair', 'Under repair.'],
            ['Retired', 'Out of service. Set by [[disposals]].'],
            ['Lost / stolen', 'Cannot be found.'],
        ]],

        ['h', 'Deleting'],
        ['warn', 'Delete only an asset entered by mistake: it removes the record. For a device that leaves the organisation, use **Dispose** instead so its history is kept. See [[disposals]].'],
    ],
];
