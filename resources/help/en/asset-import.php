<?php
return [
    'title' => 'Importing assets',
    'summary' => 'Add or update many assets at once from a CSV file or an Excel workbook.',
    'blocks' => [
        ['h', 'Before you start'],
        ['steps', [
            'Open [Assets](/assets) › **Import**.',
            'Download the template as CSV or Excel. It has the column names and one sample row.',
            'Fill one row per device. Keep the first row as the column names.',
        ]],

        ['h', 'Columns'],
        ['fields', [
            ['name', 'Required for a new asset.'],
            ['tag', 'Leave empty to get the next number. If it matches an existing asset, that asset is updated.'],
            ['serial', 'If there is no tag, an asset with the same serial number is updated.'],
            ['category, location, department', 'By name. A name not listed yet is added to the list.'],
            ['assigned_to', 'The employee’s name. The employee must already exist, otherwise the device is left without a holder. The hand-over is recorded on the asset.'],
            ['status', '`deployed`, `in_stock`, `reserved`, `in_repair`, `retired` or `lost`. Empty means `in_stock` for a new asset.'],
            ['condition_state', '`excellent`, `good`, `fair` or `poor`.'],
            ['criticality', '1 (low) to 4 (mission-critical).'],
            ['manufacturer, model', 'Added to the reference lists if new.'],
            ['Other columns', '`hostname`, `ip_address`, `mac_address`, `os`, `cpu`, `ram_gb`, `storage_gb`, `acquired_at`, `warranty_until`, `value`, `po_number`, `notes`.'],
        ]],
        ['tip', 'Dates can be written as 2025-01-15 or another common format; ITAM stores them as year-month-day.'],

        ['h', 'Importing'],
        ['steps', [
            'Choose the file (.csv or .xlsx) and press the import button.',
            'Read the result: how many assets were created, updated and skipped, and the reason for every skipped line.',
            'Fix the skipped lines in the file and import it again. Lines already imported are simply updated.',
        ]],

        ['h', 'Updating existing assets'],
        ['p', 'When a row matches an asset by tag or serial number, ITAM changes only the cells that are filled in. An empty cell or a missing column keeps the current value. So a file with just `tag` and `location` moves devices without touching anything else.'],

        ['h', 'Good to know'],
        ['list', [
            'CSV files may use commas or semicolons, and should be saved as UTF-8 so Arabic text stays readable.',
            'An export from the asset list in CSV can be edited and imported back.',
            'A user limited to some locations can only import into those locations, and cannot add new locations.',
            'Every import is recorded in the activity log.',
        ]],

        ['h', 'Frequently asked questions'],
        ['faq', [
            ['Excel removed the leading zeros of my tags', 'Format the tag column as text before typing. If an import already dropped them, importing again with the full tags puts them back.'],
            ['A row was skipped as “missing name”', 'New assets need a name. Add it, or add the tag or serial of the existing asset you meant to update.'],
        ]],
    ],
];
