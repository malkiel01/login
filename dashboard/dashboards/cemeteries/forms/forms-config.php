<?php
// dashboard/dashboards/cemeteries/forms/forms-config.php
// גרסה פשוטה לדיבוג

// נסה לטעון את config.php
$configFile = dirname(__DIR__) . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

/**
 * קבלת שדות לטופס - גרסה פשוטה שעובדת
 */
function getFormFields($type, $data = null) {
    error_log("getFormFields called for type: $type");
    
    // נסה קודם לטעון מהקונפיג המרכזי
    $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
    
    if (file_exists($configPath)) {
        error_log("Loading config from: $configPath");
        $config = require $configPath;
        
        if (isset($config[$type]) && isset($config[$type]['form_fields'])) {
            error_log("Found form_fields in config for type: $type");
            
            $fields = [];
            foreach ($config[$type]['form_fields'] as $field) {
                $fieldArray = [
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'required' => $field['required'] ?? false,
                    'placeholder' => $field['placeholder'] ?? '',
                    'options' => $field['options'] ?? [],
                    'default' => $field['default'] ?? '',
                    'min' => $field['min'] ?? null,
                    'max' => $field['max'] ?? null,
                    'step' => $field['step'] ?? null,
                    'rows' => $field['rows'] ?? 3,
                    'readonly' => false,
                    'value' => ''
                ];
                
                // אם יש נתונים לעריכה, הוסף את הערך
                if ($data && isset($data[$field['name']])) {
                    $fieldArray['value'] = $data[$field['name']];
                } elseif (isset($field['default'])) {
                    $fieldArray['value'] = $field['default'];
                }
                
                $fields[] = $fieldArray;
            }
            
            error_log("Returning " . count($fields) . " fields from config");
            return $fields;
        }
    }
    
    // אם לא מצאנו בקונפיג, השתמש בהגדרות קשיחות
    error_log("Config not found, using hardcoded fields for type: $type");
    
    switch($type) {
        case 'cemetery':
            return [
                [
                    'name' => 'cemeteryNameHe',
                    'label' => 'שם בית עלמין בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם בית עלמין',
                    'value' => $data['cemeteryNameHe'] ?? ''
                ],
                [
                    'name' => 'cemeteryNameEn',
                    'label' => 'שם בית עלמין באנגלית',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'Enter cemetery name',
                    'value' => $data['cemeteryNameEn'] ?? ''
                ],
                [
                    'name' => 'cemeteryCode',
                    'label' => 'קוד בית עלמין',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'קוד ייחודי',
                    'value' => $data['cemeteryCode'] ?? ''
                ],
                [
                    'name' => 'nationalInsuranceCode',
                    'label' => 'קוד ביטוח לאומי',
                    'type' => 'text',
                    'required' => false,
                    'value' => $data['nationalInsuranceCode'] ?? ''
                ],
                [
                    'name' => 'address',
                    'label' => 'כתובת',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 2,
                    'placeholder' => 'הזן כתובת מלאה',
                    'value' => $data['address'] ?? ''
                ],
                [
                    'name' => 'coordinates',
                    'label' => 'קואורדינטות',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'lat,lng',
                    'value' => $data['coordinates'] ?? ''
                ],
                [
                    'name' => 'contactName',
                    'label' => 'שם איש קשר',
                    'type' => 'text',
                    'required' => false,
                    'value' => $data['contactName'] ?? ''
                ],
                [
                    'name' => 'contactPhoneName',
                    'label' => 'טלפון איש קשר',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => '050-0000000',
                    'value' => $data['contactPhoneName'] ?? ''
                ]
            ];
            
        case 'block':
            return [
                [
                    'name' => 'blockNameHe',
                    'label' => 'שם גוש בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם גוש',
                    'value' => $data['blockNameHe'] ?? ''
                ],
                [
                    'name' => 'blockNameEn',
                    'label' => 'שם גוש באנגלית',
                    'type' => 'text',
                    'required' => false,
                    'value' => $data['blockNameEn'] ?? ''
                ],
                [
                    'name' => 'blockCode',
                    'label' => 'קוד גוש',
                    'type' => 'text',
                    'required' => false,
                    'value' => $data['blockCode'] ?? ''
                ],
                [
                    'name' => 'blockLocation',
                    'label' => 'מיקום',
                    'type' => 'text',
                    'required' => false,
                    'value' => $data['blockLocation'] ?? ''
                ],
                [
                    'name' => 'comments',
                    'label' => 'הערות',
                    'type' => 'textarea',
                    'required' => false,
                    'rows' => 3,
                    'value' => $data['comments'] ?? ''
                ]
            ];
            
        case 'plot':
            return [
                [
                    'name' => 'plotNameHe',
                    'label' => 'שם חלקה בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם חלקה',
                    'value' => $data['plotNameHe'] ?? ''
                ]
            ];
            
        case 'row':
            return [
                [
                    'name' => 'lineNameHe',
                    'label' => 'שם שורה בעברית',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם שורה',
                    'value' => $data['lineNameHe'] ?? ''
                ]
            ];
            
        case 'area_grave':
            return [
                [
                    'name' => 'areaGraveNameHe',
                    'label' => 'שם אחוזת קבר',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן שם אחוזת קבר',
                    'value' => $data['areaGraveNameHe'] ?? ''
                ]
            ];
            
        case 'grave':
            return [
                [
                    'name' => 'graveNameHe',
                    'label' => 'מספר קבר',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'הזן מספר קבר',
                    'value' => $data['graveNameHe'] ?? ''
                ]
            ];
            
        default:
            error_log("Unknown type: $type");
            return [];
    }
}

/**
 * טעינת נתונים לעריכה
 */
function getFormData($type, $id) {
    try {
        error_log("getFormData called for type: $type, id: $id");
        
        // נסה לקבל חיבור למסד נתונים
        if (function_exists('getDBConnection')) {
            $pdo = getDBConnection();
        } else {
            error_log("getDBConnection function not found");
            return null;
        }
        
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
            error_log("Unknown type for table mapping: $type");
            return null;
        }
        
        // טען את הנתונים - נסה קודם לפי unicId ואז לפי id
        $sql = "SELECT * FROM $table WHERE unicId = :id OR id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            error_log("Data found for id: $id");
        } else {
            error_log("No data found for id: $id");
        }
        
        return $result;
        
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

error_log("forms-config.php loaded successfully");
?>