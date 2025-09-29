<?php
/**
 * Get File API
 * Location: /dashboard/dashboards/printPDF/api/get-file.php
 * 
 * This API serves temporary files for viewing/downloading
 */

// Include configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('view', 'pdf_editor')) {
    http_response_code(403);
    die('Access denied');
}

// Get file parameter
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    die('File parameter is required');
}

// Sanitize filename - remove any path traversal attempts
$filename = basename($filename);

// Build file path
$filepath = TEMP_PATH . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Check if file is within temp directory (security check)
$realpath = realpath($filepath);
$tempRealpath = realpath(TEMP_PATH);

if (strpos($realpath, $tempRealpath) !== 0) {
    http_response_code(403);
    die('Access denied');
}

// Determine content type
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'json' => 'application/json'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Check if it's a download request
$download = isset($_GET['download']) && $_GET['download'] === 'true';

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filepath));

if ($download) {
    // Force download
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    // Display inline (for PDFs and images)
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

// Cache headers (cache for 1 hour)
header('Cache-Control: private, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Output file
readfile($filepath);

// Log file access (optional)
logActivity('file_viewed', 'pdf_editor', $filename, [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
]);

exit;
?>