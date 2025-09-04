<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "<h1>בדיקת גרסאות וחלופות</h1><pre>";

// בדוק גרסאות
echo "=== גרסאות ===\n";
$composerJson = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);
echo "mPDF: " . ($composerJson['require']['mpdf/mpdf'] ?? 'unknown') . "\n";
echo "FPDI: " . ($composerJson['require']['setasign/fpdi'] ?? 'unknown') . "\n\n";

// נסה גישה אחרת - השתמש ב-FPDI הרגיל ולא ב-trait
echo "=== ניסיון עם FPDI ישיר ===\n";

try {
    // השתמש ב-FPDI class הרגיל
    require_once __DIR__ . '/vendor/setasign/fpdi/src/autoload.php';
    
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    // הורד טמפלייט
    $tempFile = 'temp/version_' . uniqid() . '.pdf';
    file_put_contents($tempFile, file_get_contents("https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf"));
    
    // צור mPDF רגיל
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8']);
    
    // צור עמוד חדש
    $mpdf->AddPage();
    
    // כתוב HTML עם טקסט בעברית
    $html = '
    <div style="text-align: center; margin-top: 200px;">
        <h1 style="color: red; font-size: 40px;">שלום עולם</h1>
        <p>טקסט בעברית על רקע הטמפלייט</p>
    </div>
    ';
    
    $mpdf->WriteHTML($html);
    
    // שמור
    $filename = 'output/simple_' . date('Ymd_His') . '.pdf';
    $mpdf->Output($filename, 'F');
    
    echo "✓ PDF נוצר בהצלחה (ללא טמפלייט לעת עתה)\n";
    
    $url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename;
    echo "\n<a href='$url' target='_blank'>פתח PDF פשוט</a>\n\n";
    
    // עכשיו נסה להוסיף את הטמפלייט כרקע בגישה אחרת
    echo "=== מנסה גישה חלופית עם SetSourceFile ===\n";
    
    // צור PDF חדש עם יכולות FPDI
    $pdf2 = new \Mpdf\Mpdf(['mode' => 'utf-8']);
    
    // נסה להוסיף את SetSourceFile באופן ידני
    if (method_exists($pdf2, 'setSourceFile')) {
        echo "✓ setSourceFile method exists\n";
    } else {
        echo "✗ setSourceFile method NOT found - checking alternatives...\n";
        
        // בדוק אם אפשר להוסיף כתמונת רקע
        $pdf3 = new \Mpdf\Mpdf(['mode' => 'utf-8']);
        $pdf3->SetDocTemplate($tempFile, true);
        $pdf3->AddPage();
        
        $pdf3->SetFont('dejavusans', '', 30);
        $pdf3->SetXY(70, 140);
        $pdf3->Write(0, 'שלום עולם');
        
        $filename2 = 'output/template_' . date('Ymd_His') . '.pdf';
        $pdf3->Output($filename2, 'F');
        
        $url2 = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename2;
        echo "✓ PDF עם טמפלייט נוצר בשיטה חלופית!\n";
        echo "\n<a href='$url2' target='_blank' style='background:green;color:white;padding:10px;'>🎉 פתח PDF עם טמפלייט!</a>\n";
    }
    
    unlink($tempFile);
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";