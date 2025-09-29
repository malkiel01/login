<?php
/**
 * Export PDF API
 * Location: /dashboard/dashboards/printPDF/api/export-pdf.php
 */

// Include configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('export', 'pdf_editor')) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'אין הרשאה לייצא קבצים'
    ]));
}

// Set headers
header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]));
}

try {
    // Get canvas data and document info
    $canvasData = $input['canvas'] ?? [];
    $documentInfo = $input['document'] ?? [];
    
    if (empty($canvasData)) {
        throw new Exception('Canvas data is required');
    }
    
    // Include TCPDF
    require_once TCPDF_PATH . 'tcpdf.php';
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('PDF Editor');
    $pdf->SetAuthor($_SESSION['user_name'] ?? 'User');
    $pdf->SetTitle('Exported Document');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Process canvas objects
    if (isset($canvasData['objects'])) {
        foreach ($canvasData['objects'] as $object) {
            processCanvasObject($pdf, $object);
        }
    }
    
    // Generate unique filename
    $filename = 'export_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
    $filepath = TEMP_PATH . $filename;
    
    // Save PDF
    $pdf->Output($filepath, 'F');
    
    // Get base64
    $base64 = base64_encode(file_get_contents($filepath));
    
    // Generate download URL
    $downloadUrl = PDF_EDITOR_URL . 'api/download.php?file=' . $filename . '&type=processed';
    
    // Return result
    echo json_encode([
        'success' => true,
        'message' => 'PDF exported successfully',
        'data' => [
            'filename' => $filename,
            'base64' => 'data:application/pdf;base64,' . $base64,
            'downloadUrl' => $downloadUrl,
            'size' => filesize($filepath)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Process canvas object and add to PDF
 */
function processCanvasObject($pdf, $object) {
    $type = $object['type'] ?? '';
    
    switch ($type) {
        case 'text':
        case 'i-text':
        case 'textbox':
            // Add text
            $text = $object['text'] ?? '';
            $x = ($object['left'] ?? 0) * 0.264583; // Convert pixels to mm
            $y = ($object['top'] ?? 0) * 0.264583;
            $fontSize = $object['fontSize'] ?? 12;
            $fontFamily = mapFontFamily($object['fontFamily'] ?? 'Arial');
            $color = hexToRGB($object['fill'] ?? '#000000');
            
            $pdf->SetFont($fontFamily, '', $fontSize);
            $pdf->SetTextColor($color['r'], $color['g'], $color['b']);
            $pdf->SetXY($x, $y);
            
            // Handle text alignment and direction
            $align = 'L';
            if (isset($object['textAlign'])) {
                $alignMap = [
                    'left' => 'L',
                    'center' => 'C',
                    'right' => 'R',
                    'justify' => 'J'
                ];
                $align = $alignMap[$object['textAlign']] ?? 'L';
            }
            
            $pdf->Cell(0, 0, $text, 0, 0, $align);
            break;
            
        case 'image':
            // Add image
            $src = $object['src'] ?? '';
            $x = ($object['left'] ?? 0) * 0.264583;
            $y = ($object['top'] ?? 0) * 0.264583;
            $width = ($object['width'] ?? 100) * ($object['scaleX'] ?? 1) * 0.264583;
            $height = ($object['height'] ?? 100) * ($object['scaleY'] ?? 1) * 0.264583;
            
            if (strpos($src, 'data:') === 0) {
                // Base64 image
                $pdf->Image('@' . base64_decode(explode(',', $src)[1]), $x, $y, $width, $height);
            } else if (file_exists($src)) {
                // File path
                $pdf->Image($src, $x, $y, $width, $height);
            }
            break;
            
        case 'rect':
            // Add rectangle
            $x = ($object['left'] ?? 0) * 0.264583;
            $y = ($object['top'] ?? 0) * 0.264583;
            $width = ($object['width'] ?? 100) * ($object['scaleX'] ?? 1) * 0.264583;
            $height = ($object['height'] ?? 100) * ($object['scaleY'] ?? 1) * 0.264583;
            
            // Set colors
            if (isset($object['fill'])) {
                $fillColor = hexToRGB($object['fill']);
                $pdf->SetFillColor($fillColor['r'], $fillColor['g'], $fillColor['b']);
            }
            
            if (isset($object['stroke'])) {
                $strokeColor = hexToRGB($object['stroke']);
                $pdf->SetDrawColor($strokeColor['r'], $strokeColor['g'], $strokeColor['b']);
            }
            
            $pdf->Rect($x, $y, $width, $height, 'DF');
            break;
    }
}

/**
 * Map font family to TCPDF font
 */
function mapFontFamily($fontFamily) {
    $fontMap = [
        'Arial' => 'helvetica',
        'Helvetica' => 'helvetica',
        'Times New Roman' => 'times',
        'Courier' => 'courier',
        'Rubik' => 'dejavusans',
        'Heebo' => 'dejavusans'
    ];
    
    return $fontMap[$fontFamily] ?? 'helvetica';
}

/**
 * Convert hex color to RGB
 */
function hexToRGB($hex) {
    $hex = str_replace('#', '', $hex);
    
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    
    return ['r' => $r, 'g' => $g, 'b' => $b];
}
?>