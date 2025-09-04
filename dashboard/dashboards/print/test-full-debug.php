<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>בדיקת מערכת מלאה</h1>";
echo "<pre>";

// 1. בדיקת ספריות
echo "=== 1. בדיקת ספריות ===\n";
echo "Autoload exists: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? '✓' : '✗') . "\n";
require_once __DIR__ . '/vendor/autoload.php';

echo "Mpdf class exists: " . (class_exists('\Mpdf\Mpdf') ? '✓' : '✗') . "\n";
echo "Fpdi class exists: " . (class_exists('\setasign\Fpdi\Fpdi') ? '✓' : '✗') . "\n";
echo "FpdiTrait exists: " . (trait_exists('\Mpdf\FpdiTrait') ? '✓' : '✗') . "\n\n";

// 2. בדיקת הטמפלייט
echo "=== 2. בדיקת הטמפלייט ===\n";
$templateUrl = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
$headers = @get_headers($templateUrl);
echo "Template URL accessible: " . (strpos($headers[0], '200') ? '✓' : '✗') . "\n";

// 3. בדיקת תיקיות
echo "\n=== 3. בדיקת תיקיות ===\n";
@mkdir('output', 0777, true);
@mkdir('temp', 0777, true);
echo "Output folder writable: " . (is_writable('output') ? '✓' : '✗') . "\n";
echo "Temp folder writable: " . (is_writable('temp') ? '✓' : '✗') . "\n";

// 4. ניסיון יצירת PDF
echo "\n=== 4. ניסיון יצירת PDF ===\n";

try {
    // הורד טמפלייט
    $tempFile = 'temp/debug_' . uniqid() . '.pdf';
    echo "Downloading template... ";
    file_put_contents($tempFile, file_get_contents($templateUrl));
    echo "✓\n";
    
    // צור PDF עם FPDI
    echo "Creating PDF instance... ";
    $pdf = new \Mpdf\Mpdf(['mode' => 'utf-8']);
    
    // הוסף FPDI capabilities ישירות
    echo "Adding FPDI... ";
    $fpdi = new class(['mode' => 'utf-8']) extends \Mpdf\Mpdf {
        use \Mpdf\FpdiTrait;
    };
    $pdf = $fpdi;
    echo "✓\n";
    
    // טען טמפלייט
    echo "Loading template... ";
    $pageCount = $pdf->setSourceFile($tempFile);
    echo "✓ (Pages: $pageCount)\n";
    
    echo "Importing page 1... ";
    $tplId = $pdf->importPage(1);
    echo "✓\n";
    
    // הוסף עמוד
    echo "Adding page... ";
    $pdf->AddPage();
    echo "✓\n";
    
    // השתמש בטמפלייט
    echo "Using template... ";
    $pdf->useTemplate($tplId);
    echo "✓\n";
    
    // כתוב טקסט במרכז
    echo "Writing text... ";
    $pdf->SetFont('dejavusans', '', 30);
    $pdf->SetXY(70, 140); // בערך במרכז דף A4
    $pdf->Write(0, 'שלום עולם');
    echo "✓\n";
    
    // שמור
    $filename = 'output/debug_' . date('Ymd_His') . '.pdf';
    echo "Saving PDF... ";
    $pdf->Output($filename, 'F');
    echo "✓\n";
    
    // נקה
    unlink($tempFile);
    
    $downloadUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename;
    
    echo "\n=== הצלחה! ===\n";
    echo "הקובץ נוצר בהצלחה!\n";
    echo "</pre>";
    echo "<h2><a href='$downloadUrl' target='_blank'>⬇️ הורד את ה-PDF</a></h2>";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
}

echo "</pre>";