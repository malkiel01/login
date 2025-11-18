<?php
/*
 * File: config/search-config.php
 * Version: 1.1.0
 * Updated: 2025-11-18
 * Author: Malkiel
 * Change Summary:
 * - v1.1.0: הוספת הגדרת 'grave' (קברים)
 *   - searchableFields מלא עבור graves
 *   - תואם למבנה של areaGrave
 * - v1.0.0: גרסה ראשונית עם areaGrave בלבד
 */

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
    
    // ⭐ הגדרת grave - קברים
    'grave' => [
        'searchableFields' => [
            [
                'name' => 'graveNameHe',
                'label' => 'שם קבר',
                'table' => 'graves',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy', 'startsWith']
            ],
            [
                'name' => 'graveStatus',
                'label' => 'סטטוס',
                'table' => 'graves',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'הכל'],
                    ['value' => '1', 'label' => 'פנוי'],
                    ['value' => '2', 'label' => 'נרכש'],
                    ['value' => '3', 'label' => 'קבור'],
                    ['value' => '4', 'label' => 'שמור']
                ],
                'matchType' => ['exact']
            ],
            [
                'name' => 'plotType',
                'label' => 'סוג חלקה',
                'table' => 'graves',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'הכל'],
                    ['value' => '1', 'label' => 'פטורה'],
                    ['value' => '2', 'label' => 'חריגה'],
                    ['value' => '3', 'label' => 'סגורה']
                ],
                'matchType' => ['exact']
            ],
            [
                'name' => 'isSmallGrave',
                'label' => 'גודל',
                'table' => 'graves',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => 'הכל'],
                    ['value' => '1', 'label' => 'קבר קטן'],
                    ['value' => '0', 'label' => 'קבר רגיל']
                ],
                'matchType' => ['exact']
            ],
            [
                'name' => 'area_grave_name',
                'label' => 'אחוזת קבר',
                'table' => 'graves_view',
                'type' => 'text',
                'matchType' => ['fuzzy']
            ],
            [
                'name' => 'row_name',
                'label' => 'שורה',
                'table' => 'graves_view',
                'type' => 'text',
                'matchType' => ['fuzzy']
            ],
            [
                'name' => 'comments',
                'label' => 'הערות',
                'table' => 'graves',
                'type' => 'text',
                'matchType' => ['exact', 'fuzzy']
            ],
            [
                'name' => 'createDate',
                'label' => 'תאריך יצירה',
                'table' => 'graves',
                'type' => 'date',
                'matchType' => ['exact', 'before', 'after', 'between', 'today', 'thisWeek', 'thisMonth']
            ]
        ]
    ],
    
    // ⭐ אפשר להוסיף entities נוספים בעתיד
    'cemetery' => [],
    'block' => [],
    'plot' => [],
    'row' => []
];