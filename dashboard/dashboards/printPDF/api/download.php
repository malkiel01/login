<?php
/**
 * Download API
 * Location: /dashboard/dashboards/printPDF/api/download.php
 * 
 * This API handles downloading of processed files
 */

// Include configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('download', 'pdf_editor')) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'אין הרשאה להוריד קבצים'
    ]));
}

// Get parameters
$file = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'processed'; // processed, template, project

// Validate file parameter
if (empty($file)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'File parameter is required'
    ]));
}

// Sanitize filename
$filename = basename($file);

// Determine file path based on type
switch ($type) {
    case 'processed':
        $filepath = TEMP_PATH . $filename;
        $downloadName = 'processed_' . $filename;
        break;
        
    case 'template':
        $filepath = TEMPLATES_PATH . $filename;
        $downloadName = 'template_' . $filename;
        break;
        
    case 'project':
        // For projects, we might need to generate the file first
        $filepath = TEMP_PATH . $filename;
        $downloadName = 'project_' . $filename;
        break;
        
    default:
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'message' => 'Invalid type parameter'
        ]));
}

// Check if file exists
if (!file_exists($filepath)) {
    // Try to generate the file if it's a project
    if ($type === 'project') {
        $generated = generateProjectFile($file);
        if (!$generated) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'הקובץ לא נמצא'
            ]));
        }
    } else {
        http_response_code(404);
        die(json_encode([
            'success' => false,
            'message' => 'הקובץ לא נמצא'
        ]));
    }
}

// Security check - ensure file is within allowed directory
$realpath = realpath($filepath);
$allowedPaths = [
    realpath(TEMP_PATH),
    realpath(TEMPLATES_PATH)
];

$isAllowed = false;
foreach ($allowedPaths as $allowedPath) {
    if (strpos($realpath, $allowedPath) === 0) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]));
}

// Get file info
$filesize = filesize($filepath);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Set content type
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'json' => 'application/json',
    'zip' => 'application/zip'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Set headers for download
header('Content-Type: ' . $contentType);
header('Content-Length: ' . $filesize);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
header('Pragma: public');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Clean output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
$handle = fopen($filepath, 'rb');
if ($handle !== false) {
    // Output file in chunks to handle large files
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
} else {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Failed to read file'
    ]));
}

// Log download
logActivity('file_downloaded', 'pdf_editor', $filename, [
    'type' => $type,
    'size' => $filesize,
    'user_id' => $_SESSION['user_id'] ?? 0
]);

// Clean up old temporary files (10% chance)
if (rand(1, 10) === 1) {
    cleanOldTempFiles();
}

exit;

/**
 * Generate project file for download
 */
function generateProjectFile($projectId) {
    try {
        $db = getPDFEditorDB();
        
        // Get project data
        $stmt = $db->prepare("
            SELECT * FROM " . DB_PREFIX . "projects 
            WHERE project_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            return false;
        }
        
        // Create JSON file
        $projectData = [
            'version' => PDF_EDITOR_VERSION,
            'exported' => date('c'),
            'project' => json_decode($project['data'], true)
        ];
        
        $filename = 'project_' . $projectId . '_' . date('Ymd_His') . '.json';
        $filepath = TEMP_PATH . $filename;
        
        file_put_contents($filepath, json_encode($projectData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return true;
        
    } catch (Exception $e) {
        error_log('Failed to generate project file: ' . $e->getMessage());
        return false;
    }
}

/**
 * Clean old temporary files
 */
function cleanOldTempFiles() {
    $files = glob(TEMP_PATH . '*');
    $now = time();
    $maxAge = 24 * 60 * 60; // 24 hours
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= $maxAge) {
                @unlink($file);
            }
        }
    }
}
?>