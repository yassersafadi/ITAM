<?php
return [
    'title' => 'Getting started',
    'summary' => 'What ITAM is, how the screen is laid out, the first set-up steps and your own profile.',
    'blocks' => [
        ['h', 'What is ITAM?'],
        ['p', 'ITAM is an open-source register of your organisation’s IT assets: computers, phones, printers, network devices, accessories and software licences. It shows who holds each device, where it is, its warranty and its work orders. Hand-overs are signed on custody receipts, and every change is written to the activity log so you can see who changed what and when.'],

        ['h', 'Signing in'],
        ['steps', [
            'Open the ITAM address in your browser. The **Welcome back** page appears.',
            'Enter your **Email** and **Password**, then press **Sign in**.',
            'If single sign-on is set up, you can press **Sign in with Microsoft** instead.',
            'If you turned on two-factor authentication, enter the 6-digit code from your authenticator app.',
        ]],
        ['tip', 'Forgot your password? Use the link on the sign-in page to get a reset link by e-mail (this needs e-mail set up in [[settings]]), or ask an administrator.'],

        ['h', 'The screen'],
        ['fields', [
            ['Side menu', 'Grouped as on the screen: overview, assets, people and custody, maintenance, reports, reference data and system. You only see what your role allows.'],
            ['Menu button ☰', 'Collapses or opens the side menu. On a phone it opens the menu.'],
            ['Quick search', 'The bar at the top, or `Ctrl K` anywhere. Finds assets, employees, licences and menu pages. See [[search]].'],
            ['Language', 'Switches between English and Arabic; the page direction follows.'],
            ['Theme', 'Light, dark or follow your device.'],
            ['Help ?', 'Opens the part of this guide for the page you are on.'],
            ['Notifications 🔔', 'Fault reports, SLA breaches, loaner requests, low stock and other alerts.'],
            ['Your account menu', 'Opens **My profile** and **Sign out**.'],
        ]],

        ['h', 'First steps for the administrator'],
        ['steps', [
            '**Identity and general settings:** in [Settings](/admin/settings), set the organisation and ITAM names, the logo, theme, time zone, currency and the **Public address** (used in QR labels and e-mail links). See [[settings]].',
            '**Reference data:** add [Locations](/directory/locations), departments in [Organization](/directory/departments), and review [Categories](/directory/categories). See [[reference-data]].',
            '**Employees:** add the people who receive devices in [Employees](/directory/employees). See [[employees]].',
            '**Users:** create accounts for the IT team in [Users & Roles](/admin/users) and give each a role. See [[roles]].',
            '**Assets:** import your devices from Excel or CSV, or add them by hand. See [[asset-import]] and [[assets]].',
            '**Labels:** print QR labels and stick them on the devices. See [[qr-labels]].',
            '**Scheduler and backups:** make sure the nightly scheduled task runs on the server. See [[server]].',
        ]],

        ['h', 'Day to day'],
        ['list', [
            'Start from the [[dashboard]]: **Needs your attention** lists what is waiting, most urgent first.',
            'Hand devices over and take them back from the asset page; ITAM prepares the custody receipt. See [[custody]].',
            'Follow work orders and their SLA times in [Maintenance](/maintenance). See [[maintenance]].',
        ]],

        ['h', 'Your profile'],
        ['p', 'From your account menu › **My profile** you can change your name, language and theme, change your password, and turn on **two-factor authentication**: scan the QR code with an authenticator app, confirm with a code, then keep the **recovery codes** somewhere safe.'],
        ['warn', 'An administrator can make two-factor authentication mandatory for administrators. ITAM then asks you to set it up before anything else.'],

        ['h', 'On a phone'],
        ['p', 'ITAM works in a phone browser and can be added to the home screen. Scanning QR codes with the camera needs a secure HTTPS address. ITAM needs a network connection to work.'],

        ['h', 'About ITAM'],
        ['p', 'ITAM is open source under the AGPL-3.0 licence. The **About ITAM** page in the menu explains this and links to the AIM platform for organisations that need more than ITAM offers.'],
    ],
];
