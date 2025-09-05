<?php
/**
 * mPDF Generator עם תמיכה מלאה בעברית ו-RTL
 * הספריה הטובה ביותר ל-PDF עברי ב-PHP
 * 
 * התקנה:
 * 1. הרץ בטרמינל: composer require mpdf/mpdf
 * 2. או הורד ידנית מ: https://github.com/mpdf/mpdf
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// בדיקת חיבור והאם mPDF מותקן
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    $mpdf_exists = file_exists('vendor/autoload.php') && class_exists('\Mpdf\Mpdf');
    echo json_encode([
        'success' => true, 
        'method' => 'mPDF',
        'installed' => $mpdf_exists,
        'message' => $mpdf_exists ? 'mPDF is installed' : 'mPDF not installed - run: composer require mpdf/mpdf'
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
        header('Content-Length: ' . filesize($filepath));
    } else {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file . '"');
    }
    
    readfile($filepath);
    exit();
}

// טען mPDF אם קיים דרך Composer
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} else {
    // נסה לטעון mPDF אם הותקן ידנית
    if (file_exists('mpdf/vendor/autoload.php')) {
        require_once 'mpdf/vendor/autoload.php';
    }
}

// עיבוד בקשות POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    
    // בדוק אם mPDF מותקן
    if (!class_exists('\Mpdf\Mpdf')) {
        echo json_encode([
            'success' => false, 
            'error' => 'mPDF not installed. Run: composer require mpdf/mpdf',
            'instructions' => [
                '1. Install Composer from getcomposer.org',
                '2. Run in terminal: composer require mpdf/mpdf',
                '3. Or download manually from github.com/mpdf/mpdf'
            ]
        ]);
        exit();
    }
    
    // צור תיקייה אם לא קיימת
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }
    
    // אם אין תיקיית temp, צור אותה
    if (!file_exists('temp')) {
        mkdir('temp', 0777, true);
    }
    
    try {
        // הגדרות mPDF עם תמיכה מלאה בעברית
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 0,
            'default_font' => '',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9,
            'orientation' => 'P',
            'tempDir' => __DIR__ . '/temp',
            // הגדרות חשובות לעברית
            'autoScriptToLang' => true,  // זיהוי אוטומטי של שפה
            'autoLangToFont' => true,     // התאמת פונט אוטומטית לשפה
            'useSubstitutions' => true,   // החלפת תווים אוטומטית
        ];
        
        // בדוק האם יש שפה בנתונים
        $isHebrew = isset($input['language']) && $input['language'] === 'he';
        
        // צור אובייקט mPDF
        $mpdf = new \Mpdf\Mpdf($config);
        
        // הפעל תמיכה ב-RTL אם עברית
        if ($isHebrew) {
            $mpdf->SetDirectionality('rtl');
        }
        
        // הגדר פונט שתומך בעברית
        // DejaVu Sans נכלל ב-mPDF ותומך בעברית
        $mpdf->SetFont('dejavusans');
        
        // אפשרות 1: יצירה דרך WriteHTML (מומלץ לטקסט עברי)
        $html = '<!DOCTYPE html>
<html dir="' . ($isHebrew ? 'rtl' : 'ltr') . '">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            direction: ' . ($isHebrew ? 'rtl' : 'ltr') . ';
        }
        .text-element {
            position: absolute;
        }
    </style>
</head>
<body>';
        
        foreach ($input['values'] as $value) {
            $x = $value['x'] ?? 100;
            $y = $value['y'] ?? 100;
            $text = $value['text'] ?? '';
            $fontSize = $value['fontSize'] ?? 12;
            
            // יצירת סגנון לכל אלמנט
            $style = "position: absolute; ";
            $style .= "left: {$x}px; ";
            $style .= "top: {$y}px; ";
            $style .= "font-size: {$fontSize}px; ";
            
            if (isset($value['color']) && is_array($value['color'])) {
                $style .= "color: rgb(" . implode(',', $value['color']) . "); ";
            }
            
            $html .= '<div style="' . $style . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        $html .= '</body></html>';
        
        // כתוב את ה-HTML ל-PDF
        $mpdf->WriteHTML($html);
        
        // אפשרות 2: אם רוצים מיקום מדויק יותר (לטקסטים פשוטים)
        /*
        foreach ($input['values'] as $value) {
            $x = ($value['x'] ?? 100) / 2.83; // המרה מפיקסלים למ"מ
            $y = ($value['y'] ?? 100) / 2.83;
            $text = $value['text'] ?? '';
            $fontSize = $value['fontSize'] ?? 12;
            
            $mpdf->SetFontSize($fontSize);
            
            if (isset($value['color']) && is_array($value['color'])) {
                $mpdf->SetTextColor($value['color'][0], $value['color'][1], $value['color'][2]);
            }
            
            // שימוש ב-WriteText למיקום מדויק
            $mpdf->WriteText($x, $y, $text);
            
            // אפס צבע
            $mpdf->SetTextColor(0, 0, 0);
        }
        */
        
        // שמור
        $filename = 'output/mpdf_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.pdf';
        $mpdf->Output($filename, \Mpdf\Output\Destination::FILE);
        
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        
        echo json_encode([
            'success' => true,
            'method' => 'mPDF with Full Hebrew Support',
            'filename' => basename($filename),
            'view_url' => $base_url . '/pdf-mpdf.php?file=' . basename($filename),
            'download_url' => $base_url . '/pdf-mpdf.php?file=' . basename($filename) . '&action=download',
            'direct_url' => $base_url . '/' . $filename,
            'features' => [
                'Full Hebrew/RTL support',
                'Unicode UTF-8',
                'DejaVu font included',
                'Auto language detection',
                'Professional quality'
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
    <title>mPDF - תמיכה מלאה בעברית</title>
    <style>
        body {
            font-family: Arial, 'Noto Sans Hebrew', sans-serif;
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
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
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
            font-family: 'Courier New', monospace;
        }
        .success {
            color: #27ae60;
            font-weight: bold;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 mPDF - הפתרון המושלם לעברית ב-PDF</h1>
        
        <h2>למה mPDF?</h2>
        <div class="feature">✅ תמיכה מלאה בעברית וערבית</div>
        <div class="feature">✅ תמיכה ב-RTL (Right-to-Left)</div>
        <div class="feature">✅ תמיכה ב-Unicode UTF-8</div>
        <div class="feature">✅ פונטים מובנים שתומכים בעברית</div>
        <div class="feature">✅ זיהוי אוטומטי של כיוון הטקסט</div>
        <div class="feature">✅ איכות מקצועית</div>
        
        <h2>הוראות התקנה</h2>
        
        <h3>אפשרות 1: התקנה עם Composer (מומלץ)</h3>
        <div class="code">
composer require mpdf/mpdf
        </div>
        
        <h3>אפשרות 2: התקנה ידנית</h3>
        <ol>
            <li>הורד את mPDF מ: <a href="https://github.com/mpdf/mpdf" target="_blank">github.com/mpdf/mpdf</a></li>
            <li>חלץ לתיקיית mpdf בשרת שלך</li>
            <li>ודא שיש הרשאות כתיבה לתיקיית temp</li>
        </ol>
        
        <h2>דוגמת שימוש</h2>
        <div class="code">
// טען את mPDF
require_once 'vendor/autoload.php';

// צור אובייקט mPDF עם תמיכה בעברית
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'autoScriptToLang' => true,
    'autoLangToFont' => true
]);

// הגדר כיוון RTL
$mpdf->SetDirectionality('rtl');

// כתוב HTML עם עברית
$html = '&lt;h1&gt;שלום עולם!&lt;/h1&gt;';
$mpdf->WriteHTML($html);

// שמור או הצג
$mpdf->Output('hebrew.pdf', 'D');
        </div>
        
        <h2>בדיקת התקנה</h2>
        <button onclick="testInstallation()">🔍 בדוק אם mPDF מותקן</button>
        <div id="testResult"></div>
        
        <h2>פונטים נתמכים לעברית</h2>
        <ul>
            <li><strong>DejaVu Sans</strong> - נכלל ב-mPDF, תומך בעברית מצוין</li>
            <li><strong>FreeSerif/FreeSans</strong> - פונטים חופשיים עם תמיכה בעברית</li>
            <li><strong>Arial Unicode MS</strong> - אם קיים במערכת</li>
            <li>ניתן להוסיף כל פונט TTF שתומך בעברית</li>
        </ul>
        
        <h2>טיפים חשובים</h2>
        <div class="feature">
            💡 השתמש תמיד ב-UTF-8 encoding
        </div>
        <div class="feature">
            💡 הגדר dir="rtl" ב-HTML שלך
        </div>
        <div class="feature">
            💡 השתמש בפונט DejaVu Sans לתוצאות הטובות ביותר
        </div>
        <div class="feature">
            💡 ודא שיש הרשאות כתיבה לתיקיית temp
        </div>
    </div>
    
    <script>
    async function testInstallation() {
        const resultDiv = document.getElementById('testResult');
        resultDiv.innerHTML = '<p>בודק...</p>';
        
        try {
            const response = await fetch('?test=1');
            const data = await response.json();
            
            if (data.installed) {
                resultDiv.innerHTML = '<p class="success">✅ mPDF מותקן ומוכן לשימוש!</p>';
            } else {
                resultDiv.innerHTML = '<p class="error">❌ mPDF לא מותקן. ' + data.message + '</p>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<p class="error">❌ שגיאה בבדיקה: ' + error.message + '</p>';
        }
    }
    </script>
</body>
</html>
<?php
}
?>