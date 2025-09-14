<?php
// dashboard/dashboards/cemeteries/handlers/save-handler.php
// מטפל בשמירת נתונים - משתמש בקונפיג המרכזי

session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/HierarchyManager.php';

header('Content-Type: application/json; charset=utf-8');

 // לוג מפורט של כל מה שמתקבל
error_log("=== SAVE HANDLER DEBUG ===");
error_log("POST data: " . print_r($_POST, true));
error_log("Type: " . ($_POST['type'] ?? 'not set'));
error_log("Parent ID: " . ($_POST['parent_id'] ?? 'not set'));
error_log("ID: " . ($_POST['id'] ?? 'not set'));


try {
    // קבל נתונים
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? null;
    $parentId = $_POST['parent_id'] ?? null;
    
    if (!$type) {
        throw new Exception('סוג לא תקין');
    }
    
    // טען את הקונפיג המרכזי
    $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
    if (!file_exists($configPath)) {
        throw new Exception('קובץ קונפיג לא נמצא');
    }
    
    $config = require $configPath;
    
    if (!isset($config[$type])) {
        throw new Exception('סוג לא מוכר: ' . $type);
    }
    
    // קבל את הגדרות הסוג מהקונפיג
    $typeConfig = $config[$type];
    $table = $typeConfig['table'];
    $primaryKey = $typeConfig['primaryKey'];
    $parentKey = $typeConfig['parentKey']; // השתמש בשדה מהקונפיג!
    
    error_log("Save handler - Type: $type, Table: $table, PrimaryKey: $primaryKey, ParentKey: $parentKey");
    
    // קבל חיבור למסד נתונים
    $pdo = getDBConnection();
    
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['dashboard_type'] ?? 'viewer';
    $manager = new HierarchyManager($pdo, $userRole);
    
    // קבל את השדות המותרים מהקונפיג
    $formFields = $manager->getFormFields($type, $id ? 'edit' : 'create');
    
    // אסוף רק את השדות המותרים
    $data = [];
    foreach ($formFields as $field) {
        $fieldName = $field['name'];
        if (isset($_POST[$fieldName])) {
            // טיפול מיוחד ב-checkbox
            if ($field['type'] === 'checkbox') {
                $data[$fieldName] = isset($_POST[$fieldName]) ? 1 : 0;
            } else {
                $data[$fieldName] = $_POST[$fieldName];
            }
        } elseif ($field['type'] === 'checkbox') {
            // אם checkbox לא נשלח, הערך הוא 0
            $data[$fieldName] = 0;
        }
    }
    
    // הוסף את ה-parent key אם יש
    if ($parentId && $parentKey) {
        $data[$parentKey] = $parentId;
        error_log("Setting parent: $parentKey = $parentId");
    }
    
    // בדוק אם זו עריכה או הוספה
    if ($id) {
        // עריכה
        error_log("Updating record: $id");
        
        // בדוק הרשאות עריכה
        if (!$manager->canEdit()) {
            throw new Exception('אין הרשאה לערוך');
        }
        
        // הוסף תאריך עדכון
        $data['updateDate'] = date('Y-m-d H:i:s');
        
        // בנה שאילתת UPDATE
        $setClause = [];
        foreach ($data as $field => $value) {
            $setClause[] = "$field = :$field";
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE $primaryKey = :primaryKey";
        
        $stmt = $pdo->prepare($sql);
        $data['primaryKey'] = $id;
        
        error_log("Update SQL: $sql");
        error_log("Update data: " . print_r($data, true));
        
        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('שגיאה בעדכון: ' . $errorInfo[2]);
        }
        
        $message = 'הנתונים עודכנו בהצלחה';
        $newId = $id;
        
    } else {
        // הוספה חדשה
        error_log("Creating new record");
        
        // בדוק הרשאות יצירה
        if (!$manager->canCreate()) {
            throw new Exception('אין הרשאה ליצור');
        }
        
        // צור מזהה ייחודי אם צריך
        if ($primaryKey === 'unicId' && !isset($data['unicId'])) {
            $data['unicId'] = generateUnicId($type);
        }
        
        // הוסף תאריכים
        $data['createDate'] = date('Y-m-d H:i:s');
        $data['updateDate'] = date('Y-m-d H:i:s');
        
        // הוסף ערכי ברירת מחדל מהקונפיג
        foreach ($typeConfig['form_fields'] as $fieldConfig) {
            if (isset($fieldConfig['default']) && !isset($data[$fieldConfig['name']])) {
                $data[$fieldConfig['name']] = $fieldConfig['default'];
            }
        }
        
        // ודא שיש isActive
        if (!isset($data['isActive'])) {
            $data['isActive'] = 1;
        }
        
        // בנה שאילתת INSERT
        $fields = array_keys($data);
        $placeholders = array_map(function($f) { return ":$f"; }, $fields);
        
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        error_log("Insert SQL: $sql");
        error_log("Insert data: " . print_r($data, true));
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('שגיאה בהוספה: ' . $errorInfo[2]);
        }
        
        $message = 'הנתונים נוספו בהצלחה';
        $newId = $data['unicId'] ?? $pdo->lastInsertId();
    }
    
    // החזר תשובה
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $newId,
        'type' => $type,
        'parentId' => $parentId
    ]);
    
} catch (Exception $e) {
    error_log('Save error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * יצירת unicId ייחודי לפי סוג
 */
function generateUnicId($type) {
    // קידומת לפי סוג
    $prefixes = [
        'cemetery' => 'CEM',
        'block' => 'BLK',
        'plot' => 'PLT',
        'row' => 'ROW',
        'area_grave' => 'ARG',
        'grave' => 'GRV'
    ];
    
    $prefix = $prefixes[$type] ?? 'UNI';
    return $prefix . '_' . date('YmdHis') . '_' . rand(1000, 9999);
}
?>