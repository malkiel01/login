<?php
/**
 * mPDF עם FPDI - כתיבה על PDF קיים
 * 
 * התקנה נדרשת:
 * composer require mpdf/mpdf
 * composer require setasign/fpdi
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// טען את הספריות
require_once __DIR__ . '/vendor/autoload.php';

// יצירת class שמשלב mPDF עם FPDI
class PDF extends \Mpdf\Mpdf {
    use \Mpdf\FpdiTrait;
}

// בדיקת התקנה
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    $mpdf_exists = class_exists('\Mpdf\Mpdf');
    $fpdi_exists = class_exists('\setasign\Fpdi\Fpdi');
    
    // נסה ליצור PDF עם FPDI
    $can_create = false;
    try {
        $test = new PDF(['mode' => 'utf-8']);
        $can_create = true;
    } catch (Exception $e) {
        $can_create = false;
    }
    
    echo json_encode([
        'success' => true,
        'mpdf_installed' => $mpdf_exists,
        'fpdi_installed' => $fpdi_exists,
        'can_create_pdf' => $can_create,
        'message' => ($mpdf_exists && $fpdi_exists && $can_create) 
            ? 'Both mPDF and FPDI are installed and working!' 
            : 'Missing: ' . (!$mpdf_exists ? 'mPDF ' : '') . (!$fpdi_exists ? 'FPDI ' : '') . (!$can_create ? 'Integration' : '')
    ]);
    exit();
}

// טיפול בהצגה/הורדה
if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = 'output/' . $file;
    
    if (!file_exists($filepath)) {
        header('HTTP/1.0 404 Not Found');
        echo 'File not found';
        exit();
    }
    
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'download') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file . '"');
    } else {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file . '"');
    }
    
    readfile($filepath);
    exit();
}

// עיבוד בקשות POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    
    // צור תיקיות נדרשות
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }
    if (!file_exists('temp')) {
        mkdir('temp', 0777, true);
    }
    
    try {
        // יצירת PDF עם FPDI
        $pdf = new PDF([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => __DIR__ . '/temp',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true
        ]);
        
        // בדוק אם יש PDF קיים
        if (isset($input['filename']) && !empty($input['filename'])) {
            $pdfUrl = $input['filename'];
            
            // אם זה URL מרוחק, הורד אותו קודם
            if (filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
                $tempFile = 'temp/source_' . uniqid() . '.pdf';
                
                // השתמש ב-curl להורדה
                $ch = curl_init($pdfUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $pdfContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200 || empty($pdfContent)) {
                    throw new Exception("Failed to download PDF from URL. HTTP Code: $httpCode");
                }
                
                file_put_contents($tempFile, $pdfContent);
                $sourcePdf = $tempFile;
            } else {
                // אם זה קובץ מקומי
                $sourcePdf = $pdfUrl;
                if (!file_exists($sourcePdf)) {
                    throw new Exception('Source PDF file not found');
                }
            }
            
            // ייבא את ה-PDF הקיים
            $pageCount = $pdf->setSourceFile($sourcePdf);
            
            // עבור על כל העמודים
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // ייבא את העמוד
                $templateId = $pdf->importPage($pageNo);
                
                // קבל את הגודל של העמוד
                $size = $pdf->getTemplateSize($templateId);
                
                // הוסף עמוד חדש
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                
                // השתמש בתבנית העמוד המיובא
                $pdf->useTemplate($templateId);
                
                // הוסף את הטקסטים החדשים רק לעמוד הראשון
                if ($pageNo == 1) {
                    // הגדר פונט שתומך בעברית
                    try {
                        $pdf->SetFont('dejavusans');
                    } catch (Exception $e) {
                        // אם הפונט לא קיים, נסה פונטים אחרים
                        try {
                            $pdf->SetFont('freeserif');
                        } catch (Exception $e2) {
                            $pdf->SetFont('');
                        }
                    }
                    
                    // הגדר כיוון RTL אם נדרש
                    $isHebrew = isset($input['language']) && $input['language'] === 'he';
                    if ($isHebrew) {
                        $pdf->SetDirectionality('rtl');
                    }
                    
                    // הוסף את הטקסטים
                    foreach ($input['values'] as $value) {
                        $x = ($value['x'] ?? 100) / 2.83; // המרה מפיקסלים למ"מ
                        $y = ($value['y'] ?? 100) / 2.83;
                        $text = $value['text'] ?? '';
                        $fontSize = $value['fontSize'] ?? 12;
                        
                        $pdf->SetFontSize($fontSize);
                        
                        if (isset($value['color']) && is_array($value['color'])) {
                            $pdf->SetTextColor($value['color'][0], $value['color'][1], $value['color'][2]);
                        }
                        
                        // כתוב טקסט
                        $pdf->SetXY($x, $y);
                        $pdf->Write(0, $text);
                        
                        // אפס צבע
                        $pdf->SetTextColor(0, 0, 0);
                    }
                }
            }
            
            // נקה קובץ זמני אם נוצר
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
        } else {
            // אם אין PDF קיים, צור חדש
            $pdf->AddPage();
            
            // הגדר פונט שתומך בעברית
            try {
                $pdf->SetFont('dejavusans');
            } catch (Exception $e) {
                try {
                    $pdf->SetFont('freeserif');
                } catch (Exception $e2) {
                    $pdf->SetFont('');
                }
            }
            
            // הגדר כיוון RTL אם נדרש
            $isHebrew = isset($input['language']) && $input['language'] === 'he';
            if ($isHebrew) {
                $pdf->SetDirectionality('rtl');
            }
            
            // הוסף את הטקסטים
            foreach ($input['values'] as $value) {
                $x = ($value['x'] ?? 100) / 2.83;
                $y = ($value['y'] ?? 100) / 2.83;
                $text = $value['text'] ?? '';
                $fontSize = $value['fontSize'] ?? 12;
                
                $pdf->SetFontSize($fontSize);
                
                if (isset($value['color']) && is_array($value['color'])) {
                    $pdf->SetTextColor($value['color'][0], $value['color'][1], $value['color'][2]);
                }
                
                $pdf->SetXY($x, $y);
                $pdf->Write(0, $text);
                
                $pdf->SetTextColor(0, 0, 0);
            }
        }
        
        // שמור
        $filename = 'output/overlay_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.pdf';
        $pdf->Output($filename, \Mpdf\Output\Destination::FILE);
        
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        
        echo json_encode([
            'success' => true,
            'method' => 'mPDF + FPDI (Overlay on existing PDF)',
            'filename' => basename($filename),
            'view_url' => $base_url . '/pdf-mpdf-overlay.php?file=' . basename($filename),
            'download_url' => $base_url . '/pdf-mpdf-overlay.php?file=' . basename($filename) . '&action=download',
            'direct_url' => $base_url . '/' . $filename,
            'features' => [
                'Can write on existing PDFs',
                'Full Hebrew/RTL support',
                'Preserves original PDF content',
                'Supports multi-page PDFs'
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// אם זה GET request רגיל, הצג דף הוראות
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['test']) && !isset($_GET['file'])) {
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>mPDF + FPDI - כתיבה על PDF קיים</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #2c3e50; }
        .feature {
            margin: 15px 0;
            padding: 10px;
            background: #ecf0f1;
            border-right: 4px solid #3498db;
        }
        .code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            direction: ltr;
            text-align: left;
            font-family: monospace;
        }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { 
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .test-button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .test-button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 mPDF + FPDI - כתיבה על PDF קיים</h1>
        
        <div class="warning">
            <strong>⚠️ חשוב:</strong> פתרון זה מאפשר כתיבה על PDF קיים!
        </div>
        
        <h2>🧪 בדיקת מערכת</h2>
        <button class="test-button" onclick="testSystem()">בדוק התקנה</button>
        <button class="test-button" onclick="testPDF()">בדוק יצירת PDF</button>
        <div id="testResult"></div>
        
        <h2>✨ יתרונות</h2>
        <div class="feature">✅ כתיבה על PDF קיים - לא רק יצירת PDF חדש</div>
        <div class="feature">✅ שומר על התוכן המקורי של ה-PDF</div>
        <div class="feature">✅ תמיכה מלאה בעברית ו-RTL</div>
        <div class="feature">✅ תמיכה ב-PDFs מרובי עמודים</div>
        <div class="feature">✅ יכול לקרוא PDFs מ-URL או מקובץ מקומי</div>
        
        <h2>📝 דוגמת שימוש</h2>
        <div class="code">
// בקשת POST עם URL של PDF קיים
{
    "filename": "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf",
    "language": "he",
    "values": [
        {
            "text": "טקסט חדש על PDF קיים!",
            "x": 100,
            "y": 100,
            "fontSize": 16
        }
    ]
}
        </div>
    </div>
    
    <script>
    async function testSystem() {
        const resultDiv = document.getElementById('testResult');
        resultDiv.innerHTML = '<p>בודק...</p>';
        
        try {
            const response = await fetch('?test=1');
            const data = await response.json();
            
            if (data.can_create_pdf) {
                resultDiv.innerHTML = '<p class="success">✅ המערכת מוכנה לעבודה!</p>';
            } else {
                resultDiv.innerHTML = '<p class="error">❌ ' + data.message + '</p>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<p class="error">❌ שגיאה: ' + error.message + '</p>';
        }
    }
    
    async function testPDF() {
        const resultDiv = document.getElementById('testResult');
        resultDiv.innerHTML = '<p>יוצר PDF לדוגמה...</p>';
        
        const testData = {
            filename: "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf",
            language: "he",
            values: [
                {
                    text: "בדיקה - " + new Date().toLocaleString('he-IL'),
                    x: 100,
                    y: 100,
                    fontSize: 20
                }
            ]
        };
        
        try {
            const response = await fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(testData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                resultDiv.innerHTML = '<p class="success">✅ PDF נוצר בהצלחה! <a href="' + data.view_url + '" target="_blank">פתח PDF</a></p>';
                window.open(data.view_url, '_blank');
            } else {
                resultDiv.innerHTML = '<p class="error">❌ שגיאה: ' + data.error + '</p>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<p class="error">❌ שגיאה: ' + error.message + '</p>';
        }
    }
    </script>
</body>
</html>
<?php
}
?>