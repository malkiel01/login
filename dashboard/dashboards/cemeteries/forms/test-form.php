<?php
// /dashboards/cemeteries/forms/test-render.php
// בדיקת רינדור הטופס

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>בדיקת רינדור טופס</h1>";

try {
    // טען את הקבצים
    require_once __DIR__ . '/FormBuilder.php';
    require_once __DIR__ . '/forms-config.php';
    
    echo "<p>✓ קבצים נטענו</p>";
    
    // צור FormBuilder
    $formBuilder = new FormBuilder('cemetery', null, null);
    echo "<p>✓ FormBuilder נוצר</p>";
    
    // קבל שדות
    $fields = getFormFields('cemetery', null);
    echo "<p>✓ נמצאו " . count($fields) . " שדות</p>";
    
    // הוסף שדות
    foreach ($fields as $field) {
        $options = [
            'required' => $field['required'] ?? false,
            'value' => '',
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
    echo "<p>✓ שדות נוספו ל-FormBuilder</p>";
    
    // נסה לרנדר
    echo "<h2>מנסה לרנדר את הטופס...</h2>";
    
    // בדוק אם המתודה קיימת
    if (method_exists($formBuilder, 'renderModal')) {
        echo "<p>✓ מתודת renderModal קיימת</p>";
        
        // נסה לרנדר
        $html = $formBuilder->renderModal();
        
        if ($html) {
            echo "<p>✓ HTML נוצר בהצלחה - אורך: " . strlen($html) . " תווים</p>";
            echo "<h3>תצוגה מקדימה:</h3>";
            echo "<div style='border: 2px solid #ccc; padding: 20px; margin: 20px 0;'>";
            echo $html;
            echo "</div>";
        } else {
            echo "<p>✗ לא נוצר HTML</p>";
        }
    } else {
        echo "<p>✗ מתודת renderModal לא נמצאה</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; background: #fee; border: 1px solid #fcc;'>";
    echo "<h3>שגיאה:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

// הצג שגיאות PHP אם יש
$error = error_get_last();
if ($error) {
    echo "<h3>שגיאת PHP אחרונה:</h3>";
    echo "<pre>";
    print_r($error);
    echo "</pre>";
}
?>