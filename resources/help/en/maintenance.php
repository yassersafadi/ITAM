<?php
return [
    'title' => 'Maintenance',
    'summary' => 'Work orders on assets: create, assign, start and complete them, with SLA due times, photos, cost and downtime, and fault reports from QR labels.',
    'blocks' => [
        ['h', 'The work order list'],
        ['p', '[Maintenance](/maintenance) shows active work orders by default, most urgent first. Tiles count open, in progress and overdue work, SLA breaches, open fault reports, downtime over 90 days, mean time to repair and the share of work done within its SLA. Filter by status, type, overdue, SLA breached, fault reports, technician or unassigned.'],

        ['h', 'Creating a work order'],
        ['steps', [
            'Press **New work order**, or open the asset’s **Maintenance** tab.',
            'Choose the asset and type a title.',
            'Choose the type (**Corrective**, **Preventive**, **Upgrade**, **Inspection**) and priority (**Low**, **Medium**, **High**, **Critical**).',
            'Optionally set a scheduled date, a technician, a description and **Repeat every (days)** for routine work.',
            'Tick **Mark asset as “in repair”** if the device is out of use, then create.',
        ]],
        ['tip', 'To open the same work order on many assets, select them in the asset list and use **Open work orders** in the bulk bar.'],

        ['h', 'SLA due times'],
        ['p', 'Corrective work orders get a due time from their priority. The number of hours for each priority is set in [Settings](/admin/settings) › **Service & SLA**.'],
        ['list', [
            'The work order shows the time left, or how late it is.',
            'When a work order passes its due time, ITAM raises its priority one step (once) and notifies the team.',
            'Completed work shows whether it met its SLA; the **SLA met** tile gives the share over 30 days.',
        ]],

        ['h', 'Working a work order'],
        ['p', 'Open a work order from the list to see the asset, its holder and location, the problem, the SLA, the photos and the history.'],
        ['steps', [
            '**Take** it to assign it to yourself, or assign it to another technician from the list.',
            'Press **Start** when you begin; the status becomes **In progress**.',
            'Add **Before** and **After** photos from the phone camera or a file.',
            'Press **Complete work order** and fill in what was done, the downtime in hours and the cost.',
        ]],
        ['list', [
            'When the last open corrective work order on an asset in repair is completed, the asset returns to **Deployed** or **In stock**.',
            'A work order with a repeat interval creates the next one when it is completed.',
            'Use the discussion on the work order to talk with the team; write @ and a full name to notify someone.',
        ]],

        ['h', 'Fault reports from QR labels'],
        ['p', 'Anyone who scans a device’s QR label without being signed in can report a problem: name, contact, what is wrong, urgency and an optional photo. It becomes a corrective work order on that device with an SLA due time, and the team is notified. Employees can also report from the portal. Filter the list on fault reports to see them. See [[qr-labels]] and [[portal]].'],
        ['warn', 'Retiring an asset cancels its open work orders. See [[disposals]].'],
    ],
];
