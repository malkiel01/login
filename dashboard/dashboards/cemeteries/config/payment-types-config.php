<?php
// קונפיגורציה מרכזית לסוגי תשלומים

return [
    'payment_types' => [
        1 => [
            'name' => 'עלות קבר',
            'mandatory' => true,
            'icon' => '🏛️'
        ],
        2 => [
            'name' => 'שירותי לוויה',
            'mandatory' => false,
            'icon' => '🕯️'
        ],
        3 => [
            'name' => 'שירותי קבורה',
            'mandatory' => true,
            'icon' => '⚰️'
        ],
        4 => [
            'name' => 'אגרת מצבה',
            'mandatory' => false,
            'icon' => '🪦'
        ],
        5 => [
            'name' => 'בדיקת עומק',
            'mandatory' => false,
            'icon' => '📏'
        ],
        6 => [
            'name' => 'פירוק מצבה',
            'mandatory' => false,
            'icon' => '🔨'
        ],
        7 => [
            'name' => 'הובלה מנתב״ג',
            'mandatory' => false,
            'icon' => '✈️'
        ],
        8 => [
            'name' => 'טהרה',
            'mandatory' => false,
            'icon' => '💧'
        ],
        9 => [
            'name' => 'תכריכים',
            'mandatory' => false,
            'icon' => '🏳️'
        ],
        10 => [
            'name' => 'החלפת שם',
            'mandatory' => false,
            'icon' => '✏️'
        ],
        99 => [
            'name' => 'אחר',
            'mandatory' => false,
            'icon' => '📝'
        ]
    ]
];