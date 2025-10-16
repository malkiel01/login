<?php
// dashboard/dashboards/cemeteries/qa-system-functions.php
// בדיקת פונקציות המערכת האמיתיות

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// עקיפת אימות
$_SESSION['user_id'] = 999999;
$_SESSION['dashboard_type'] = 'cemetery_manager';
$_SESSION['username'] = 'QA_TESTER';

// טען config רק אם לא נטען כבר
if (!defined('DB_HOST')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
}

// טען את קבצי המערכת
$systemLoaded = false;
$loadedFiles = [];
$missingFiles = [];
$jsFiles = [];

// רשימת קבצי PHP לבדיקה בלבד (לא טעינה)
$requiredFiles = [
    'config' => $_SERVER['DOCUMENT_ROOT'] . '/config.php',
    'functions' => __DIR__ . '/includes/functions.php',
    'form-loader' => __DIR__ . '/forms/form-loader.php',
    'FormBuilder' => __DIR__ . '/forms/FormBuilder.php',
    'forms-config' => __DIR__ . '/forms/forms-config.php'
];

// רשימת קבצי JS לבדיקה
$jsFilesToCheck = [
    'form-handler-js' => __DIR__ . '/forms/form-handler.js'
];

// בדוק קבצי PHP (רק בדיקת קיום, לא טעינה)
foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        // טען רק את הקבצים ההכרחיים שלא גורמים לבעיות
        if ($name === 'functions' && !function_exists('checkPermission')) {
            require_once $path;
        } elseif ($name === 'FormBuilder' && !class_exists('FormBuilder')) {
            require_once $path;
        } elseif ($name === 'forms-config') {
            // forms-config בטוח לטעינה
            require_once $path;
        }
        $loadedFiles[$name] = $path;
    } else {
        $missingFiles[$name] = $path;
    }
}

// בדוק קבצי JS
foreach ($jsFilesToCheck as $name => $path) {
    if (file_exists($path)) {
        $jsFiles[$name] = $path;
    } else {
        $missingFiles[$name] = $path;
    }
}

