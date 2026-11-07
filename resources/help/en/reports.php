<?php
return [
    'title' => 'Reports',
    'summary' => 'Three ready-made reports, warranties ending, utilisation and data quality, exportable as CSV, Excel, PDF or JSON.',
    'blocks' => [
        ['h', 'The reports'],
        ['p', 'Open [Reports](/reports) and pick a report at the top. Each report counts only the assets in your locations and leaves out retired and lost devices.'],
        ['fields', [
            ['Warranties ending', 'Every asset whose warranty ends in the next 12 months, grouped by quarter, with the number of devices and their value per quarter. Use it to plan renewals and replacements.'],
            ['Utilisation', 'For each category: how many devices are deployed, in stock, in repair and reserved, and the share in use. Below it, the number and value of assets per department.'],
            ['Data quality', 'How complete the register is (serial number, location, category, warranty, purchase date, manufacturer), a quality score, and identity conflicts: serial numbers, IP addresses, MAC addresses or host names used by more than one asset.'],
        ]],
        ['tip', 'Fix the conflicts listed under **Data quality** first: duplicates usually mean a device was entered twice.'],

        ['h', 'Exporting'],
        ['p', 'With the right to export reports, press **Export** and choose the format:'],
        ['fields', [
            ['CSV', 'For other tools and spreadsheets; keeps the raw values.'],
            ['Excel', 'A ready workbook with readable headings.'],
            ['PDF', 'For printing and sharing. The server needs Microsoft Edge or Google Chrome installed; see [[server]].'],
            ['JSON', 'For scripts and other systems.'],
        ]],
        ['p', 'The asset list has the same **Export** button for the current filters. Every export is recorded in the activity log.'],
    ],
];
