<?php
/**
 * Upload File API
 * Location: /dashboard/dashboards/printPDF/api/upload-file.php
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to client
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
$configPath = dirname(__DIR__) . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // Basic configuration if config.php doesn't exist
    define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
}

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Helper function to check permissions
function checkUploadPermission() {
    // If checkPermission function exists from config.php, use it
    if (function_exists('checkPermission')) {
        return checkPermission('edit', 'pdf_editor');
    }
    // Otherwise allow for now (you can add your own permission logic)
    return true;
}

// Helper function to verify CSRF token
function verifyUploadCSRFToken($token) {
    // If verifyCSRFToken function exists from config.php, use it
    if (function_exists('verifyCSRFToken')) {
        return verifyCSRFToken($token);
    }
    // Basic CSRF check
    if (!isset($_SESSION['csrf_token'])) {
        return true; // Allow if no token in session
    }
    return $token === $_SESSION['csrf_token'];
}

// Check permissions
if (!checkUploadPermission()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'אין לך הרשאה להעלות קבצים'
    ]);
    exit();
}

// Verify CSRF token
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyUploadCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit();
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('לא נבחר קובץ או שהייתה שגיאה בהעלאה');
    }

    $file = $_FILES['file'];

    // Validate file size
    $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('הקובץ גדול מדי. הגודל המקסימלי הוא ' . ($maxSize / 1024 / 1024) . 'MB');
    }

    // Validate file type
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        // Check by extension as fallback
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('סוג הקובץ אינו נתמך. קבצים מותרים: PDF, JPG, PNG');
        }
    }

    // Create upload directory if it doesn't exist
    $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : dirname(__DIR__) . '/uploads/';
    
    // Create user-specific directory
    $userId = $_SESSION['user_id'] ?? 'guest';
    $userPath = $uploadPath . $userId . '/';
    
    if (!is_dir($userPath)) {
        if (!mkdir($userPath, 0755, true)) {
            error_log("Failed to create directory: $userPath");
            throw new Exception('לא ניתן ליצור תיקיית העלאה');
        }
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('file_', true) . '.' . $extension;
    $filePath = $userPath . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        error_log("Failed to move uploaded file to: $filePath");
        throw new Exception('שגיאה בשמירת הקובץ');
    }

    // Get file dimensions if it's an image
    $dimensions = null;
    if (strpos($mimeType, 'image/') === 0) {
        $imageInfo = getimagesize($filePath);
        if ($imageInfo) {
            $dimensions = [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ];
        }
    }

    // Generate public URL
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
               '://' . $_SERVER['HTTP_HOST'];
    $publicUrl = $baseUrl . '/dashboard/dashboards/printPDF/uploads/' . $userId . '/' . $filename;

    // Save file info to database (optional)
    // You can add database storage here if needed

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'הקובץ הועלה בהצלחה',
        'data' => [
            'filename' => $filename,
            'original_name' => $file['name'],
            'url' => $publicUrl,
            'path' => $filePath,
            'size' => $file['size'],
            'type' => $mimeType,
            'extension' => $extension,
            'dimensions' => $dimensions,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>