<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>בדיקת כל שיטות ה-PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .method-box {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .method-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .status { 
            padding: 5px 10px; 
            border-radius: 5px; 
            display: inline-block;
            margin: 10px 0;
        }
        .status-ready { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-warning { background: #fff3cd; color: #856404; }
        button {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-test { background: #007bff; color: white; }
        .btn-view { background: #17a2b8; color: white; }
        .btn-download { background: #28a745; color: white; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .result {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
        .result.show { display: block; }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>

<h1>🧪 בדיקת כל שיטות יצירת PDF</h1>

<?php
// בדוק אילו קבצים קיימים
$methods = [
    'pdf-minimal.php' => 'Minimal PDF - ללא תלויות',
    'pdf-html.php' => 'HTML - להמרה ידנית',
    'pdf-fpdf.php' => 'FPDF - איכותי (דורש התקנה)',
    'pdf-postscript.php' => 'PostScript - להמרה'
];

foreach ($methods as $file => $description) {
    $exists = file_exists($file);
    $method_id = str_replace(['pdf-', '.php'], '', $file);
    ?>
    <div class="method-box">
        <div class="method-title"><?php echo $description; ?></div>
        <div class="status <?php echo $exists ? 'status-ready' : 'status-error'; ?>">
            <?php echo $exists ? '✅ הקובץ קיים' : '❌ הקובץ חסר'; ?>
        </div>
        
        <?php if ($exists): ?>
            <div>
                <button class="btn-test" onclick="testMethod('<?php echo $file; ?>', '<?php echo $method_id; ?>')">
                    בדוק שיטה
                </button>
                <button class="btn-test" onclick="checkStatus('<?php echo $file; ?>', '<?php echo $method_id; ?>')">
                    בדוק סטטוס
                </button>
            </div>
            <div id="result-<?php echo $method_id; ?>" class="result"></div>
        <?php else: ?>
            <div class="status status-warning">
                העלה את הקובץ <?php echo $file; ?> לשרת
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<div class="method-box" style="background: #e3f2fd;">
    <h3>נתוני בדיקה:</h3>
    <pre id="test-data">{
    "values": [
        {"text": "Test PDF", "x": 100, "y": 100, "fontSize": 20},
        {"text": "בדיקה בעברית", "x": 100, "y": 130},
        {"text": "<?php echo date('Y-m-d H:i:s'); ?>", "x": 100, "y": 160}
    ]
}</pre>
</div>

<script>
// בדוק סטטוס של שיטה
async function checkStatus(file, method) {
    const resultDiv = document.getElementById('result-' + method);
    resultDiv.innerHTML = 'בודק...';
    resultDiv.classList.add('show');
    
    try {
        const response = await fetch(file + '?test=1');
        const data = await response.json();
        
        resultDiv.innerHTML = `<strong>סטטוס:</strong><br>
            <pre>${JSON.stringify(data, null, 2)}</pre>`;
    } catch (error) {
        resultDiv.innerHTML = `<span style="color: red;">שגיאה: ${error.message}</span>`;
    }
}

// בדוק שיטה
async function testMethod(file, method) {
    const resultDiv = document.getElementById('result-' + method);
    resultDiv.innerHTML = 'מעבד...';
    resultDiv.classList.add('show');
    
    const testData = {
        values: [
            {text: "Test " + method.toUpperCase(), x: 100, y: 100, fontSize: 20},
            {text: "בדיקה בעברית", x: 100, y: 130},
            {text: new Date().toLocaleString(), x: 100, y: 160}
        ]
    };
    
    if (method === 'html') {
        testData.language = 'he';
    }
    
    try {
        const response = await fetch(file, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(testData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            resultDiv.innerHTML = `
                <strong style="color: green;">✅ הצלחה!</strong><br>
                שיטה: ${result.method}<br>
                קובץ: ${result.filename}<br>
                ${result.message ? 'הערה: ' + result.message + '<br>' : ''}
                <br>
                <button class="btn-view" onclick="window.open('${result.view_url || result.direct_url}', '_blank')">
                    👁️ הצג
                </button>
                <button class="btn-download" onclick="forceDownload('${result.download_url || result.direct_url}', '${result.filename}')">
                    📥 הורד
                </button>
                <br><br>
                <details>
                    <summary>פרטי התגובה</summary>
                    <pre>${JSON.stringify(result, null, 2)}</pre>
                </details>
            `;
        } else {
            resultDiv.innerHTML = `
                <strong style="color: red;">❌ שגיאה</strong><br>
                ${result.error}<br>
                <pre>${JSON.stringify(result, null, 2)}</pre>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <strong style="color: red;">❌ שגיאת רשת</strong><br>
            ${error.message}
        `;
    }
}

// פונקציית הורדה
function forceDownload(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

</body>
</html>