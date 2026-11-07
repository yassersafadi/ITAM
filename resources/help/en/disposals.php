<?php
return [
    'title' => 'Disposals',
    'summary' => 'Retire an asset from service: record the reason, how it left, when and to whom.',
    'blocks' => [
        ['h', 'Retiring an asset'],
        ['steps', [
            'Open the asset and press **Dispose** (you need the right to delete and dispose of assets).',
            'Read what will change: the current holder, the open work orders and the licence seats.',
            'Choose the **reason** and the **method**, enter the date (not in the future), the recipient if any, and notes.',
            'Confirm.',
        ]],
        ['fields', [
            ['Reasons', 'End of life, Beyond economic repair, Obsolete, Damaged, Other.'],
            ['Methods', 'Certified recycling, Donation, Physical destruction, Returned to vendor.'],
        ]],

        ['h', 'What happens'],
        ['list', [
            'The asset’s status becomes **Retired** and a banner on its page shows the date.',
            'Its custody is closed, its licence seats are freed and its open work orders are cancelled.',
            'A disposal record with its own number (starting with RET- and the year) is created and written to the activity log.',
        ]],

        ['h', 'The disposals list'],
        ['p', '[Disposals](/disposals) lists every retired asset with its tag, serial, reason, method, date, recipient and who recorded it, with the total and the count for this year.'],
        ['warn', 'ITAM records the decision. Keep any supporting documents, such as the recipient’s handover note, in the asset’s **Files**.'],

        ['h', 'Disposal or delete?'],
        ['p', 'Dispose of any real device that leaves the organisation, so its history stays. Delete an asset only when it was entered by mistake. See [[assets]].'],
    ],
];
