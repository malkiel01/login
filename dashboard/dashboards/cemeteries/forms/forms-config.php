<?php
// dashboard/dashboards/cemeteries/forms/forms-config.php
// הגדרת שדות לטפסים - גרסה מלאה עם תמיכה בהרשאות

// טען את המחלקות והקונפיג
require_once dirname(__DIR__) . '/classes/HierarchyManager.php';
require_once dirname(__DIR__) . '/config.php';

// טען את מיפוי ההרשאות אם קיים
$permissionsMapperPath = dirname(__DIR__) . '/includes/permissions-mapper.php';
if (file_exists($permissionsMapperPath)) {
    require_once $permissionsMapperPath;
}

/**
 * קבלת תפקיד המשתמש הנוכחי
 */
function getUserRole() {
    // אם יש פונקציית מיפוי, השתמש בה
    if (function_exists('getCurrentUserRole')) {
        return getCurrentUserRole();
    }
    
    // אחרת, מיפוי ידני
    $dashboardType = $_SESSION['dashboard_type'] ?? 'default';
    
    $mapping = [
        'cemetery_manager' => 'cemetery_manager',
        'admin' => 'cemetery_manager',
        'manager' => 'manager',
        'employee' => 'editor',
        'client' => 'viewer',
        'default' => 'viewer'
    ];
    
    return $mapping[$dashboardType] ?? 'viewer';
}

/**
 * קבלת שדות לטופס מהקונפיג המרכזי
 */
