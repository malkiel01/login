<?php
/**
 * PDF Processor - Server-side handler
 * Receives PDF upload, processes it with Python script, and returns metadata
 */

header('Content-Type: application/json; charset=utf-8');

// Configuration
$upload_dir = __DIR__ . '/uploads/';
$output_dir = __DIR__ . '/outputs/';
$python_script = __DIR__ . '/add_text_to_pdf.py';

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

// Call Python script
// $command = sprintf(
//     'python3 %s %s %s 2>&1',
//     escapeshellarg($python_script),
//     escapeshellarg($input_path),
//     escapeshellarg($output_path)
// );

// נתיב ל-Python של venv
$venv_python = '/home2/mbeplusc/public_html/form/login/venv/bin/python3';

$command = sprintf(
    '%s %s %s %s 2>&1',
    $venv_python,
    escapeshellarg($python_script),
    escapeshellarg($input_path),
    escapeshellarg($output_path)
);

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// Parse Python output
$python_output = implode("\n", $output);
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

// Clean up input file (we don't need it anymore)
@unlink($input_path);

// Return success with metadata
echo json_encode([
    'success' => true,
    'pages' => $result['pages'],
    'width' => round($result['width'], 2),
    'height' => round($result['height'], 2),
    'output_file' => $output_filename
], JSON_UNESCAPED_UNICODE);

/**
 * Clean old files from directory
 * 
 * @param string $dir Directory path
 * @param int $max_age Maximum age in seconds
 */
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
