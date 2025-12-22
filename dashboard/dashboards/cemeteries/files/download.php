<?php
/**
 * Download Handler for Processed PDFs
 */

// Configuration
$output_dir = __DIR__ . '/outputs/';

// Get requested file
$file = isset($_GET['file']) ? $_GET['file'] : '';

// Validate filename (security check)
if (empty($file) || !preg_match('/^pdf_[a-f0-9.]+_output\.pdf$/', $file)) {
    http_response_code(400);
    die('שם קובץ לא תקין');
}

$file_path = $output_dir . $file;

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    die('הקובץ לא נמצא');
}

// Set headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="processed_' . date('Y-m-d_H-i-s') . '.pdf"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file
readfile($file_path);

// Optionally delete file after download (uncomment if you want)
// @unlink($file_path);

exit;
