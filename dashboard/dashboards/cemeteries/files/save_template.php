<?php
/**
 * Save Template - Save PDF template with field configuration
 */

header('Content-Type: application/json; charset=utf-8');

// Directories
$templates_dir = __DIR__ . '/templates/';
$templates_json = __DIR__ . '/templates.json';

// Create templates directory if not exists
if (!file_exists($templates_dir)) {
    mkdir($templates_dir, 0755, true);
}

// Get data
if (!isset($_POST['template_data']) || !isset($_FILES['pdf_file'])) {
    echo json_encode([
        'success' => false,
        'error' => 'נתונים חסרים'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$template_data = json_decode($_POST['template_data'], true);
$pdf_file = $_FILES['pdf_file'];

// Validate
if (!$template_data || !isset($template_data['name'])) {
    echo json_encode([
        'success' => false,
        'error' => 'שם תבנית חסר'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$template_name = trim($template_data['name']);

// Validate template name
if (strlen($template_name) < 3) {
    echo json_encode([
        'success' => false,
        'error' => 'שם התבנית קצר מדי'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($template_name) > 50) {
    echo json_encode([
        'success' => false,
        'error' => 'שם התבנית ארוך מדי'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Load existing templates
$templates_list = [];
if (file_exists($templates_json)) {
    $templates_list = json_decode(file_get_contents($templates_json), true);
    if (!$templates_list || !isset($templates_list['templates'])) {
        $templates_list = ['templates' => []];
    }
}

// Check for duplicate name
foreach ($templates_list['templates'] as $existing) {
    if (strcasecmp($existing['name'], $template_name) === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'שם תבנית זה כבר קיים. נא לבחור שם אחר.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Validate PDF file
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $pdf_file['tmp_name']);
finfo_close($finfo);

if ($mime_type !== 'application/pdf') {
    echo json_encode([
        'success' => false,
        'error' => 'הקובץ חייב להיות PDF'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Generate unique template ID
$template_id = 'template_' . uniqid();
$template_folder = $templates_dir . $template_id . '/';

// Create template folder
if (!mkdir($template_folder, 0755, true)) {
    echo json_encode([
        'success' => false,
        'error' => 'שגיאה ביצירת תיקיית תבנית'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Save PDF file
$pdf_filename = 'template.pdf';
$pdf_path = $template_folder . $pdf_filename;

if (!move_uploaded_file($pdf_file['tmp_name'], $pdf_path)) {
    @rmdir($template_folder);
    echo json_encode([
        'success' => false,
        'error' => 'שגיאה בשמירת קובץ PDF'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Create config.json
$config = [
    'template_id' => $template_id,
    'template_name' => $template_name,
    'description' => $template_data['description'] ?? '',
    'created_at' => date('Y-m-d\TH:i:s'),
    'original_filename' => $template_data['original_filename'] ?? 'unknown.pdf',
    'pdf_file' => $pdf_filename,
    'pdf_dimensions' => $template_data['pdf_dimensions'],
    'page_count' => $template_data['page_count'],
    'fields' => $template_data['fields']
];

$config_path = $template_folder . 'config.json';
file_put_contents($config_path, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Update templates.json
$templates_list['templates'][] = [
    'template_id' => $template_id,
    'name' => $template_name,
    'description' => $config['description'],
    'created_at' => $config['created_at'],
    'page_count' => $config['page_count'],
    'field_count' => count($config['fields'])
];

file_put_contents($templates_json, json_encode($templates_list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// Success
echo json_encode([
    'success' => true,
    'template_id' => $template_id,
    'message' => 'התבנית נשמרה בהצלחה'
], JSON_UNESCAPED_UNICODE);