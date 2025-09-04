<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

echo "<h1>PDF לרוחב עם WriteHTML</h1><pre>";

try {
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    // הורד טמפלייט
    $templateUrl = "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
    $tempFile = 'temp/landscape_' . uniqid() . '.pdf';
    echo "מוריד טמפלייט... ";
    file_put_contents($tempFile, file_get_contents($templateUrl));
    echo "✓\n";
    
    // צור PDF לרוחב
    echo "יוצר PDF לרוחב (Landscape)... ";
    $pdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',  // L = Landscape (לרוחב)
        'orientation' => 'L', // לרוחב
        'default_font' => 'dejavusans',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0
    ]);
    echo "✓\n";
    
    // הגדר RTL
    echo "מגדיר RTL... ";
    $pdf->SetDirectionality('rtl');
    echo "✓\n";
    
    // הגדר טמפלייט
    echo "מגדיר טמפלייט... ";
    $pdf->SetDocTemplate($tempFile, true);
    echo "✓\n";
    
    // הוסף עמוד לרוחב
    echo "מוסיף עמוד לרוחב... ";
    $pdf->AddPage('L'); // L = Landscape
    echo "✓\n";
    
    // כתוב טקסט עם WriteHTML בלבד (שעובד טוב)
    echo "כותב טקסט בעברית... ";
    
    // דוגמה לטקסטים במיקומים שונים
    $html = '
    <div dir="rtl">
        <div style="position: absolute; left: 100px; top: 100px;">
            <h2 style="color: red; font-size: 25pt;">שלום עולם</h2>
        </div>
        
        <div style="position: absolute; left: 300px; top: 200px;">
            <p style="color: blue; font-size: 18pt;">טקסט בעברית נוסף</p>
        </div>
        
        <div style="position: absolute; left: 150px; top: 300px;">
            <p style="color: green; font-size: 20pt;">זה עובד מצוין!</p>
        </div>
        
        <div style="position: absolute; left: 400px; top: 400px;">
            <p style="color: purple; font-size: 16pt;">תאריך: ' . date('d/m/Y H:i') . '</p>
        </div>
    </div>';
    
    $pdf->WriteHTML($html);
    echo "✓\n";
    
    // שמור
    $filename = 'output/landscape_' . date('Ymd_His') . '.pdf';
    echo "שומר PDF... ";
    $pdf->Output($filename, 'F');
    echo "✓\n";
    
    // נקה
    unlink($tempFile);
    
    $downloadUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $filename;
    
    echo "\n</pre>";
    echo "<h2 style='color:green;'>✅ PDF לרוחב עם עברית תקינה!</h2>";
    echo "<h3>השתמשנו רק ב-WriteHTML שעובד טוב עם RTL</h3>";
    echo "<h2><a href='$downloadUrl' target='_blank' style='background:green;color:white;padding:15px;text-decoration:none;font-size:24px;'>⬇️ הורד PDF לרוחב!</a></h2>";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "</pre>";
}