<?php
/**
 * בדיקה ישירה של יצירת PDF עם MinimalPDF
 * מבלי לעבור דרך process-pdf-simple.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// טען את הקלאס MinimalPDF
require_once('process-pdf-simple.php');

echo "<h1>בדיקת יצירת PDF אמיתי</h1>";

// צור תיקיית output אם לא קיימת
if (!file_exists('output')) {
    mkdir('output', 0777, true);
    echo "<p>✅ נוצרה תיקיית output</p>";
}

// נסה ליצור PDF פשוט
try {
    $pdf = new MinimalPDF();
    $pdf->addPage();
    
    // הוסף טקסטים
    $pdf->addText(100, 100, "This is a real PDF!", 16);
    $pdf->addText(100, 130, "Created with MinimalPDF", 12);
    $pdf->addText(100, 160, "Date: " . date('Y-m-d H:i:s'), 10);
    $pdf->addText(100, 190, "Hebrew test: Shalom Olam", 12);
    
    // שמור את הקובץ
    $filename = 'output/test_minimal_' . time() . '.pdf';
    
    if ($pdf->save($filename)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        $url = $protocol . '://' . $host . $script . '/' . $filename;
        
        echo "<h2>✅ הצלחה!</h2>";
        echo "<p>נוצר קובץ PDF אמיתי: <strong>$filename</strong></p>";
        echo "<p>גודל הקובץ: " . filesize($filename) . " bytes</p>";
        echo "<p><a href='$url' target='_blank' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>📥 הורד/הצג PDF</a></p>";
        
        // הצג את תוכן ה-PDF הגולמי (10 השורות הראשונות)
        echo "<h3>תוכן ה-PDF (התחלה):</h3>";
        echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
        $content = file_get_contents($filename);
        echo htmlspecialchars(substr($content, 0, 500)) . "...";
        echo "</pre>";
        
        // בדוק אם זה באמת PDF
        if (substr($content, 0, 4) === '%PDF') {
            echo "<p style='color: green;'>✅ זהו קובץ PDF תקין (מתחיל ב-%PDF)</p>";
        } else {
            echo "<p style='color: red;'>⚠️ הקובץ לא מתחיל ב-%PDF</p>";
        }
        
    } else {
        echo "<h2 style='color: red;'>❌ נכשל!</h2>";
        echo "<p>לא הצלחנו ליצור את קובץ ה-PDF</p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ שגיאה!</h2>";
    echo "<p>שגיאה: " . $e->getMessage() . "</p>";
}

// נסיון נוסף - יצירת PDF ידנית מינימלי
echo "<hr>";
echo "<h2>נסיון נוסף - PDF ידני מינימלי:</h2>";

$simple_pdf = "%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> >>
endobj
4 0 obj
<< /Length 70 >>
stream
BT
/F1 16 Tf
100 700 Td
(This is a manually created PDF) Tj
ET
endstream
endobj
xref
0 5
0000000000 65535 f
0000000009 00000 n
0000000058 00000 n
0000000115 00000 n
0000000274 00000 n
trailer
<< /Size 5 /Root 1 0 R >>
startxref
384
%%EOF";

$manual_filename = 'output/manual_pdf_' . time() . '.pdf';
if (file_put_contents($manual_filename, $simple_pdf)) {
    $url2 = $protocol . '://' . $host . $script . '/' . $manual_filename;
    echo "<p>✅ PDF ידני נוצר: <a href='$url2' target='_blank'>$manual_filename</a></p>";
} else {
    echo "<p>❌ נכשל ביצירת PDF ידני</p>";
}

echo "<hr>";
echo "<h3>מידע על השרת:</h3>";
echo "<ul>";
echo "<li>PHP Version: " . PHP_VERSION . "</li>";
echo "<li>תיקייה נוכחית: " . __DIR__ . "</li>";
echo "<li>הרשאות תיקיית output: " . (is_writable('output') ? '✅ ניתן לכתוב' : '❌ לא ניתן לכתוב') . "</li>";
echo "</ul>";
?>