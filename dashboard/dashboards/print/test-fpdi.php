<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "<pre>";
echo "Checking all FPDI classes:\n\n";

// רשימת מחלקות אפשריות
$classes = [
    '\setasign\Fpdi\Mpdf\Fpdi',
    '\setasign\Fpdi\Fpdi',
    '\setasign\Fpdi\FpdiTrait',
    '\setasign\Fpdi\PdfParser\PdfParser'
];

foreach ($classes as $class) {
    echo "$class: " . (class_exists($class) ? 'EXISTS' : 'NOT FOUND') . "\n";
}

// נסה ליצור FPDI עם mPDF
echo "\nTrying different approaches:\n";

// גישה 1: יצירת mPDF רגיל עם FPDI trait
try {
    class MyPDF extends \Mpdf\Mpdf {
        use \setasign\Fpdi\FpdiTrait;
    }
    $pdf = new MyPDF();
    echo "SUCCESS - Created mPDF with FpdiTrait\n";
} catch (Exception $e) {
    echo "ERROR Method 1: " . $e->getMessage() . "\n";
}

// גישה 2: בדוק אם יש namespace אחר
$namespaces = get_declared_classes();
$fpdiClasses = array_filter($namespaces, function($class) {
    return strpos($class, 'Fpdi') !== false;
});
echo "\nAll FPDI related classes loaded:\n";
print_r($fpdiClasses);

echo "</pre>";