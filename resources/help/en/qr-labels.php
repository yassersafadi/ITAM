<?php
return [
    'title' => 'QR labels',
    'summary' => 'Print QR labels for your devices, and what happens when someone scans one.',
    'blocks' => [
        ['h', 'What is on a label'],
        ['p', 'Each label carries a QR code, your logo, the asset tag, the asset name, the serial number (or host name) and your organisation’s name. The QR code holds the asset’s address in ITAM.'],
        ['warn', 'Set the **Public address** in [Settings](/admin/settings) › **General** before printing, so the codes point to an address that phones can reach. Labels printed with the wrong address must be reprinted.'],

        ['h', 'Printing labels'],
        ['list', [
            '**One asset:** on the asset page, press **Label**.',
            '**A filtered list:** on [Assets](/assets), filter the list, then press **QR labels**. Up to 300 labels at a time.',
            '**Chosen assets:** tick them in the list and choose the labels action in the bulk bar.',
        ]],
        ['p', 'The labels open on a print page. Press **Print** and choose your label printer or paper. Use durable labels for devices that move a lot.'],

        ['h', 'When a label is scanned'],
        ['fields', [
            ['IT staff signed in to ITAM', 'The asset page opens.'],
            ['Anyone else', 'A short form to report a problem with that device opens, without an account. The report becomes a work order. See [[maintenance]].'],
            ['During a physical audit', 'The scanner on the audit page reads the label and records the device as found. See [[audits]].'],
        ]],
        ['p', 'Public fault reporting can be turned off, and the number of reports per hour limited, in [Settings](/admin/settings) › **Service & SLA**.'],
    ],
];
