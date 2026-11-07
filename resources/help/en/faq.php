<?php
return [
    'title' => 'Frequently asked questions',
    'summary' => 'Quick answers to the questions people ask most often.',
    'blocks' => [
        ['h', 'General'],
        ['faq', [
            ['What does ITAM do?', 'ITAM keeps the register of your IT assets, licences and consumables, who holds what with signed receipts, loaners, work orders, physical audits and disposals. For organisations that need more, the **About ITAM** page links to the AIM platform.'],
            ['Is ITAM free?', 'Yes. ITAM is open source under the AGPL-3.0 licence, with no limit on assets or users.'],
            ['Does ITAM send our data anywhere?', 'No. ITAM runs on your server and sends nothing outside, except the e-mails you set up. Browsers load the interface font from Google Fonts unless you choose the system font in Settings › Identity.'],
            ['Can I use ITAM in Arabic?', 'Yes. Every screen and this guide are in Arabic and English. Switch with the language button at the top.'],
        ]],

        ['h', 'Access'],
        ['faq', [
            ['I cannot see a section in the menu', 'Sections depend on your role. Ask an administrator. See [[roles]].'],
            ['I only see some locations', 'Your account is limited to those locations. An administrator can change it in [Users & Roles](/admin/users).'],
            ['I forgot my password', 'Use the reset link on the sign-in page, or ask an administrator to set a new one.'],
            ['I lost the phone with my authenticator app', 'Sign in with one of your recovery codes, or ask an administrator to reset your two-factor authentication.'],
        ]],

        ['h', 'Assets'],
        ['faq', [
            ['How do I bring in our existing spreadsheet?', 'Import it as CSV or Excel. See [[asset-import]].'],
            ['What is the difference between disposing of and deleting an asset?', 'Disposal retires a real device and keeps its history. Delete is only for records entered by mistake. See [[disposals]].'],
            ['Can I add our own fields, such as a SIM number?', 'Yes, per category. See [[reference-data]].'],
            ['How do I know who held a device last year?', 'Open the asset’s **Custody** tab or **Timeline**.'],
        ]],

        ['h', 'Custody and employees'],
        ['faq', [
            ['The employee is not at the desk to sign', 'Send them the signing link or let them sign in the employee portal. See [[custody]].'],
            ['An employee did not get the portal sign-in e-mail', 'Check that their e-mail is on their employee record and that e-mail sending works in [[settings]].'],
        ]],

        ['h', 'Server'],
        ['faq', [
            ['Nightly backups and reminders do not happen', 'The scheduled task is not running. See [[server]].'],
            ['PDF export says it is unavailable', 'Install Microsoft Edge or Google Chrome on the server. See [[server]].'],
            ['The phone camera does not open when scanning', 'The camera needs ITAM on an HTTPS address.'],
        ]],
    ],
];
