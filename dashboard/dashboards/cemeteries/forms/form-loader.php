<?php
// dashboard/dashboards/cemeteries/forms/form-loader.php
// טוען טופס דינמי לפי סוג - עם דיבוג מלא

// דיבוג מלא
error_reporting(E_ALL);
ini_set('display_errors', 1);

// הגדר headers
header('Content-Type: text/html; charset=utf-8');

try {
    // ✅ קבלת פרמטרים - שמות אחידים בלבד (לא משתנים מקומיים!)
    $formType = $_GET['formType'] ?? $_GET['type'] ?? '';
    $itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
    $parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
    
    error_log("Form Loader - Type: $formType, ItemID: $itemId, ParentID: $parentId");
    
    // בדוק אם יש טופס מותאם אישית
    $customFormFile = __DIR__ . "/{$formType}-form.php";
    if (file_exists($customFormFile)) {
        error_log("Custom form found: $customFormFile");
        require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
        include $customFormFile;
        exit;
    }
    
    // בדיקת קיום קבצים
    $configPath = dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';
    $hierarchyManagerPath = dirname(__DIR__) . '/classes/HierarchyManager.php';
    $formBuilderPath = __DIR__ . '/FormBuilder.php';
    $formsConfigPath = __DIR__ . '/forms-config.php';
    
    // בדוק אם הקונפיג המרכזי קיים
    if (file_exists($configPath)) {
        error_log("Loading config from: $configPath");
        $config = require $configPath;
        
        // בדוק אם יש הגדרות לסוג המבוקש
        if (isset($config[$formType])) {
            error_log("Config found for type: $formType");
            if (isset($config[$formType]['form_fields'])) {
                error_log("Form fields found: " . count($config[$formType]['form_fields']) . " fields");
            } else {
                error_log("No form_fields in config for type: $formType");
            }
        } else {
            error_log("No config for type: $formType");
            error_log("Available types: " . implode(', ', array_keys($config)));
        }
    }
    
    // טען את הקבצים הנדרשים
    if (!file_exists($formBuilderPath)) {
        throw new Exception("FormBuilder.php not found at: " . $formBuilderPath);
    }
    
    if (!file_exists($formsConfigPath)) {
        throw new Exception("forms-config.php not found at: " . $formsConfigPath);
    }
    
    require_once $formBuilderPath;
    require_once $formsConfigPath;
    
    // טעינת נתונים אם מדובר בעריכה
    $data = null;
    if ($itemId) {
        error_log("Loading data for ItemID: $itemId");
        $data = getFormData($formType, $itemId);
        if ($data) {
            error_log("Data loaded successfully");
        } else {
            error_log("No data found for ItemID: $itemId");
        }
    }
    
    // בניית הטופס
    $formBuilder = new FormBuilder($formType, $itemId, $parentId);
    
    // הוספת השדות לפי הסוג
    error_log("Getting form fields for type: $formType");

    if ($formType === 'parent_selector') {
        // כאן parentId מכיל את סוג ההורה (למשל 'cemetery')
        $parentType = $parentId;
        
        error_log("Parent selector - loading items for type: $parentType");
        
        // חיבור למסד נתונים
        require_once dirname(__DIR__) . '/config.php';
        $pdo = getDBConnection();
        
        // בדוק שיש קונפיג לסוג ההורה
        if (isset($config[$parentType])) {
            $parentConfig = $config[$parentType];
            $table = $parentConfig['table'];
            $nameField = $parentConfig['displayFields']['name'] ?? 'name';
            $primaryKey = $parentConfig['primaryKey'] ?? 'id';
            
            error_log("Loading from table: $table, field: $nameField");
            
            // שאילתה לטעינת הרשימה
            $sql = "SELECT $primaryKey as id, $nameField as name FROM $table WHERE isActive = 1 ORDER BY $nameField";
            $stmt = $pdo->query($sql);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($items) . " items");
            
            // המר לפורמט של options
            $options = [];
            foreach ($items as $item) {
                $options[$item['id']] = $item['name'];
            }
            
            // עדכן את השדות
            $fields = [
                [
                    'name' => 'selected_parent',
                    'label' => 'בחר ' . ($parentConfig['singular'] ?? 'הורה'),
                    'type' => 'select',
                    'required' => true,
                    'options' => $options,
                    'placeholder' => '-- בחר --',
                    'value' => ''
                ]
            ];
        } else {
            throw new Exception("No config found for parent type: $parentType");
        }
    } else {
        // הקוד הקיים - טעינת שדות רגילים
        $fields = getFormFields($formType, $data);
    }
    
    if (empty($fields)) {
        error_log("No fields returned from getFormFields");
        
        // נסה לטעון ישירות מהקונפיג כפתרון זמני
        if (file_exists($configPath)) {
            $config = require $configPath;
            if (isset($config[$formType]) && isset($config[$formType]['form_fields'])) {
                error_log("Loading fields directly from config");
                $fields = [];
                foreach ($config[$formType]['form_fields'] as $field) {
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
                        'value' => $data ? ($data[$field['name']] ?? '') : ''
                    ];
                }
                error_log("Loaded " . count($fields) . " fields directly from config");
            }
        }
        
        if (empty($fields)) {
            throw new Exception("No fields defined for type: " . $formType);
        }
    }
    
    error_log("Building form with " . count($fields) . " fields");
    
    foreach ($fields as $field) {
        $options = [
            'required' => $field['required'] ?? false,
            'value' => $field['value'] ?? ($data ? ($data[$field['name']] ?? '') : ''),
            'placeholder' => $field['placeholder'] ?? '',
            'options' => $field['options'] ?? [],
            'class' => $field['class'] ?? '',
            'readonly' => $field['readonly'] ?? false,
            'min' => $field['min'] ?? null,
            'max' => $field['max'] ?? null,
            'step' => $field['step'] ?? null,
            'rows' => $field['rows'] ?? 3
        ];
        
        $formBuilder->addField(
            $field['name'],
            $field['label'],
            $field['type'],
            $options
        );
    }
    
    error_log("Form built successfully, rendering modal");
    
    // החזרת ה-HTML של הטופס
    echo $formBuilder->renderModal();
    
    error_log("=== End Form Loader ===");
    
} catch (Exception $e) {
    // הצג שגיאה ברורה
    error_log("ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo "<div style='color: red; padding: 20px; background: #fee; border: 1px solid #fcc; margin: 20px; border-radius: 5px;'>";
    echo "<h3>שגיאה בטעינת הטופס:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>