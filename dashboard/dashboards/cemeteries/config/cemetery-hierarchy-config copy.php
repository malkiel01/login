<?php
// dashboard/dashboards/cemeteries/config/cemetery-hierarchy-config.php
// קונפיגורציה מרכזית לכל היררכיית בתי העלמין

return [
    // ========================================
    // הגדרות לבתי עלמין
    // ========================================
    'cemetery' => [
        'table' => 'cemeteries',
        'title' => 'בתי עלמין',
        'singular' => 'בית עלמין',
        'icon' => '🏛️',
        'primaryKey' => 'unicId',
        'parentKey' => null,
        
        // שדות לשאילתות SELECT
        'queryFields' => [
            'id',
            'unicId',
            'cemeteryNameHe',
            'cemeteryNameEn',
            'cemeteryCode',
            'nationalInsuranceCode',
            'address',
            'coordinates',
            'contactName',
            'contactPhoneName',
            'documents',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        // מיפוי שדות לתצוגה
        'displayFields' => [
            'name' => 'cemeteryNameHe',
            'nameEn' => 'cemeteryNameEn',
            'code' => 'cemeteryCode',
            'address' => 'address',
            'contact' => 'contactName',
            'phone' => 'contactPhoneName',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        // הגדרות טבלה
        'table_columns' => [
            [
                'field' => 'index',
                'title' => 'מס׳',
                'width' => '6px',
                'type' => 'index',
                'sortable' => false
            ],
            [
                'field' => 'cemeteryNameHe',
                'title' => 'שם בית עלמין',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'show_secondary' => 'cemeteryNameEn',
                'required' => true
            ],
            [
                'field' => 'cemeteryCode',
                'title' => 'קוד',
                'type' => 'text',
                'width' => '100px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'address',
                'title' => 'כתובת',
                'type' => 'text',
                'show_secondary' => 'coordinates',
                'icon_secondary' => '📍'
            ],
            [
                'field' => 'contactName',
                'title' => 'איש קשר',
                'type' => 'text',
                'show_secondary' => 'contactPhoneName',
                'icon_secondary' => '📞'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px',
            //     'badges' => [
            //         1 => ['text' => 'פעיל', 'class' => 'badge-success'],
            //         0 => ['text' => 'לא פעיל', 'class' => 'badge-danger']
            //     ]
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px',
                'actions' => ['edit', 'delete', 'enter']
            ]
        ],
        
        // שדות לטופס הוספה/עריכה
        'form_fields' => [
            [
                'name' => 'cemeteryNameHe',
                'label' => 'שם בעברית',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'הזן שם בית עלמין בעברית',
                'validation' => ['required', 'minLength:2'],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'cemeteryNameEn',
                'label' => 'שם באנגלית',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Enter cemetery name in English',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'cemeteryCode',
                'label' => 'קוד בית עלמין',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'קוד ייחודי',
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            [
                'name' => 'nationalInsuranceCode',
                'label' => 'קוד ביטוח לאומי',
                'type' => 'text',
                'required' => false,
                'permissions' => ['admin', 'cemetery_manager']
            ],
            [
                'name' => 'address',
                'label' => 'כתובת',
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => 'הזן כתובת מלאה',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'coordinates',
                'label' => 'קואורדינטות',
                'type' => 'text',
                'placeholder' => 'lat,lng',
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            [
                'name' => 'contactName',
                'label' => 'שם איש קשר',
                'type' => 'text',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'contactPhoneName',
                'label' => 'טלפון איש קשר',
                'type' => 'tel',
                'placeholder' => '050-0000000',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            // [
            //     'name' => 'isActive',
            //     'label' => 'סטטוס',
            //     'type' => 'select',
            //     'options' => [
            //         1 => 'פעיל',
            //         0 => 'לא פעיל'
            //     ],
            //     'default' => 1,
            //     'permissions' => ['admin', 'cemetery_manager']
            // ]
        ]
    ],
    
    // ========================================
    // הגדרות לגושים
    // ========================================
    'block' => [
        'table' => 'blocks',
        'title' => 'גושים',
        'singular' => 'גוש',
        'icon' => '📦',
        'primaryKey' => 'unicId',
        'parentKey' => 'cemeteryId',
        
        'queryFields' => [
            'id',
            'unicId',
            'blockNameHe',
            'blockNameEn',
            'blockCode',
            'blockLocation',
            'nationalInsuranceCode',
            'coordinates',
            'comments',
            'documentsList',
            'cemeteryId',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'blockNameHe',
            'nameEn' => 'blockNameEn',
            'code' => 'blockCode',
            'location' => 'blockLocation',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => 'מס׳',
                'width' => '60px',
                'type' => 'index'
            ],
            [
                'field' => 'blockNameHe',
                'title' => 'שם גוש',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'show_secondary' => 'blockNameEn',
                'required' => true
            ],
            [
                'field' => 'blockCode',
                'title' => 'קוד',
                'type' => 'text',
                'width' => '100px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'blockLocation',
                'title' => 'מיקום',
                'type' => 'text'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px',
            //     'badges' => [
            //         1 => ['text' => 'פעיל', 'class' => 'badge-success'],
            //         0 => ['text' => 'לא פעיל', 'class' => 'badge-danger']
            //     ]
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px',
                'actions' => ['edit', 'delete', 'enter']
            ]
        ],
        
        'form_fields' => [
            [
                'name' => 'blockNameHe',
                'label' => 'שם גוש בעברית',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'הזן שם גוש',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'blockNameEn',
                'label' => 'שם גוש באנגלית',
                'type' => 'text',
                'placeholder' => 'Enter block name',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'blockCode',
                'label' => 'קוד גוש',
                'type' => 'text',
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            [
                'name' => 'blockLocation',
                'label' => 'מיקום',
                'type' => 'text',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'nationalInsuranceCode',
                'label' => 'קוד ביטוח לאומי',
                'type' => 'text',
                'permissions' => ['admin', 'cemetery_manager']
            ],
            [
                'name' => 'coordinates',
                'label' => 'קואורדינטות',
                'type' => 'text',
                'placeholder' => 'lat,lng',
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'type' => 'textarea',
                'rows' => 3,
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            // [
            //     'name' => 'isActive',
            //     'label' => 'סטטוס',
            //     'type' => 'select',
            //     'options' => [
            //         1 => 'פעיל',
            //         0 => 'לא פעיל'
            //     ],
            //     'default' => 1,
            //     'permissions' => ['admin', 'cemetery_manager']
            // ]
        ]
    ],
    
    // ========================================
    // הגדרות לחלקות
    // ========================================
    'plot' => [
        'table' => 'plots',
        'title' => 'חלקות',
        'singular' => 'חלקה',
        'icon' => '📋',
        'primaryKey' => 'unicId',
        'parentKey' => 'blockId',
        
        'queryFields' => [
            'id',
            'unicId',
            'plotNameHe',
            'plotNameEn',
            'plotCode',
            'plotLocation',
            'nationalInsuranceCode',
            'coordinates',
            'comments',
            'documentsList',
            'blockId',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'plotNameHe',
            'nameEn' => 'plotNameEn',
            'code' => 'plotCode',
            'location' => 'plotLocation',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => 'מס׳',
                'width' => '60px',
                'type' => 'index'
            ],
            [
                'field' => 'plotNameHe',
                'title' => 'שם חלקה',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'show_secondary' => 'plotNameEn',
                'required' => true
            ],
            [
                'field' => 'plotCode',
                'title' => 'קוד',
                'type' => 'text',
                'width' => '100px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'plotLocation',
                'title' => 'מיקום',
                'type' => 'text'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px'
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px'
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px'
            ]
        ],
        
        'form_fields' => [
            [
                'name' => 'plotNameHe',
                'label' => 'שם חלקה בעברית',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'plotNameEn',
                'label' => 'שם חלקה באנגלית',
                'type' => 'text'
            ],
            [
                'name' => 'plotCode',
                'label' => 'קוד חלקה',
                'type' => 'text'
            ],
            [
                'name' => 'plotLocation',
                'label' => 'מיקום',
                'type' => 'text'
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'type' => 'textarea',
                'rows' => 3
            ]
        ]
    ],
    
    // ========================================
    // הגדרות לשורות
    // ========================================
    'row' => [
        'table' => 'rows',
        'title' => 'שורות',
        'singular' => 'שורה',
        'icon' => '📏',
        'primaryKey' => 'unicId',
        'parentKey' => 'plotId',
        
        'queryFields' => [
            'id',
            'unicId',
            'lineNameHe',
            'lineNameEn',
            'lineLocation',
            'serialNumber',
            'comments',
            'documentsList',
            'plotId',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'lineNameHe',
            'nameEn' => 'lineNameEn',
            'serial' => 'serialNumber',
            'location' => 'lineLocation',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'serialNumber',
                'title' => 'מס׳ סידורי',
                'width' => '100px',
                'type' => 'number',
                'sortable' => true
            ],
            [
                'field' => 'lineNameHe',
                'title' => 'שם שורה',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'lineLocation',
                'title' => 'מיקום',
                'type' => 'text'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px'
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px'
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px'
            ]
        ],
        
        'form_fields' => [
            [
                'name' => 'lineNameHe',
                'label' => 'שם שורה בעברית',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'lineNameEn',
                'label' => 'שם שורה באנגלית',
                'type' => 'text'
            ],
            [
                'name' => 'serialNumber',
                'label' => 'מספר סידורי',
                'type' => 'number',
                'required' => true
            ],
            [
                'name' => 'lineLocation',
                'label' => 'מיקום',
                'type' => 'text'
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'type' => 'textarea'
            ]
        ]
    ],
    
    // ========================================
    // הגדרות לאחוזות קבר
    // ========================================
    'area_grave' => [
        'table' => 'areaGraves',
        'title' => 'אחוזות קבר',
        'singular' => 'אחוזת קבר',
        'icon' => '🏘️',
        'primaryKey' => 'unicId',
        'parentKey' => 'lineId',
        
        'queryFields' => [
            'id',
            'unicId',
            'areaGraveNameHe',
            'coordinates',
            'gravesList',
            'graveType',
            'lineId',
            'comments',
            'documentsList',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'areaGraveNameHe',
            'type' => 'graveType',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => 'מס׳',
                'width' => '60px',
                'type' => 'index'
            ],
            [
                'field' => 'areaGraveNameHe',
                'title' => 'שם אחוזת קבר',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'graveType',
                'title' => 'סוג',
                'type' => 'select',
                'width' => '120px',
                'options' => [
                    1 => 'שדה',
                    2 => 'רוויה',
                    3 => 'סנהדרין'
                ]
            ],
            [
                'field' => 'gravesList',
                'title' => 'מספר קברים',
                'type' => 'text',
                'width' => '100px'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px'
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px'
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px'
            ]
        ],
        
        'form_fields' => [
            [
                'name' => 'areaGraveNameHe',
                'label' => 'שם אחוזת קבר',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'lineId',
                'label' => 'שורה',
                'type' => 'select',
                'required' => true,
                'placeholder' => 'בחר שורה',
                'validation' => [
                    'required' => true,
                    'message' => 'חובה לבחור שורה'
                ]
            ],
            [
                'name' => 'graveType',
                'label' => 'סוג אחוזת קבר',
                'type' => 'select',
                'options' => [
                    1 => 'שדה',
                    2 => 'רוויה',
                    3 => 'סנהדרין'
                ],
                'required' => true
            ],
            [
                'name' => 'coordinates',
                'label' => 'קואורדינטות',
                'type' => 'text'
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'type' => 'textarea'
            ]
        ]
    ],
    
    // ========================================
    // הגדרות לקברים
    // ========================================
    'grave' => [
        'table' => 'graves',
        'title' => 'קברים',
        'singular' => 'קבר',
        'icon' => '🪦',
        'primaryKey' => 'unicId',
        'parentKey' => 'areaGraveId',
        
        'queryFields' => [
            'id',
            'unicId',
            'graveNameHe',
            'plotType',
            'graveStatus',
            'graveLocation',
            'constructionCost',
            'isSmallGrave',
            'areaGraveId',
            'comments',
            'documentsList',
            'createDate',
            'updateDate',
            'saveDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'graveNameHe',
            'status' => 'graveStatus',
            'type' => 'plotType',
            'location' => 'graveLocation',
            'created' => 'createDate',
            'active' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'graveNameHe',
                'title' => 'מספר קבר',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'plotType',
                'title' => 'סוג חלקה',
                'type' => 'select',
                'width' => '120px',
                'options' => [
                    1 => 'פטורה',
                    2 => 'חריגה',
                    3 => 'סגורה'
                ],
                'badges' => [
                    1 => ['text' => 'פטורה', 'class' => 'badge-success'],
                    2 => ['text' => 'חריגה', 'class' => 'badge-warning'],
                    3 => ['text' => 'סגורה', 'class' => 'badge-danger']
                ]
            ],
            [
                'field' => 'graveStatus',
                'title' => 'סטטוס',
                'type' => 'select',
                'width' => '120px',
                'options' => [
                    1 => 'פנוי',
                    2 => 'נרכש',
                    3 => 'קבור',
                    4 => 'שמור'
                ],
                'badges' => [
                    1 => ['text' => 'פנוי', 'class' => 'badge-success'],
                    2 => ['text' => 'נרכש', 'class' => 'badge-info'],
                    3 => ['text' => 'קבור', 'class' => 'badge-secondary'],
                    4 => ['text' => 'שמור', 'class' => 'badge-warning']
                ]
            ],
            [
                'field' => 'graveLocation',
                'title' => 'מיקום',
                'type' => 'number',
                'width' => '100px'
            ],
            [
                'field' => 'isSmallGrave',
                'title' => 'קבר קטן',
                'type' => 'boolean',
                'width' => '80px',
                'icons' => [
                    1 => '✓',
                    0 => '-'
                ]
            ],
            [
                'field' => 'constructionCost',
                'title' => 'עלות בנייה',
                'type' => 'currency',
                'width' => '120px',
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px'
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px'
            ]
        ],
        
        'form_fields' => [
            [
                'name' => 'graveNameHe',
                'label' => 'מספר קבר',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'הזן מספר קבר'
            ],
            [
                'name' => 'plotType',
                'label' => 'סוג חלקה',
                'type' => 'select',
                'options' => [
                    1 => 'פטורה',
                    2 => 'חריגה',
                    3 => 'סגורה'
                ],
                'required' => true
            ],
            [
                'name' => 'graveStatus',
                'label' => 'סטטוס קבר',
                'type' => 'select',
                'options' => [
                    1 => 'פנוי',
                    2 => 'נרכש',
                    3 => 'קבור',
                    4 => 'שמור'
                ],
                'default' => 1,
                'required' => true
            ],
            [
                'name' => 'graveLocation',
                'label' => 'מיקום בשורה',
                'type' => 'number',
                'min' => 1
            ],
            [
                'name' => 'isSmallGrave',
                'label' => 'קבר קטן',
                'type' => 'checkbox',
                'default' => 0
            ],
            [
                'name' => 'constructionCost',
                'label' => 'עלות בנייה',
                'type' => 'number',
                'step' => '0.01',
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'type' => 'textarea',
                'rows' => 3
            ]
        ]
    ],

    // ========================================
    // הגדרות תושבות
    // ========================================
    'residency' => [
        'table' => 'residency_settings',
        'title' => 'הגדרות תושבות',
        'singular' => 'הגדרת תושבות',
        'icon' => '🏠',
        'primaryKey' => 'unicId',
        'parentKey' => null, // אין הורה - זו רשומה עצמאית
        
        'queryFields' => [
            'id',
            'unicId',
            'residencyName',
            'countryId',
            'cityId',
            'residencyType',
            'description',
            'countryNameHe',
            'cityNameHe',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'residencyName',
            'type' => 'residencyType',
            'country' => 'countryNameHe',
            'city' => 'cityNameHe',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => '#',
                'type' => 'index',
                'width' => '50px'
            ],
            [
                'field' => 'residencyName',
                'title' => 'שם הגדרה',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'residencyType',
                'title' => 'סוג תושבות',
                'type' => 'badge',
                'width' => '150px',
                'badges' => [
                    'jerusalem_area' => ['text' => 'ירושלים והסביבה', 'class' => 'badge-primary'],
                    'israel' => ['text' => 'ישראל', 'class' => 'badge-info'],
                    'abroad' => ['text' => 'חו״ל', 'class' => 'badge-warning']
                ]
            ],
            [
                'field' => 'countryNameHe',
                'title' => 'מדינה',
                'type' => 'text',
                'width' => '120px'
            ],
            [
                'field' => 'cityNameHe',
                'title' => 'עיר',
                'type' => 'text',
                'width' => '120px'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px',
            //     'badges' => [
            //         1 => ['text' => 'פעיל', 'class' => 'badge-success'],
            //         0 => ['text' => 'לא פעיל', 'class' => 'badge-danger']
            //     ]
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px',
                'actions' => ['view', 'edit', 'delete']
            ]
        ],
        
        // שדות לטופס הוספה/עריכה
        'form_fields' => [
            [
                'name' => 'residencyName',
                'label' => 'שם הגדרת תושבות',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'הכנס שם להגדרת התושבות',
                'validation' => ['required', 'minLength:2'],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'residencyType',
                'label' => 'סוג תושבות',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'jerusalem_area' => 'תושבי ירושלים והסביבה',
                    'israel' => 'תושבי ישראל',
                    'abroad' => 'תושבי חו״ל'
                ],
                'validation' => ['required'],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'countryId',
                'label' => 'מדינה',
                'type' => 'select_search',
                'required' => false,
                'dataSource' => [
                    'table' => 'countries',
                    'valueField' => 'unicId',
                    'displayField' => 'countryNameHe',
                    'searchFields' => ['countryNameHe', 'countryNameEn'],
                    'orderBy' => 'countryNameHe ASC'
                ],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'cityId',
                'label' => 'עיר',
                'type' => 'select_search',
                'required' => false,
                'dataSource' => [
                    'table' => 'cities',
                    'valueField' => 'unicId',
                    'displayField' => 'cityNameHe',
                    'searchFields' => ['cityNameHe', 'cityNameEn'],
                    'orderBy' => 'cityNameHe ASC',
                    'dependsOn' => 'countryId',
                    'dependsOnField' => 'countryId'
                ],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'description',
                'label' => 'תיאור',
                'type' => 'textarea',
                'required' => false,
                'rows' => 4,
                'placeholder' => 'תיאור ההגדרה (אופציונלי)',
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ]
        ],
        
        // הגדרות נוספות
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/residency-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],
        
        // הרשאות ספציפיות לסוג זה
        'permissions' => [
            'view' => ['admin', 'cemetery_manager', 'manager', 'editor', 'viewer'],
            'create' => ['admin', 'cemetery_manager', 'manager'],
            'edit' => ['admin', 'cemetery_manager', 'manager'],
            'delete' => ['admin', 'cemetery_manager']
        ]
    ],

    // ========================================
    // הגדרות מדינות
    // ========================================

    'country' => [
        'table' => 'countries',
        'title' => 'מדינות',
        'singular' => 'מדינה',
        'icon' => '🌍',
        'primaryKey' => 'unicId',
        'parentKey' => null, // אין הורה - זו רשומה עצמאית
        
        'queryFields' => [
            'id',
            'unicId',
            'countryNameHe',
            'countryNameEn',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'countryNameHe',
            'nameEn' => 'countryNameEn',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => '#',
                'type' => 'index',
                'width' => '50px'
            ],
            [
                'field' => 'countryNameHe',
                'title' => 'שם בעברית',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'countryNameEn',
                'title' => 'שם באנגלית',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'cities_count',
                'title' => 'מספר ערים',
                'type' => 'badge',
                'width' => '100px',
                'badge_class' => 'badge-secondary'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px',
            //     'badges' => [
            //         1 => ['text' => 'פעיל', 'class' => 'badge-success'],
            //         0 => ['text' => 'לא פעיל', 'class' => 'badge-danger']
            //     ]
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px',
                'actions' => ['view', 'edit', 'delete']
            ]
        ],
        
        // שדות לטופס הוספה/עריכה
        'form_fields' => [
            [
                'name' => 'countryNameHe',
                'label' => 'שם מדינה בעברית',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'לדוגמה: ישראל',
                'validation' => ['required', 'minLength:2'],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'countryNameEn',
                'label' => 'שם מדינה באנגלית',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Example: Israel',
                'validation' => ['required', 'minLength:2'],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ]
        ],
        
        // הגדרות נוספות
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/countries-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],
        
        // הרשאות ספציפיות לסוג זה
        'permissions' => [
            'view' => ['admin', 'cemetery_manager', 'manager', 'editor', 'viewer'],
            'create' => ['admin', 'cemetery_manager', 'manager'],
            'edit' => ['admin', 'cemetery_manager', 'manager'],
            'delete' => ['admin', 'cemetery_manager']
        ]
    ],

    // ========================================
    // הגדרות ערים
    // ========================================

    'city' => [
        'table' => 'cities',
        'title' => 'ערים',
        'singular' => 'עיר',
        'icon' => '🏙️',
        'primaryKey' => 'unicId',
        'parentKey' => 'countryId', // עיר שייכת למדינה
        
        'queryFields' => [
            'id',
            'unicId',
            'countryId',
            'cityNameHe',
            'cityNameEn',
            'countryNameHe',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'name' => 'cityNameHe',
            'nameEn' => 'cityNameEn',
            'country' => 'countryNameHe',
            'created' => 'createDate',
            'status' => 'isActive'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => '#',
                'type' => 'index',
                'width' => '50px'
            ],
            [
                'field' => 'cityNameHe',
                'title' => 'שם בעברית',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'cityNameEn',
                'title' => 'שם באנגלית',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'countryNameHe',
                'title' => 'מדינה',
                'type' => 'badge',
                'width' => '150px',
                'badge_class' => 'badge-info'
            ],
            // [
            //     'field' => 'isActive',
            //     'title' => 'סטטוס',
            //     'type' => 'status',
            //     'width' => '100px',
            //     'badges' => [
            //         1 => ['text' => 'פעיל', 'class' => 'badge-success'],
            //         0 => ['text' => 'לא פעיל', 'class' => 'badge-danger']
            //     ]
            // ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '120px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '150px',
                'actions' => ['view', 'edit', 'delete']
            ]
        ],
        
        // שדות לטופס הוספה/עריכה
        'form_fields' => [
            [
                'name' => 'countryId',
                'label' => 'מדינה',
                'type' => 'select',
                'required' => true,
                'dataSource' => [
                    'table' => 'countries',
                    'valueField' => 'unicId',
                    'displayField' => 'countryNameHe',
                    'orderBy' => 'countryNameHe ASC'
                ],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'cityNameHe',
                'label' => 'שם עיר בעברית',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'לדוגמה: ירושלים',
                'validation' => ['required', 'minLength:2'],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ],
            [
                'name' => 'cityNameEn',
                'label' => 'שם עיר באנגלית',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Example: Jerusalem',
                'validation' => ['required', 'minLength:2'],
                'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            ]
        ],
        
        // הגדרות נוספות
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/cities-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],
        
        // הרשאות ספציפיות לסוג זה
        'permissions' => [
            'view' => ['admin', 'cemetery_manager', 'manager', 'editor', 'viewer'],
            'create' => ['admin', 'cemetery_manager', 'manager'],
            'edit' => ['admin', 'cemetery_manager', 'manager'],
            'delete' => ['admin', 'cemetery_manager']
        ]
    ],

    // ========================================
    // הגדרות לקבלת רשומת הורה
    // ========================================
    'parent_selector' => [
        'table' => '',
        'title' => 'בחירת הורה',
        'singular' => 'הורה',
        'icon' => '📁',
        'primaryKey' => '',
        'parentKey' => null,
        'permissions' => [
            'can_create' => false,
            'can_edit' => false,
            'can_delete' => false
        ],
        'form_fields' => [] // ימולא דינמית
    ],
    
    // ========================================
    // הגדרות הרשאות
    // ========================================
    'permissions' => [
        'roles' => [
            'admin' => [
                'title' => 'מנהל מערכת',
                'can_view_all' => true,
                'can_edit_all' => true,
                'can_delete_all' => true,
                'can_create_all' => true
            ],
            'cemetery_manager' => [
                'title' => 'מנהל בית עלמין',
                'can_view_all' => true,
                'can_edit_all' => true,
                'can_delete_all' => true,
                'can_create_all' => true
            ],
            'manager' => [
                'title' => 'מנהל',
                'can_view_all' => true,
                'can_edit_all' => true,
                'can_delete_all' => false,
                'can_create_all' => true
            ],
            'editor' => [
                'title' => 'עורך',
                'can_view_all' => true,
                'can_edit_all' => true,
                'can_delete_all' => false,
                'can_create_all' => true,
                'restricted_fields' => ['constructionCost', 'nationalInsuranceCode']
            ],
            'viewer' => [
                'title' => 'צופה',
                'can_view_all' => true,
                'can_edit_all' => false,
                'can_delete_all' => false,
                'can_create_all' => false
            ]
        ]
    ],
    
    // ========================================
    // הגדרות כלליות
    // ========================================
    'general' => [
        'items_per_page' => 50,
        'enable_soft_delete' => true,
        'enable_audit_log' => true,
        'date_format' => 'd/m/Y',
        'datetime_format' => 'd/m/Y H:i',
        'currency_symbol' => '₪'
    ]
];
?>