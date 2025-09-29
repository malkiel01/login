<?php
/**
 * Upload File API
 * Location: /dashboard/dashboards/printPDF/api/upload-file.php
 */

// Include configuration
require_once '../config.php';

// Check user access
checkUserAccess();

// Set headers
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]));
}

// Verify CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]));
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        throw new Exception('לא נבחר קובץ');
    }
    
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(getUploadErrorMessage($file['error']));
    }
    
    // Validate file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('הקובץ גדול מדי (מקסימום ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB)');
    }
    
    // Validate file type
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension'] ?? '');
    
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        throw new Exception('סוג קובץ לא נתמך. סוגי קבצים מותרים: ' . implode(', ', ALLOWED_FILE_TYPES));
    }
    
    // Additional MIME type validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg'
    ];
    
    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('סוג קובץ לא תקין');
    }
    
    // Generate unique filename
    $uniqueName = uniqid('upload_') . '_' . time() . '.' . $extension;
    $uploadPath = TEMP_PATH . $uniqueName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('שגיאה בהעלאת הקובץ');
    }
    
    // Process based on file type
    $processedData = processUploadedFile($uploadPath, $extension, $mimeType);
    
    // Generate URL for the file
    $fileUrl = PDF_EDITOR_URL . 'api/get-file.php?file=' . $uniqueName;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'הקובץ הועלה בהצלחה',
        'data' => [
            'filename' => $file['name'],
            'uniqueName' => $uniqueName,
            'url' => $fileUrl,
            'size' => $file['size'],
            'type' => $mimeType,
            'extension' => $extension,
            ...array_merge($processedData)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Process uploaded file based on type
 */
function processUploadedFile($filepath, $extension, $mimeType) {
    $data = [];
    
    if ($extension === 'pdf') {
        // Get PDF info
        $data['pageCount'] = getPDFPageCount($filepath);
        $data['dimensions'] = getPDFDimensions($filepath);
    } else if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
        // Get image info
        $imageInfo = getimagesize($filepath);
        if ($imageInfo) {
            $data['width'] = $imageInfo[0];
            $data['height'] = $imageInfo[1];
            $data['dimensions'] = [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ];
            
            // Generate base64 for preview
            $imageData = file_get_contents($filepath);
            $data['preview'] = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        }
    }
    
    return $data;
}

/**
 * Get PDF page count
 */
function getPDFPageCount($filepath) {
    try {
        // Use TCPDF to get page count
        require_once TCPDF_PATH . 'tcpdi.php';
        $pdf = new TCPDI();
        $pageCount = $pdf->setSourceFile($filepath);
        return $pageCount;
    } catch (Exception $e) {
        return 1;
    }
}

/**
 * Get PDF dimensions
 */
function getPDFDimensions($filepath) {
    try {
        require_once TCPDF_PATH . 'tcpdi.php';
        $pdf = new TCPDI();
        $pdf->setSourceFile($filepath);
        $templateId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($templateId);
        
        return [
            'width' => $size['width'],
            'height' => $size['height'],
            'orientation' => $size['orientation']
        ];
    } catch (Exception $e) {
        return [
            'width' => 210,
            'height' => 297,
            'orientation' => 'P'
        ];
    }
}

/**
 * Get upload error message
 */
function getUploadErrorMessage($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'הקובץ גדול מהמותר בהגדרות השרת',
        UPLOAD_ERR_FORM_SIZE => 'הקובץ גדול מהמותר בטופס',
        UPLOAD_ERR_PARTIAL => 'הקובץ הועלה באופן חלקי בלבד',
        UPLOAD_ERR_NO_FILE => 'לא נבחר קובץ להעלאה',
        UPLOAD_ERR_NO_TMP_DIR => 'תיקיית קבצים זמנית חסרה',
        UPLOAD_ERR_CANT_WRITE => 'נכשלה כתיבת הקובץ לדיסק',
        UPLOAD_ERR_EXTENSION => 'הרחבת PHP עצרה את העלאת הקובץ'
    ];
    
    return $errors[$errorCode] ?? 'שגיאה לא ידועה בהעלאת הקובץ';
}

?>