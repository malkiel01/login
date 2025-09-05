<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== PDF Test ===\n\n";

// בדוק vendor
if (!file_exists('vendor/autoload.php')) {
    die("ERROR: vendor/autoload.php not found!\n");
}
require_once 'vendor/autoload.php';
echo "✓ Vendor loaded\n";

// בדוק mPDF
try {
    $pdf = new \Mpdf\Mpdf();
    echo "✓ mPDF works\n";
} catch (Exception $e) {
    die("ERROR: mPDF failed - " . $e->getMessage() . "\n");
}

// בדוק תיקיות
$dirs = ['output', 'temp', 'assets/fonts'];
foreach ($dirs as $dir) {
    if (file_exists($dir)) {
        echo "✓ Directory exists: $dir\n";
    } else {
        echo "✗ Directory missing: $dir\n";
    }
}

// בדוק פונקציה
function validateFontFamily($font) {
    return 'dejavusans';
}
echo "✓ Function defined\n";

// נסה את הקוד הבעייתי
$testData = [
    'values' => [
        ['text' => 'Test', 'x' => 100, 'y' => 100, 'fontSize' => 12, 'color' => '#000000', 'fontFamily' => 'rubik']
    ]
];

echo "\n=== Testing with data ===\n";
$values = $testData['values'];
foreach ($values as $value) {
    $fontFamily = isset($value['fontFamily']) ? $value['fontFamily'] : 'dejavusans';
    $fontFamily = validateFontFamily($fontFamily);
    echo "Requested font: {$value['fontFamily']} -> Using: $fontFamily\n";
}

echo "\n✅ All tests passed!\n";