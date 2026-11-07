<?php
return [
    'title' => 'Software licences',
    'summary' => 'Record the software your organisation owns, how many seats it has, and which devices or employees use them.',
    'blocks' => [
        ['h', 'The licence list'],
        ['p', 'Open [Software Licenses](/licenses). Each licence shows its seats in use against seats bought, and its expiry date. Filter the list to see licences that are **over-allocated**, **expiring** within 45 days or **expired**.'],

        ['h', 'Adding a licence'],
        ['steps', [
            'Press the add button on the licence list.',
            'Fill in the name, publisher, edition, licence type, the product key if you want to keep it, the number of seats, the expiry date and notes.',
            'Save.',
        ]],
        ['fields', [
            ['Per user', 'One seat per person.'],
            ['Per device', 'One seat per computer.'],
            ['Subscription', 'Paid by period; set the expiry date to the renewal date.'],
            ['Site license', 'Covers a whole site; give it a seat count that suits you.'],
            ['Concurrent', 'A number of people at the same time.'],
        ]],

        ['h', 'Assigning seats'],
        ['steps', [
            'Open the licence.',
            'Choose a device, an employee, or both, and assign the seat.',
            'To free a seat, remove it from the list of seats.',
        ]],
        ['list', [
            'ITAM refuses a new seat when all seats are in use. Raise the seat count first if you bought more.',
            'Seats assigned to a device appear on the asset page under **Software**; seats assigned to an employee appear on the employee page and in the employee portal.',
            'Retiring a device frees its seats automatically. See [[disposals]].',
        ]],
        ['tip', 'A licence shows as over-allocated when you lower its seat count below the seats in use. The dashboard lists it until you fix it.'],

        ['h', 'Good to know'],
        ['list', [
            'Every licence change and seat assignment is recorded in the activity log.',
            'The dashboard chart **Seat use per license** shows the busiest licences.',
        ]],
    ],
];
