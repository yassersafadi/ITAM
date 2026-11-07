<?php
return [
    'title' => 'Physical audits',
    'summary' => 'Count the devices that are really there by scanning their QR labels, and see what is found, misplaced, unexpected or missing.',
    'blocks' => [
        ['h', 'How an audit works'],
        ['p', 'You choose a location, a category or both. ITAM then expects every active asset registered there. As you scan labels, each device is compared with the register in real time. When you close the audit, everything expected but not scanned is marked missing.'],

        ['h', 'Starting an audit'],
        ['steps', [
            'Open [Physical Audits](/audits) and press **Start audit**.',
            'Give it a name, for example “Riyadh HQ – 3rd floor – Q2”.',
            'Choose the location and/or category to check, then start.',
        ]],
        ['tip', 'Choose at least a location or a category. With neither, ITAM has nothing to expect and nothing can be counted as missing.'],

        ['h', 'Scanning'],
        ['list', [
            '**Phone camera:** press **Start camera** and point it at each label. The camera needs a secure HTTPS address.',
            '**Handheld scanner or keyboard:** type or scan the tag, the serial number or the label address in the box and press Enter.',
        ]],
        ['fields', [
            ['Found', 'Registered at the audited location (and in the audited category).'],
            ['Misplaced', 'A known device registered at another location.'],
            ['Unexpected', 'An unknown tag, or a device of another category.'],
            ['Duplicate', 'Already scanned in this audit; counted once.'],
            ['Missing', 'Set on close for every expected device that was not scanned.'],
        ]],
        ['p', 'The page shows the devices still to find. Several people can scan into the same audit at the same time.'],
        ['warn', 'Audits need a connection. Each scan is sent to ITAM straight away.'],

        ['h', 'Closing the audit'],
        ['steps', [
            'Press **Close audit**.',
            'Leave **Correct locations of misplaced assets** ticked to move misplaced devices to the audited location, or untick it to keep their recorded location.',
            'Confirm. The missing devices are listed; investigate them and update their status if needed (for example **Lost / stolen**).',
        ]],
        ['p', 'Each scan also appears on the asset’s timeline.'],
    ],
];
