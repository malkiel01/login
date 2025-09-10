<?php
// /dashboards/cemeteries/forms/test-form.php
// קובץ בדיקה לאבחון בעיות

echo "<h1>בדיקת מערכת טפסים</h1>";

// בדיקה 1: האם PHP עובד
echo "<p>✓ PHP עובד</p>";

// בדיקה 2: האם הקבצים קיימים
$files = [
    'FormBuilder.php',
    'forms-config.php',
    'form-loader.php',
    'form-handler.js'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "<p>✓ קובץ קיים: $file</p>";
    } else {
        echo "<p>✗ קובץ חסר: $file</p>";
    }
}

// בדיקה 3: טעינת FormBuilder
try {
    require_once __DIR__ . '/FormBuilder.php';
    echo "<p>✓ FormBuilder נטען בהצלחה</p>";
    
    // בדיקה אם המחלקה קיימת
    if (class_exists('FormBuilder')) {
        echo "<p>✓ מחלקת FormBuilder קיימת</p>";
    } else {
        echo "<p>✗ מחלקת FormBuilder לא נמצאה</p>";
    }
} catch (Exception $e) {
    echo "<p>✗ שגיאה בטעינת FormBuilder: " . $e->getMessage() . "</p>";
}

// בדיקה 4: טעינת forms-config
try {
    require_once __DIR__ . '/forms-config.php';
    echo "<p>✓ forms-config נטען בהצלחה</p>";
    
    // בדיקה אם הפונקציות קיימות
    if (function_exists('getFormFields')) {
        echo "<p>✓ פונקציית getFormFields קיימת</p>";
    } else {
        echo "<p>✗ פונקציית getFormFields לא נמצאה</p>";
    }
} catch (Exception $e) {
    echo "<p>✗ שגיאה בטעינת forms-config: " . $e->getMessage() . "</p>";
}

// בדיקה 5: יצירת FormBuilder
try {
    $formBuilder = new FormBuilder('cemetery', null, null);
    echo "<p>✓ FormBuilder נוצר בהצלחה</p>";
} catch (Exception $e) {
    echo "<p>✗ שגיאה ביצירת FormBuilder: " . $e->getMessage() . "</p>";
}

// בדיקה 6: קבלת שדות
try {
    $fields = getFormFields('cemetery', null);
    echo "<p>✓ השדות נטענו: " . count($fields) . " שדות</p>";
} catch (Exception $e) {
    echo "<p>✗ שגיאה בטעינת שדות: " . $e->getMessage() . "</p>";
}

phpinfo();
?>