<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "Testing font configuration...\n\n";

require_once dirname(__DIR__) . '/vendor/autoload.php';

echo "1. Checking font files:\n";
$fonts = ['Rubik-Regular.ttf', 'Heebo-Regular.ttf', 'Assistant-Regular.ttf'];
foreach ($fonts as $font) {
    $path = dirname(__DIR__) . '/assets/fonts/' . $font;
    if (file_exists($path)) {
        echo "✓ $font exists (" . filesize($path) . " bytes)\n";
    } else {
        echo "✗ $font NOT FOUND at $path\n";
    }
}

echo "\n2. Testing mPDF with fonts:\n";
try {
    $fontData = \Mpdf\Config\FontVariables::getDefaults();
    echo "✓ Got default font data\n";
    
    $fontData['fontdata']['rubik'] = [
        'R' => 'Rubik-Regular.ttf',
        'useOTL' => 0xFF,
    ];
    echo "✓ Added Rubik\n";
    
    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'tempDir' => dirname(__DIR__) . '/temp',
        'fontDir' => [
            dirname(__DIR__) . '/vendor/mpdf/mpdf/ttfonts/',
            dirname(__DIR__) . '/assets/fonts/'
        ],
        'fontdata' => $fontData['fontdata']
    ]);
    echo "✓ Created mPDF with custom fonts\n";
    
    $pdf->SetFont('rubik', '', 12);
    echo "✓ Set font to Rubik\n";
    
    $pdf->WriteText(10, 10, 'Test');
    echo "✓ Wrote text with Rubik\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\nDone!";