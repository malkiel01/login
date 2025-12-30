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

        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/cemeteries-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],
                
        // ⭐ הגדרות חיפוש
        'search' => [
            'placeholder' => 'חיפוש בתי עלמין לפי שם, קוד, כתובת...',
            'minLength' => 0
        ],
        
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
        ],

        // שדות לטבלה
        'table_columns' => [
            [
                'field' => 'cemeteryNameHe',
                'title' => 'שם בית עלמין',
                'width' => '400px',
                'sortable' => true,
                'type' => 'link',
                'clickable' => true
            ],
            [
                'field' => 'cemeteryCode',
                'title' => 'קוד',
                'width' => '200px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'availableSum',
                'title' => 'פנויים',
                'width' => '150px',
                'type' => 'badge',
                'badge_style' => 'success'
            ],
            [
                'field' => 'savedSum',
                'title' => 'שמורים',
                'width' => '100px',
                'type' => 'badge',
                'badge_style' => 'warning'
            ],
            [
                'field' => 'purchasedSum',
                'title' => 'נרכשו',
                'width' => '100px',
                'type' => 'badge',
                'badge_style' => 'info'
            ],
            [
                'field' => 'buriedSum',
                'title' => 'קבורים',
                'width' => '100px',
                'type' => 'badge',
                'badge_style' => 'secondary'
            ],
            [
                'field' => 'graveSum',
                'title' => 'סה"כ',
                'width' => '100px',
                'type' => 'badge',
                'badge_style' => 'primary'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'width' => '200px',
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
        ],

        // ⭐ שדות חיפוש
        'searchableFields' => [
            [
                'name' => 'cemeteryNameHe',
                'label' => 'שם בית עלמין (עברית)',
                'table' => 'cemeteries',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'cemeteryNameEn',
                'label' => 'שם בית עלמין (אנגלית)',
                'table' => 'cemeteries',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'cemeteryCode',
                'label' => 'קוד בית עלמין',
                'table' => 'cemeteries',
                'type' => 'text',
                'matchType' => ['exact', 'startsWith']
            ],
            [
                'name' => 'address',
                'label' => 'כתובת',
                'table' => 'cemeteries',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'contactName',
                'label' => 'איש קשר',
                'table' => 'cemeteries',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'contactPhoneName',
                'label' => 'טלפון',
                'table' => 'cemeteries',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'createDate',
                'label' => 'תאריך יצירה',
                'table' => 'cemeteries',
                'type' => 'date',
                'matchType' => ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
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
        
        // ⭐ עמודות טבלה (אחת בלבד!)
        'table_columns' => [
            [
                'field' => 'blockNameHe',
                'title' => 'שם גוש',
                'width' => '200px',
                'sortable' => true,
                'type' => 'link'
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
                'type' => 'badge'
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
        ],
        
        // ⭐ שדות חיפוש
        'searchableFields' => [
            [
                'name' => 'blockNameHe',
                'label' => 'שם גוש (עברית)',
                'table' => 'blocks',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'blockNameEn',
                'label' => 'שם גוש (אנגלית)',
                'table' => 'blocks',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'blockCode',
                'label' => 'קוד גוש',
                'table' => 'blocks',
                'type' => 'text',
                'matchType' => ['exact', 'startsWith']
            ],
            [
                'name' => 'blockLocation',
                'label' => 'מיקום גוש',
                'table' => 'blocks',
                'type' => 'text',
                'matchType' => ['exact', 'startsWith']
            ],
            [
                'name' => 'cemeteryNameHe',
                'label' => 'בית עלמין',
                'table' => 'cemeteries',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'table' => 'blocks',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'createDate',
                'label' => 'תאריך יצירה',
                'table' => 'blocks',
                'type' => 'date',
                'matchType' => ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            ]
        ],
        
        // ⭐ שדות טופס
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
        
        // ⭐ הגדרות API
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/blocks-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],

        // ⭐ הגדרות חיפוש
        'search' => [
            'placeholder' => 'חיפוש גושים לפי שם, קוד, מיקום...',
            'minLength' => 0
        ],
    ],
    
    // ========================================
    // הגדרות לחלקות
    // ========================================
    'plot' => [
        // ⭐ שדות חדשים להוסיף:
        'singularArticle' => 'את החלקה',
        'plural' => 'חלקות',
        'nameField' => 'plotNameHe',
        'idField' => 'unicId',  // או להשתמש ב-primaryKey
        
        // פרמטרים
        'hasParent' => true,
        'parentParam' => 'blockId',
        'defaultLimit' => 200,
        'defaultOrderBy' => 'createDate',
        'defaultSortDirection' => 'DESC',

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
     
        // ⭐ עמודות טבלה
        'table_columns' => [
            [
                'field' => 'plotNameHe',
                'title' => 'שם חלקה',
                'width' => '180px',
                'sortable' => true,
                'type' => 'link'
            ],
            [
                'field' => 'plotCode',
                'title' => 'קוד',
                'width' => '80px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'cemeteryNameHe',
                'title' => 'בית עלמין',
                'width' => '150px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'blockNameHe',
                'title' => 'גוש',
                'width' => '120px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'availableSum',
                'title' => 'פנויים',
                'width' => '70px',
                'sortable' => true,
                'type' => 'badge',
                'style' => 'success'
            ],
            [
                'field' => 'savedSum',
                'title' => 'שמורים',
                'width' => '70px',
                'sortable' => true,
                'type' => 'badge',
                'style' => 'warning'
            ],
            [
                'field' => 'purchasedSum',
                'title' => 'נרכשו',
                'width' => '70px',
                'sortable' => true,
                'type' => 'badge',
                'style' => 'info'
            ],
            [
                'field' => 'buriedSum',
                'title' => 'קבורים',
                'width' => '70px',
                'sortable' => true,
                'type' => 'badge',
                'style' => 'secondary'
            ],
            [
                'field' => 'graveSum',
                'title' => 'סה"כ',
                'width' => '70px',
                'sortable' => true,
                'type' => 'badge',
                'style' => 'primary'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'width' => '100px',
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
        ],
        
        // ⭐ שדות חיפוש
        'searchableFields' => [
            [
                'name' => 'plotNameHe',
                'label' => 'שם חלקה (עברית)',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'plotNameEn',
                'label' => 'שם חלקה (אנגלית)',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'plotCode',
                'label' => 'קוד חלקה',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'startsWith']
            ],
            [
                'name' => 'plotLocation',
                'label' => 'מיקום חלקה',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'startsWith']
            ],
            [
                'name' => 'blockNameHe',
                'label' => 'גוש',
                'table' => 'blocks',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'createDate',
                'label' => 'תאריך יצירה',
                'table' => 'plots',
                'type' => 'date',
                'matchType' => ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            ]
        ],
        
        // ⭐ שדות טופס
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
        
        // ⭐ הגדרות API
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/plots-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],

        // ⭐ הגדרות חיפוש
        'search' => [
            'placeholder' => 'חיפוש חלקות לפי שם, קוד, מיקום...',
            'minLength' => 0
        ],
    
        // משתנים גלובליים (backward compatibility)
        'jsVars' => [
            'searchVar' => 'plotSearch',
            'tableVar' => 'plotsTable',
            'currentPageVar' => 'plotsCurrentPage',
            'totalPagesVar' => 'plotsTotalPages',
            'dataArrayVar' => 'currentPlots',
            'isLoadingVar' => 'plotsIsLoadingMore',
            'isSearchModeVar' => 'plotsIsSearchMode',
            'currentQueryVar' => 'plotsCurrentQuery',
            'searchResultsVar' => 'plotsSearchResults',
        ],
        
        // פונקציות
        'jsFunctions' => [
            'renderFunctionName' => 'renderPlotsRows',
            'loadFunctionName' => 'loadPlots',
            'loadBrowseFunctionName' => 'loadPlotsBrowseData',
            'appendMoreFunctionName' => 'appendMorePlots',
        ],
        
        // סטטיסטיקות
        'statsConfig' => [
            'elements' => [
                'totalPlots' => 'total_plots',
                'totalAreaGraves' => 'total_area_graves',
                'newThisMonth' => 'new_this_month'
            ],
            'parentParam' => 'blockId'
        ],
        
        // סטטוסים
        'statuses' => [
            'active' => ['text' => 'פעיל', 'color' => '#10b981'],
            'inactive' => ['text' => 'לא פעיל', 'color' => '#6b7280'],
            'full' => ['text' => 'מלא', 'color' => '#ef4444']
        ]
    ],
    
    // ========================================
    // הגדרות לשורות
    // ========================================
    'row' => [
        // ⭐ שדות חדשים
        'singularArticle' => 'את השורה',
        'plural' => 'שורות',
        'nameField' => 'lineNameHe',
        'idField' => 'unicId',
        
        // פרמטרים
        'hasParent' => true,
        'parentParam' => 'plotId',
        'defaultLimit' => 200,
        'defaultOrderBy' => 'serialNumber',
        'defaultSortDirection' => 'ASC',

        // מקורי
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
                'width' => '180px',
                'type' => 'link',
                'sortable' => true
            ],
            [
                'field' => 'plotNameHe',
                'title' => 'חלקה',
                'width' => '150px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'blockNameHe',
                'title' => 'גוש',
                'width' => '120px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'cemeteryNameHe',
                'title' => 'בית עלמין',
                'width' => '150px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'areaGraves_count',
                'title' => 'אחוזות קבר',
                'width' => '100px',
                'type' => 'badge',
                'style' => 'info'
            ],
            [
                'field' => 'lineLocation',
                'title' => 'מיקום',
                'width' => '120px',
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
                'width' => '120px',
                'sortable' => false
            ]
        ],
        
        'searchableFields' => [
            [
                'name' => 'lineNameHe',
                'label' => 'שם שורה (עברית)',
                'table' => 'rows',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'lineNameEn',
                'label' => 'שם שורה (אנגלית)',
                'table' => 'rows',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'serialNumber',
                'label' => 'מספר סידורי',
                'table' => 'rows',
                'type' => 'number',
                'matchType' => ['exact']
            ],
            [
                'name' => 'plotNameHe',
                'label' => 'חלקה',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'table' => 'rows',
                'type' => 'text',
                'matchType' => ['fuzzy']
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
                'type' => 'textarea',
                'rows' => 3
            ]
        ],
        
        // ⭐ הגדרות API
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/rows-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],

        // ⭐ הגדרות חיפוש
        'search' => [
            'placeholder' => 'חיפוש שורות לפי שם, מספר סידורי...',
            'minLength' => 0
        ],
    
        // משתנים גלובליים
        'jsVars' => [
            'searchVar' => 'rowSearch',
            'tableVar' => 'rowsTable',
            'currentPageVar' => 'rowsCurrentPage',
            'totalPagesVar' => 'rowsTotalPages',
            'dataArrayVar' => 'currentRows',
            'isLoadingVar' => 'rowsIsLoadingMore',
            'isSearchModeVar' => 'rowsIsSearchMode',
            'currentQueryVar' => 'rowsCurrentQuery',
            'searchResultsVar' => 'rowsSearchResults',
        ],
        
        // פונקציות
        'jsFunctions' => [
            'renderFunctionName' => 'renderRowsRows',
            'loadFunctionName' => 'loadRows',
            'loadBrowseFunctionName' => 'loadRowsBrowseData',
            'appendMoreFunctionName' => 'appendMoreRows',
        ],
        
        // סטטיסטיקות
        'statsConfig' => [
            'elements' => [
                'totalRows' => 'total_rows',
                'totalAreaGraves' => 'total_area_graves',
                'newThisMonth' => 'new_this_month'
            ],
            'parentParam' => 'plotId'
        ],
        
        // סטטוסים
        'statuses' => [
            'active' => ['text' => 'פעיל', 'color' => '#10b981'],
            'inactive' => ['text' => 'לא פעיל', 'color' => '#6b7280']
        ]
    ],
    
    // ========================================
    // הגדרות לאחוזות קבר
    // ========================================
    'areaGrave' => [
        // ⭐ שדות חדשים
        'singularArticle' => 'את אחוזת הקבר',
        'plural' => 'אחוזות קבר',
        'nameField' => 'areaGraveNameHe',
        'idField' => 'unicId',
        
        // פרמטרים
        'hasParent' => true,
        // 'parentParam' => 'lineId',
        'parentParam' => 'plotId',
        'defaultLimit' => 200,
        'defaultOrderBy' => 'createDate',
        'defaultSortDirection' => 'DESC',

        // מקורי
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
        
        // ⭐ עמודות טבלה (מתוקן - ללא כפילויות!)
        'table_columns' => [
            [
                'field' => 'areaGraveNameHe',
                'title' => 'שם אחוזת קבר',
                'width' => '180px',
                'sortable' => true,
                'type' => 'link'
            ],
            [
                'field' => 'graveType',
                'title' => 'סוג',
                'width' => '100px',
                'sortable' => true,
                'type' => 'graveType'
            ],
            [
                'field' => 'lineNameHe',
                'title' => 'שורה',
                'width' => '120px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'plotNameHe',
                'title' => 'חלקה',
                'width' => '120px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'blockNameHe',
                'title' => 'גוש',
                'width' => '120px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'cemeteryNameHe',
                'title' => 'בית עלמין',
                'width' => '150px',
                'sortable' => true,
                'type' => 'text'
            ],
            [
                'field' => 'graves_count',
                'title' => 'קברים',
                'width' => '80px',
                'sortable' => true,
                'type' => 'badge',
                'style' => 'success'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'width' => '100px',
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
        ],
        
        // ⭐ שדות חיפוש
        'searchableFields' => [
            [
                'name' => 'areaGraveNameHe',
                'label' => 'שם אחוזת קבר',
                'table' => 'areaGraves',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'graveType',
                'label' => 'סוג אחוזת קבר',
                'table' => 'areaGraves',
                'type' => 'select',
                'matchType' => ['exact'],
                'options' => [
                    ['value' => 1, 'label' => 'שדה'],
                    ['value' => 2, 'label' => 'רוויה'],
                    ['value' => 3, 'label' => 'סנהדרין']
                ]
            ],
            [
                'name' => 'lineNameHe',
                'label' => 'שורה',
                'table' => 'rows',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'plotNameHe',
                'label' => 'חלקה',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'table' => 'areaGraves',
                'type' => 'text',
                'matchType' => ['fuzzy']
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
                'placeholder' => 'בחר שורה'
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
                'type' => 'text',
                'placeholder' => 'lat,lng'
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'type' => 'textarea',
                'rows' => 3
            ]
        ],
        
        // ⭐ הגדרות API
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/areaGraves-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],

        // ⭐ הגדרות חיפוש
        'search' => [
            'placeholder' => 'חיפוש אחוזות קבר לפי שם, קוד, סוג...',
            'minLength' => 0
        ],
    
        // משתנים גלובליים
        'jsVars' => [
            'searchVar' => 'areaGraveSearch',
            'tableVar' => 'areaGravesTable',
            'currentPageVar' => 'areaGravesCurrentPage',
            'totalPagesVar' => 'areaGravesTotalPages',
            'dataArrayVar' => 'currentAreaGraves',
            'isLoadingVar' => 'areaGravesIsLoadingMore',
            'isSearchModeVar' => 'areaGravesIsSearchMode',
            'currentQueryVar' => 'areaGravesCurrentQuery',
            'searchResultsVar' => 'areaGravesSearchResults',
        ],
        
        // פונקציות
        'jsFunctions' => [
            'renderFunctionName' => 'renderAreaGravesRows',
            'loadFunctionName' => 'loadAreaGraves',
            'loadBrowseFunctionName' => 'loadAreaGravesBrowseData',
            'appendMoreFunctionName' => 'appendMoreAreaGraves',
        ],
        
        // סטטיסטיקות
        'statsConfig' => [
            'elements' => [
                'totalAreaGraves' => 'total_area_graves',
                'totalGraves' => 'total_graves',
                'newThisMonth' => 'new_this_month'
            ],
            // 'parentParam' => 'lineId',
            'parentParam' => 'plotId',
        ],
        
        // סטטוסים (סוגי אחוזות קבר)
        'graveTypes' => [
            1 => ['text' => 'שדה', 'color' => '#10b981'],
            2 => ['text' => 'רוויה', 'color' => '#3b82f6'],
            3 => ['text' => 'סנהדרין', 'color' => '#8b5cf6']
        ],
        
        'statuses' => [
            'active' => ['text' => 'פעיל', 'color' => '#10b981'],
            'inactive' => ['text' => 'לא פעיל', 'color' => '#6b7280']
        ]
    ],
    
    // ========================================
    // הגדרות לקברים
    // ========================================
    'grave' => [
        // ⭐ שדות חדשים
        'singularArticle' => 'את הקבר',
        'plural' => 'קברים',
        'nameField' => 'graveNameHe',
        'idField' => 'unicId',
        
        // פרמטרים
        'hasParent' => true,
        'parentParam' => 'areaGraveId',
        'defaultLimit' => 200,
        'defaultOrderBy' => 'graveLocation',
        'defaultSortDirection' => 'ASC',

        // מקורי
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
        
        // ⭐ עמודות טבלה (עם היררכיה!)
        'table_columns' => [
            [
                'field' => 'graveNameHe',
                'title' => 'מספר קבר',
                'width' => '120px',
                'type' => 'link',
                'sortable' => true
            ],
            [
                'field' => 'graveStatus',
                'title' => 'סטטוס',
                'type' => 'graveStatus',
                'width' => '100px',
                'sortable' => true
            ],
            [
                'field' => 'plotType',
                'title' => 'סוג חלקה',
                'type' => 'plotType',
                'width' => '100px',
                'sortable' => true
            ],
            [
                'field' => 'graveLocation',
                'title' => 'מיקום',
                'type' => 'number',
                'width' => '80px',
                'sortable' => true
            ],
            [
                'field' => 'areaGraveNameHe',
                'title' => 'אחוזת קבר',
                'width' => '150px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'lineNameHe',
                'title' => 'שורה',
                'width' => '120px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'plotNameHe',
                'title' => 'חלקה',
                'width' => '120px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'blockNameHe',
                'title' => 'גוש',
                'width' => '120px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'cemeteryNameHe',
                'title' => 'בית עלמין',
                'width' => '150px',
                'type' => 'text',
                'sortable' => true
            ],
            [
                'field' => 'isSmallGrave',
                'title' => 'קבר קטן',
                'type' => 'boolean',
                'width' => '80px',
                'sortable' => true
            ],
            [
                'field' => 'constructionCost',
                'title' => 'עלות בנייה',
                'type' => 'currency',
                'width' => '100px',
                'sortable' => true,
                'permissions' => ['admin', 'cemetery_manager', 'manager']
            ],
            [
                'field' => 'createDate',
                'title' => 'נוצר',
                'type' => 'date',
                'width' => '100px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '120px',
                'sortable' => false
            ]
        ],
        
        // ⭐ שדות חיפוש
        'searchableFields' => [
            [
                'name' => 'graveNameHe',
                'label' => 'מספר קבר',
                'table' => 'graves',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'graveStatus',
                'label' => 'סטטוס קבר',
                'table' => 'graves',
                'type' => 'select',
                'matchType' => ['exact'],
                'options' => [
                    ['value' => 1, 'label' => 'פנוי'],
                    ['value' => 2, 'label' => 'נרכש'],
                    ['value' => 3, 'label' => 'קבור'],
                    ['value' => 4, 'label' => 'שמור']
                ]
            ],
            [
                'name' => 'plotType',
                'label' => 'סוג חלקה',
                'table' => 'graves',
                'type' => 'select',
                'matchType' => ['exact'],
                'options' => [
                    ['value' => 1, 'label' => 'פטורה'],
                    ['value' => 2, 'label' => 'חריגה'],
                    ['value' => 3, 'label' => 'סגורה']
                ]
            ],
            [
                'name' => 'areaGraveNameHe',
                'label' => 'אחוזת קבר',
                'table' => 'areaGraves',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'lineNameHe',
                'label' => 'שורה',
                'table' => 'rows',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'plotNameHe',
                'label' => 'חלקה',
                'table' => 'plots',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'table' => 'graves',
                'type' => 'text',
                'matchType' => ['fuzzy']
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
        ],
        
        // ⭐ הגדרות API
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/graves-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],

        // ⭐ הגדרות חיפוש
        'search' => [
            'placeholder' => 'חיפוש קברים לפי מספר, סטטוס, מיקום...',
            'minLength' => 0
        ],
    
        // משתנים גלובליים
        'jsVars' => [
            'searchVar' => 'graveSearch',
            'tableVar' => 'gravesTable',
            'currentPageVar' => 'gravesCurrentPage',
            'totalPagesVar' => 'gravesTotalPages',
            'dataArrayVar' => 'currentGraves',
            'isLoadingVar' => 'gravesIsLoadingMore',
            'isSearchModeVar' => 'gravesIsSearchMode',
            'currentQueryVar' => 'gravesCurrentQuery',
            'searchResultsVar' => 'gravesSearchResults',
        ],
        
        // פונקציות
        'jsFunctions' => [
            'renderFunctionName' => 'renderGravesRows',
            'loadFunctionName' => 'loadGraves',
            'loadBrowseFunctionName' => 'loadGravesBrowseData',
            'appendMoreFunctionName' => 'appendMoreGraves',
        ],
        
        // סטטיסטיקות
        'statsConfig' => [
            'elements' => [
                'totalGraves' => 'total_graves',
                'availableGraves' => 'available',
                'purchasedGraves' => 'purchased',
                'buriedGraves' => 'buried',
                'savedGraves' => 'saved',
                'newThisMonth' => 'new_this_month'
            ],
            'parentParam' => 'areaGraveId'
        ],
        
        // סטטוסים קבר
        'graveStatuses' => [
            1 => ['text' => 'פנוי', 'color' => '#10b981', 'class' => 'badge-success'],
            2 => ['text' => 'נרכש', 'color' => '#3b82f6', 'class' => 'badge-info'],
            3 => ['text' => 'קבור', 'color' => '#6b7280', 'class' => 'badge-secondary'],
            4 => ['text' => 'שמור', 'color' => '#f59e0b', 'class' => 'badge-warning']
        ],
        
        // סוגי חלקה
        'plotTypes' => [
            1 => ['text' => 'פטורה', 'color' => '#10b981', 'class' => 'badge-success'],
            2 => ['text' => 'חריגה', 'color' => '#f59e0b', 'class' => 'badge-warning'],
            3 => ['text' => 'סגורה', 'color' => '#ef4444', 'class' => 'badge-danger']
        ],
        
        'statuses' => [
            'active' => ['text' => 'פעיל', 'color' => '#10b981'],
            'inactive' => ['text' => 'לא פעיל', 'color' => '#6b7280']
        ]
    ],

    // ========================================
    // הגדרות ללקוחות (Customers)
    // ========================================
    'customer' => [
        // ⭐ שדות חדשים
        'singularArticle' => 'את הלקוח',
        'plural' => 'לקוחות',
        'nameField' => 'fullNameHe',
        'idField' => 'unicId',
        
        // פרמטרים
        'hasParent' => false,
        'parentParam' => null,
        'defaultLimit' => 200,
        'defaultOrderBy' => 'createDate',
        'defaultSortDirection' => 'DESC',

        // מקורי
        'table' => 'customers',
        'title' => 'לקוחות',
        'singular' => 'לקוח',
        'icon' => '👤',
        'primaryKey' => 'unicId',
        'parentKey' => null,
        
        'queryFields' => [
            'id',
            'unicId',
            'numId',
            'firstName',
            'lastName',
            'fullNameHe',
            'phone',
            'phoneMobile',
            'address',
            'cityNameHe',
            'countryNameHe',
            'statusCustomer',
            'resident',
            'graveNameHe',
            'cemeteryNameHe',
            'createDate',
            'updateDate',
            'isActive'
        ],
        
        'displayFields' => [
            'id_number' => 'numId',
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'full_name' => 'fullNameHe',
            'phone' => 'phone',
            'mobile' => 'phoneMobile',
            'address' => 'address',
            'city' => 'cityNameHe',
            'country' => 'countryNameHe',
            'status' => 'statusCustomer',
            'resident' => 'resident',
            'grave' => 'graveNameHe',
            'cemetery' => 'cemeteryNameHe',
            'created' => 'createDate'
        ],
        
        // ⭐ עמודות טבלה
        'table_columns' => [
            [
                'field' => 'numId',
                'title' => 'ת.ז.',
                'type' => 'text',
                'width' => '110px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'fullNameHe',
                'title' => 'שם מלא',
                'type' => 'text',
                'width' => '180px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'phone',
                'title' => 'טלפון',
                'type' => 'text',
                'width' => '110px',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'phoneMobile',
                'title' => 'נייד',
                'type' => 'text',
                'width' => '110px',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'address',
                'title' => 'כתובת',
                'type' => 'text',
                'width' => '150px',
                'sortable' => false,
                'searchable' => true
            ],
            [
                'field' => 'cityNameHe',
                'title' => 'עיר',
                'type' => 'text',
                'width' => '100px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'countryNameHe',
                'title' => 'מדינה',
                'type' => 'text',
                'width' => '100px',
                'sortable' => true,
                'searchable' => true
            ],
            [
                'field' => 'statusCustomer',
                'title' => 'סטטוס',
                'type' => 'status',
                'width' => '90px',
                'sortable' => true,
                'render' => 'formatCustomerStatus'
            ],
            [
                'field' => 'resident',
                'title' => 'תושבות',
                'type' => 'type',
                'width' => '90px',
                'sortable' => true,
                'render' => 'formatResidentType'
            ],
            [
                'field' => 'createDate',
                'title' => 'תאריך',
                'type' => 'date',
                'width' => '100px',
                'sortable' => true
            ],
            [
                'field' => 'actions',
                'title' => 'פעולות',
                'type' => 'actions',
                'width' => '100px',
                'sortable' => false,
                'actions' => ['edit', 'delete']
            ]
        ],
        
        // ⭐ שדות חיפוש
        'searchableFields' => [
            [
                'name' => 'numId',
                'label' => 'תעודת זהות',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'fullNameHe',
                'label' => 'שם מלא',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'firstName',
                'label' => 'שם פרטי',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'lastName',
                'label' => 'שם משפחה',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'phone',
                'label' => 'טלפון',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'phoneMobile',
                'label' => 'נייד',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'statusCustomer',
                'label' => 'סטטוס לקוח',
                'table' => 'customers',
                'type' => 'select',
                'matchType' => ['exact'],
                'options' => [
                    ['value' => 1, 'label' => 'פעיל'],
                    ['value' => 2, 'label' => 'רכש'],
                    ['value' => 3, 'label' => 'נפטר']
                ]
            ],
            [
                'name' => 'resident',
                'label' => 'תושבות',
                'table' => 'customers',
                'type' => 'select',
                'matchType' => ['exact'],
                'options' => [
                    ['value' => 1, 'label' => 'תושב'],
                    ['value' => 2, 'label' => 'לא תושב']
                ]
            ],
            [
                'name' => 'cityNameHe',
                'label' => 'עיר',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'countryNameHe',
                'label' => 'מדינה',
                'table' => 'customers',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ]
        ],
        
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
                'name' => 'address',
                'label' => 'כתובת',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'הזן כתובת'
            ],
            [
                'name' => 'cityId',
                'label' => 'עיר',
                'type' => 'select',
                'required' => false,
                'dataSource' => 'cities'
            ],
            [
                'name' => 'countryId',
                'label' => 'מדינה',
                'type' => 'select',
                'required' => false,
                'dataSource' => 'countries'
            ],
            [
                'name' => 'resident',
                'label' => 'תושבות',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => 1, 'label' => 'תושב'],
                    ['value' => 2, 'label' => 'לא תושב']
                ]
            ],
            [
                'name' => 'statusCustomer',
                'label' => 'סטטוס',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => 1, 'label' => 'פעיל'],
                    ['value' => 2, 'label' => 'רכש'],
                    ['value' => 3, 'label' => 'נפטר']
                ],
                'default' => 1
            ]
        ],
        
        // ⭐ הגדרות API
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/customers-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],

        // ⭐ הגדרות חיפוש
        'search' => [
            'placeholder' => 'חיפוש לקוחות לפי שם, ת.ז., טלפון...',
            'minLength' => 0
        ],

        // משתנים גלובליים
        'jsVars' => [
            'searchVar' => 'customerSearch',
            'tableVar' => 'customersTable',
            'currentPageVar' => 'customersCurrentPage',
            'totalPagesVar' => 'customersTotalPages',
            'dataArrayVar' => 'currentCustomers',
            'isLoadingVar' => 'customersIsLoadingMore',
            'isSearchModeVar' => 'customersIsSearchMode',
            'currentQueryVar' => 'customersCurrentQuery',
            'searchResultsVar' => 'customersSearchResults',
        ],
        
        // פונקציות
        'jsFunctions' => [
            'renderFunctionName' => 'renderCustomersRows',
            'loadFunctionName' => 'loadCustomers',
            'loadBrowseFunctionName' => 'loadCustomersBrowseData',
            'appendMoreFunctionName' => 'appendMoreCustomers',
        ],
        
        // סטטיסטיקות
        'statsConfig' => [
            'elements' => [
                'totalCustomers' => 'total_customers',
                'activeCustomers' => 'active',
                'purchasedCustomers' => 'purchased',
                'deceasedCustomers' => 'deceased',
                'newThisMonth' => 'new_this_month'
            ],
            'parentParam' => null
        ],
        
        // סטטוסים לקוח
        'customerStatuses' => [
            1 => ['text' => 'פעיל', 'color' => '#10b981', 'class' => 'badge-success'],
            2 => ['text' => 'רכש', 'color' => '#3b82f6', 'class' => 'badge-info'],
            3 => ['text' => 'נפטר', 'color' => '#6b7280', 'class' => 'badge-secondary']
        ],
        
        // סוגי תושבות
        'residentTypes' => [
            1 => ['text' => 'תושב', 'color' => '#10b981', 'class' => 'badge-success'],
            2 => ['text' => 'לא תושב', 'color' => '#f59e0b', 'class' => 'badge-warning']
        ],
        
        'statuses' => [
            'active' => ['text' => 'פעיל', 'color' => '#10b981'],
            'inactive' => ['text' => 'לא פעיל', 'color' => '#6b7280']
        ],

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
        'singularArticle' => 'את הרכישה',
        'plural' => 'רכישות',
        'nameField' => 'serialPurchaseId',
        'idField' => 'unicId',
        'hasParent' => false,
        'parentParam' => null,
        'defaultLimit' => 200,
        'defaultOrderBy' => 'createDate',
        'defaultSortDirection' => 'DESC',
        'table' => 'purchases',
        'title' => 'רכישות',
        'singular' => 'רכישה',
        'icon' => '📋',
        'primaryKey' => 'unicId',
        'parentKey' => null,
        
        // ⭐ שדות מה-VIEW - שמות נכונים!
        'queryFields' => [
            'id', 'unicId', 'serialPurchaseId', 'clientId', 'graveId',
            'price', 'calculated_price', 'dateOpening',
            'purchaseStatus', 'buyerStatus',  // ✅ שמות נכונים
            'numOfPayments', 'PaymentEndDate',
            'clientFullNameHe', 'clientNumId', 'contactFullNameHe',
            'graveNameHe', 'areaGraveNameHe', 'lineNameHe', 'plotNameHe', 
            'blockNameHe', 'cemeteryNameHe', 'graveStatus',
            'createDate', 'updateDate', 'isActive'
        ],
        
        'table_columns' => [
            ['field' => 'serialPurchaseId', 'title' => 'מספר רכישה', 'type' => 'text', 'width' => '120px', 'sortable' => true, 'searchable' => true],
            ['field' => 'clientFullNameHe', 'title' => 'שם לקוח', 'type' => 'text', 'width' => '180px', 'sortable' => true, 'searchable' => true],
            ['field' => 'clientNumId', 'title' => 'ת.ז. לקוח', 'type' => 'text', 'width' => '110px', 'sortable' => true],
            ['field' => 'graveNameHe', 'title' => 'קבר', 'type' => 'text', 'width' => '100px', 'sortable' => true],
            ['field' => 'areaGraveNameHe', 'title' => 'אחוזת קבר', 'type' => 'text', 'width' => '120px', 'sortable' => true],
            ['field' => 'plotNameHe', 'title' => 'חלקה', 'type' => 'text', 'width' => '100px', 'sortable' => true],
            ['field' => 'blockNameHe', 'title' => 'גוש', 'type' => 'text', 'width' => '100px', 'sortable' => true],
            ['field' => 'cemeteryNameHe', 'title' => 'בית עלמין', 'type' => 'text', 'width' => '120px', 'sortable' => true],
            ['field' => 'price', 'title' => 'סכום', 'type' => 'currency', 'width' => '100px', 'sortable' => true],
            ['field' => 'dateOpening', 'title' => 'תאריך רכישה', 'type' => 'date', 'width' => '110px', 'sortable' => true],
            ['field' => 'purchaseStatus', 'title' => 'סטטוס', 'type' => 'status', 'width' => '100px', 'sortable' => true, 'render' => 'formatPurchaseStatus'],
            ['field' => 'buyerStatus', 'title' => 'סוג רוכש', 'type' => 'type', 'width' => '100px', 'sortable' => true, 'render' => 'formatBuyerStatus'],
            ['field' => 'actions', 'title' => 'פעולות', 'type' => 'actions', 'width' => '120px', 'sortable' => false]
        ],
        
        'searchableFields' => [
            ['name' => 'serialPurchaseId', 'label' => 'מספר רכישה', 'type' => 'text', 'matchType' => ['exact', 'fuzzy', 'startsWith']],
            ['name' => 'clientFullNameHe', 'label' => 'שם לקוח', 'type' => 'text', 'matchType' => ['exact', 'fuzzy', 'startsWith']],
            ['name' => 'clientNumId', 'label' => 'ת.ז. לקוח', 'type' => 'text', 'matchType' => ['exact', 'startsWith']],
            ['name' => 'graveNameHe', 'label' => 'מספר קבר', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'purchaseStatus', 'label' => 'סטטוס רכישה', 'type' => 'select', 'matchType' => ['exact'],
                'options' => [
                    ['value' => 1, 'label' => 'טיוטה'],
                    ['value' => 2, 'label' => 'פעיל'],
                    ['value' => 3, 'label' => 'שולם']
                ]
            ],
            ['name' => 'buyerStatus', 'label' => 'סוג רוכש', 'type' => 'select', 'matchType' => ['exact'],
                'options' => [
                    ['value' => 1, 'label' => 'רוכש לעצמו'],
                    ['value' => 2, 'label' => 'רוכש לאחר']
                ]
            ],
            ['name' => 'cemeteryNameHe', 'label' => 'בית עלמין', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'blockNameHe', 'label' => 'גוש', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'plotNameHe', 'label' => 'חלקה', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'dateOpening', 'label' => 'תאריך רכישה', 'type' => 'date', 'matchType' => ['exact', 'before', 'after', 'between']]
        ],
        
        // סטטוסים
        'purchaseStatuses' => [
            1 => ['text' => 'טיוטה', 'color' => '#6b7280', 'class' => 'badge-secondary'],
            2 => ['text' => 'פעיל', 'color' => '#3b82f6', 'class' => 'badge-info'],
            3 => ['text' => 'שולם', 'color' => '#10b981', 'class' => 'badge-success']
        ],
        
        'buyerStatuses' => [
            1 => ['text' => 'רוכש לעצמו', 'color' => '#10b981', 'class' => 'badge-success'],
            2 => ['text' => 'רוכש לאחר', 'color' => '#3b82f6', 'class' => 'badge-info']
        ],
        
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/purchases-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],
        
        'search' => [
            'placeholder' => 'חיפוש רכישות לפי מספר, לקוח, קבר...',
            'minLength' => 0
        ],
        
        'enable_search' => true,
        'enable_filters' => true,
        'enable_export' => true,
        'items_per_page' => 999999,
        'enable_soft_delete' => true
    ],

    // ========================================
    // הגדרות קבורות (Burials)
    // ========================================
    'burial' => [
        'singularArticle' => 'את הקבורה',
        'plural' => 'קבורות',
        'nameField' => 'serialBurialId',
        'idField' => 'unicId',
        'hasParent' => false,
        'parentParam' => null,
        'defaultLimit' => 200,
        'defaultOrderBy' => 'createDate',
        'defaultSortDirection' => 'DESC',
        'table' => 'burials',
        'title' => 'קבורות',
        'singular' => 'קבורה',
        'icon' => '⚰️',
        'primaryKey' => 'unicId',
        'parentKey' => null,
        
        // ⭐ שדות מה-VIEW - שמות נכונים!
        'queryFields' => [
            'id', 'unicId', 'serialBurialId', 'clientId', 'graveId', 'purchaseId',
            'dateDeath', 'timeDeath', 'dateBurial', 'timeBurial',
            'placeDeath', 'nationalInsuranceBurial', 'deathAbroad',
            'dateOpening', 'reportingBL', 'contactId', 'kinship',
            'buriaLicense', 'comment',
            'clientFullNameHe', 'clientNumId', 'clientNameFather', 'clientNameMother',
            'contactFullNameHe',
            'graveNameHe', 'areaGraveNameHe', 'lineNameHe', 'plotNameHe', 
            'blockNameHe', 'cemeteryNameHe', 'graveStatus', 'graveComments',
            'cemeteryId', 'blockId', 'plotId',
            'createDate', 'updateDate', 'isActive'
        ],
        
        'table_columns' => [
            ['field' => 'serialBurialId', 'title' => 'מס׳ תיק קבורה', 'type' => 'text', 'width' => '130px', 'sortable' => true, 'searchable' => true],
            ['field' => 'clientFullNameHe', 'title' => 'נפטר/ת', 'type' => 'text', 'width' => '180px', 'sortable' => true, 'searchable' => true],
            ['field' => 'clientNumId', 'title' => 'ת.ז.', 'type' => 'text', 'width' => '110px', 'sortable' => true, 'searchable' => true],
            ['field' => 'clientNameFather', 'title' => 'שם האב', 'type' => 'text', 'width' => '120px', 'sortable' => false],
            ['field' => 'dateDeath', 'title' => 'תאריך פטירה', 'type' => 'date', 'width' => '110px', 'sortable' => true],
            ['field' => 'dateBurial', 'title' => 'תאריך קבורה', 'type' => 'date', 'width' => '110px', 'sortable' => true],
            ['field' => 'timeBurial', 'title' => 'שעה', 'type' => 'time', 'width' => '80px', 'sortable' => false],
            ['field' => 'graveNameHe', 'title' => 'קבר', 'type' => 'text', 'width' => '80px', 'sortable' => true],
            ['field' => 'areaGraveNameHe', 'title' => 'אחוזת קבר', 'type' => 'text', 'width' => '100px', 'sortable' => true],
            ['field' => 'plotNameHe', 'title' => 'חלקה', 'type' => 'text', 'width' => '100px', 'sortable' => true],
            ['field' => 'blockNameHe', 'title' => 'גוש', 'type' => 'text', 'width' => '100px', 'sortable' => true],
            ['field' => 'cemeteryNameHe', 'title' => 'בית עלמין', 'type' => 'text', 'width' => '120px', 'sortable' => true],
            ['field' => 'nationalInsuranceBurial', 'title' => 'ב.ל.', 'type' => 'boolean', 'width' => '70px', 'sortable' => false],
            ['field' => 'actions', 'title' => 'פעולות', 'type' => 'actions', 'width' => '120px', 'sortable' => false]
        ],
        
        'searchableFields' => [
            ['name' => 'serialBurialId', 'label' => 'מס׳ תיק קבורה', 'type' => 'text', 'matchType' => ['exact', 'fuzzy', 'startsWith']],
            ['name' => 'clientFullNameHe', 'label' => 'שם נפטר/ת', 'type' => 'text', 'matchType' => ['exact', 'fuzzy', 'startsWith']],
            ['name' => 'clientNumId', 'label' => 'ת.ז.', 'type' => 'text', 'matchType' => ['exact', 'startsWith']],
            ['name' => 'clientNameFather', 'label' => 'שם האב', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'graveNameHe', 'label' => 'מספר קבר', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'nationalInsuranceBurial', 'label' => 'ביטוח לאומי', 'type' => 'select', 'matchType' => ['exact'],
                'options' => [
                    ['value' => 'כן', 'label' => 'כן'],
                    ['value' => 'לא', 'label' => 'לא']
                ]
            ],
            ['name' => 'cemeteryNameHe', 'label' => 'בית עלמין', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'blockNameHe', 'label' => 'גוש', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'plotNameHe', 'label' => 'חלקה', 'type' => 'text', 'matchType' => ['exact', 'fuzzy']],
            ['name' => 'dateDeath', 'label' => 'תאריך פטירה', 'type' => 'date', 'matchType' => ['exact', 'before', 'after', 'between']],
            ['name' => 'dateBurial', 'label' => 'תאריך קבורה', 'type' => 'date', 'matchType' => ['exact', 'before', 'after', 'between']]
        ],
        
        // סטטוסים - ביטוח לאומי
        'nationalInsuranceStatuses' => [
            'כן' => ['text' => 'כן', 'color' => '#10b981', 'class' => 'badge-success'],
            'לא' => ['text' => 'לא', 'color' => '#ef4444', 'class' => 'badge-danger']
        ],
        
        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/burials-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],
        
        'search' => [
            'placeholder' => 'חיפוש קבורות לפי מספר תיק, שם נפטר, ת.ז., קבר...',
            'minLength' => 0
        ],
        
        'enable_search' => true,
        'enable_filters' => true,
        'enable_export' => true,
        'items_per_page' => 999999,
        'enable_soft_delete' => true
    ],

    // ========================================
    // הגדרות תשלומים (Payments)
    // ========================================
    'payment' => [
        'singularArticle' => 'את התשלום',
        'plural' => 'תשלומים',
        'nameField' => 'id',
        'idField' => 'id',
        'hasParent' => false,
        'parentParam' => null,
        'defaultLimit' => 200,
        'defaultOrderBy' => 'createDate',
        'defaultSortDirection' => 'DESC',
        'table' => 'payments',
        'title' => 'תשלומים',
        'singular' => 'תשלום',
        'icon' => '💰',
        'primaryKey' => 'id',
        'parentKey' => null,

        'queryFields' => [
            'id', 'plotType', 'graveType', 'resident', 'priceDefinition',
            'price', 'startPayment', 'createDate', 'updateDate', 'isActive'
        ],

        'table_columns' => [
            ['field' => 'id', 'title' => 'מזהה', 'type' => 'text', 'width' => '80px', 'sortable' => true],
            ['field' => 'plotType', 'title' => 'סוג חלקה', 'type' => 'badge', 'width' => '120px', 'sortable' => true, 'render' => 'getPlotTypeBadge'],
            ['field' => 'graveType', 'title' => 'סוג קבר', 'type' => 'text', 'width' => '100px', 'sortable' => true, 'render' => 'getGraveTypeBadge'],
            ['field' => 'resident', 'title' => 'תושב', 'type' => 'badge', 'width' => '120px', 'sortable' => true, 'render' => 'getResidentBadge'],
            ['field' => 'priceDefinition', 'title' => 'הגדרת מחיר', 'type' => 'text', 'width' => '150px', 'sortable' => true, 'render' => 'getPriceDefinitionBadge'],
            ['field' => 'price', 'title' => 'מחיר', 'type' => 'currency', 'width' => '120px', 'sortable' => true],
            ['field' => 'startPayment', 'title' => 'תאריך התחלה', 'type' => 'date', 'width' => '120px', 'sortable' => true],
            ['field' => 'createDate', 'title' => 'תאריך יצירה', 'type' => 'date', 'width' => '120px', 'sortable' => true],
            ['field' => 'actions', 'title' => 'פעולות', 'type' => 'actions', 'width' => '120px', 'sortable' => false]
        ],

        'searchableFields' => [
            ['name' => 'id', 'label' => 'מזהה', 'type' => 'text', 'matchType' => ['exact']],
            ['name' => 'plotType', 'label' => 'סוג חלקה', 'type' => 'select', 'matchType' => ['exact'],
                'options' => [
                    ['value' => '1', 'label' => 'פטורה'],
                    ['value' => '2', 'label' => 'חריגה'],
                    ['value' => '3', 'label' => 'סגורה']
                ]
            ],
            ['name' => 'graveType', 'label' => 'סוג קבר', 'type' => 'select', 'matchType' => ['exact'],
                'options' => [
                    ['value' => '1', 'label' => 'שדה'],
                    ['value' => '2', 'label' => 'רוויה'],
                    ['value' => '3', 'label' => 'סנהדרין']
                ]
            ],
            ['name' => 'resident', 'label' => 'תושב', 'type' => 'select', 'matchType' => ['exact'],
                'options' => [
                    ['value' => '1', 'label' => 'ירושלים'],
                    ['value' => '2', 'label' => 'חוץ'],
                    ['value' => '3', 'label' => 'חו״ל']
                ]
            ],
            ['name' => 'price', 'label' => 'מחיר', 'type' => 'number', 'matchType' => ['exact', 'greater', 'less', 'between']],
            ['name' => 'startPayment', 'label' => 'תאריך התחלה', 'type' => 'date', 'matchType' => ['exact', 'before', 'after', 'between']],
            ['name' => 'createDate', 'label' => 'תאריך יצירה', 'type' => 'date', 'matchType' => ['exact', 'before', 'after', 'between']]
        ],

        'plotTypes' => [
            '1' => ['text' => 'פטורה', 'color' => '#10b981', 'class' => 'badge-success'],
            '2' => ['text' => 'חריגה', 'color' => '#f97316', 'class' => 'badge-warning'],
            '3' => ['text' => 'סגורה', 'color' => '#dc2626', 'class' => 'badge-danger']
        ],

        'graveTypes' => [
            '1' => ['text' => 'שדה', 'color' => '#3b82f6', 'class' => 'badge-info'],
            '2' => ['text' => 'רוויה', 'color' => '#8b5cf6', 'class' => 'badge-primary'],
            '3' => ['text' => 'סנהדרין', 'color' => '#ec4899', 'class' => 'badge-pink']
        ],

        'residentTypes' => [
            '1' => ['text' => 'ירושלים', 'color' => '#10b981', 'class' => 'badge-success'],
            '2' => ['text' => 'חוץ', 'color' => '#f97316', 'class' => 'badge-warning'],
            '3' => ['text' => 'חו״ל', 'color' => '#dc2626', 'class' => 'badge-danger']
        ],

        'api' => [
            'endpoint' => '/dashboard/dashboards/cemeteries/api/payments-api.php',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ],

        'search' => [
            'placeholder' => 'חיפוש תשלומים לפי מזהה, סוג, מחיר...',
            'minLength' => 0
        ],

        'enable_search' => true,
        'enable_filters' => true,
        'enable_export' => true,
        'items_per_page' => 999999,
        'enable_soft_delete' => true
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

        'searchableFields' => [
            'residencyName',
            'residencyType',
            'countryNameHe',
            'cityNameHe',
            'description'
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

        'searchableFields' => [
            'countryNameHe',
            'countryNameEn'
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

        'searchableFields' => [
            'cityNameHe',
            'cityNameEn',
            'countryNameHe'
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