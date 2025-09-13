<?php
// /dashboards/cemeteries/forms/forms-config.php
// הגדרת שדות לטפסים - גרסה זמנית עד שנעדכן את הקונפיג המרכזי

require_once __DIR__ . '/../config.php';

/**
 * קבלת שדות לטופס - גרסה זמנית עם הגדרות קשיחות
 */
function getFormFields($type, $data = null) {
    
    // בינתיים, עד שנטמיע את הקונפיג המרכזי, נגדיר את השדות ישירות כאן
    // התאמה למבנה החדש של הטבלאות
    
    switch($type) {
        case 'cemetery':
            return [
                [
                    'name' => 'cemeteryNameHe',
                    'label' => 'שם בית עלמין בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם בית עלמין'
                ],
                [
                    'name' => 'cemeteryNameEn',
                    'label' => 'שם בית עלמין באנגלית',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'Enter cemetery name'
                ],
                [
                    'name' => 'cemeteryCode',
                    'label' => 'קוד בית עלמין',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'קוד ייחודי'
                ],
                [
                    'name' => 'nationalInsuranceCode',
                    'label' => 'קוד ביטוח לאומי',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'address',
                    'label' => 'כתובת',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 2,
                    'placeholder' => 'הזן כתובת מלאה'
                ],
                [
                    'name' => 'coordinates',
                    'label' => 'קואורדינטות',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'lat,lng'
                ],
                [
                    'name' => 'contactName',
                    'label' => 'שם איש קשר',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'contactPhoneName',
                    'label' => 'טלפון איש קשר',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => '050-0000000'
                ]
            ];
            
        case 'block':
            return [
                [
                    'name' => 'blockNameHe',
                    'label' => 'שם גוש בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם גוש'
                ],
                [
                    'name' => 'blockNameEn',
                    'label' => 'שם גוש באנגלית',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'blockCode',
                    'label' => 'קוד גוש',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'blockLocation',
                    'label' => 'מיקום',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'nationalInsuranceCode',
                    'label' => 'קוד ביטוח לאומי',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'coordinates',
                    'label' => 'קואורדינטות',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'comments',
                    'label' => 'הערות',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 3
                ]
            ];
            
        case 'plot':
            return [
                [
                    'name' => 'plotNameHe',
                    'label' => 'שם חלקה בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם חלקה'
                ],
                [
                    'name' => 'plotNameEn',
                    'label' => 'שם חלקה באנגלית',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'plotCode',
                    'label' => 'קוד חלקה',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'plotLocation',
                    'label' => 'מיקום',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'nationalInsuranceCode',
                    'label' => 'קוד ביטוח לאומי',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'coordinates',
                    'label' => 'קואורדינטות',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'comments',
                    'label' => 'הערות',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 3
                ]
            ];
            
        case 'row':
            return [
                [
                    'name' => 'lineNameHe',
                    'label' => 'שם שורה בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם שורה'
                ],
                [
                    'name' => 'lineNameEn',
                    'label' => 'שם שורה באנגלית',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'serialNumber',
                    'label' => 'מספר סידורי',
                    'type' => 'number',
                    'required' => false
                ],
                [
                    'name' => 'lineLocation',
                    'label' => 'מיקום',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'comments',
                    'label' => 'הערות',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 3
                ]
            ];
            
        case 'area_grave':
            return [
                [
                    'name' => 'areaGraveNameHe',
                    'label' => 'שם אחוזת קבר',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם אחוזת קבר'
                ],
                [
                    'name' => 'graveType',
                    'label' => 'סוג אחוזת קבר',
                    'type' => 'select',
                    'required' => false,
                    'options' => [
                        '' => '-- בחר סוג --',
                        '1' => 'שדה',
                        '2' => 'רוויה',
                        '3' => 'סנהדרין'
                    ]
                ],
                [
                    'name' => 'coordinates',
                    'label' => 'קואורדינטות',
                    'type' => 'text',
                    'required' => false
                ],
                [
                    'name' => 'comments',
                    'label' => 'הערות',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 3
                ]
            ];
            
        case 'grave':
            return [
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
                    'required' => true,
                    'options' => [
                        '' => '-- בחר סוג --',
                        '1' => 'פטורה',
                        '2' => 'חריגה',
                        '3' => 'סגורה'
                    ]
                ],
                [
                    'name' => 'graveStatus',
                    'label' => 'סטטוס קבר',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        '1' => 'פנוי',
                        '2' => 'נרכש',
                        '3' => 'קבור',
                        '4' => 'שמור'
                    ],
                    'default' => '1'
                ],
                [
                    'name' => 'graveLocation',
                    'label' => 'מיקום בשורה',
                    'type' => 'number',
                    'required' => false,
                    'min' => 1
                ],
                [
                    'name' => 'isSmallGrave',
                    'label' => 'קבר קטן',
                    'type' => 'checkbox',
                    'required' => false,
                    'default' => 0
                ],
                [
                    'name' => 'constructionCost',
                    'label' => 'עלות בנייה',
                    'type' => 'number',
                    'required' => false,
                    'step' => '0.01'
                ],
                [
                    'name' => 'comments',
                    'label' => 'הערות',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 3
                ]
            ];
            
        default:
            return [];
    }
}

/**
 * טעינת נתונים לעריכה
 */
function getFormData($type, $id) {
    try {
        $pdo = getDBConnection();
        
        // מיפוי סוג לטבלה
        $tables = [
            'cemetery' => 'cemeteries',
            'block' => 'blocks',
            'plot' => 'plots',
            'row' => 'rows',
            'area_grave' => 'areaGraves',
            'grave' => 'graves'
        ];
        
        $table = $tables[$type] ?? null;
        if (!$table) {
            return null;
        }
        
        // טען את הנתונים - נסה קודם לפי unicId ואז לפי id
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE unicId = :id OR id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error loading form data: ' . $e->getMessage());
        return null;
    }
}

/**
 * קבלת כותרת הטופס
 */
function getFormTitle($type, $isEdit = false) {
    $titles = [
        'cemetery' => ['singular' => 'בית עלמין', 'plural' => 'בתי עלמין'],
        'block' => ['singular' => 'גוש', 'plural' => 'גושים'],
        'plot' => ['singular' => 'חלקה', 'plural' => 'חלקות'],
        'row' => ['singular' => 'שורה', 'plural' => 'שורות'],
        'area_grave' => ['singular' => 'אחוזת קבר', 'plural' => 'אחוזות קבר'],
        'grave' => ['singular' => 'קבר', 'plural' => 'קברים']
    ];
    
    $typeTitle = $titles[$type]['singular'] ?? 'פריט';
    return $isEdit ? "עריכת $typeTitle" : "הוספת $typeTitle";
}

/**
 * בדיקת הרשאות - בינתיים מחזיר true לכולם
 */
function canUserEditField($fieldName, $type) {
    // TODO: להוסיף בדיקת הרשאות אמיתית
    return true;
}

/**
 * ולידציה בסיסית
 */
function validateFormData($type, $data) {
    $errors = [];
    $fields = getFormFields($type);
    
    foreach ($fields as $field) {
        if ($field['required'] && empty($data[$field['name']])) {
            $errors[] = "השדה {$field['label']} הוא חובה";
        }
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    return ['success' => true];
}

/**
 * סינון נתונים לפני שמירה
 */
function filterFormData($type, $data) {
    $fields = getFormFields($type);
    $filtered = [];
    
    foreach ($fields as $field) {
        if (isset($data[$field['name']])) {
            $filtered[$field['name']] = $data[$field['name']];
        }
    }
    
    return $filtered;
}
?>