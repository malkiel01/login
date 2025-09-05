<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

echo "Testing from api/ folder:\n\n";

// Test paths
$paths = [
    'vendor' => dirname(__DIR__) . '/vendor/autoload.php',
    'temp' => dirname(__DIR__) . '/temp',
    'output' => dirname(__DIR__) . '/output'
];

foreach ($paths as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name exists: $path\n";
    } else {
        echo "✗ $name MISSING: $path\n";
    }
}

// Test mPDF
if (file_exists($paths['vendor'])) {
    require_once $paths['vendor'];
    try {
        $pdf = new \Mpdf\Mpdf(['tempDir' => dirname(__DIR__) . '/temp']);
        echo "\n✓ mPDF works from api/ folder\n";
    } catch (Exception $e) {
        echo "\n✗ mPDF ERROR: " . $e->getMessage() . "\n";
    }
}

// Test JSON handling
$testJson = '{"values":[{"text":"test","x":100,"y":100}]}';
$data = json_decode($testJson, true);
echo "\n✓ JSON decode works\n";

echo "\nEverything seems OK from api/ folder!\n";