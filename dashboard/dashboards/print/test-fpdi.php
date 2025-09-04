<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

echo "Testing FPDI integration:<br>";

try {
    // בדיקה 1: האם המחלקות קיימות
    echo "1. Mpdf exists: " . (class_exists('\Mpdf\Mpdf') ? 'YES' : 'NO') . "<br>";
    echo "2. Fpdi exists: " . (class_exists('\setasign\Fpdi\Fpdi') ? 'YES' : 'NO') . "<br>";
    
    // בדיקה 2: האם יש את ה-Trait
    echo "3. FpdiTrait exists: " . (trait_exists('\setasign\Fpdi\Tcpdf\FpdiTrait') ? 'YES' : 'NO') . "<br>";
    
    // אם ה-Trait לא קיים, חפש traits אחרים
    $traits = get_declared_traits();
    $fpdiTraits = array_filter($traits, function($t) {
        return strpos($t, 'Fpdi') !== false;
    });
    echo "4. Available FPDI traits: <pre>" . print_r($fpdiTraits, true) . "</pre>";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}