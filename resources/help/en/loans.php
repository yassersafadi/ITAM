<?php
return [
    'title' => 'Loaner bookings',
    'summary' => 'Lend spare devices for a set period: a loaner pool, bookings by staff or employees, approval, hand-over and return.',
    'blocks' => [
        ['h', 'The loaner pool'],
        ['p', 'Only devices in the pool can be booked. In [Loaner bookings](/loans), add in-stock devices that nobody holds to the **Loaner pool**. A device with open bookings cannot be taken out of the pool until they are finished or cancelled.'],

        ['h', 'Booking'],
        ['fields', [
            ['By IT staff', 'Press **New booking**, choose the employee, a type of device or a specific device, the dates and the purpose. A staff booking is approved at once.'],
            ['By an employee', 'In the employee portal, **Book a loaner**. The request waits under **Requests** for approval. See [[portal]].'],
        ]],
        ['p', 'ITAM picks a free device of the type for the dates, and refuses the booking if none is free. The longest loan and how far ahead people may book are set in [Settings](/admin/settings) › **Service & SLA**.'],

        ['h', 'The views'],
        ['fields', [
            ['Calendar', 'Three weeks of the pool, device by device.'],
            ['Requests', 'Employee requests to **Approve** or **Reject**.'],
            ['Approved & out', 'Bookings waiting for hand-over and devices currently out, with overdue ones marked.'],
            ['History', 'Returned, cancelled, rejected and expired bookings.'],
        ]],

        ['h', 'Hand-over and return'],
        ['steps', [
            'On or after the start date, press **Hand over** on the booking, or scan the device’s label at the **Hand-over desk**.',
            'When the device comes back, press **Take back**, or scan it at the desk again.',
        ]],
        ['p', 'While out, the device is deployed to the employee. Back, it returns to stock and to the pool.'],
        ['list', [
            'An approved booking that is never collected expires a few days after its start date.',
            'Overdue loaners are marked under **Approved & out**, and the nightly scheduled task reminds the employees. See [[server]].',
        ]],
    ],
];
