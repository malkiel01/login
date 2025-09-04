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
    
    // First, detect the orientation of the existing PDF
    $tempPdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/temp']);
    $pageCount = $tempPdf->SetSourceFile($tempFile);
    $pageInfo = $tempPdf->GetTemplateSize($tempPdf->ImportPage(1));
    
    // Determine orientation based on page dimensions
    $isLandscape = $pageInfo['width'] > $pageInfo['height'];
    $orientation = $isLandscape ? 'L' : 'P';
    
    // Allow override from input
    if (isset($input['orientation'])) {
        $orientation = strtoupper($input['orientation']) === 'L' ? 'L' : 'P';
    }
    
    $format = ($orientation === 'L') ? 'A4-L' : 'A4';
    
    // Create new mPDF instance with correct orientation
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
    
    // Get page dimensions in mm
    $pageWidth = $pdf->w;
    $pageHeight = $pdf->h;
    
    // Process each text value
    foreach ($values as $value) {
        // Extract value properties
        $text = isset($value['text']) ? $value['text'] : '';
        $x = isset($value['x']) ? floatval($value['x']) : 100;
        $y = isset($value['y']) ? floatval($value['y']) : 100;
        $fontSize = isset($value['fontSize']) ? intval($value['fontSize']) : 12;
        $color = isset($value['color']) ? $value['color'] : '#000000';
        $bold = isset($value['bold']) && $value['bold'];
        $italic = isset($value['italic']) && $value['italic'];
        $align = isset($value['align']) ? $value['align'] : 'L';
        
        // Convert pixels to mm (assuming 72 DPI)
        // 1 inch = 25.4mm, 1 inch = 72 points
        $x_mm = $x * 0.3527778; // pixels to mm conversion
        $y_mm = $y * 0.3527778;
        
        // Parse color
        $r = 0; $g = 0; $b = 0;
        if (strpos($color, '#') === 0) {
            $hex = str_replace('#', '', $color);
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        // Set text color
        $pdf->SetTextColor($r, $g, $b);
        
        // Set font
        $fontStyle = '';
        if ($bold) $fontStyle .= 'B';
        if ($italic) $fontStyle .= 'I';
        $pdf->SetFont('dejavusans', $fontStyle, $fontSize);
        
        // For RTL, adjust alignment
        if ($isRTL) {
            if ($align === 'L') $align = 'R';
            elseif ($align === 'R') $align = 'L';
            
            // Also adjust X position for RTL
            // We might need to calculate from right edge
            // $x_mm = $pageWidth - $x_mm;
        }
        
        // Method 1: Using SetXY and Write
        $pdf->SetXY($x_mm, $y_mm);
        
        if ($isRTL) {
            // For RTL text, we use WriteCell or MultiCell
            $pdf->MultiCell(
                0,              // width (0 = to the right margin)
                $fontSize * 0.3527778, // height in mm
                $text,
                0,              // border
                $align,         // alignment
                false,          // fill
                1,              // ln (move to next line)
                $x_mm,          // x position
                $y_mm,          // y position
                true,           // reset height
                0,              // stretch
                false,          // is html
                true,           // autopadding
                0,              // max height
                'T',            // vertical align
                false           // fit cell
            );
        } else {
            // For LTR text, we can use Write
            $pdf->SetXY($x_mm, $y_mm);
            $pdf->Write($fontSize * 0.3527778, $text);
        }
    }
    
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
        'orientation' => $orientation,
        'detected_landscape' => $isLandscape,
        'page_dimensions' => [
            'width' => round($pageWidth, 2) . 'mm',
            'height' => round($pageHeight, 2) . 'mm'
        ],
        'values_count' => count($values),
        'template_info' => [
            'original_width' => round($pageInfo['width'], 2),
            'original_height' => round($pageInfo['height'], 2)
        ]
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