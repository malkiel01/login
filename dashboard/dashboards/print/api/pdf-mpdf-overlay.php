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

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Validate font family and return valid font name for mPDF
 * For now, just returns default font until we set up custom fonts
 */
function validateFontFamily2($fontFamily) {
    // רשימת פונטים נתמכים
    $validFonts = ['dejavusans', 'rubik', 'heebo', 'assistant'];
    
    if (in_array(strtolower($fontFamily), $validFonts)) {
        return strtolower($fontFamily);
    }
    
    return 'dejavusans';
}
function validateFontFamily($fontFamily) {
    // רשימת פונטים שעובדים
    // $validFonts = ['dejavusans', 'rubik'];  // הסרתי את heebo כרגע
    
    // if (in_array(strtolower($fontFamily), $validFonts)) {
    //     return strtolower($fontFamily);
    // }
    
    return 'dejavusans';
}

// Create directories if needed
@mkdir(dirname(__DIR__) . '/output', 0777, true);
@mkdir(dirname(__DIR__) . '/temp', 0777, true);

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
    $tempFile = dirname(__DIR__) . '/temp/template_' . uniqid() . '.pdf';
    $pdfContent = @file_get_contents($pdfUrl);
    
    if ($pdfContent === false) {
        throw new Exception("Could not download PDF from: $pdfUrl");
    }
    
    file_put_contents($tempFile, $pdfContent);
    
    // Detect orientation (default to portrait)
    $orientation = isset($input['orientation']) ? strtoupper($input['orientation']) : 'P';
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
        'tempDir' => dirname(__DIR__) . '/temp'
    ]);

    // Create mPDF instance
    // $pdf = new \Mpdf\Mpdf([
    //     'mode' => 'utf-8',
    //     'format' => $format,
    //     'orientation' => $orientation,
    //     'default_font' => 'dejavusans',
    //     'margin_left' => 0,
    //     'margin_right' => 0,
    //     'margin_top' => 0,
    //     'margin_bottom' => 0,
    //     'tempDir' => dirname(__DIR__) . '/temp',
    //     'fontDir' => [
    //         dirname(__DIR__) . '/vendor/mpdf/mpdf/ttfonts/',
    //         dirname(__DIR__) . '/assets/fonts/'
    //     ],
    //     'fontdata' => [
    //         'rubik' => [
    //             'R' => 'Rubik-Regular.ttf',
    //             'useOTL' => 0xFF,
    //         ],
    //         'heebo' => [
    //             'R' => 'Heebo-Regular.ttf', 
    //             'useOTL' => 0xFF,
    //         ],
    //         'assistant' => [
    //             'R' => 'Assistant-Regular.ttf',
    //             'useOTL' => 0xFF,
    //         ]
    //     ] + \Mpdf\Config\FontVariables::getDefaults()['fontdata']
    // ]);

    // -=-=-=-=-=-=-=-=-=-

    // // Create mPDF instance
    // $fontData = \Mpdf\Config\FontVariables::getDefaults();
    // $fontData['fontdata']['rubik'] = [
    //     'R' => 'Rubik-Regular.ttf',
    //     'useOTL' => 0xFF,
    // ];
    // $fontData['fontdata']['heebo'] = [
    //     'R' => 'Heebo-Regular.ttf',
    //     'useOTL' => 0xFF,
    // ];
    // $fontData['fontdata']['assistant'] = [
    //     'R' => 'Assistant-Regular.ttf',
    //     'useOTL' => 0xFF,
    // ];

    // $pdf = new \Mpdf\Mpdf([
    //     'mode' => 'utf-8',
    //     'format' => $format,
    //     'orientation' => $orientation,
    //     'default_font' => 'dejavusans',
    //     'margin_left' => 0,
    //     'margin_right' => 0,
    //     'margin_top' => 0,
    //     'margin_bottom' => 0,
    //     'tempDir' => dirname(__DIR__) . '/temp',
    //     'fontDir' => [
    //         dirname(__DIR__) . '/vendor/mpdf/mpdf/ttfonts/',
    //         dirname(__DIR__) . '/assets/fonts/'
    //     ],
    //     'fontdata' => $fontData['fontdata']
    // ]);

    // -=-=-=-=-=-=-=-=-=
    
    // Set RTL if needed
    if ($isRTL) {
        $pdf->SetDirectionality('rtl');
    }
    
    // Set template
    $pdf->SetDocTemplate($tempFile, true);
    
    // Add page with same orientation
    $pdf->AddPage($orientation);
    
    // Debug array
    $debugInfo = [];
    
    // Process each value individually with WriteText
    foreach ($values as $index => $value) {
        // Extract value properties
        $text = isset($value['text']) ? $value['text'] : '';
        $x = isset($value['x']) ? floatval($value['x']) : 100;
        $y = isset($value['y']) ? floatval($value['y']) : 100;
        $fontSize = isset($value['fontSize']) ? intval($value['fontSize']) : 12;
        $color = isset($value['color']) ? $value['color'] : '#000000';
        $fontFamily = isset($value['fontFamily']) ? $value['fontFamily'] : 'dejavusans';
        
        // Validate font family (currently returns 'dejavusans' always)
        $fontFamily = validateFontFamily($fontFamily);
        
        // Convert pixels to mm
        // A4 is 210mm x 297mm (portrait)
        // Standard screen resolution is 96 DPI
        // 1 inch = 25.4mm, 1 inch = 96 pixels
        // So: 1 pixel = 25.4/96 = 0.264583mm
        $x_mm = $x * 0.264583;
        $y_mm = $y * 0.264583;
        
        // Store debug info
        $debugInfo[] = [
            'text' => $text,
            'x_px' => $x,
            'y_px' => $y,
            'x_mm' => round($x_mm, 2),
            'y_mm' => round($y_mm, 2),
            'fontSize' => $fontSize,
            'color' => $color,
            'fontFamily' => $fontFamily,
            'requested_font' => isset($value['fontFamily']) ? $value['fontFamily'] : 'default'
        ];
        
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
        
        // Set font (currently always uses dejavusans)
        $pdf->SetFont($fontFamily, '', $fontSize);
        
        // Use WriteText for absolute positioning
        // WriteText places text at exact X,Y coordinates
        $pdf->WriteText($x_mm, $y_mm, $text);
    }
    
    // Generate filename - IMPORTANT: use the same filename for saving and URLs
    $filename = 'mpdf_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
    $outputPath = dirname(__DIR__) . '/output/' . $filename;
    
    // Save PDF
    $pdf->Output($outputPath, 'F');
    
    // Clean up temp file
    @unlink($tempFile);
    
    // Generate URLs
    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME']));
    $viewUrl = $baseUrl . '/output/' . $filename;
    $downloadUrl = $baseUrl . '/download.php?file=' . urlencode('output/' . $filename);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'PDF created successfully with mPDF overlay',
        'method' => 'mpdf-overlay-writetext',
        'filename' => $filename,
        'view_url' => $viewUrl,
        'download_url' => $downloadUrl,
        'direct_url' => $viewUrl,
        'rtl' => $isRTL,
        'orientation' => $orientation,
        'values_count' => count($values),
        'debug_positions' => $debugInfo,
        'note' => 'Using WriteText for precise positioning'
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