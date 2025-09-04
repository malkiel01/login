<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Test connection
if (isset($_GET['test'])) {
    die(json_encode([
        'success' => true,
        'message' => 'mPDF overlay handler ready',
        'installed' => class_exists('\Mpdf\Mpdf'),
        'method' => 'mpdf-overlay'
    ]));
}

require_once __DIR__ . '/vendor/autoload.php';

// Create directories if needed
@mkdir('output', 0777, true);
@mkdir('temp', 0777, true);

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['values']) || !is_array($input['values'])) {
        throw new Exception('Invalid input data');
    }
    
    // Extract parameters
    $values = $input['values'];
    $language = isset($input['language']) ? $input['language'] : 'he';
    $isRTL = ($language === 'he' || $language === 'ar');
    $pdfUrl = isset($input['filename']) ? $input['filename'] : null;
    
    // If no URL provided, use default template
    if (empty($pdfUrl)) {
        $pdfUrl = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
    }
    
    // Download template
    $tempFile = 'temp/template_' . uniqid() . '.pdf';
    $pdfContent = @file_get_contents($pdfUrl);
    
    if ($pdfContent === false) {
        throw new Exception("Could not download PDF from: $pdfUrl");
    }
    
    file_put_contents($tempFile, $pdfContent);
    
    // Detect orientation from the existing PDF (optional, default to portrait)
    $orientation = isset($input['orientation']) ? $input['orientation'] : 'P';
    $format = ($orientation === 'L') ? 'A4-L' : 'A4';
    
    // Create mPDF instance
    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => $format,
        'orientation' => $orientation,
        'default_font' => 'dejavusans',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
        'tempDir' => __DIR__ . '/temp'
    ]);
    
    // Set RTL if needed
    if ($isRTL) {
        $pdf->SetDirectionality('rtl');
    }
    
    // Set template
    $pdf->SetDocTemplate($tempFile, true);
    
    // Add page with same orientation
    $pdf->AddPage($orientation);
    
    // Build HTML from values
    $html = '<div' . ($isRTL ? ' dir="rtl"' : '') . '>';
    
    foreach ($values as $value) {
        // Extract value properties
        $text = isset($value['text']) ? $value['text'] : '';
        $x = isset($value['x']) ? intval($value['x']) : 100;
        $y = isset($value['y']) ? intval($value['y']) : 100;
        $fontSize = isset($value['fontSize']) ? intval($value['fontSize']) : 12;
        $color = isset($value['color']) ? $value['color'] : '#000000';
        $fontWeight = isset($value['bold']) && $value['bold'] ? 'bold' : 'normal';
        $fontStyle = isset($value['italic']) && $value['italic'] ? 'italic' : 'normal';
        $textAlign = isset($value['align']) ? $value['align'] : 'left';
        
        // For RTL, adjust alignment
        if ($isRTL && $textAlign === 'left') {
            $textAlign = 'right';
        } elseif ($isRTL && $textAlign === 'right') {
            $textAlign = 'left';
        }
        
        // Escape HTML
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Create positioned div for each text
        $html .= sprintf(
            '<div style="position: absolute; left: %dpx; top: %dpx; font-size: %dpt; color: %s; font-weight: %s; font-style: %s; text-align: %s;">%s</div>',
            $x,
            $y,
            $fontSize,
            $color,
            $fontWeight,
            $fontStyle,
            $textAlign,
            $text
        );
    }
    
    $html .= '</div>';
    
    // Write HTML to PDF
    $pdf->WriteHTML($html);
    
    // Generate filename
    $outputFilename = 'output/mpdf_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
    
    // Save PDF
    $pdf->Output($outputFilename, 'F');
    
    // Clean up temp file
    @unlink($tempFile);
    
    // Generate URLs
    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $viewUrl = $baseUrl . '/' . $outputFilename;
    $downloadUrl = $baseUrl . '/download.php?file=' . urlencode($outputFilename);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'PDF created successfully with mPDF overlay',
        'method' => 'mpdf-overlay',
        'filename' => $outputFilename,
        'view_url' => $viewUrl,
        'download_url' => $downloadUrl,
        'direct_url' => $viewUrl,
        'rtl' => $isRTL,
        'values_count' => count($values),
        'html_generated' => strlen($html) . ' bytes'
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'method' => 'mpdf-overlay'
    ]);
    
    // Clean up on error
    if (isset($tempFile) && file_exists($tempFile)) {
        @unlink($tempFile);
    }
}