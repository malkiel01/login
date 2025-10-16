<?php
// ========================================
// קובץ 1: dashboard/dashboards/cemeteries/forms/country-form.php
// ========================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . 
'/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';


// === קבלת פרמטרים אחידה ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
$formType = basename(__FILE__, '.php'); // מזהה אוטומטי של סוג הטופס


try {
    $conn = getDBConnection();
    
    // טען מדינה אם בעריכה
    $country = null;
    if ($itemId) {
        $stmt = $conn->prepare("
            SELECT * FROM countries 
            WHERE unicId = ? AND isActive = 1
        ");
        $stmt->execute([$itemId]);
        $country = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('country', $itemId, $parentId);

// שם מדינה בעברית
$formBuilder->addField('countryNameHe', 'שם מדינה בעברית', 'text', [
    'required' => true,
    'placeholder' => 'לדוגמה: ישראל',
    'value' => $country['countryNameHe'] ?? ''
]);

// שם מדינה באנגלית
$formBuilder->addField('countryNameEn', 'שם מדינה באנגלית', 'text', [
    'required' => true,
    'placeholder' => 'Example: Israel',
    'value' => $country['countryNameEn'] ?? ''
]);

// אם זה עריכה, הוסף את ה-unicId כשדה מוסתר
if ($country && $country['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', [
        'value' => $country['unicId']
    ]);
}

// הצג את הטופס עם FormBuilder
echo $formBuilder->renderModal();
?>