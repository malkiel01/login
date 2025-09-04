<?php
error_reporting(0); // ביטול הודעות שגיאה שמפריעות ל-JSON

require_once __DIR__ . '/vendor/autoload.php';

class PDF extends \Mpdf\Mpdf {
    use \Mpdf\FpdiTrait;
}

header('Content-Type: application/json');

try {
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    // צור PDF
    $pdf = new PDF([
        'mode' => 'c',
        'format' => 'A4',
        'default_font' => 'dejavusans'
    ]);
    
    // הורד את הטמפלייט
    $tempFile = 'temp/test_' . uniqid() . '.pdf';
    $url = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
    file_put_contents($tempFile, file_get_contents($url));
    
    // טען את הטמפלייט
    $pageCount = $pdf->setSourceFile($tempFile);
    $tplId = $pdf->importPage(1);
    
    // הוסף עמוד
    $pdf->AddPage();
    
    // השתמש בטמפלייט
    $pdf->useTemplate($tplId);
    
    // כתוב טקסט בעברית
    $pdf->SetFont('dejavusans', '', 20);
    $pdf->SetXY(50, 50);
    $pdf->Cell(0, 10, 'שלום עולם - טקסט בעברית', 0, 1, 'R');
    
    // שמור
    $output = 'output/hebrew_test_' . time() . '.pdf';
    $pdf->Output($output, 'F');
    
    unlink($tempFile);
    
    $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    echo json_encode([
        'success' => true, 
        'file' => $base_url . '/' . $output,
        'message' => 'PDF created with Hebrew text on template'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}