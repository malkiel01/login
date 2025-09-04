<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

// יצירת class שמשלב mPDF עם FPDI
class PDF extends \Mpdf\Mpdf {
    use \Mpdf\FpdiTrait;
}

try {
    // צור PDF חדש
    $pdf = new PDF(['mode' => 'utf-8']);
    
    // טען PDF קיים
    $templateUrl = 'https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf';
    
    // הורד לקובץ זמני
    $tempFile = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($tempFile, file_get_contents($templateUrl));
    
    // ייבא את ה-PDF
    $pageCount = $pdf->setSourceFile($tempFile);
    $templateId = $pdf->importPage(1);
    
    // הוסף עמוד וייבא את התבנית
    $pdf->AddPage();
    $pdf->useTemplate($templateId);
    
    // כתוב טקסט
    $pdf->SetXY(50, 50);
    $pdf->Write(0, 'Test: ' . date('Y-m-d H:i:s'));
    
    // שמור
    $filename = 'test_' . time() . '.pdf';
    $pdf->Output($filename, 'F');
    
    // נקה
    unlink($tempFile);
    
    echo "SUCCESS! PDF created: <a href='$filename'>$filename</a>";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}