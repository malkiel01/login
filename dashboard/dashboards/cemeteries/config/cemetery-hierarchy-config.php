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
            // 'address',
            'coordinates',
            // 'contactName',
            // 'contactPhoneName',
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
            // 'address' => 'address',
            // 'contact' => 'contactName',
            // 'phone' => 'contactPhoneName',
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
                'width' => '190px',
                'actions' => ['edit', 'delete', 'enter']
            ],
            [
                'field' => 'availableSum',
                'title' => 'פנויים',
                'width' => '80px',
                'type' => 'badge',
                'badge_style' => 'success'
            ],
            [
                'field' => 'savedSum',
                'title' => 'שמורים',
                'width' => '80px',
                'type' => 'badge',
                'badge_style' => 'warning'
            ],
            [
                'field' => 'purchasedSum',
                'title' => 'נרכשו',
                'width' => '80px',
                'type' => 'badge',
                'badge_style' => 'info'
            ],
            [
                'field' => 'buriedSum',
                'title' => 'קבורים',
                'width' => '80px',
                'type' => 'badge',
                'badge_style' => 'secondary'
            ],
            [
                'field' => 'graveSum',
                'title' => 'סה"כ',
                'width' => '80px',
                'type' => 'badge',
                'badge_style' => 'primary'
            ],
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

            // [
            //     'name' => 'address',
            //     'label' => 'כתובת',
            //     'type' => 'textarea',
            //     'rows' => 2,
            //     'placeholder' => 'הזן כתובת מלאה',
            //     'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            // ],
            [
                'name' => 'coordinates',
                'label' => 'קואורדינטות',
                'type' => 'text',
                'placeholder' => 'lat,lng',
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            // [
            //     'name' => 'contactName',
            //     'label' => 'שם איש קשר',
            //     'type' => 'text',
            //     'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            // ],
            // [
            //     'name' => 'contactPhoneName',
            //     'label' => 'טלפון איש קשר',
            //     'type' => 'tel',
            //     'placeholder' => '050-0000000',
            //     'permissions' => ['admin', 'cemetery_manager', 'manager', 'editor']
            // ],
        ],

        // שדות לטבלה
        'table_columns2' => [
            [
                'field' => 'cemeteryNameHe',
                'title' => 'שם בית עלמין',  // ⚠️ שים לב: title ולא label
                'width' => '200px',
                'sortable' => true,
                'type' => 'link',  // ⭐ סוג מיוחד - יטופל ב-JS
                'clickable' => true
            ],
            [
                'field' => 'cemeteryCode',
                'title' => 'קוד',
                'width' => '100px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'blocks_count',
                'title' => 'גושים',
                'width' => '80px',
                'sortable' => true,
                'type' => 'badge',  // ⭐ סוג מיוחד - יטופל ב-JS
                'badge_style' => 'info'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'width' => '120px',
                'sortable' => true,
                'type' => 'date'  // ⭐ סוג מיוחד - יטופל ב-JS
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'width' => '120px',
                'sortable' => false,
                'type' => 'actions',  // ⭐ סוג מיוחד - יטופל ב-JS
                'actions' => ['edit', 'delete']
            ]
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
                'width' => '190px',
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
            ]
        ],

        'table_columns' => [
            [
                'field' => 'blockNameHe',
                'title' => 'שם גוש',
                'width' => '200px',
                'sortable' => true,
                'type' => 'link'  // ⭐ סוג מיוחד
            ],
            [
                'field' => 'blockCode',
                'title' => 'קוד',
                'width' => '100px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'cemeteryNameHe',
                'title' => 'בית עלמין',
                'width' => '200px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'plots_count',
                'title' => 'חלקות',
                'width' => '80px',
                'sortable' => true,
                'type' => 'badge'  // ⭐ סוג מיוחד
            ],
            [
                'field' => 'statusBlock',
                'title' => 'סטטוס',
                'width' => '100px',
                'sortable' => true,
                'type' => 'status'  // ⭐ סוג מיוחד
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'width' => '120px',
                'sortable' => true,
                'type' => 'date'  // ⭐ סוג מיוחד
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'width' => '120px',
                'sortable' => false,
                'type' => 'actions'  // ⭐ סוג מיוחד
            ]
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
        ],

        'table_columns' => [
            [
                'field' => 'plotNameHe',
                'title' => 'שם חלקה',
                'width' => '200px',
                'sortable' => true,
                'type' => 'link'
            ],
            [
                'field' => 'plotCode',
                'title' => 'קוד',
                'width' => '100px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'cemeteryNameHe',  // ⭐ זה כבר מגיע מה-API
                'title' => 'בית עלמין',
                'width' => '200px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'blockNameHe',  // ⭐ זה כבר מגיע מה-API
                'title' => 'גוש',
                'width' => '200px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'rows_count',
                'title' => 'שורות',
                'width' => '80px',
                'sortable' => true,
                'type' => 'badge'
            ],
            [
                'field' => 'statusPlot',
                'title' => 'סטטוס',
                'width' => '100px',
                'sortable' => true,
                'type' => 'status'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'width' => '120px',
                'sortable' => true,
                'type' => 'date'
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'width' => '120px',
                'sortable' => false,
                'type' => 'actions'
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
    'areaGrave' => [
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
        ],

        'table_columns' => [
            [
                'field' => 'areaGraveNameHe',
                'title' => 'שם אחוזת קבר',
                'width' => '200px',
                'sortable' => true,
                'type' => 'link'
            ],
            [
                'field' => 'areaGraveCode',
                'title' => 'קוד',
                'width' => '100px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'lineNameHe',  // ⭐ חדש
                'title' => 'שורה',
                'width' => '120px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'plotNameHe',  // ⭐ חדש
                'title' => 'חלקה',
                'width' => '120px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'blockNameHe',  // ⭐ חדש
                'title' => 'גוש',
                'width' => '120px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'cemeteryNameHe',  // ⭐ חדש
                'title' => 'בית עלמין',
                'width' => '150px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'plotNameHe',
                'title' => 'חלקה',
                'width' => '150px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'coordinates',
                'title' => 'קואורדינטות',
                'width' => '150px',
                'sortable' => true,
                'type' => 'coordinates'  // סוג מיוחד
            ],
            [
                'field' => 'graveType',
                'title' => 'סוג קבר',
                'width' => '120px',
                'sortable' => true,
                'type' => 'graveType'  // סוג מיוחד
            ],
            [
                'field' => 'lineNameHe',
                'title' => 'שורה',
                'width' => '150px',
                'sortable' => true,
                'type' => 'row'  // סוג מיוחד
            ],
            [
                'field' => 'graves_count',
                'title' => 'קברים',
                'width' => '80px',
                'sortable' => true,
                'type' => 'badge',
                'badge_style' => 'success'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'width' => '120px',
                'sortable' => true,
                'type' => 'date'
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'width' => '120px',
                'sortable' => false,
                'type' => 'actions',
                'actions' => ['edit', 'delete']
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
    // הגדרות ללקוחות (Customers)
    // ========================================
    'customer' => [
        'table' => 'customers',
        'title' => 'לקוחות',
        'singular' => 'לקוח',
        'icon' => '👤',
        'primaryKey' => 'unicId',
        'parentKey' => null,  // רמת שורש - אין parent
        
        // שדות לשאילתות SELECT
        'queryFields' => [
            'id',
            'unicId',
            'numId',
            'firstName',
            'lastName',
            'phone',
            'phoneMobile',
            'email',
            'streetAddress',
            'city',
            'city_name',
            'statusCustomer',
            'statusResident',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        // מיפוי שדות לתצוגה
        'displayFields' => [
            'id_number' => 'numId',
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'phone' => 'phone',
            'mobile' => 'phoneMobile',
            'email' => 'email',
            'address' => 'streetAddress',
            'city' => 'city_name',
            'status' => 'statusCustomer',
            'type' => 'statusResident',
            'created' => 'createDate'
        ],
        
        // הגדרות טבלה
        'table_columns' => [
            [
                'field' => 'numId',
                'title' => 'ת.ז.',
                'type' => 'text',
                'width' => '120px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'firstName',
                'title' => 'שם פרטי',
                'type' => 'text',
                'width' => '150px',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'lastName',
                'title' => 'שם משפחה',
                'type' => 'text',
                'width' => '150px',
                'sortable' => true,
                'searchable' => true,
                'required' => true
            ],
            [
                'field' => 'phone',
                'title' => 'טלפון',
                'type' => 'text',
                'width' => '120px',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'phoneMobile',
                'title' => 'נייד',
                'type' => 'text',
                'width' => '120px',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'email',
                'title' => 'אימייל',
                'type' => 'text',
                'width' => '200px',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'streetAddress',
                'title' => 'רחוב',
                'type' => 'text',
                'width' => '150px',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'city_name',
                'title' => 'עיר',
                'type' => 'text',
                'width' => '120px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'statusCustomer',
                'title' => 'סטטוס',
                'type' => 'status',
                'width' => '100px',
                'sortable' => true,
                'render' => 'formatCustomerStatus'
            ],
            [
                'field' => 'statusResident',
                'title' => 'סוג',
                'type' => 'type',
                'width' => '100px',
                'sortable' => true,
                'render' => 'formatCustomerType'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'type' => 'date',
                'width' => '120px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '120px',
                'sortable' => false,
                'actions' => ['edit', 'delete']
            ]
        ],
        
        // שדות לטופס הוספה/עריכה (אופציונלי - ניתן להוסיף בעתיד)
        'form_fields' => [
            [
                'name' => 'numId',
                'label' => 'תעודת זהות',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'הזן מספר ת.ז.',
                'validation' => ['required', 'numeric', 'length:9']
            ],
            [
                'name' => 'firstName',
                'label' => 'שם פרטי',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'הזן שם פרטי',
                'validation' => ['required', 'minLength:2']
            ],
            [
                'name' => 'lastName',
                'label' => 'שם משפחה',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'הזן שם משפחה',
                'validation' => ['required', 'minLength:2']
            ],
            [
                'name' => 'phone',
                'label' => 'טלפון',
                'type' => 'tel',
                'required' => false,
                'placeholder' => '02-1234567'
            ],
            [
                'name' => 'phoneMobile',
                'label' => 'טלפון נייד',
                'type' => 'tel',
                'required' => false,
                'placeholder' => '050-1234567'
            ],
            [
                'name' => 'email',
                'label' => 'דואר אלקטרוני',
                'type' => 'email',
                'required' => false,
                'placeholder' => 'example@domain.com',
                'validation' => ['email']
            ],
            [
                'name' => 'streetAddress',
                'label' => 'רחוב',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'הזן כתובת'
            ],
            [
                'name' => 'city',
                'label' => 'עיר',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'הזן עיר'
            ]
        ],
        
        // הגדרות נוספות
        'enable_search' => true,
        'enable_filters' => true,
        'enable_export' => true,
        'items_per_page' => 999999,
        'enable_soft_delete' => true,
        'enable_audit_log' => true
    ],

    // ========================================
    // הגדרות רכישות (Purchases)
    // ========================================
    'purchase' => [
        'table' => 'purchases',
        'title' => 'רכישות',
        'singular' => 'רכישה',
        'icon' => '📋',
        'primaryKey' => 'unicId',
        'parentKey' => null,
        
        'queryFields' => [
            'id',
            'unicId',
            'serialPurchaseId',
            'clientId',
            'graveId',
            'purchaseAmount',
            'purchaseDate',
            'statusPurchase',
            'buyer_status',
            'paymentsList',
            'documentsList',
            'comments',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'serialNumber' => 'serialPurchaseId',
            'customer' => 'clientId',
            'grave' => 'graveId',
            'amount' => 'purchaseAmount',
            'date' => 'purchaseDate',
            'status' => 'statusPurchase',
            'created' => 'createDate'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => '#',
                'width' => '60px',
                'type' => 'index',
                'sortable' => false
            ],
            [
                'field' => 'serialPurchaseId',
                'title' => 'מספר רכישה',
                'width' => '120px',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'customerName',
                'title' => 'שם לקוח',
                'width' => '180px',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'graveName',
                'title' => 'קבר',
                'width' => '150px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'purchaseAmount',
                'title' => 'סכום',
                'width' => '120px',
                'type' => 'currency',
                'sortable' => true
            ],
            [
                'field' => 'purchaseDate',
                'title' => 'תאריך רכישה',
                'width' => '130px',
                'type' => 'date',
                'sortable' => true
            ],
            [
                'field' => 'statusPurchase',
                'title' => 'סטטוס',
                'width' => '100px',
                'type' => 'status',
                'sortable' => true
            ],
            [
                'field' => 'createDate',
                'title' => 'נוצר בתאריך',
                'width' => '130px',
                'type' => 'date',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'width' => '150px',
                'type' => 'actions',
                'sortable' => false
            ]
        ],
        
        'form_fields' => [
            [
                'name' => 'clientId',
                'label' => 'לקוח',
                'type' => 'select',
                'required' => true
            ],
            [
                'name' => 'buyer_status',
                'label' => 'סטטוס רוכש',
                'type' => 'select',
                'options' => [
                    1 => 'רוכש לעצמו',
                    2 => 'רוכש לאחר'
                ],
                'default' => 1
            ],
            [
                'name' => 'graveId',
                'label' => 'קבר',
                'type' => 'select',
                'required' => true
            ],
            [
                'name' => 'purchaseAmount',
                'label' => 'סכום רכישה',
                'type' => 'number',
                'step' => '0.01',
                'required' => true
            ],
            [
                'name' => 'purchaseDate',
                'label' => 'תאריך רכישה',
                'type' => 'date',
                'required' => true
            ],
            [
                'name' => 'statusPurchase',
                'label' => 'סטטוס רכישה',
                'type' => 'select',
                'options' => [
                    1 => 'טיוטה',
                    2 => 'אושר',
                    3 => 'שולם',
                    4 => 'בוטל'
                ],
                'default' => 1
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
    // הגדרות קבורות (Burials)
    // ========================================
    'burial' => [
        'table' => 'burials',
        'title' => 'קבורות',
        'singular' => 'קבורה',
        'icon' => '⚰️',
        'primaryKey' => 'unicId',
        'parentKey' => null,
        
        'queryFields' => [
            'id',
            'unicId',
            'serialBurialId',
            'customerId',
            'customerFirstName',
            'customerLastName',
            'customerNumId',
            'customerPhone',
            'graveId',
            'graveName',
            'fullLocation',
            'dateDeath',
            'dateBurial',
            'timeBurial',
            'burialStatus',
            'nationalInsuranceBurial',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'serialNumber' => 'serialBurialId',
            'deceased' => 'customerLastName',
            'idNumber' => 'customerNumId',
            'deathDate' => 'dateDeath',
            'burialDate' => 'dateBurial',
            'burialTime' => 'timeBurial',
            'location' => 'fullLocation',
            'status' => 'burialStatus',
            'created' => 'createDate'
        ],
        
        'table_columns' => [
            [
                'field' => 'index',
                'title' => 'מס׳',
                'width' => '60px',
                'type' => 'index',
                'sortable' => false
            ],
            [
                'field' => 'serialBurialId',
                'title' => 'מס׳ תיק קבורה',
                'width' => '140px',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'customerLastName',
                'title' => 'נפטר/ת',
                'width' => '180px',
                'type' => 'text',
                'sortable' => true,
                'searchable' => true,
                'show_secondary' => 'customerFirstName'
            ],
            [
                'field' => 'customerNumId',
                'title' => 'ת.ז.',
                'width' => '120px',
                'type' => 'text',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'dateDeath',
                'title' => 'תאריך פטירה',
                'width' => '130px',
                'type' => 'date',
                'sortable' => true
            ],
            [
                'field' => 'dateBurial',
                'title' => 'תאריך קבורה',
                'width' => '130px',
                'type' => 'date',
                'sortable' => true
            ],
            [
                'field' => 'timeBurial',
                'title' => 'שעת קבורה',
                'width' => '100px',
                'type' => 'time',
                'sortable' => false
            ],
            [
                'field' => 'fullLocation',
                'title' => 'מיקום קבר',
                'width' => '150px',
                'type' => 'text',
                'sortable' => false
            ],
            [
                'field' => 'burialStatus',
                'title' => 'סטטוס',
                'width' => '110px',
                'type' => 'status',
                'sortable' => true,
                'render' => 'formatBurialStatus'
            ],
            [
                'field' => 'nationalInsuranceBurial',
                'title' => 'ביטוח לאומי',
                'width' => '120px',
                'type' => 'boolean',
                'sortable' => false
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'width' => '180px',
                'type' => 'actions',
                'sortable' => false,
                'actions' => ['view', 'edit', 'delete']
            ]
        ],
        
        'form_fields' => [
            [
                'name' => 'serialBurialId',
                'label' => 'מס׳ תיק קבורה',
                'type' => 'text',
                'required' => true
            ],
            [
                'name' => 'customerId',
                'label' => 'לקוח',
                'type' => 'select',
                'required' => true
            ],
            [
                'name' => 'graveId',
                'label' => 'קבר',
                'type' => 'select',
                'required' => true
            ],
            [
                'name' => 'dateDeath',
                'label' => 'תאריך פטירה',
                'type' => 'date',
                'required' => true
            ],
            [
                'name' => 'dateBurial',
                'label' => 'תאריך קבורה',
                'type' => 'date',
                'required' => true
            ],
            [
                'name' => 'timeBurial',
                'label' => 'שעת קבורה',
                'type' => 'time',
                'required' => false
            ],
            [
                'name' => 'burialStatus',
                'label' => 'סטטוס קבורה',
                'type' => 'select',
                'options' => [
                    1 => 'ברישום',
                    2 => 'אושרה',
                    3 => 'בוצעה',
                    4 => 'בוטלה'
                ],
                'default' => 1
            ],
            [
                'name' => 'nationalInsuranceBurial',
                'label' => 'ביטוח לאומי',
                'type' => 'select',
                'options' => [
                    'כן' => 'כן',
                    'לא' => 'לא'
                ],
                'default' => 'לא'
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