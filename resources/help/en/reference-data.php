<?php
return [
    'title' => 'Reference data',
    'summary' => 'The lists the register is built on: locations, departments, categories (with their extra fields), manufacturers and models.',
    'blocks' => [
        ['h', 'Who can change them'],
        ['p', 'Everyone can read these lists. Adding, editing and deleting entries needs the **Lists and categories** permission. Every change is recorded in the activity log.'],

        ['h', 'Locations'],
        ['p', 'In [Locations](/directory/locations), add each site with its city, building, floor and room. Locations are used on assets, employees, audits and stock. An administrator can limit a user to some locations; see [[roles]].'],

        ['h', 'Departments'],
        ['p', 'In [Organization](/directory/departments), build your structure in levels: **Executive office**, **Sector**, **Department** and **Section**. Each unit can sit under a unit of a higher level, and the list shows how many assets each unit and everything under it holds. A unit with units under it cannot be deleted.'],

        ['h', 'Categories'],
        ['p', 'In [Categories](/directory/categories), each category has a name, a short **code** used in new asset tags (for example LAP for laptops), an icon and a few reference values such as the expected lifespan.'],
        ['h', 'Extra fields per category'],
        ['steps', [
            'Open a category to edit it.',
            'Under **Additional details**, add a field: its label (for example “SIM number”), its type (text, number, date or a list of choices) and whether it is required.',
            'The field now appears on the form and page of every asset in that category, and can be used as a filter in the asset list.',
        ]],
        ['warn', 'Deleting an extra field deletes its values on every asset.'],

        ['h', 'Manufacturers and models'],
        ['p', 'In [Manufacturers](/directory/manufacturers) and [Models](/directory/models), keep one clean list so that “Dell”, “DELL” and “Dell Inc.” do not become three makers.'],
        ['list', [
            'A manufacturer has a website and a support phone; a model belongs to a manufacturer and can have a category, specifications and an image link.',
            'Names are compared without case, so the same name cannot be added twice.',
            'Renaming an entry renames it on every asset. Renaming it to a name already listed merges the two entries.',
            'An entry still used on assets cannot be deleted.',
            'Importing assets adds new manufacturers and models to the lists for you. See [[asset-import]].',
        ]],
        ['tip', 'On the asset form, users without the permission to edit lists pick the manufacturer and model from these lists; others can type a new name, which is added.'],
    ],
];
