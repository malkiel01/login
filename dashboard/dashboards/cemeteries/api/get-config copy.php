<?php
// קובץ: api/get-config.php
header('Content-Type: application/json; charset=utf-8');

// טען את הקונפיג
$config = require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/cemetery-hierarchy-config.php';


$type = $_GET['type'] ?? '';  // cemetery / block / plot...
$section = $_GET['section'] ?? '';  // table_columns / form_fields...

// החזר את החלק המבוקש
echo json_encode([
    'success' => true,
    'data' => $config[$type][$section] ?? []
]);
?>