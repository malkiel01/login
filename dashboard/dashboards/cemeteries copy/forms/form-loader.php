<?php
// /dashboards/cemeteries/forms/form-loader.php
// טוען טופס דינמי לפי סוג

// דיבוג מלא
error_reporting(E_ALL);
ini_set('display_errors', 1);

// הגדר headers
header('Content-Type: text/html; charset=utf-8');

try {
    // קבלת פרמטרים
    $type = $_GET['type'] ?? '';
    $id = $_GET['id'] ?? null;
    $parent_id = $_GET['parent_id'] ?? null;
    $item_id = $_GET['item_id'] ?? null; // הוסף גם את זה
    
    // בדיקת סוג
    if (!$type) {
        throw new Exception('Type is required');
    }
    
    // בדוק אם יש טופס מותאם אישית
    $customFormFile = __DIR__ . "/{$type}-form.php";
    if (file_exists($customFormFile)) {
        // טען את הקונפיג אם צריך
        require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
        include $customFormFile;
        exit; // חשוב! לא להמשיך לטופס הגנרי
    }
    
    // אם אין טופס מותאם, המשך לטופס הגנרי
    // טען את הקבצים הנדרשים
    $formBuilderPath = __DIR__ . '/FormBuilder.php';
    $formsConfigPath = __DIR__ . '/forms-config.php';
    
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
    if ($id) {
        $data = getFormData($type, $id);
    }
    
    // בניית הטופס
    $formBuilder = new FormBuilder($type, $id, $parent_id);
    
    // הוספת השדות לפי הסוג
    $fields = getFormFields($type, $data);
    
    if (empty($fields)) {
        throw new Exception("No fields defined for type: " . $type);
    }
    
    foreach ($fields as $field) {
        $options = [
            'required' => $field['required'] ?? false,
            'value' => $data ? ($data[$field['name']] ?? '') : '',
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
    
    // החזרת ה-HTML של הטופס
    echo $formBuilder->renderModal();
    
} catch (Exception $e) {
    // הצג שגיאה ברורה
    echo "<div style='color: red; padding: 20px; background: #fee; border: 1px solid #fcc; margin: 20px; border-radius: 5px;'>";
    echo "<h3>שגיאה בטעינת הטופס:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>