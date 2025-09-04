<?php
/**
 * בדיקת כל 5 השיטות ליצירת PDF
 * Debug and Test All Methods
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// למנוע שגיאות headers
ob_start();

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>בדיקת כל שיטות יצירת PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .method-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .method-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        .status-ready {
            color: green;
            font-weight: bold;
        }
        .status-not-ready {
            color: red;
            font-weight: bold;
        }
        .status-partial {
            color: orange;
            font-weight: bold;
        }
        .test-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        .test-button:hover {
            background: #218838;
        }
        .test-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .setup-instructions {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ffc107;
        }
        .test-result {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        .test-result.error {
            background: #ffebee;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .command-box {
            background: #2d2d2d;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>

<h1>🔧 בדיקת כל שיטות יצירת PDF</h1>

<?php
// בדיקת תיקיית output
if (!file_exists('output')) {
    mkdir('output', 0777, true);
    echo '<div class="method-card"><p>✅ נוצרה תיקיית output</p></div>';
}

// שיטה 1: Minimal PDF (Native PHP)
?>
<div class="method-card">
    <div class="method-title">שיטה 1: Minimal PDF (Native PHP)</div>
    <div class="status-ready">✅ מוכן לשימוש - לא דורש התקנה!</div>
    <p>יוצר PDF בסיסי ללא תלויות חיצוניות. עובד על כל שרת PHP.</p>
    
    <button class="test-button" onclick="testMethod('minimal')">🧪 בדוק שיטה זו</button>
    <div id="result-minimal" class="test-result"></div>
    
    <details>
        <summary>פרטים טכניים</summary>
        <ul>
            <li>יתרונות: עובד תמיד, לא דורש התקנות</li>
            <li>חסרונות: PDF בסיסי, ללא פונטים מיוחדים</li>
            <li>תמיכה בעברית: מוגבלת</li>
        </ul>
    </details>
</div>

<?php
// שיטה 2: FPDF
$fpdfReady = file_exists('fpdf/fpdf.php');
?>
<div class="method-card">
    <div class="method-title">שיטה 2: FPDF</div>
    <?php if ($fpdfReady): ?>
        <div class="status-ready">✅ מוכן לשימוש!</div>
        <button class="test-button" onclick="testMethod('fpdf')">🧪 בדוק שיטה זו</button>
    <?php else: ?>
        <div class="status-not-ready">❌ לא מותקן</div>
        <div class="setup-instructions">
            <h4>📦 הוראות התקנה:</h4>
            <p><strong>אופציה 1: הורדה ידנית</strong></p>
            <ol>
                <li>הורד את FPDF מ: <a href="http://www.fpdf.org/en/download.php" target="_blank">http://www.fpdf.org/</a></li>
                <li>חלץ את הקובץ ZIP</li>
                <li>העלה את תיקיית <code>fpdf</code> לתיקייה הנוכחית</li>
            </ol>
            
            <p><strong>אופציה 2: דרך השרת</strong></p>
            <div class="command-box">
cd <?php echo __DIR__; ?><br>
wget http://www.fpdf.org/downloads/fpdf186.zip<br>
unzip fpdf186.zip<br>
mv fpdf186 fpdf<br>
rm fpdf186.zip
            </div>
            
            <button class="test-button" disabled>🧪 התקן תחילה</button>
        </div>
    <?php endif; ?>
    <div id="result-fpdf" class="test-result"></div>
    
    <details>
        <summary>פרטים טכניים</summary>
        <ul>
            <li>יתרונות: PDF איכותי, תמיכה בפונטים</li>
            <li>חסרונות: דורש התקנה</li>
            <li>תמיכה בעברית: טובה עם פונטים מתאימים</li>
        </ul>
    </details>
</div>

<?php
// שיטה 3: HTML Output
?>
<div class="method-card">
    <div class="method-title">שיטה 3: HTML Output</div>
    <div class="status-ready">✅ מוכן לשימוש - לא דורש התקנה!</div>
    <p>יוצר HTML שניתן להמיר ל-PDF דרך הדפדפן (Ctrl+P).</p>
    
    <button class="test-button" onclick="testMethod('html')">🧪 בדוק שיטה זו</button>
    <div id="result-html" class="test-result"></div>
    
    <details>
        <summary>פרטים טכניים</summary>
        <ul>
            <li>יתרונות: תמיכה מלאה בעברית, CSS, עיצוב מתקדם</li>
            <li>חסרונות: דורש המרה ידנית ל-PDF</li>
            <li>תמיכה בעברית: מעולה!</li>
        </ul>
    </details>
</div>

<?php
// שיטה 4: System Commands
$wkhtmltopdf = shell_exec('which wkhtmltopdf 2>/dev/null');
$chrome = shell_exec('which google-chrome 2>/dev/null');
$chromium = shell_exec('which chromium-browser 2>/dev/null');
$systemReady = ($wkhtmltopdf || $chrome || $chromium);
?>
<div class="method-card">
    <div class="method-title">שיטה 4: System Commands</div>
    <?php if ($systemReady): ?>
        <div class="status-ready">✅ מוכן לשימוש!</div>
        <p>נמצאו הכלים הבאים:</p>
        <ul>
            <?php 
            if ($wkhtmltopdf) echo "<li>wkhtmltopdf</li>";
            if ($chrome) echo "<li>Google Chrome</li>";
            if ($chromium) echo "<li>Chromium</li>";
            ?>
        </ul>
        <button class="test-button" onclick="testMethod('system')">🧪 בדוק שיטה זו</button>
    <?php else: ?>
        <div class="status-not-ready">❌ לא זמין בשרת זה</div>
        <div class="setup-instructions">
            <h4>📦 הוראות התקנה (דורש גישת root):</h4>
            <div class="command-box">
# Ubuntu/Debian:
sudo apt-get update
sudo apt-get install wkhtmltopdf

# או
sudo apt-get install chromium-browser
            </div>
            <p>⚠️ רוב שרתי אירוח משותפים לא תומכים בזה</p>
            <button class="test-button" disabled>🧪 לא זמין</button>
        </div>
    <?php endif; ?>
    <div id="result-system" class="test-result"></div>
</div>

<?php
// שיטה 5: PostScript
?>
<div class="method-card">
    <div class="method-title">שיטה 5: PostScript</div>
    <div class="status-partial">⚠️ יוצר קובץ PS, דורש המרה ל-PDF</div>
    <p>יוצר קובץ PostScript שניתן להמיר ל-PDF עם כלים חיצוניים.</p>
    
    <button class="test-button" onclick="testMethod('postscript')">🧪 בדוק שיטה זו</button>
    <div id="result-postscript" class="test-result"></div>
    
    <details>
        <summary>פרטים טכניים</summary>
        <ul>
            <li>יתרונות: פורמט סטנדרטי</li>
            <li>חסרונות: דורש המרה עם ps2pdf או אונליין</li>
            <li>תמיכה בעברית: בסיסית</li>
        </ul>
    </details>
</div>

<!-- סיכום -->
<div class="method-card" style="background: #e3f2fd;">
    <h3>📊 סיכום מצב המערכת:</h3>
    <?php
    $readyMethods = ['minimal', 'html'];
    if ($fpdfReady) $readyMethods[] = 'fpdf';
    if ($systemReady) $readyMethods[] = 'system';
    ?>
    <ul>
        <li><strong>שיטות זמינות:</strong> <?php echo count($readyMethods); ?> מתוך 5</li>
        <li><strong>שיטה מומלצת:</strong> 
            <?php 
            if ($fpdfReady) {
                echo "FPDF - איכות טובה ותמיכה בעברית";
            } else {
                echo "HTML - תמיכה מלאה בעברית";
            }
            ?>
        </li>
        <li><strong>תיקיית output:</strong> <?php echo is_writable('output') ? '✅ ניתנת לכתיבה' : '❌ בעיית הרשאות'; ?></li>
    </ul>
</div>

<script>
async function testMethod(method) {
    const resultDiv = document.getElementById('result-' + method);
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '⏳ בודק...';
    resultDiv.className = 'test-result';
    
    const testData = {
        method: method,
        values: [
            {text: "Test " + method.toUpperCase(), x: 100, y: 100},
            {text: "בדיקה בעברית", x: 100, y: 120},
            {text: new Date().toLocaleString(), x: 100, y: 140}
        ]
    };
    
    // אם יש לנו קובץ template, נוסיף אותו
    if (method === 'fpdf' || method === 'system') {
        testData.filename = 'templates/sample.pdf';
    }
    
    try {
        const response = await fetch('process-pdf-simple.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(testData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            resultDiv.innerHTML = `
                <strong>✅ הצלחה!</strong><br>
                שיטה: ${result.method}<br>
                קובץ: ${result.filename}<br>
                <a href="${result.download_url}" target="_blank">📥 הורד/הצג קובץ</a>
            `;
        } else {
            resultDiv.innerHTML = `<strong>❌ שגיאה:</strong> ${result.error}`;
            resultDiv.className = 'test-result error';
        }
    } catch (error) {
        resultDiv.innerHTML = `<strong>❌ שגיאת רשת:</strong> ${error.message}`;
        resultDiv.className = 'test-result error';
    }
}

// בדיקה אוטומטית בטעינה
window.onload = function() {
    console.log('Test page loaded successfully');
}
</script>

</body>
</html>
<?php
ob_end_flush();
?>