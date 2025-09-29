<?php
/**
 * Export Image API
 * Location: /dashboard/dashboards/printPDF/api/export-image.php
 */

// Include configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('export', 'pdf_editor')) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'אין הרשאה לייצא תמונות'
    ]));
}

// Set headers
header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]));
}

try {
    // Get canvas data
    $canvasData = $input['canvas'] ?? '';
    $format = $input['format'] ?? 'png';
    
    if (empty($canvasData)) {
        throw new Exception('Canvas data is required');
    }
    
    // Validate format
    $allowedFormats = ['png', 'jpg', 'jpeg'];
    if (!in_array($format, $allowedFormats)) {
        throw new Exception('Invalid image format');
    }
    
    // Decode base64 image
    if (strpos($canvasData, 'data:image') !== 0) {
        throw new Exception('Invalid image data');
    }
    
    // Extract base64 data
    $parts = explode(',', $canvasData);
    if (count($parts) !== 2) {
        throw new Exception('Invalid base64 format');
    }
    
    $imageData = base64_decode($parts[1]);
    if ($imageData === false) {
        throw new Exception('Failed to decode image');
    }
    
    // Generate unique filename
    $extension = ($format === 'jpg') ? 'jpeg' : $format;
    $filename = 'export_image_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
    $filepath = TEMP_PATH . $filename;
    
    // Save image
    if (!file_put_contents($filepath, $imageData)) {
        throw new Exception('Failed to save image');
    }
    
    // Process image if needed (resize, optimize, etc.)
    if ($format === 'jpg' || $format === 'jpeg') {
        optimizeJPEG($filepath);
    }
    
    // Generate download URL
    $downloadUrl = PDF_EDITOR_URL . 'api/download.php?file=' . $filename . '&type=processed';
    
    // Get file size
    $filesize = filesize($filepath);
    
    // Get image dimensions
    $imageInfo = getimagesize($filepath);
    $width = $imageInfo[0] ?? 0;
    $height = $imageInfo[1] ?? 0;
    
    // Return result
    echo json_encode([
        'success' => true,
        'message' => 'Image exported successfully',
        'data' => [
            'filename' => $filename,
            'format' => $format,
            'downloadUrl' => $downloadUrl,
            'size' => $filesize,
            'dimensions' => [
                'width' => $width,
                'height' => $height
            ],
            'base64' => $canvasData // Return original for preview
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Optimize JPEG image
 */
function optimizeJPEG($filepath, $quality = 85) {
    try {
        // Load image
        $image = imagecreatefromjpeg($filepath);
        if (!$image) {
            $image = imagecreatefrompng($filepath);
        }
        
        if ($image) {
            // Save with optimized quality
            imagejpeg($image, $filepath, $quality);
            imagedestroy($image);
        }
    } catch (Exception $e) {
        // If optimization fails, keep original
        error_log('Image optimization failed: ' . $e->getMessage());
    }
}
?>