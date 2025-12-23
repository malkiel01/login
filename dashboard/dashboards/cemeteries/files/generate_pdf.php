<?php
/**
 * Generate PDF API
 * This is the main API endpoint for generating PDFs from templates
 */

header('Content-Type: application/json; charset=utf-8');

// Get JSON input
$input = file_get_contents('php://input');
$request = json_decode($input, true);

if (!$request || !isset($request['template_id']) || !isset($request['data'])) {
    echo json_encode([
        'success' => false,
        'error' => 'נתונים חסרים. נדרש: template_id, data'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$template_id = $request['template_id'];
$field_data = $request['data'];

// Validate template ID
if (!preg_match('/^template_[a-f0-9]+$/', $template_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'מזהה תבנית לא תקין'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Load template config
$template_dir = __DIR__ . '/templates/' . $template_id . '/';
$config_file = $template_dir . 'config.json';
$pdf_file = $template_dir . 'template.pdf';

if (!file_exists($config_file) || !file_exists($pdf_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'תבנית לא נמצאה'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = json_decode(file_get_contents($config_file), true);

// Build texts array for Python script
$texts = [];
foreach ($config['fields'] as $field) {
    $field_id = $field['id'];
    $text_value = isset($field_data[$field_id]) ? $field_data[$field_id] : $field['text'];
    
    $texts[] = [
        'text' => $text_value,
        'font' => $field['font'],
        'size' => $field['size'],
        'color' => $field['color'],
        'top' => $field['top'],
        'right' => $field['right']
    ];
}

// Create output directory
$output_dir = __DIR__ . '/generated/';
if (!file_exists($output_dir)) {
    mkdir($output_dir, 0755, true);
}

// Generate unique output filename
$output_filename = 'output_' . uniqid() . '.pdf';
$output_path = $output_dir . $output_filename;

// Create temp JSON file for texts
$texts_file = $output_dir . uniqid('texts_') . '.json';
file_put_contents($texts_file, json_encode($texts, JSON_UNESCAPED_UNICODE));

// Call Python script
$venv_python = '/home2/mbeplusc/public_html/form/login/venv/bin/python3';
$python_script = __DIR__ . '/add_text_to_pdf.py';

$command = sprintf(
    '%s %s %s %s %s 2>&1',
    $venv_python,
    escapeshellarg($python_script),
    escapeshellarg($pdf_file),
    escapeshellarg($output_path),
    escapeshellarg($texts_file)
);

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// Clean up texts file
@unlink($texts_file);

// Parse Python output
$python_output = implode("\n", $output);
$result = json_decode($python_output, true);

if ($return_var !== 0 || !$result || !isset($result['success']) || !$result['success']) {
    @unlink($output_path);
    
    echo json_encode([
        'success' => false,
        'error' => $result['error'] ?? 'שגיאה ביצירת PDF: ' . $python_output
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Build full URL for download
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_path = dirname($_SERVER['PHP_SELF']);
$pdf_url = $protocol . '://' . $host . $base_path . '/generated/' . $output_filename;

// Success
echo json_encode([
    'success' => true,
    'pdf_url' => $pdf_url,
    'filename' => $output_filename,
    'template_name' => $config['template_name']
], JSON_UNESCAPED_UNICODE);