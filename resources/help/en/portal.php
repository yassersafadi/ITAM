<?php
return [
    'title' => 'Employee portal',
    'summary' => 'My IT: where employees see their devices and software, sign custody receipts, report a fault and book a loaner, without a staff account.',
    'blocks' => [
        ['h', 'What the portal is'],
        ['p', 'The employee portal (**My IT**) is a simple page for every employee, at the ITAM address followed by `/portal`. Employees do not need a staff account. They see only their own devices, software and documents.'],

        ['h', 'Signing in'],
        ['steps', [
            'Open the portal address. The sign-in page appears.',
            'Enter your **Work e-mail** and press **E-mail me a sign-in link**. Open the e-mail and press the link within 15 minutes. Each link works once.',
            'Or, if your organisation uses Microsoft sign-in, press **Sign in with Microsoft**.',
        ]],
        ['warn', 'Your e-mail must be recorded on your employee record, and the record must be active. If the link does not arrive, check the junk folder, then ask IT to check your e-mail address.'],

        ['h', 'What you can do'],
        ['fields', [
            ['Home', 'Receipts waiting for your signature, your open fault reports, your loaner bookings, and devices whose warranty ends soon.'],
            ['My devices', 'Every device you hold, with its tag, serial number and warranty, and **My software**: the licences assigned to you. Open a device to see its details and fault history.'],
            ['Documents', 'Custody receipts to sign, and receipts you already signed, which you can view or download as PDF.'],
            ['Report a problem', 'Choose the device, describe the problem and say how urgent it is. IT receives it as a work order and you get a reference such as **WO-25**.'],
            ['Book a loaner', 'Choose the type of device, the dates and the purpose. IT approves the request, then hands the device over.'],
        ]],

        ['h', 'Signing a custody receipt'],
        ['steps', [
            'On the home page, open the receipt under **Receipts waiting for your signature** and press **Sign now**.',
            'Read the items and the terms.',
            'Type your name, sign in the box with a finger, pen or mouse, and confirm.',
        ]],
        ['p', 'Once signed, the receipt cannot be changed. You can download it from **Documents**.'],

        ['h', 'Reporting a fault'],
        ['p', 'Press **Report a problem**, pick the device and describe what happens. The urgency sets the priority: **I cannot work** is high, **Works, but badly** is medium, **Minor issue** is low. You can follow the report on the home page.'],
        ['tip', 'For a device you do not hold, such as a shared printer, scan the QR label on it instead. See [[qr-labels]].'],

        ['h', 'For IT staff'],
        ['list', [
            'Portal e-mail links need e-mail sending set up in [[settings]]; Microsoft sign-in needs single sign-on set up there too.',
            'Portal fault reports appear in [[maintenance]] marked as reports; loaner requests appear under **Requests** in [[loans]].',
            'The menu item **Employee portal** opens the portal for you to see what employees see.',
        ]],
    ],
];
