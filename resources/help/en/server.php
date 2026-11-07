<?php
return [
    'title' => 'Installation and server',
    'summary' => 'Installing ITAM with the wizard, the scheduled task, PDF output, backups and updating to a new release.',
    'blocks' => [
        ['h', 'What ITAM needs'],
        ['list', [
            'A web server (Apache, Nginx or IIS) with PHP 8 and the extensions the wizard checks: `pdo_sqlite`, `openssl`, `curl`, `zip`, `mbstring`, `fileinfo` and `sodium`. `pdo_mysql` is needed for MySQL/MariaDB; `intl` and `gd` are recommended.',
            'The web server’s document root pointing to the `public` folder, with all requests sent to `public/index.php` (an `.htaccess` file is included for Apache).',
            'Write access for PHP to `storage/`, `config/` and `public/assets/uploads/`.',
            'An HTTPS address, so phone cameras can scan QR codes.',
        ]],

        ['h', 'The installation wizard'],
        ['p', 'Open the ITAM address in a browser. Until ITAM is installed, the wizard opens by itself:'],
        ['steps', [
            '**Language:** English or Arabic.',
            '**Requirements:** PHP version, extensions and writable folders. Fix anything marked missing, then continue.',
            '**Database:** SQLite (the default, nothing to set up; the file is kept in `storage/`) or MySQL/MariaDB (host, port, database name, user and password; the connection is tested).',
            '**Organisation:** organisation name, ITAM’s name in English and Arabic, default language, time zone and currency.',
            '**Administrator:** your name, e-mail and a strong password. There is no default account.',
            '**Finish:** review, choose whether to load demo data, and install.',
        ]],
        ['p', 'After installation the wizard locks itself. Sign in with the administrator account and follow [[getting-started]].'],
        ['tip', 'Load demo data only on a test installation: it fills the register with sample assets, employees and work orders.'],

        ['h', 'The scheduled task'],
        ['p', 'Some work happens on a schedule: SLA escalation, sending queued e-mails, the nightly backup, the weekly summary, reminders for unsigned receipts and overdue loaners, and expiring uncollected bookings. Run `scripts/scheduler.php` with PHP every night:'],
        ['fields', [
            ['Linux (cron)', '`15 2 * * * php /path/to/itam/scripts/scheduler.php`'],
            ['Windows (Task Scheduler)', 'A daily task that runs `php C:\path\to\itam\scripts\scheduler.php`.'],
        ]],
        ['p', 'For faster SLA escalation and e-mail delivery, also run it every 15 minutes with `--quick`.'],

        ['h', 'PDF files'],
        ['p', 'PDF exports and custody receipts as PDF are produced by a browser on the server: Microsoft Edge or Google Chrome (Chromium on Linux). Without one, PDF is unavailable but every receipt can still be printed from its page.'],

        ['h', 'Backups'],
        ['p', 'Set nightly backups in [[settings]] › **Backup**. For SQLite, the whole database is one file in `storage/`; also copy the `storage/` folder (uploaded files, signatures) and `config/local.php`. For MySQL/MariaDB, the server needs `mysqldump`, or use your database host’s backups.'],

        ['h', 'Updating'],
        ['steps', [
            'Take a backup and download it.',
            'Download the new ITAM release.',
            'Replace the program files, keeping `config/local.php`, `storage/` and `public/assets/uploads/`.',
            'Open ITAM in the browser. Database changes are applied automatically on the first request.',
        ]],
        ['warn', 'ITAM never updates itself and sends no data anywhere. Updates are always a step you take.'],
    ],
];
