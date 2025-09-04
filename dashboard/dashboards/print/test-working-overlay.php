<?php
error_reporting(0);
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

try {
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    // צור PDF רגיל של mPDF
    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4'
    ]);
    
    // הוסף את ה-trait
    $pdf = new class extends \Mpdf\Mpdf {
        use \Mpdf\FpdiTrait;
        
        public function __construct($config = []) {
            parent::__construct($config);
        }
    };
    
    $pdf = new $pdf([
        'mode' => 'utf-8',
        'format' => 'A4'
    ]);
    
    // הורד את הטמפלייט
    $tempFile = 'temp/source_' . uniqid() . '.pdf';
    $url = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $content = curl_exec($ch);
    curl_close($ch);
    
    file_put_contents($tempFile, $content);
    
    // טען את הטמפלייט
    $pageCount = $pdf->setSourceFile($tempFile);
    $tplId = $pdf->importPage(1);
    
    // הוסף עמוד
    $pdf->AddPage();
    
    // השתמש בטמפלייט
    $pdf->useTemplate($tplId);
    
    // כתוב טקסט
    $pdf->SetFont('dejavusans', '', 20);
    $pdf->SetXY(50, 50);
    $pdf->Write(0, 'TEST HEBREW: שלום עולם');
    
    // שמור
    $output = 'output/working_' . time() . '.pdf';
    $pdf->Output($output, 'F');
    
    unlink($tempFile);
    
    $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    
    echo json_encode([
        'success' => true,
        'view_url' => $base_url . '/' . $output,
        'message' => 'PDF created successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}