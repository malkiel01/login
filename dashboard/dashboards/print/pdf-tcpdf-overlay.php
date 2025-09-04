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
        'message' => 'TCPDF overlay handler ready',
        'installed' => class_exists('TCPDF'),
        'method' => 'tcpdf-overlay'
    ]));
}

require_once __DIR__ . '/vendor/autoload.php';

// Include TCPDF
if (!class_exists('TCPDF')) {
    require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
}

// Include FPDI for PDF import
if (!class_exists('FPDI')) {
    require_once __DIR__ . '/vendor/setasign/fpdi/src/autoload.php';
}

// Create custom class that combines TCPDF with FPDI
class TCPDF_IMPORT extends FPDI {
    // Custom methods can be added here if needed
}

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
    
    // Detect orientation
    $orientation = isset($input['orientation']) ? $input['orientation'] : 'P';
    
    // Create TCPDF instance with FPDI
    $pdf = new TCPDF_IMPORT($orientation, 'mm', 'A4', true, 'UTF-8', false);
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins to 0
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    
    // Set RTL if needed
    if ($isRTL) {
        $pdf->setRTL(true);
    }
    
    // Set font for Hebrew/Arabic support
    $pdf->SetFont('dejavusans', '', 12, '', true);
    
    // Import the template
    $pageCount = $pdf->setSourceFile($tempFile);
    
    if ($pageCount > 0) {
        // Import first page
        $templateId = $pdf->importPage(1);
        
        // Add page
        $pdf->AddPage($orientation);
        
        // Use the template
        $pdf->useTemplate($templateId, 0, 0, null, null, true);
        
        // Add text overlays
        foreach ($values as $value) {
            // Extract value properties
            $text = isset($value['text']) ? $value['text'] : '';
            $x = isset($value['x']) ? floatval($value['x']) : 100;
            $y = isset($value['y']) ? floatval($value['y']) : 100;
            $fontSize = isset($value['fontSize']) ? intval($value['fontSize']) : 12;
            $color = isset($value['color']) ? $value['color'] : '#000000';
            $bold = isset($value['bold']) && $value['bold'] ? 'B' : '';
            $italic = isset($value['italic']) && $value['italic'] ? 'I' : '';
            $fontStyle = $bold . $italic;
            $align = isset($value['align']) ? $value['align'] : 'L';
            
            // Convert pixels to mm (approximate conversion)
            $x_mm = $x * 0.264583; // 1px ≈ 0.264583mm
            $y_mm = $y * 0.264583;
            
            // Convert hex color to RGB
            if (strpos($color, '#') === 0) {
                $hex = str_replace('#', '', $color);
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $pdf->SetTextColor($r, $g, $b);
            }
            
            // Set font with size
            $pdf->SetFont('dejavusans', $fontStyle, $fontSize);
            
            // For RTL text, adjust alignment
            if ($isRTL) {
                if ($align === 'L') $align = 'R';
                elseif ($align === 'R') $align = 'L';
            }
            
            // Write text at position
            $pdf->SetXY($x_mm, $y_mm);
            
            // Use MultiCell for better text handling
            $pdf->MultiCell(0, 0, $text, 0, $align, false, 0, $x_mm, $y_mm, true, 0, false, true, 0, 'T', false);
        }
    } else {
        // If no template, create blank page
        $pdf->AddPage($orientation);
        
        // Add text overlays
        foreach ($values as $value) {
            $text = isset($value['text']) ? $value['text'] : '';
            $x = isset($value['x']) ? floatval($value['x']) : 100;
            $y = isset($value['y']) ? floatval($value['y']) : 100;
            $fontSize = isset($value['fontSize']) ? intval($value['fontSize']) : 12;
            
            // Convert pixels to mm
            $x_mm = $x * 0.264583;
            $y_mm = $y * 0.264583;
            
            $pdf->SetFont('dejavusans', '', $fontSize);
            $pdf->SetXY($x_mm, $y_mm);
            $pdf->MultiCell(0, 0, $text, 0, 'L', false, 0, $x_mm, $y_mm, true, 0, false, true, 0, 'T', false);
        }
    }
    
    // Generate filename
    $outputFilename = 'output/tcpdf_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
    
    // Save PDF
    $pdf->Output(__DIR__ . '/' . $outputFilename, 'F');
    
    // Clean up temp file
    @unlink($tempFile);
    
    // Generate URLs
    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $viewUrl = $baseUrl . '/' . $outputFilename;
    $downloadUrl = $baseUrl . '/download.php?file=' . urlencode($outputFilename);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'PDF created successfully with TCPDF overlay',
        'method' => 'tcpdf-overlay',
        'filename' => $outputFilename,
        'view_url' => $viewUrl,
        'download_url' => $downloadUrl,
        'direct_url' => $viewUrl,
        'rtl' => $isRTL,
        'values_count' => count($values),
        'template_pages' => $pageCount
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'method' => 'tcpdf-overlay'
    ]);
    
    // Clean up on error
    if (isset($tempFile) && file_exists($tempFile)) {
        @unlink($tempFile);
    }
}