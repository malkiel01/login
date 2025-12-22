<?php
/**
 * Delete Handler for PDF Files
 */

header('Content-Type: application/json; charset=utf-8');

// Configuration
$upload_dir = __DIR__ . '/uploads/';
$output_dir = __DIR__ . '/outputs/';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['type']) || !isset($input['file'])) {
    echo json_encode([
        'success' => false,
        'error' => 'נתונים חסרים'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = $input['type'];
$filename = basename($input['file']); // Security: prevent path traversal

// Determine which directory
$dir = ($type === 'source') ? $upload_dir : $output_dir;
$file_path = $dir . $filename;

// Validate file exists
if (!file_exists($file_path)) {
    echo json_encode([
        'success' => false,
        'error' => 'הקובץ לא נמצא'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate it's actually in the correct directory (security)
if (realpath($file_path) !== $file_path || strpos(realpath($file_path), realpath($dir)) !== 0) {
    echo json_encode([
        'success' => false,
        'error' => 'נתיב לא חוקי'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Delete the file
if (@unlink($file_path)) {
    echo json_encode([
        'success' => true,
        'message' => 'הקובץ נמחק בהצלחה'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'שגיאה במחיקת הקובץ'
    ], JSON_UNESCAPED_UNICODE);
}