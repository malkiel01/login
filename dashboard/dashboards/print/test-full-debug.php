<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

echo "<h1>תיקון כיוון דף ו-RTL</h1><pre>";

try {
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    // הורד טמפלייט
    $templateUrl = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
    $tempFile = 'temp/fixed_' . uniqid() . '.pdf';
    echo "מוריד טמפלייט... ";
    file_put_contents($tempFile, file_get_contents($templateUrl));
    echo "✓\n";
    
    // צור PDF עם הגדרות נכונות
    echo "יוצר PDF עם כיוון נכון... ";
    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-P',  // P = Portrait (לאורך)
        'orientation' => 'P', // וודא שזה לאורך
        'default_font' => 'dejavusans',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0
    ]);
    echo "✓\n";
    
    // הגדר RTL לעברית
    echo "מגדיר RTL... ";
    $pdf->SetDirectionality('rtl');
    echo "✓\n";
    
    // הגדר את הטמפלייט כרקע
    echo "מגדיר טמפלייט... ";
    $pdf->SetDocTemplate($tempFile, true);
    echo "✓\n";
    
    // הוסף עמוד לאורך
    echo "מוסיף עמוד לאורך... ";
    $pdf->AddPage('P'); // P = Portrait
    echo "✓\n";
    
    // כתוב טקסט בעברית נכון
    echo "כותב טקסט בעברית (RTL)... ";
    $pdf->SetFont('dejavusans', 'B', 30);
    $pdf->SetTextColor(255, 0, 0);
    
    // שיטה 1: השתמש ב-WriteHTML עם RTL
    $html = '<div dir="rtl" style="text-align: center; margin-top: 300px;">
        <h1 style="color: red; font-size: 30pt;">שלום עולם</h1>
        <p style="color: blue; font-size: 20pt;">זה עובד עם עברית!</p>
    </div>';
    $pdf->WriteHTML($html);
    
    // שיטה 2: או השתמש ב-Write עם RTL
    $pdf->SetXY(70, 200);
    $pdf->SetFontSize(25);
    $pdf->SetTextColor(0, 128, 0); // ירוק
    $pdf->Write(0, 'טקסט נוסף בעברית');
    
    echo "✓\n";
    
    // שמור
    $filename = 'output/fixed_rtl_' . date('Ymd_His') . '.pdf';
    echo "שומר PDF... ";
    $pdf->Output($filename, 'F');
    echo "✓\n";
    
    // נקה
    unlink($tempFile);
    
    $downloadUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename;
    
    echo "\n</pre>";
    echo "<h2 style='color:green;'>✅ תוקן: כיוון דף נכון + עברית RTL!</h2>";
    echo "<h2><a href='$downloadUrl' target='_blank' style='background:green;color:white;padding:15px;text-decoration:none;font-size:24px;'>⬇️ הורד PDF מתוקן!</a></h2>";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "</pre>";
}