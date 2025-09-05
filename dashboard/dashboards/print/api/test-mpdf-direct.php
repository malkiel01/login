<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start with text/plain to see any errors
header('Content-Type: text/plain');

echo "Direct test of pdf-mpdf-overlay.php logic:\n\n";

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Test the validateFontFamily function
function validateFontFamily($fontFamily) {
    return 'dejavusans';
}

try {
    // Simulate the exact input
    $input = [
        'language' => 'he',
        'orientation' => 'L',
        'values' => [
            [
                'text' => 'ggfg',
                'x' => 100,
                'y' => 100,
                'fontSize' => 12,
                'color' => '#000000',
                'fontFamily' => 'heebo',
                'fontUrl' => null
            ]
        ],
        'filename' => 'https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf'
    ];
    
    echo "1. Input OK\n";
    
    // Try to download the PDF
    $pdfUrl = $input['filename'];
    $tempFile = dirname(__DIR__) . '/temp/test_' . uniqid() . '.pdf';
    
    echo "2. Downloading PDF from: $pdfUrl\n";
    $pdfContent = @file_get_contents($pdfUrl);
    
    if ($pdfContent === false) {
        die("ERROR: Could not download PDF\n");
    }
    
    echo "3. PDF downloaded (" . strlen($pdfContent) . " bytes)\n";
    
    file_put_contents($tempFile, $pdfContent);
    echo "4. PDF saved to temp\n";
    
    // Create mPDF
    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'orientation' => 'L',
        'default_font' => 'dejavusans',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
        'tempDir' => dirname(__DIR__) . '/temp'
    ]);
    
    echo "5. mPDF created\n";
    
    $pdf->SetDirectionality('rtl');
    echo "6. RTL set\n";
    
    $pdf->SetDocTemplate($tempFile, true);
    echo "7. Template set\n";
    
    $pdf->AddPage('L');
    echo "8. Page added\n";
    
    // Process one value
    $value = $input['values'][0];
    $x_mm = $value['x'] * 0.264583;
    $y_mm = $value['y'] * 0.264583;
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', '', $value['fontSize']);
    $pdf->WriteText($x_mm, $y_mm, $value['text']);
    
    echo "9. Text written\n";
    
    // Save
    $outputPath = dirname(__DIR__) . '/output/test_' . uniqid() . '.pdf';
    $pdf->Output($outputPath, 'F');
    
    echo "10. PDF saved to: $outputPath\n";
    
    @unlink($tempFile);
    
    echo "\n✅ ALL STEPS COMPLETED SUCCESSFULLY!\n";
    
    // Now switch to JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Test passed']);
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}