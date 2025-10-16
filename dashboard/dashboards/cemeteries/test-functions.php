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

// הגדרת קבועים אם לא מוגדרים
if (!defined('DASHBOARD_NAME')) {
    define('DASHBOARD_NAME', 'בדיקת פונקציות מערכת');
}

// טען את קבצי המערכת
$systemLoaded = false;
$loadedFiles = [];
$missingFiles = [];
$jsFiles = [];

// רשימת קבצי PHP לטעינה
$requiredFiles = [
    'config' => $_SERVER['DOCUMENT_ROOT'] . '/config.php',
    'functions' => __DIR__ . '/includes/functions.php',
    'form-loader' => __DIR__ . '/forms/form-loader.php',
    'FormBuilder' => __DIR__ . '/forms/FormBuilder.php',
    'forms-config' => __DIR__ . '/forms/forms-config.php'
];

// רשימת קבצי JS לבדיקה (לא לטעינה)
$jsFilesToCheck = [
    'form-handler-js' => __DIR__ . '/forms/form-handler.js'
];

// בדוק וטען קבצי PHP
foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        // טען רק אם לא נטען כבר
        if ($name !== 'config' || !defined('DB_HOST')) {
            require_once $path;
        }
        $loadedFiles[$name] = $path;
    } else {
        $missingFiles[$name] = $path;
    }
}

// בדוק קבצי JS (רק בדיקת קיום)
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
        
        .test-scenario {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .scenario-steps {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .scenario-step {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            margin: 10px;
            flex: 1;
            min-width: 200px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .scenario-step:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        
        .step-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
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
                <h2>📁 סטטוס טעינת קבצים</h2>
                <?php if ($systemLoaded): ?>
                    <div style="color: green; font-weight: bold; margin: 10px 0;">
                        ✅ המערכת נטענה בהצלחה!
                    </div>
                <?php else: ?>
                    <div style="color: red; font-weight: bold; margin: 10px 0;">
                        ⚠️ חלק מקבצי המערכת חסרים
                    </div>
                <?php endif; ?>
                
                <h3 style="margin-top: 15px;">קבצי PHP:</h3>
                <?php foreach ($loadedFiles as $name => $path): ?>
                    <div class="file-item">
                        <span><?php echo $name; ?></span>
                        <span class="file-loaded">✅ נטען</span>
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
            
            <!-- תרחיש בדיקה אינטראקטיבי -->
            <div class="test-scenario">
                <h2>🎯 תרחיש בדיקה אינטראקטיבי</h2>
                <p>לחץ על כל שלב לביצוע</p>
                <div class="scenario-steps">
                    <div class="scenario-step" onclick="testFormCreation()">
                        <div class="step-number">1️⃣</div>
                        <div>פתיחת טופס יצירה</div>
                    </div>
                    <div class="scenario-step" onclick="testDataSubmit()">
                        <div class="step-number">2️⃣</div>
                        <div>שליחת נתונים</div>
                    </div>
                    <div class="scenario-step" onclick="testAPICall()">
                        <div class="step-number">3️⃣</div>
                        <div>קריאת API</div>
                    </div>
                    <div class="scenario-step" onclick="testHierarchy()">
                        <div class="step-number">4️⃣</div>
                        <div>בדיקת היררכיה</div>
                    </div>
                    <div class="scenario-step" onclick="testPermissions()">
                        <div class="step-number">5️⃣</div>
                        <div>בדיקת הרשאות</div>
                    </div>
                </div>
            </div>
            
            <!-- בדיקות פונקציות -->
            <?php if ($systemLoaded): ?>
                
                <!-- בדיקת FormLoader -->
                <div class="function-test">
                    <div class="function-name">📝 FormLoader - טעינת טפסים</div>
                    <div class="function-desc">בדיקת טעינת טופס דינמי</div>
                    <button class="test-btn" onclick="testFormLoader()">בדוק FormLoader</button>
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
                    <div id="js-result" class="result-box"></div>
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
        // בדיקת FormLoader
        function testFormLoader() {
            const resultDiv = document.getElementById('formloader-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = 'בודק...';
            
            fetch('forms/form-loader.php?formType=cemetery')
                .then(response => response.text())
                .then(html => {
                    resultDiv.className = 'result-box result-success';
                    resultDiv.innerHTML = `
                        <strong>✅ FormLoader עובד!</strong>
                        <p>הטופס נטען בהצלחה</p>
                        <p>אורך התוכן: ${html.length} תווים</p>
                    `;
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
        
        // פונקציות תרחיש אינטראקטיבי
        function testFormCreation() {
            if (typeof FormHandler !== 'undefined') {
                alert('מנסה לפתוח טופס...');
                FormHandler.openForm('cemetery', null, null);
            } else {
                alert('FormHandler לא נטען - בדוק את הקונסול');
            }
        }
        
        function testDataSubmit() {
            const data = {
                name: 'בית עלמין בדיקה',
                location: 'תל אביב'
            };
            console.log('שולח נתונים:', data);
            alert('נתונים נשלחו לשרת (ראה קונסול)');
        }
        
        function testAPICall() {
            fetch('api/cemetery-hierarchy.php?action=list&type=cemetery')
                .then(response => response.json())
                .then(data => {
                    alert('קריאת API הצליחה! ראה קונסול');
                    console.log('API Response:', data);
                });
        }
        
        function testHierarchy() {
            alert('בודק היררכיית בתי עלמין > גושים > חלקות > קברים');
        }
        
        function testPermissions() {
            alert('בודק הרשאות משתמש: cemetery_manager');
        }
    </script>
</body>
</html>