<?php
return [
    'areaGrave' => [
        'searchableFields' => [
            [
                'name' => 'areaGraveNameHe',
                'label' => 'שם אחוזת קבר',
                'table' => 'areaGraves',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'coordinates',
                'label' => 'קואורדינטות',
                'table' => 'areaGraves',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'graveType',
                'label' => 'סוג קבר',
                'table' => 'areaGraves',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'הכל'],
                    ['value' => '1', 'label' => 'שדה'],
                    ['value' => '2', 'label' => 'רוויה'],
                    ['value' => '3', 'label' => 'סנהדרין']
                ],
                'matchType' => ['exact']
            ],
            [
                'name' => 'lineNameHe',
                'label' => 'שם שורה',
                'table' => 'areaGraves_view',
                'type' => 'text',
                'matchType' => ['fuzzy']
            ],
            [
                'name' => 'plotNameHe',
                'label' => 'שם חלקה',
                'table' => 'areaGraves_view',
                'type' => 'text',
                'matchType' => ['fuzzy']
            ],
            [
                'name' => 'blockNameHe',
                'label' => 'שם גוש',
                'table' => 'areaGraves_view',
                'type' => 'text',
                'matchType' => ['fuzzy']
            ],
            [
                'name' => 'cemeteryNameHe',
                'label' => 'שם בית עלמין',
                'table' => 'areaGraves_view',
                'type' => 'text',
                'matchType' => ['fuzzy']
            ],
            [
                'name' => 'createDate',
                'label' => 'תאריך יצירה',
                'table' => 'areaGraves',
                'type' => 'date',
                'matchType' => ['exact', 'before', 'after', 'between']
            ]
        ]
    ],
    
    // ⭐ אפשר להוסיף entities נוספים בעתיד
    'cemetery' => [],
    'block' => [],
    // ...
];