$systemLoaded = empty($missingFiles);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 בדיקת פונקציות מערכת</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        
        .test-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .test-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .test-content {
            padding: 30px;
        }
        
        .function-test {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .function-test:hover {
            border-color: #667eea;
            transform: translateX(-5px);
        }
        
        .function-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .function-desc {
            color: #666;
            margin-bottom: 15px;
        }
        
        .test-btn {
            padding: 8px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
        }
        
        .test-btn:hover {
            background: #5a67d8;
        }
        
        .result-box {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            display: none;
        }
        
        .result-success {
            background: #d4edda;
            border-color: #28a745;
        }
        
        .result-error {
            background: #f8d7da;
            border-color: #dc3545;
        }
        
        .files-status {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .file-loaded { color: #28a745; }
        .file-js { color: #17a2b8; }
        .file-missing { color: #dc3545; }
        
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>🔧 בדיקת פונקציות מערכת בתי עלמין</h1>
            <p>בדיקה ישירה של הפונקציות והטפסים במערכת</p>
        </div>
        
        <div class="test-content">
            <!-- סטטוס קבצים -->
            <div class="files-status">
                <h2>📁 סטטוס קבצים</h2>
                <?php if ($systemLoaded): ?>
                    <div style="color: green; font-weight: bold; margin: 10px 0;">
                        ✅ כל הקבצים קיימים!
                    </div>
                <?php else: ?>
                    <div style="color: red; font-weight: bold; margin: 10px 0;">
                        ⚠️ חלק מהקבצים חסרים
                    </div>
                <?php endif; ?>
                
                <h3 style="margin-top: 15px;">קבצי PHP:</h3>
                <?php foreach ($loadedFiles as $name => $path): ?>
                    <div class="file-item">
                        <span><?php echo $name; ?></span>
                        <span class="file-loaded">✅ קיים</span>
                    </div>
                <?php endforeach; ?>
                
                <h3 style="margin-top: 15px;">קבצי JavaScript:</h3>
                <?php foreach ($jsFiles as $name => $path): ?>
                    <div class="file-item">
                        <span><?php echo $name; ?></span>
                        <span class="file-js">✅ קיים</span>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!empty($missingFiles)): ?>
                    <h3 style="margin-top: 15px; color: red;">קבצים חסרים:</h3>
                    <?php foreach ($missingFiles as $name => $path): ?>
                        <div class="file-item">
                            <span><?php echo $name; ?></span>
                            <span class="file-missing">❌ חסר</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- בדיקות פונקציות -->
            <?php if ($systemLoaded): ?>
                
                <!-- בדיקת FormLoader -->
                <div class="function-test">
                    <div class="function-name">📝 FormLoader - טעינת טפסים</div>
                    <div class="function-desc">בדיקת טעינת טופס דינמי</div>
                    <button class="test-btn" onclick="testFormLoader('cemetery')">טופס בית עלמין</button>
                    <button class="test-btn" onclick="testFormLoader('customer')">טופס לקוח</button>
                    <button class="test-btn" onclick="testFormLoader('purchase')">טופס רכישה</button>
                    <div id="formloader-result" class="result-box"></div>
                </div>
                
                <!-- בדיקת API -->
                <div class="function-test">
                    <div class="function-name">🌐 API Endpoints</div>
                    <div class="function-desc">בדיקת נקודות קצה של ה-API</div>
                    <button class="test-btn" onclick="testAPI('cemetery-hierarchy')">היררכיה</button>
                    <button class="test-btn" onclick="testAPI('customers')">לקוחות</button>
                    <button class="test-btn" onclick="testAPI('purchases')">רכישות</button>
                    <div id="api-result" class="result-box"></div>
                </div>
                
                <!-- בדיקת form-handler.js -->
                <div class="function-test">
                    <div class="function-name">🔧 FormHandler JavaScript</div>
                    <div class="function-desc">בדיקת טעינת הקובץ form-handler.js</div>
                    <button class="test-btn" onclick="testFormHandlerJS()">בדוק JS</button>
                    <button class="test-btn" onclick="testOpenForm()">נסה לפתוח טופס</button>
                    <div id="js-result" class="result-box"></div>
                </div>
                
                <!-- בדיקת חיבור למסד נתונים -->
                <div class="function-test">
                    <div class="function-name">🗄️ חיבור למסד נתונים</div>
                    <div class="function-desc">בדיקת חיבור ל-MySQL</div>
                    <button class="test-btn" onclick="testDatabase()">בדוק חיבור</button>
                    <div id="db-result" class="result-box"></div>
                </div>
                
            <?php else: ?>
                <div style="background: #f8d7da; padding: 20px; border-radius: 10px; color: #721c24;">
                    <h3>⚠️ לא ניתן להריץ בדיקות</h3>
                    <p>חלק מקבצי המערכת חסרים. יש לוודא שכל הקבצים הנדרשים קיימים.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- טען את form-handler.js אם קיים -->
    <?php if (isset($jsFiles['form-handler-js'])): ?>
        <script src="forms/form-handler.js"></script>
    <?php endif; ?>
    
    <script>
        // הגדר API_BASE אם לא מוגדר
        if (typeof API_BASE === 'undefined') {
            window.API_BASE = '/dashboard/dashboards/cemeteries/api/';
        }
        
        // בדיקת FormLoader
        function testFormLoader(formType) {
            const resultDiv = document.getElementById('formloader-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = 'בודק טופס ' + formType + '...';
            
            fetch(`forms/form-loader.php?formType=${formType}`)
                .then(response => response.text())
                .then(html => {
                    const hasError = html.includes('שגיאה') || html.includes('error');
                    
                    if (hasError) {
                        resultDiv.className = 'result-box result-error';
                        resultDiv.innerHTML = `
                            <strong>❌ בעיה בטעינת הטופס ${formType}</strong>
                            <pre>${html.substring(0, 500)}</pre>
                        `;
                    } else {
                        resultDiv.className = 'result-box result-success';
                        resultDiv.innerHTML = `
                            <strong>✅ טופס ${formType} נטען בהצלחה!</strong>
                            <p>אורך התוכן: ${html.length} תווים</p>
                        `;
                    }
                })
                .catch(error => {
                    resultDiv.className = 'result-box result-error';
                    resultDiv.innerHTML = `❌ שגיאה: ${error}`;
                });
        }
        
        // בדיקת API
        function testAPI(endpoint) {
            const resultDiv = document.getElementById('api-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = 'בודק...';
            
            let url = '';
            switch(endpoint) {
                case 'cemetery-hierarchy':
                    url = 'api/cemetery-hierarchy.php?action=list&type=cemetery';
                    break;
                case 'customers':
                    url = 'api/customers-api.php?action=list';
                    break;
                case 'purchases':
                    url = 'api/purchases-api.php?action=list';
                    break;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    resultDiv.className = 'result-box result-success';
                    resultDiv.innerHTML = `
                        <strong>✅ API ${endpoint} עובד!</strong>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                })
                .catch(error => {
                    resultDiv.className = 'result-box result-error';
                    resultDiv.innerHTML = `❌ שגיאה ב-API: ${error}`;
                });
        }
        
        // בדיקת FormHandler JavaScript
        function testFormHandlerJS() {
            const resultDiv = document.getElementById('js-result');
            resultDiv.style.display = 'block';
            
            if (typeof FormHandler !== 'undefined') {
                resultDiv.className = 'result-box result-success';
                resultDiv.innerHTML = `
                    <strong>✅ FormHandler JavaScript טעון!</strong>
                    <p>פונקציות זמינות:</p>
                    <ul>
                        <li>openForm: ${typeof FormHandler.openForm === 'function' ? '✅' : '❌'}</li>
                        <li>closeForm: ${typeof FormHandler.closeForm === 'function' ? '✅' : '❌'}</li>
                        <li>saveForm: ${typeof FormHandler.saveForm === 'function' ? '✅' : '❌'}</li>
                    </ul>
                `;
            } else {
                resultDiv.className = 'result-box result-error';
                resultDiv.innerHTML = '❌ FormHandler לא נטען';
            }
        }
        
        // נסיון לפתוח טופס
        function testOpenForm() {
            if (typeof FormHandler !== 'undefined' && typeof FormHandler.openForm === 'function') {
                try {
                    FormHandler.openForm('cemetery', null, null);
                    alert('✅ ניסיון פתיחת טופס הצליח - בדוק אם הטופס נפתח');
                } catch (e) {
                    alert('❌ שגיאה בפתיחת טופס: ' + e.message);
                }
            } else {
                alert('❌ FormHandler לא זמין');
            }
        }
        
        // בדיקת חיבור למסד נתונים
        function testDatabase() {
            const resultDiv = document.getElementById('db-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = 'בודק חיבור...';
            
            fetch('api/test-db.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.className = 'result-box result-success';
                        resultDiv.innerHTML = `
                            <strong>✅ חיבור למסד נתונים תקין!</strong>
                            <p>שרת: ${data.server || 'לא ידוע'}</p>
                            <p>מסד נתונים: ${data.database || 'לא ידוע'}</p>
                        `;
                    } else {
                        throw new Error(data.error || 'שגיאה לא ידועה');
                    }
                })
                .catch(error => {
                    resultDiv.className = 'result-box result-error';
                    resultDiv.innerHTML = `❌ שגיאה בחיבור: ${error}`;
                });
        }
    </script>
</body>
</html>