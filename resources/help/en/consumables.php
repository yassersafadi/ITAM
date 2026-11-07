<?php
return [
    'title' => 'Accessories & consumables',
    'summary' => 'Keep stock of items counted by quantity, such as mice, cables, chargers, toner and spare parts, with a minimum level for each.',
    'blocks' => [
        ['h', 'When to use it'],
        ['p', 'Use [Accessories & consumables](/consumables) for items you count rather than track one by one. A laptop is an asset with its own tag; a box of USB cables is a consumable with a quantity.'],

        ['h', 'Adding an item'],
        ['steps', [
            'Press **New item**.',
            'Fill in the name, SKU, type (accessory, cable, power / charger, peripheral, toner / ink, storage media, spare part or other) and unit (pieces, box, pack, metre, roll, set).',
            'Set the **Minimum level**, and if you like a **Reorder quantity**, the unit cost and the location where it is kept.',
            'Enter the opening quantity and save.',
        ]],

        ['h', 'Moving stock'],
        ['p', 'Open the item and choose a movement:'],
        ['fields', [
            ['Issue', 'Give a quantity to an employee. ITAM refuses to issue more than is in stock.'],
            ['Receive', 'Add a delivery to stock.'],
            ['Stock count', 'Enter the quantity you actually counted; ITAM records the difference.'],
        ]],
        ['p', 'Every movement is listed on the item page with the date, the employee, the user and any note.'],

        ['h', 'Minimum level alerts'],
        ['p', 'When an item reaches or falls below its minimum, ITAM sends one notification (and an e-mail if notifications are set up) and lists it on the dashboard under **Needs your attention**. Filter the list to show only low items when you reorder.'],

        ['h', 'Good to know'],
        ['list', [
            'The tiles at the top show the number of items, how many are low, the stock value and the quantity issued in the last 30 days.',
            'Items have no QR label and are not part of physical audits; use **Stock count** to correct quantities.',
        ]],
    ],
];
