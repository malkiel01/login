<?php
/**
 * Delete template
 */

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$request = json_decode($input, true);

if (!$request || !isset($request['template_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'מזהה תבנית חסר'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$template_id = $request['template_id'];

// Validate template ID
if (!preg_match('/^template_[a-f0-9]+$/', $template_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'מזהה תבנית לא תקין'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$template_dir = __DIR__ . '/templates/' . $template_id . '/';
$templates_json = __DIR__ . '/templates.json';

if (!is_dir($template_dir)) {
    echo json_encode([
        'success' => false,
        'error' => 'תבנית לא נמצאה'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Delete directory recursively
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

if (!deleteDirectory($template_dir)) {
    echo json_encode([
        'success' => false,
        'error' => 'שגיאה במחיקת תיקיית התבנית'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Update templates.json
if (file_exists($templates_json)) {
    $data = json_decode(file_get_contents($templates_json), true);
    
    if ($data && isset($data['templates'])) {
        $data['templates'] = array_filter($data['templates'], function($t) use ($template_id) {
            return $t['template_id'] !== $template_id;
        });
        
        $data['templates'] = array_values($data['templates']); // Re-index array
        
        file_put_contents($templates_json, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

echo json_encode([
    'success' => true,
    'message' => 'התבנית נמחקה בהצלחה'
], JSON_UNESCAPED_UNICODE);