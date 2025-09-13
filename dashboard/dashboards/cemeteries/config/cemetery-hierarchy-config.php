<?php
// dashboard/dashboards/cemeteries/config/cemetery-hierarchy-config.php
// קונפיגורציה מרכזית לכל היררכיית בתי העלמין

return [
    // ========================================
    // הגדרות לבתי עלמין
    // ========================================
    'cemetery' => [
        'table' => 'cemeteries',
        'title' => 'בת2י עלמין',
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
                'width' => '60px',
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
            [
                'field' => 'isActive',
                'title' => 'סטטוס',
                'type' => 'status',
                'width' => '100px',
                'badges' => [
                    1 => ['text' => 'פעיל', 'class' => 'badge-success'],
                    0 => ['text' => 'לא פעיל', 'class' => 'badge-danger']
                ]
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
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'cemeteryNameEn',
                'label' => 'שם באנגלית',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Enter cemetery name in English',
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'cemeteryCode',
                'label' => 'קוד בית עלמין',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'קוד ייחודי',
                'permissions' => ['admin', 'manager']
            ],
            [
                'name' => 'nationalInsuranceCode',
                'label' => 'קוד ביטוח לאומי',
                'type' => 'text',
                'required' => false,
                'permissions' => ['admin']
            ],
            [
                'name' => 'address',
                'label' => 'כתובת',
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => 'הזן כתובת מלאה',
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'coordinates',
                'label' => 'קואורדינטות',
                'type' => 'text',
                'placeholder' => 'lat,lng',
                'permissions' => ['admin', 'manager']
            ],
            [
                'name' => 'contactName',
                'label' => 'שם איש קשר',
                'type' => 'text',
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'contactPhoneName',
                'label' => 'טלפון איש קשר',
                'type' => 'tel',
                'placeholder' => '050-0000000',
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'isActive',
                'label' => 'סטטוס',
                'type' => 'select',
                'options' => [
                    1 => 'פעיל',
                    0 => 'לא פעיל'
                ],
                'default' => 1,
                'permissions' => ['admin']
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
                'field' => 'isActive',
                'title' => 'סטטוס',
                'type' => 'status',
                'width' => '100px',
                'badges' => [
                    1 => ['text' => 'פעיל', 'class' => 'badge-success'],
                    0 => ['text' => 'לא פעיל', 'class' => 'badge-danger']
                ]
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
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'blockNameEn',
                'label' => 'שם גוש באנגלית',
                'type' => 'text',
                'placeholder' => 'Enter block name',
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'blockCode',
                'label' => 'קוד גוש',
                'type' => 'text',
                'permissions' => ['admin', 'manager']
            ],
            [
                'name' => 'blockLocation',
                'label' => 'מיקום',
                'type' => 'text',
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'nationalInsuranceCode',
                'label' => 'קוד ביטוח לאומי',
                'type' => 'text',
                'permissions' => ['admin']
            ],
            [
                'name' => 'coordinates',
                'label' => 'קואורדינטות',
                'type' => 'text',
                'placeholder' => 'lat,lng',
                'permissions' => ['admin', 'manager']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'type' => 'textarea',
                'rows' => 3,
                'permissions' => ['admin', 'manager', 'editor']
            ],
            [
                'name' => 'isActive',
                'label' => 'סטטוס',
                'type' => 'select',
                'options' => [
                    1 => 'פעיל',
                    0 => 'לא פעיל'
                ],
                'default' => 1,
                'permissions' => ['admin']
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
            [
                'field' => 'isActive',
                'title' => 'סטטוס',
                'type' => 'status',
                'width' => '100px'
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
            [
                'field' => 'isActive',
                'title' => 'סטטוס',
                'type' => 'status',
                'width' => '100px'
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
            [
                'field' => 'isActive',
                'title' => 'סטטוס',
                'type' => 'status',
                'width' => '100px'
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
                'name' => 'areaGraveNameHe',
                'label' => 'שם אחוזת קבר',
                'type' => 'text',
                'required' => true
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
                'permissions' => ['admin', 'manager']
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
                'permissions' => ['admin', 'manager']
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