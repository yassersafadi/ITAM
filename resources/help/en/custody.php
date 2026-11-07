<?php
return [
    'title' => 'Custody receipts',
    'summary' => 'Hand devices to employees and take them back with a signed receipt, signed on screen or through a link.',
    'blocks' => [
        ['h', 'Checking a device out'],
        ['steps', [
            'Open the asset and press **Check out**.',
            'Choose the employee. Add an expected return date for a temporary hand-over, and notes if needed.',
            'Keep the signature option ticked to prepare a custody receipt, then confirm.',
            'The signing screen opens. The employee reads the items and terms, types their name and signs with a finger, pen or mouse.',
        ]],
        ['p', 'The asset becomes **Deployed**, takes the employee’s department and location, and the hand-over appears on its **Custody** tab and timeline.'],

        ['h', 'Signing later or remotely'],
        ['p', 'If the employee is not in front of you, the signing screen shows a link and a QR code. Send the link or let them scan the code; they sign on their own phone without an account. They can also sign from the employee portal. See [[portal]].'],
        ['list', [
            'The link is valid for the number of days set in [Settings](/admin/settings) › **Service & SLA** (7 by default).',
            'To send a new link, open the receipt and renew it; the old link stops working.',
        ]],

        ['h', 'Checking a device in'],
        ['steps', [
            'Open the asset and press **Check in**.',
            'Choose the condition the device came back in.',
            'Keep the return-signature option ticked to prepare a return receipt, and let the employee sign.',
        ]],
        ['p', 'The asset goes back to **In stock** with the condition you chose.'],

        ['h', 'The receipts list'],
        ['p', '[Custody receipts](/custody) lists every receipt with its number, type (issue or return), employee and status. Filter it to see receipts **awaiting signature**, **late** (unsigned for more than a few days) or **signed**.'],
        ['list', [
            'Open a receipt to view or print it, with the signature, date and the IT staff member who handled it.',
            'While a receipt is unsigned you can remove an item added by mistake, or cancel the receipt.',
            'A signed receipt cannot be changed. To correct it, check the device in or out again.',
        ]],
        ['tip', 'Write your own receipt terms in [Settings](/admin/settings) › **Service & SLA** › **Custody terms**. Leave it empty to use the default text.'],

        ['h', 'Frequently asked questions'],
        ['faq', [
            ['The employee says the link does not work', 'It was used already or has expired. Open the receipt and renew the link.'],
            ['Can I hand over a device without a receipt?', 'Yes: untick the signature option. The hand-over is still recorded on the asset and in the activity log.'],
        ]],
    ],
];