function getFormFields($type, $data = null) {
    try {
        // דיבוג הרשאות
        error_log("=== Getting Form Fields ===");
        error_log("Type: $type");
        error_log("Session dashboard_type: " . ($_SESSION['dashboard_type'] ?? 'not set'));
        error_log("Mapped role: " . getUserRole());
        
        // קבל את תפקיד המשתמש
        $userRole = getUserRole();
        
        // צור מופע של HierarchyManager
        $pdo = getDBConnection();
        $manager = new HierarchyManager($pdo, $userRole);
        
        // קבל את הקונפיג לסוג
        $config = $manager->getConfig($type);
        if (!$config || !isset($config['form_fields'])) {
            error_log("No config or form_fields for type: $type");
            // נסה לטעון ישירות מהקונפיג
            return getFallbackFields($type, $data);
        }
        
        // קבל את השדות מהקונפיג
        $mode = $data ? 'edit' : 'create';
        $formFields = $manager->getFormFields($type, $mode);
        
        error_log("Got " . count($formFields) . " fields from HierarchyManager");
        
        // המר לפורמט שה-FormBuilder מצפה לו
        $fields = [];
        foreach ($formFields as $field) {
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
        
        error_log("Returning " . count($fields) . " formatted fields");
        return $fields;
        
    } catch (Exception $e) {
        error_log('Error getting form fields: ' . $e->getMessage());
        // במקרה של שגיאה, החזר שדות ברירת מחדל
        return getFallbackFields($type, $data);
    }
}

/**
 * שדות ברירת מחדל אם המחלקה לא זמינה
 */
function getFallbackFields($type, $data = null) {
    error_log("Using fallback fields for type: $type");
    
    // טען ישירות מהקונפיג
    $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
    
    if (file_exists($configPath)) {
        $config = require $configPath;
        
        if (isset($config[$type]) && isset($config[$type]['form_fields'])) {
            $fields = [];
            $userRole = getUserRole();
            
            foreach ($config[$type]['form_fields'] as $field) {
                // בדוק הרשאות
                if (isset($field['permissions']) && !in_array($userRole, $field['permissions'])) {
                    continue; // דלג על שדות שאין הרשאה
                }
                
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
                
                if ($data && isset($data[$field['name']])) {
                    $fieldArray['value'] = $data[$field['name']];
                } elseif (isset($field['default'])) {
                    $fieldArray['value'] = $field['default'];
                }
                
                $fields[] = $fieldArray;
            }
            
            error_log("Loaded " . count($fields) . " fields from config for role: $userRole");
            return $fields;
        }
    }
    
    // אם גם זה נכשל, החזר מינימום שדות
    error_log("Returning minimal default fields");
    
    switch($type) {
        case 'cemetery':
            return [
                [
                    'name' => 'cemeteryNameHe',
                    'label' => 'שם בית עלמין',
                    'type' => 'text',
                    'required' => true,
                    'value' => $data['cemeteryNameHe'] ?? ''
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
        error_log("Loading form data for type: $type, id: $id");
        
        // בדוק הרשאות צפייה
        if (!hasUserPermission('view')) {
            error_log("User doesn't have view permission");
            return null;
        }
        
        $pdo = getDBConnection();
        $userRole = getUserRole();
        $manager = new HierarchyManager($pdo, $userRole);
        
        // קבל את הקונפיג לסוג
        $config = $manager->getConfig($type);
        if (!$config) {
            error_log("No config found for type: $type");
            return null;
        }
        
        $table = $config['table'];
        $primaryKey = $config['primaryKey'];
        
        // טען את הנתונים
        $sql = "SELECT * FROM $table WHERE $primaryKey = :id OR id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            error_log("Data loaded successfully");
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
    try {
        $userRole = getUserRole();
        $pdo = getDBConnection();
        $manager = new HierarchyManager($pdo, $userRole);
        
        $config = $manager->getConfig($type);
        if (!$config) {
            return $isEdit ? 'עריכת פריט' : 'הוספת פריט';
        }
        
        $singular = $config['singular'] ?? 'פריט';
        return $isEdit ? "עריכת $singular" : "הוספת $singular";
        
    } catch (Exception $e) {
        error_log('Error getting form title: ' . $e->getMessage());
        return $isEdit ? 'עריכת פריט' : 'הוספת פריט';
    }
}

/**
 * בדיקת הרשאות
 */
function hasUserPermission($permission) {
    $userRole = getUserRole();
    
    // טען את הקונפיג
    $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
    if (!file_exists($configPath)) {
        return false;
    }
    
    $config = require $configPath;
    $roleConfig = $config['permissions']['roles'][$userRole] ?? null;
    
    if (!$roleConfig) {
        return false;
    }
    
    switch ($permission) {
        case 'view':
            return $roleConfig['can_view_all'] ?? false;
        case 'edit':
            return $roleConfig['can_edit_all'] ?? false;
        case 'create':
            return $roleConfig['can_create_all'] ?? false;
        case 'delete':
            return $roleConfig['can_delete_all'] ?? false;
        default:
            return false;
    }
}

/**
 * בדיקת הרשאות לשדה
 */
function canUserEditField($fieldName, $type) {
    $userRole = getUserRole();
    
    // טען את הקונפיג
    $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
    if (!file_exists($configPath)) {
        return false;
    }
    
    $config = require $configPath;
    $typeConfig = $config[$type] ?? null;
    
    if (!$typeConfig || !isset($typeConfig['form_fields'])) {
        return false;
    }
    
    // חפש את השדה
    foreach ($typeConfig['form_fields'] as $field) {
        if ($field['name'] === $fieldName) {
            // אם אין הגדרת הרשאות, השדה פתוח לכולם
            if (!isset($field['permissions'])) {
                return true;
            }
            // בדוק אם התפקיד נמצא ברשימת ההרשאות
            return in_array($userRole, $field['permissions']);
        }
    }
    
    return false;
}

/**
 * ולידציה של נתונים
 */
function validateFormData($type, $data) {
    try {
        // בדוק הרשאות יצירה/עריכה
        $mode = isset($data['id']) ? 'edit' : 'create';
        if (!hasUserPermission($mode)) {
            return ['success' => false, 'errors' => ['אין לך הרשאה לבצע פעולה זו']];
        }
        
        $userRole = getUserRole();
        $pdo = getDBConnection();
        $manager = new HierarchyManager($pdo, $userRole);
        
        $config = $manager->getConfig($type);
        if (!$config) {
            return ['success' => false, 'errors' => ['סוג לא תקין']];
        }
        
        $errors = [];
        
        // עבור על כל השדות בקונפיג
        foreach ($config['form_fields'] as $field) {
            // בדוק אם למשתמש יש גישה לשדה זה
            if (!canUserEditField($field['name'], $type)) {
                continue;
            }
            
            $fieldName = $field['name'];
            $fieldValue = $data[$fieldName] ?? null;
            
            // בדיקת שדה חובה
            if (isset($field['required']) && $field['required'] && empty($fieldValue)) {
                $errors[] = "השדה {$field['label']} הוא חובה";
            }
            
            // בדיקות ולידציה נוספות
            if (isset($field['validation'])) {
                foreach ($field['validation'] as $rule) {
                    // כאן אפשר להוסיף כללי ולידציה נוספים
                    if (is_string($rule)) {
                        switch ($rule) {
                            case 'required':
                                if (empty($fieldValue)) {
                                    $errors[] = "השדה {$field['label']} הוא חובה";
                                }
                                break;
                        }
                    } elseif (strpos($rule, ':') !== false) {
                        list($ruleName, $ruleValue) = explode(':', $rule);
                        
                        switch ($ruleName) {
                            case 'minLength':
                                if (!empty($fieldValue) && mb_strlen($fieldValue) < $ruleValue) {
                                    $errors[] = "השדה {$field['label']} חייב להכיל לפחות {$ruleValue} תווים";
                                }
                                break;
                        }
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log('Error validating form data: ' . $e->getMessage());
        return ['success' => false, 'errors' => ['שגיאה בולידציה']];
    }
}

/**
 * סינון נתונים לפני שמירה
 */
function filterFormData($type, $data) {
    try {
        $userRole = getUserRole();
        $pdo = getDBConnection();
        $manager = new HierarchyManager($pdo, $userRole);
        
        // קבל את השדות המותרים
        $mode = isset($data['id']) ? 'edit' : 'create';
        $allowedFields = $manager->getFormFields($type, $mode);
        
        // סנן רק שדות מותרים
        $filtered = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field['name']])) {
                $filtered[$field['name']] = $data[$field['name']];
            }
        }
        
        return $filtered;
        
    } catch (Exception $e) {
        error_log('Error filtering form data: ' . $e->getMessage());
        return [];
    }
}

/**
 * פונקציה לדיבאג הרשאות
 */
function debugPermissions() {
    error_log("=== Debug Permissions ===");
    error_log("Dashboard Type: " . ($_SESSION['dashboard_type'] ?? 'not set'));
    error_log("Mapped Role: " . getUserRole());
    error_log("Can View: " . (hasUserPermission('view') ? 'YES' : 'NO'));
    error_log("Can Edit: " . (hasUserPermission('edit') ? 'YES' : 'NO'));
    error_log("Can Create: " . (hasUserPermission('create') ? 'YES' : 'NO'));
    error_log("Can Delete: " . (hasUserPermission('delete') ? 'YES' : 'NO'));
    error_log("========================");
}

error_log("forms-config.php loaded successfully - Full version with permissions");
?>