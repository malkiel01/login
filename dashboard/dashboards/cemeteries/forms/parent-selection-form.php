<?php
// dashboard/dashboards/cemeteries/forms/parent-selection-form.php

session_start();
require_once __DIR__ . '/FormBuilder.php';
require_once dirname(__DIR__) . '/config.php';

// קבל את הנתונים מהסשן או מהפרמטרים
$childType = $_GET['child_type'] ?? $_SESSION['temp_child_type'] ?? '';
$parentType = $_GET['parent_type'] ?? $_SESSION['temp_parent_type'] ?? '';

// טען רשימת הורים
$pdo = getDBConnection();
$config = require dirname(__DIR__) . '/config/cemetery-hierarchy-config.php';

if (isset($config[$parentType])) {
    $parentConfig = $config[$parentType];
    $table = $parentConfig['table'];
    $nameField = $parentConfig['displayFields']['name'] ?? 'name';
    $primaryKey = $parentConfig['primaryKey'] ?? 'id';
    
    $sql = "SELECT $primaryKey, $nameField FROM $table WHERE isActive = 1 ORDER BY $nameField";
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // יצירת FormBuilder
    $formBuilder = new FormBuilder('parent_selection');
    
    // הוסף שדה select
    $options = [];
    foreach ($items as $item) {
        $options[$item[$primaryKey]] = $item[$nameField];
    }
    
    $parentTypeNames = [
        'cemetery' => 'בית עלמין',
        'block' => 'גוש',
        'plot' => 'חלקה',
        'row' => 'שורה',
        'area_grave' => 'אחוזת קבר'
    ];
    
    $parentLabel = $parentTypeNames[$parentType] ?? 'פריט הורה';
    
    $formBuilder->addField('parent_id', "בחר $parentLabel", 'select', [
        'required' => true,
        'options' => $options,
        'placeholder' => "-- בחר $parentLabel --"
    ]);
    
    // הוסף שדה נסתר עם הסוג
    $formBuilder->addField('child_type', '', 'hidden', [
        'value' => $childType
    ]);
    
    echo $formBuilder->renderModal();
}
?>