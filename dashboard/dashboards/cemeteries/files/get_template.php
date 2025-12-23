<?php
/**
 * Get single template details
 */

header('Content-Type: application/json; charset=utf-8');

$template_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($template_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'מזהה תבנית חסר'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

echo json_encode([
    'success' => true,
    'template' => $config
], JSON_UNESCAPED_UNICODE);