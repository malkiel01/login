<?php
// dashboard/dashboards/cemeteries/handlers/save-handler.php
// מטפל בשמירת נתונים - משתמש בקונפיג המרכזי

session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/HierarchyManager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ✅ קבלת פרמטרים - שמות אחידים בלבד
    $formType = $_POST['formType'] ?? $_POST['type'] ?? '';
    $itemId = $_POST['itemId'] ?? $_POST['id'] ?? null;
    $parentId = $_POST['parentId'] ?? $_POST['parent_id'] ?? null;
    
    if (!$formType) {
        throw new Exception('סוג לא תקין');
    }
    
    // טען את הקונפיג המרכזי
    $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
    if (!file_exists($configPath)) {
        throw new Exception('קובץ קונפיג לא נמצא');
    }
    
    $config = require $configPath;
    
    if (!isset($config[$formType])) {
        throw new Exception('סוג לא מוכר: ' . $formType);
    }
    
    // קבל את הגדרות הסוג מהקונפיג
    $typeConfig = $config[$formType];
    $table = $typeConfig['table'];
    $primaryKey = $typeConfig['primaryKey'];

    // מיפוי שדות ההורה לפי סוג
    $parentKeyMapping = [
        'block' => 'cemeteryId',
        'plot' => 'blockId', 
        'row' => 'plotId',
        'area_grave' => 'lineId',
        'grave' => 'areaGraveId'
    ];

    $parentKey = $parentKeyMapping[$formType] ?? $typeConfig['parentKey'] ?? null;
    
    error_log("Save handler - FormType: $formType, Table: $table, PrimaryKey: $primaryKey, ParentKey: $parentKey, ItemID: $itemId");
    
    // קבל חיבור למסד נתונים
    $pdo = getDBConnection();
    
    // קבל את תפקיד המשתמש
    $userRole = $_SESSION['dashboard_type'] ?? 'viewer';
    $manager = new HierarchyManager($pdo, $userRole);
    
    // קבל את השדות המותרים מהקונפיג
    $formFields = $manager->getFormFields($formType, $itemId ? 'edit' : 'create');
    
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
    if ($itemId) {
        // ✅ עריכה
        error_log("Updating record: ItemID=$itemId, FormType=$formType");
        
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
        
        $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE $primaryKey = :itemId";
        
        $stmt = $pdo->prepare($sql);
        $data['itemId'] = $itemId;
        
        error_log("Update SQL: $sql");
        error_log("Update data: " . json_encode($data));
        
        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('שגיאה בעדכון: ' . $errorInfo[2]);
        }
        
        $message = 'הנתונים עודכנו בהצלחה';
        $newId = $itemId;
        
    } else {
        // ✅ הוספה חדשה
        error_log("Creating new record for FormType: $formType");
        
        // בדוק הרשאות יצירה
        if (!$manager->canCreate()) {
            throw new Exception('אין הרשאה ליצור');
        }
        
        // צור מזהה ייחודי אם צריך
        if ($primaryKey === 'unicId' && !isset($data['unicId'])) {
            $data['unicId'] = generateUnicId($formType);
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
        error_log("Insert data: " . json_encode($data));
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($data)) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('שגיאה בהוספה: ' . $errorInfo[2]);
        }
        
        $message = 'הנתונים נוספו בהצלחה';
        $newId = $data['unicId'] ?? $pdo->lastInsertId();
    }
    
    // ✅ החזר תשובה
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $newId,
        'type' => $formType,
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
 * ✅ יצירת unicId ייחודי לפי סוג
 */
function generateUnicId($formType) {
    // קידומת לפי סוג
    $prefixes = [
        'cemetery' => 'CEM',
        'block' => 'BLK',
        'plot' => 'PLT',
        'row' => 'ROW',
        'area_grave' => 'ARG',
        'grave' => 'GRV'
    ];
    
    $prefix = $prefixes[$formType] ?? 'UNI';
    return $prefix . '_' . date('YmdHis') . '_' . rand(1000, 9999);
}
?>