<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

echo "<h1>פתרון עובד - SetDocTemplate</h1><pre>";

try {
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    // הורד טמפלייט
    $templateUrl = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
    $tempFile = 'temp/template_' . uniqid() . '.pdf';
    echo "מוריד טמפלייט... ";
    file_put_contents($tempFile, file_get_contents($templateUrl));
    echo "✓\n";
    
    // צור PDF עם mPDF
    echo "יוצר PDF... ";
    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'dejavusans',
        'default_font_size' => 0,
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0
    ]);
    echo "✓\n";
    
    // הגדר את הטמפלייט כרקע
    echo "מגדיר טמפלייט כרקע... ";
    $pdf->SetDocTemplate($tempFile, true);
    echo "✓\n";
    
    // הוסף עמוד
    echo "מוסיף עמוד... ";
    $pdf->AddPage();
    echo "✓\n";
    
    // כתוב טקסט בעברית במרכז
    echo "כותב טקסט בעברית... ";
    $pdf->SetFont('dejavusans', 'B', 30);
    $pdf->SetTextColor(255, 0, 0); // אדום
    
    // מרכז הדף בערך
    $pdf->SetXY(70, 140);
    $pdf->Cell(0, 10, 'שלום עולם', 0, 1, 'R');
    
    // הוסף עוד טקסט
    $pdf->SetFontSize(20);
    $pdf->SetTextColor(0, 0, 255); // כחול
    $pdf->SetXY(70, 160);
    $pdf->Cell(0, 10, 'זה עובד!', 0, 1, 'R');
    echo "✓\n";
    
    // שמור
    $filename = 'output/working_' . date('Ymd_His') . '.pdf';
    echo "שומר PDF... ";
    $pdf->Output($filename, 'F');
    echo "✓\n";
    
    // נקה
    unlink($tempFile);
    
    $downloadUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename;
    
    echo "\n</pre>";
    echo "<h2 style='color:green;'>✅ הצלחה מלאה!</h2>";
    echo "<h2><a href='$downloadUrl' target='_blank' style='background:green;color:white;padding:15px;text-decoration:none;font-size:24px;'>⬇️ הורד PDF עם טמפלייט ועברית!</a></h2>";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "</pre>";
}