<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$fontToTest = 'BAPyutimRegular.ttf';  // שנה את זה לפונט שאתה רוצה לבדוק
$fontId = 'ba';

echo "Testing font: $fontToTest\n";
echo "=====================================\n\n";

$fontPath = dirname(__DIR__) . '/assets/fonts/' . $fontToTest;

if (!file_exists($fontPath)) {
    die("Font file not found at: $fontPath\n");
}

echo "✓ Font file exists (" . number_format(filesize($fontPath)) . " bytes)\n\n";

// נסה כל קומבינציה של הגדרות
$tests = [
    ['useOTL' => 0xFF, 'useKashida' => 75, 'desc' => 'Full OTL + Kashida (Hebrew standard)'],
    ['useOTL' => 0x00, 'desc' => 'No OTL (for problematic fonts)'],
    ['useOTL' => 0x22, 'desc' => 'Basic OTL only'],
    ['desc' => 'No special settings (mPDF defaults)']
];

foreach ($tests as $index => $test) {
    echo "Test #" . ($index + 1) . ": " . $test['desc'] . "\n";
    echo "------------------------------------\n";
    
    try {
        $config = new \Mpdf\Config\ConfigVariables();
        $fontDirs = $config->getDefaults()['fontDir'];
        $fontDirs[] = dirname(__DIR__) . '/assets/fonts/';
        
        $fontConfig = new \Mpdf\Config\FontVariables();
        $fontData = $fontConfig->getDefaults()['fontdata'];
        
        // הגדר את הפונט עם ההגדרות הנוכחיות
        $fontSettings = ['R' => $fontToTest];
        if (isset($test['useOTL'])) $fontSettings['useOTL'] = $test['useOTL'];
        if (isset($test['useKashida'])) $fontSettings['useKashida'] = $test['useKashida'];
        
        $fontData[$fontId] = $fontSettings;
        
        $pdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'tempDir' => dirname(__DIR__) . '/temp',
            'fontDir' => $fontDirs,
            'fontdata' => $fontData
        ]);
        
        $pdf->SetFont($fontId, '', 12);
        $pdf->WriteText(10, 10, 'Test טסט בעברית 123');
        
        $output = dirname(__DIR__) . '/temp/test_' . $index . '.pdf';
        $pdf->Output($output, 'F');
        
        echo "✓ SUCCESS! Font works with these settings:\n";
        echo "  " . json_encode($fontSettings, JSON_PRETTY_PRINT) . "\n";
        
        @unlink($output);
        
        // אם הצליח, תצא
        echo "\n🎉 RECOMMENDED SETTINGS FOR fonts.json:\n";
        echo json_encode([
            'id' => $fontId,
            'name' => pathinfo($fontToTest, PATHINFO_FILENAME),
            'displayName' => pathinfo($fontToTest, PATHINFO_FILENAME),
            'file' => $fontToTest,
            'type' => 'local',
            'supports' => ['hebrew', 'english'],
            'category' => 'sans-serif',
            'mpdfSettings' => array_filter([
                'useOTL' => isset($test['useOTL']) ? '0x' . dechex($test['useOTL']) : null,
                'useKashida' => $test['useKashida'] ?? null
            ])
        ], JSON_PRETTY_PRINT);
        
        break;
        
    } catch (Exception $e) {
        echo "✗ Failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}