<?php
return [
    'title' => 'Settings',
    'summary' => 'Everything an administrator can set from the screen: identity and look, general options, security, single sign-on, service and SLA, e-mail notifications, backups and API tokens.',
    'blocks' => [
        ['p', 'Open [Settings](/admin/settings) (needs the **Settings** permission). The home page groups the settings in cards and has a search box. Each page has **Save**, and most have a button to restore the defaults. Every change is recorded in the activity log.'],

        ['h', 'Identity'],
        ['list', [
            '**Theme:** apply a ready theme, or build your own colours and font with a live preview. Themes can be exported and imported as files.',
            '**Names:** the organisation and the name shown for ITAM, in English and Arabic.',
            '**Logos:** logo mark, full logo, full logo for dark mode and browser icon (PNG, JPG or WebP, up to 2 MB).',
            '**Sign-in page:** layout, background (theme, photo or plain), a notice and a welcome line.',
            '**Branded e-mails:** send e-mails with your logo and colours.',
        ]],

        ['h', 'Footer'],
        ['p', 'The rights notice at the bottom of every page, and links to your website, contact e-mail and social media accounts.'],

        ['h', 'General'],
        ['fields', [
            ['Default language', 'For new users and the sign-in page; you can also follow the browser’s language.'],
            ['Time zone and date format', 'How dates and times are shown.'],
            ['Asset tag prefix', 'New tags look like `IT-LAP-0001` (prefix, category code, number). Leave it empty for plain numbers.'],
            ['Public address', 'The full address people use to reach ITAM, such as `https://itam.example.com`. Used in QR labels, signing links and e-mails.'],
            ['Currency', 'Used for asset values and costs.'],
        ]],

        ['h', 'Security & access'],
        ['fields', [
            ['Minimum password length', 'From 8 to 64 characters.'],
            ['Sign out after inactivity', 'In minutes; 0 keeps people signed in until they close the browser.'],
            ['Failed sign-ins before lockout / Lockout duration', 'Protects accounts against password guessing.'],
            ['Require two-factor authentication for administrators', 'Administrators must set it up before using ITAM.'],
        ]],

        ['h', 'Single sign-on'],
        ['steps', [
            'In the Microsoft Entra admin center, register a new application (single tenant).',
            'Add the Web redirect address shown on the settings page.',
            'Create a client secret and copy its value into ITAM.',
            'Copy the Directory (tenant) ID and Application (client) ID, save, then tick **Enable “Sign in with Microsoft”**.',
        ]],
        ['p', 'You can also limit sign-in to some e-mail domains, create accounts automatically on first sign-in, and choose the role new accounts get. Employees use the same sign-in for the employee portal.'],

        ['h', 'Service & SLA'],
        ['fields', [
            ['Fault reports from QR labels', 'Turn public reporting on or off, and limit reports per hour from one address.'],
            ['SLA targets', 'Hours allowed for corrective work at each priority: critical, high, medium, low.'],
            ['Custody terms', 'Your own text for issue receipts; empty uses the default.'],
            ['Remote signing link valid for', 'Days a signing link works.'],
            ['Longest loan / Book up to', 'Limits for loaner bookings.'],
        ]],

        ['h', 'Notifications'],
        ['p', 'ITAM sends e-mail through your SMTP server. Fill in the server, port, encryption, user name, password and sender address, list the recipients of team alerts, and choose whether to send a weekly summary. Press **Send a test** and check the list of recent messages below to confirm delivery.'],
        ['tip', 'Portal sign-in links, signing links and password resets also need e-mail to work.'],

        ['h', 'Backup'],
        ['list', [
            'Press **Back up now** for a copy of the database; download or delete copies from the list.',
            'Tick **Create a backup every night** and choose how many copies to keep. The nightly backup needs the scheduled task; see [[server]].',
            'For MySQL/MariaDB the server needs the `mysqldump` tool; otherwise use your database host’s backups.',
            'The page explains how to restore a copy.',
        ]],
        ['warn', 'Backups stay on the same server. Download a copy regularly and keep it somewhere else.'],

        ['h', 'Integrations & API'],
        ['p', 'Create an API token for another system to read the asset register. The token is shown once; copy it then. Revoke a token when it is no longer needed.'],
        ['fields', [
            ['GET /api/v1/assets', 'The asset list. Accepts the same filters as the asset list, for example `?status=deployed`.'],
            ['GET /api/v1/assets/{tag}', 'One asset by its tag.'],
            ['Authentication', 'Send the header `Authorization: Bearer <token>`.'],
        ]],
        ['p', 'The API is read-only.'],
    ],
];
