<?php
/**
 * Logo Upload API
 * v1.0.0 - 2026-01-25
 *
 * Handles company logo upload
 */

// Load main config
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/middleware.php';

// Set headers
header('Content-Type: application/json; charset=utf-8');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication (admin only)
requireApiDashboard(['admin', 'cemetery_manager']);

// Define upload directory
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/company/';
$webPath = '/uploads/company/';

// Create directory if not exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'הקובץ גדול מדי',
            UPLOAD_ERR_FORM_SIZE => 'הקובץ גדול מדי',
            UPLOAD_ERR_PARTIAL => 'העלאה חלקית',
            UPLOAD_ERR_NO_FILE => 'לא נבחר קובץ',
            UPLOAD_ERR_NO_TMP_DIR => 'שגיאת שרת',
            UPLOAD_ERR_CANT_WRITE => 'שגיאת כתיבה',
            UPLOAD_ERR_EXTENSION => 'סיומת לא מורשית'
        ];
        $error = $_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new Exception($errorMessages[$error] ?? 'שגיאה לא ידועה');
    }

    $file = $_FILES['logo'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('סוג קובץ לא מורשה. יש להעלות תמונה (JPG, PNG, GIF, WebP או SVG)');
    }

    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('הקובץ גדול מדי. גודל מקסימלי: 2MB');
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    // Delete old logo if exists
    require_once __DIR__ . '/CompanySettingsManager.php';
    $conn = getDBConnection();
    $manager = CompanySettingsManager::getInstance($conn);
    $oldLogo = $manager->get('company_logo');

    if ($oldLogo && file_exists($_SERVER['DOCUMENT_ROOT'] . $oldLogo)) {
        @unlink($_SERVER['DOCUMENT_ROOT'] . $oldLogo);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('שגיאה בשמירת הקובץ');
    }

    // Save new logo path to settings
    $logoPath = $webPath . $filename;
    $manager->set('company_logo', $logoPath);

    echo json_encode([
        'success' => true,
        'message' => 'הלוגו הועלה בהצלחה',
        'path' => $logoPath
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
