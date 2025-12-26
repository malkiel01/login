<?php
/**
 * PDF Processor - Server-side handler
 *
 * Phase 2 Refactoring: Uses new services with fallback to legacy code
 *
 * @version 2.0.0 (with backward compatibility)
 */

// Feature flag: Set to false to use legacy code entirely
define('USE_NEW_CODE', true);

// Try new refactored code first
if (USE_NEW_CODE) {
    try {
        // Load new system
        require_once __DIR__ . '/src/php/bootstrap.php';

        // Clean old files
        $pdfService = new \PDFEditor\Services\PDFService();
        $pdfService->cleanOldFiles(3600);

        // Get form data
        $allItems = [];
        if (isset($_POST['allItems'])) {
            $allItems = json_decode($_POST['allItems'], true) ?? [];
        }

        // Process PDF
        $result = $pdfService->process($_FILES['pdf'], $allItems);

        // Return success
        \PDFEditor\Core\Response::success([
            'pages' => $result['pages'],
            'width' => round($result['width'], 2),
            'height' => round($result['height'], 2),
            'output_file' => $result['output_file']
        ]);

    } catch (\Exception $e) {
        // Log error for debugging
        error_log("[" . date('Y-m-d H:i:s') . "] New code failed, falling back to legacy: " . $e->getMessage());

        // Fallback to legacy code below
        $use_legacy = true;
    }
}

// ===================================
// LEGACY CODE (Fallback)
// ===================================

if (!USE_NEW_CODE || isset($use_legacy)) {
    header('Content-Type: application/json; charset=utf-8');

    // Configuration
    $upload_dir = __DIR__ . '/uploads/';
    $output_dir = __DIR__ . '/outputs/';
    $python_script = __DIR__ . '/add_text_to_pdf.py';

    // נתיב ל-Python של venv
    $venv_python = '/home2/mbeplusc/public_html/form/login/venv/bin/python3';

    // Create directories if they don't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    if (!file_exists($output_dir)) {
        mkdir($output_dir, 0755, true);
    }

    // Clean old files (older than 1 hour)
    cleanOldFiles($upload_dir, 3600);
    cleanOldFiles($output_dir, 3600);

    // Check if file was uploaded
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'error' => 'לא התקבל קובץ או שגיאה בהעלאה'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // קבל את כל הנתונים
    $texts_json = isset($_POST['texts']) ? $_POST['texts'] : '[]';
    $images_json = isset($_POST['images']) ? $_POST['images'] : '[]';
    $allItems_json = isset($_POST['allItems']) ? $_POST['allItems'] : '[]';

    $texts = json_decode($texts_json, true);
    $images = json_decode($images_json, true);
    $allItems = json_decode($allItems_json, true);

    // Debug log
    error_log("PROCESS.PHP (LEGACY) - Received texts count: " . count($texts));
    error_log("PROCESS.PHP (LEGACY) - Received images count: " . count($images));
    error_log("PROCESS.PHP (LEGACY) - Received allItems count: " . count($allItems));

    $file = $_FILES['pdf'];

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime_type !== 'application/pdf') {
        echo json_encode([
            'success' => false,
            'error' => 'הקובץ חייב להיות PDF'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Generate unique filenames
    $unique_id = uniqid('pdf_', true);
    $input_filename = $unique_id . '_input.pdf';
    $output_filename = $unique_id . '_output.pdf';
    $input_path = $upload_dir . $input_filename;
    $output_path = $output_dir . $output_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $input_path)) {
        echo json_encode([
            'success' => false,
            'error' => 'שגיאה בשמירת הקובץ'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Create temp JSON file - שלח את allItems במקום texts
    $data_file = $upload_dir . $unique_id . '_data.json';
    $data_to_send = [
        'texts' => $texts,
        'images' => $images,
        'allItems' => $allItems
    ];
    file_put_contents($data_file, json_encode($data_to_send, JSON_UNESCAPED_UNICODE));

    // Call Python script
    $command = sprintf(
        '%s %s %s %s %s 2>&1',
        $venv_python,
        escapeshellarg($python_script),
        escapeshellarg($input_path),
        escapeshellarg($output_path),
        escapeshellarg($data_file)
    );

    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);

    // Clean up data file
    @unlink($data_file);

    // Parse Python output - סנן DEBUG messages
    $json_lines = [];
    foreach ($output as $line) {
        if (strpos($line, 'DEBUG:') === false) {
            $json_lines[] = $line;
        }
    }
    $python_output = implode("\n", $json_lines);
    $result = json_decode($python_output, true);

    if ($return_var !== 0 || !$result || !isset($result['success']) || !$result['success']) {
        // Clean up
        @unlink($input_path);
        @unlink($output_path);

        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'שגיאה בעיבוד הקובץ: ' . $python_output
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Clean up input file
    @unlink($input_path);

    // Return success with metadata
    echo json_encode([
        'success' => true,
        'pages' => $result['pages'],
        'width' => round($result['width'], 2),
        'height' => round($result['height'], 2),
        'output_file' => $output_filename
    ], JSON_UNESCAPED_UNICODE);

    function cleanOldFiles($dir, $max_age) {
        if (!is_dir($dir)) {
            return;
        }

        $now = time();
        $files = glob($dir . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= $max_age) {
                    @unlink($file);
                }
            }
        }
    }
}
