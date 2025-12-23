<?php
/**
 * Get all templates list
 */

header('Content-Type: application/json; charset=utf-8');

$templates_json = __DIR__ . '/templates.json';

// אם הקובץ לא קיים - החזר רשימה ריקה
if (!file_exists($templates_json)) {
    echo json_encode([
        'success' => true,
        'templates' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// קרא את הקובץ
$data = json_decode(file_get_contents($templates_json), true);

// בדוק תקינות
if (!$data || !isset($data['templates'])) {
    echo json_encode([
        'success' => true,
        'templates' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// החזר את כל התבניות
echo json_encode([
    'success' => true,
    'templates' => $data['templates']
], JSON_UNESCAPED_UNICODE);