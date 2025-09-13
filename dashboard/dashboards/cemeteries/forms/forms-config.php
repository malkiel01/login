<?php
// /dashboards/cemeteries/forms/forms-config.php
// הגדרת שדות לטפסים - משתמש בקונפיג המרכזי

require_once __DIR__ . '/../classes/HierarchyManager.php';
require_once __DIR__ . '/../config.php';

/**
 * קבלת שדות לטופס מהקונפיג המרכזי
 */
function getFormFields($type, $data = null) {
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['user_role'] ?? 'viewer';
    
    // צור מופע של HierarchyManager
    $pdo = getDBConnection();
    $manager = new HierarchyManager($pdo, $userRole);
    
    // קבל את השדות מהקונפיג
    $mode = $data ? 'edit' : 'create';
    $formFields = $manager->getFormFields($type, $mode);
    
    // המר לפורמט שה-FormBuilder מצפה לו
    $fields = [];
    foreach ($formFields as $field) {
        $fields[] = [
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
            'readonly' => false // יתעדכן בהמשך לפי הרשאות
        ];
    }
    
    return $fields;
}

/**
 * טעינת נתונים לעריכה
 */
function getFormData($type, $id) {
    try {
        $pdo = getDBConnection();
        
        // קבל את תפקיד המשתמש
        $userRole = $_SESSION['user_role'] ?? 'viewer';
        $manager = new HierarchyManager($pdo, $userRole);
        
        // קבל את הקונפיג לסוג
        $config = $manager->getConfig($type);
        if (!$config) {
            return null;
        }
        
        $table = $config['table'];
        $primaryKey = $config['primaryKey'];
        
        // טען את הנתונים
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE $primaryKey = :id OR id = :id");
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
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['user_role'] ?? 'viewer';
    
    // צור מופע של HierarchyManager
    $pdo = getDBConnection();
    $manager = new HierarchyManager($pdo, $userRole);
    
    // קבל את הקונפיג
    $config = $manager->getConfig($type);
    if (!$config) {
        return $isEdit ? 'עריכת פריט' : 'הוספת פריט';
    }
    
    $singular = $config['singular'] ?? 'פריט';
    return $isEdit ? "עריכת $singular" : "הוספת $singular";
}

/**
 * בדיקת הרשאות
 */
function canUserEditField($fieldName, $type) {
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['user_role'] ?? 'viewer';
    
    // צור מופע של HierarchyManager
    $pdo = getDBConnection();
    $manager = new HierarchyManager($pdo, $userRole);
    
    // קבל את הקונפיג
    $config = $manager->getConfig($type);
    if (!$config) {
        return false;
    }
    
    // חפש את השדה בקונפיג
    foreach ($config['form_fields'] as $field) {
        if ($field['name'] === $fieldName) {
            // בדוק הרשאות
            if (isset($field['permissions'])) {
                return in_array($userRole, $field['permissions']);
            }
            // אם אין הגדרת הרשאות, השדה פתוח לכולם
            return true;
        }
    }
    
    return false;
}

/**
 * קבלת אפשרויות לשדה select
 */
function getFieldOptions($type, $fieldName) {
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['user_role'] ?? 'viewer';
    
    // צור מופע של HierarchyManager
    $pdo = getDBConnection();
    $manager = new HierarchyManager($pdo, $userRole);
    
    // קבל את הקונפיג
    $config = $manager->getConfig($type);
    if (!$config) {
        return [];
    }
    
    // חפש את השדה
    foreach ($config['form_fields'] as $field) {
        if ($field['name'] === $fieldName && isset($field['options'])) {
            return $field['options'];
        }
    }
    
    return [];
}

/**
 * ולידציה של נתונים
 */
function validateFormData($type, $data) {
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['user_role'] ?? 'viewer';
    
    // צור מופע של HierarchyManager
    $pdo = getDBConnection();
    $manager = new HierarchyManager($pdo, $userRole);
    
    // קבל את הקונפיג
    $config = $manager->getConfig($type);
    if (!$config) {
        return ['success' => false, 'errors' => ['סוג לא תקין']];
    }
    
    $errors = [];
    
    // עבור על כל השדות בקונפיג
    foreach ($config['form_fields'] as $field) {
        $fieldName = $field['name'];
        $fieldValue = $data[$fieldName] ?? null;
        
        // בדיקת שדה חובה
        if ($field['required'] && empty($fieldValue)) {
            $errors[] = "השדה {$field['label']} הוא חובה";
        }
        
        // בדיקות ולידציה נוספות
        if (isset($field['validation'])) {
            foreach ($field['validation'] as $rule) {
                if (is_string($rule)) {
                    // ולידציות פשוטות
                    switch ($rule) {
                        case 'required':
                            if (empty($fieldValue)) {
                                $errors[] = "השדה {$field['label']} הוא חובה";
                            }
                            break;
                            
                        case 'email':
                            if (!empty($fieldValue) && !filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                                $errors[] = "השדה {$field['label']} חייב להכיל כתובת אימייל תקינה";
                            }
                            break;
                    }
                } elseif (strpos($rule, ':') !== false) {
                    // ולידציות עם פרמטרים
                    list($ruleName, $ruleValue) = explode(':', $rule);
                    
                    switch ($ruleName) {
                        case 'minLength':
                            if (!empty($fieldValue) && strlen($fieldValue) < $ruleValue) {
                                $errors[] = "השדה {$field['label']} חייב להכיל לפחות {$ruleValue} תווים";
                            }
                            break;
                            
                        case 'maxLength':
                            if (!empty($fieldValue) && strlen($fieldValue) > $ruleValue) {
                                $errors[] = "השדה {$field['label']} לא יכול להכיל יותר מ-{$ruleValue} תווים";
                            }
                            break;
                            
                        case 'min':
                            if (!empty($fieldValue) && $fieldValue < $ruleValue) {
                                $errors[] = "הערך בשדה {$field['label']} חייב להיות לפחות {$ruleValue}";
                            }
                            break;
                            
                        case 'max':
                            if (!empty($fieldValue) && $fieldValue > $ruleValue) {
                                $errors[] = "הערך בשדה {$field['label']} לא יכול להיות יותר מ-{$ruleValue}";
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
}

/**
 * סינון נתונים לפני שמירה
 */
function filterFormData($type, $data) {
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['user_role'] ?? 'viewer';
    
    // צור מופע של HierarchyManager
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
}
?>