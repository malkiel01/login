<?php
/**
 * Batch Process API
 * Location: /dashboard/dashboards/printPDF/api/batch-process.php
 */

// Include configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('edit', 'pdf_editor')) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'אין הרשאה לבצע עיבוד קבוצתי'
    ]));
}

// Set headers
header('Content-Type: application/json');

// Increase time limit for batch processing
set_time_limit(300); // 5 minutes

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
    // Check if files were uploaded
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        throw new Exception('לא נבחרו קבצים לעיבוד');
    }
    
    // Get elements to apply
    $elementsJson = $_POST['elements'] ?? '[]';
    $elements = json_decode($elementsJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid elements data');
    }
    
    // Process uploaded files
    $files = reorganizeFilesArray($_FILES['files']);
    
    // Validate number of files
    if (count($files) > PDFEditorConfig::batch['maxFiles']) {
        throw new Exception('מספר הקבצים עולה על המגבלה המותרת (' . PDFEditorConfig::batch['maxFiles'] . ')');
    }
    
    // Process each file
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    foreach ($files as $index => $file) {
        try {
            // Validate file
            validateUploadedFile($file);
            
            // Save uploaded file
            $savedFile = saveUploadedFile($file);
            
            // Process file with elements
            $processedFile = processFileWithElements($savedFile, $elements);
            
            $results[] = [
                'index' => $index,
                'filename' => $file['name'],
                'status' => 'success',
                'result' => $processedFile
            ];
            
            $successCount++;
            
        } catch (Exception $e) {
            $results[] = [
                'index' => $index,
                'filename' => $file['name'],
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
            
            $failCount++;
        }
    }
    
    // Return results
    echo json_encode([
        'success' => true,
        'message' => "העיבוד הושלם: {$successCount} הצליחו, {$failCount} נכשלו",
        'data' => [
            'results' => $results,
            'summary' => [
                'total' => count($files),
                'success' => $successCount,
                'failed' => $failCount
            ]
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
 * Reorganize $_FILES array for multiple files
 */
function reorganizeFilesArray($files) {
    $reorganized = [];
    
    $fileCount = count($files['name']);
    $fileKeys = array_keys($files);
    
    for ($i = 0; $i < $fileCount; $i++) {
        $reorganized[$i] = [];
        foreach ($fileKeys as $key) {
            $reorganized[$i][$key] = $files[$key][$i];
        }
    }
    
    return $reorganized;
}

/**
 * Validate uploaded file
 */
function validateUploadedFile($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(getUploadErrorMessage($file['error']));
    }
    
    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('הקובץ גדול מדי (מקסימום ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB)');
    }
    
    // Check file type
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        throw new Exception('סוג קובץ לא נתמך');
    }
    
    // Check MIME type
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
    
    return true;
}

/**
 * Save uploaded file
 */
function saveUploadedFile($file) {
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $uniqueName = 'batch_' . uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = TEMP_PATH . $uniqueName;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('שגיאה בשמירת הקובץ');
    }
    
    return [
        'original_name' => $file['name'],
        'saved_name' => $uniqueName,
        'path' => $uploadPath,
        'extension' => $extension
    ];
}

/**
 * Process file with elements
 */
function processFileWithElements($fileInfo, $elements) {
    // Include TCPDF
    require_once TCPDF_PATH . 'tcpdf.php';
    
    // Include fonts configuration
    require_once FONTS_PATH . 'fonts-config.php';
    
    // Determine document type
    $document = [
        'file' => [
            'path' => $fileInfo['path']
        ]
    ];
    
    // Get file dimensions
    if ($fileInfo['extension'] === 'pdf') {
        require_once TCPDF_PATH . 'tcpdi.php';
        $pdf = new TCPDI();
        $pageCount = $pdf->setSourceFile($fileInfo['path']);
        if ($pageCount > 0) {
            $templateId = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($templateId);
            $document['size'] = [
                'width' => $size['width'],
                'height' => $size['height']
            ];
        }
    } else {
        // Image file
        $imageInfo = getimagesize($fileInfo['path']);
        if ($imageInfo) {
            $document['size'] = [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ];
        }
    }
    
    // Create processor instance
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/api/process-document.php';
    $processor = new PDFProcessor($document, $elements);
    
    // Process document
    $result = $processor->process();
    
    // Add original filename to result
    $result['original_filename'] = $fileInfo['original_name'];
    
    return $result;
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