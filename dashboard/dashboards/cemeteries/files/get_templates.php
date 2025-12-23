<?php
/**
 * Get templates - supports both list and single template
 */

header('Content-Type: application/json; charset=utf-8');

$template_id = isset($_GET['id']) ? $_GET['id'] : '';

// אם יש ID - החזר תבנית בודדת
if (!empty($template_id)) {
    // Validate template ID format
    if (!preg_match('/^template_[a-f0-9]+$/', $template_id)) {
        echo json_encode([
            'success' => false,
            'error' => 'מזהה תבנית לא תקין'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $template_dir = __DIR__ . '/templates/' . $template_id . '/';
    $config_file = $template_dir . 'config.json';
    
    if (!file_exists($config_file)) {
        echo json_encode([
            'success' => false,
            'error' => 'תבנית לא נמצאה'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $config = json_decode(file_get_contents($config_file), true);
    
    if (!$config) {
        echo json_encode([
            'success' => false,
            'error' => 'שגיאה בקריאת התבנית'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ← שים לב! החזר template בלי S
    echo json_encode([
        'success' => true,
        'template' => $config
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// אין ID - החזר רשימת כל התבניות
$templates_json = __DIR__ . '/templates.json';

if (!file_exists($templates_json)) {
    echo json_encode([
        'success' => true,
        'templates' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents($templates_json), true);

if (!$data || !isset($data['templates'])) {
    echo json_encode([
        'success' => true,
        'templates' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'templates' => $data['templates']
], JSON_UNESCAPED_UNICODE);