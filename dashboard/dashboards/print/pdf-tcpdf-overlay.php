<?php
// pdf-tcpdf-overlay.php
require_once('vendor/autoload.php');

use setasign\Fpdi\Tcpdf\Fpdi;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        // צור אובייקט FPDI עם TCPDF
        $pdf = new Fpdi();
        
        // טען את ה-PDF הקיים
        $templateUrl = 'https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf';
        
        // הורד את הקובץ לזמני
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tempFile, file_get_contents($templateUrl));
        
        // קבע את קובץ המקור
        $pageCount = $pdf->setSourceFile($tempFile);
        
        // עבור על כל עמוד
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pdf->AddPage();
            $templateId = $pdf->importPage($pageNo);
            $pdf->useTemplate($templateId);
            
            // הגדר פונט שתומך בעברית
            $pdf->SetFont('dejavusans', '', 12);
            
            // רק בעמוד הראשון - הוסף טקסטים
            if ($pageNo == 1) {
                foreach ($input['values'] as $value) {
                    $x = $value['x'] / 2.83; // המרה לmm
                    $y = $value['y'] / 2.83;
                    $text = $value['text'];
                    $fontSize = $value['fontSize'] ?? 12;
                    
                    $pdf->SetFontSize($fontSize);
                    $pdf->SetXY($x, $y);
                    
                    // כתוב טקסט עם תמיכה ב-RTL
                    $pdf->Write(0, $text, '', 0, '', false, 0, false, true, 0);
                }
            }
        }
        
        // שמור
        $filename = 'output/overlay_' . date('Ymd_His') . '.pdf';
        $pdf->Output($filename, 'F');
        
        // נקה קובץ זמני
        unlink($tempFile);
        
        echo json_encode([
            'success' => true,
            'filename' => basename($filename),
            'url' => 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}