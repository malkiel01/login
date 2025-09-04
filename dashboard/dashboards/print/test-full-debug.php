<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

echo "<h1>תיקון בעיית Writer</h1><pre>";

try {
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    // הורד טמפלייט
    $templateUrl = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
    $tempFile = 'temp/fix_' . uniqid() . '.pdf';
    echo "Downloading template... ";
    file_put_contents($tempFile, file_get_contents($templateUrl));
    echo "✓\n";
    
    // גישה 1: צור מחלקה נפרדת (לא אנונימית)
    if (!class_exists('MyPDF')) {
        class MyPDF extends \Mpdf\Mpdf {
            use \Mpdf\FpdiTrait;
        }
    }
    
    echo "Creating PDF with separate class... ";
    $pdf = new MyPDF([
        'mode' => 'utf-8',
        'format' => 'A4'
    ]);
    echo "✓\n";
    
    // טען טמפלייט
    echo "Loading template... ";
    $pageCount = $pdf->setSourceFile($tempFile);
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
    echo "Writing Hebrew text... ";
    $pdf->SetFont('dejavusans', '', 30);
    $pdf->SetTextColor(255, 0, 0); // אדום כדי שיבלוט
    $pdf->SetXY(70, 140);
    $pdf->Write(0, 'שלום עולם');
    echo "✓\n";
    
    // שמור
    $filename = 'output/fixed_' . date('Ymd_His') . '.pdf';
    echo "Saving PDF... ";
    $pdf->Output($filename, 'F');
    echo "✓\n";
    
    // נקה
    unlink($tempFile);
    
    $downloadUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename;
    
    echo "\n=== הצלחה! ===\n";
    echo "</pre>";
    echo "<h2 style='color:green;'>✅ הקובץ נוצר בהצלחה!</h2>";
    echo "<h2><a href='$downloadUrl' target='_blank' style='background:blue;color:white;padding:10px;text-decoration:none;'>⬇️ לחץ כאן להוריד את ה-PDF</a></h2>";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}