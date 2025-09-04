cat > test-fpdi.php << 'EOF'
<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n\n";
echo "Checking classes:\n";
echo "Mpdf\Mpdf exists: " . (class_exists('\Mpdf\Mpdf') ? 'YES' : 'NO') . "\n";
echo "setasign\Fpdi\Mpdf\Fpdi exists: " . (class_exists('\setasign\Fpdi\Mpdf\Fpdi') ? 'YES' : 'NO') . "\n";

if (is_dir(__DIR__ . '/vendor/setasign')) {
    echo "\nVendor setasign contents:\n";
    print_r(scandir(__DIR__ . '/vendor/setasign/'));
}

echo "\nTrying to create FPDI instance:\n";
try {
    $pdf = new \setasign\Fpdi\Mpdf\Fpdi();
    echo "SUCCESS - FPDI instance created!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
EOF