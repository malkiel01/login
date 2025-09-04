<?php
/**
 * יוצר קובץ PDF פשוט לבדיקות
 * הרץ את זה פעם אחת ליצירת sample.pdf
 */

// יצירת PDF בסיסי
$pdf_content = "%PDF-1.4
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
<< /Length 44 >>
stream
BT
/F1 24 Tf
100 700 Td
(Sample PDF File) Tj
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
362
%%EOF";

// שמור בתיקיית templates
if (!file_exists('templates')) {
    mkdir('templates', 0777, true);
}

$filename = 'templates/sample.pdf';
if (file_put_contents($filename, $pdf_content)) {
    echo "✅ PDF נוצר בהצלחה: $filename\n";
    echo "גודל: " . filesize($filename) . " bytes\n";
    echo "URL: https://login.form.mbe-plus.com/dashboard/dashboards/print/$filename\n";
} else {
    echo "❌ שגיאה ביצירת הקובץ\n";
}
?>