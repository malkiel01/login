<?php
// dashboard/dashboards/cemeteries/forms/city-form.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once dirname(__DIR__) . '/config.php';

$itemId = $_GET['item_id'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parent_id'] ?? null;

try {
    $conn = getDBConnection();
    
    // טען מדינות
    $countriesStmt = $conn->prepare("
        SELECT unicId, countryNameHe 
        FROM countries 
        WHERE isActive = 1 
        ORDER BY countryNameHe
    ");
    $countriesStmt->execute();
    $countries = [];
    while ($row = $countriesStmt->fetch(PDO::FETCH_ASSOC)) {
        $countries[$row['unicId']] = $row['countryNameHe'];
    }
    
    // טען עיר אם בעריכה
    $city = null;
    if ($itemId) {
        $stmt = $conn->prepare("
            SELECT * FROM cities 
            WHERE unicId = ? AND isActive = 1
        ");
        $stmt->execute([$itemId]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('city', $itemId, $parentId);

// שדה מדינה
$formBuilder->addField('countryId', 'מדינה', 'select', [
    'required' => true,
    'options' => array_merge(
        ['' => '-- בחר מדינה --'],
        $countries
    ),
    'value' => $parentId ?? $city['countryId'] ?? ''
]);

// שם עיר בעברית
$formBuilder->addField('cityNameHe', 'שם עיר בעברית', 'text', [
    'required' => true,
    'placeholder' => 'לדוגמה: ירושלים',
    'value' => $city['cityNameHe'] ?? ''
]);

// שם עיר באנגלית
$formBuilder->addField('cityNameEn', 'שם עיר באנגלית', 'text', [
    'required' => true,
    'placeholder' => 'Example: Jerusalem',
    'value' => $city['cityNameEn'] ?? ''
]);

// אם זו עריכה, הוסף את ה-unicId
if ($city && $city['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', [
        'value' => $city['unicId']
    ]);
}

// הצג את הטופס
echo $formBuilder->renderModal();
?>