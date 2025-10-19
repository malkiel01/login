<?php
/**
 * אבחון SmartSelect - בדיקת תקינות והדפסת דוח
 * נועד לזהות בעיות עם הסלקט החכם
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// מערך לשמירת הבעיות והתוצאות
$issues = [];
$checks = [];

// פונקציה להוספת בדיקה
function addCheck($name, $status, $message = '') {
    global $checks;
    $checks[] = [
        'name' => $name,
        'status' => $status,
        'message' => $message
    ];
}

// פונקציה להוספת בעיה
function addIssue($severity, $message, $solution = '') {
    global $issues;
    $issues[] = [
        'severity' => $severity,
        'message' => $message,
        'solution' => $solution
    ];
}

echo '<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>אבחון SmartSelect</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section h2 {
            margin-top: 0;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .check {
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .check.pass {
            background: #d4edda;
            border-right: 4px solid #28a745;
        }
        .check.fail {
            background: #f8d7da;
            border-right: 4px solid #dc3545;
        }
        .check.warning {
            background: #fff3cd;
            border-right: 4px solid #ffc107;
        }
        .icon {
            font-size: 24px;
            font-weight: bold;
        }
        .issue {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
        }
        .issue.critical {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .issue h4 {
            margin-top: 0;
            color: #856404;
        }
        .issue.critical h4 {
            color: #721c24;
        }
        .solution {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>';

echo '<div class="header">
    <h1>🔍 אבחון מערכת SmartSelect</h1>
    <p>בדיקת תקינות ואיתור בעיות בסלקט החכם</p>
</div>';

// ======================================
// בדיקה 1: קבצי PHP
// ======================================
$basePath = __DIR__ . '/dashboard/dashboards/cemeteries';
$formsPath = $basePath . '/forms';

$requiredFiles = [
    'SmartSelect.php' => $formsPath . '/SmartSelect.php',
    'FormBuilder.php' => $formsPath . '/FormBuilder.php',
    'customer-form.php' => $formsPath . '/customer-form.php'
];

foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        addCheck("קובץ $name", 'pass', "נמצא ב: $path");
    } else {
        addCheck("קובץ $name", 'fail', "לא נמצא ב: $path");
        addIssue('critical', "קובץ $name חסר", "צור את הקובץ בנתיב: $path");
    }
}

// ======================================
// בדיקה 2: קבצי JS ו-CSS
// ======================================
$jsPath = $basePath . '/js/smart-select.js';
$cssPath = $basePath . '/css/smart-select.css';

if (file_exists($jsPath)) {
    addCheck('קובץ JavaScript', 'pass', "נמצא ב: $jsPath");
    
    // בדוק אם יש את הפונקציות הנדרשות
    $jsContent = file_get_contents($jsPath);
    if (strpos($jsContent, 'SmartSelectManager') !== false) {
        addCheck('SmartSelectManager מוגדר', 'pass');
    } else {
        addCheck('SmartSelectManager מוגדר', 'fail');
        addIssue('critical', 'SmartSelectManager לא מוגדר ב-JS');
    }
} else {
    addCheck('קובץ JavaScript', 'fail', "לא נמצא ב: $jsPath");
    addIssue('critical', 'קובץ JavaScript חסר', "צור את הקובץ: $jsPath");
}

if (file_exists($cssPath)) {
    addCheck('קובץ CSS', 'pass', "נמצא ב: $cssPath");
} else {
    addCheck('קובץ CSS', 'warning', "לא נמצא ב: $cssPath");
    addIssue('warning', 'קובץ CSS חסר - העיצוב לא יעבוד כראוי');
}

// ======================================
// בדיקה 3: בדיקת SmartSelect.php
// ======================================
if (file_exists($requiredFiles['SmartSelect.php'])) {
    require_once $requiredFiles['SmartSelect.php'];
    
    if (class_exists('SmartSelect')) {
        addCheck('מחלקת SmartSelect קיימת', 'pass');
        
        // בדוק מתודות
        $methods = get_class_methods('SmartSelect');
        $requiredMethods = ['__construct', 'render', 'create'];
        
        foreach ($requiredMethods as $method) {
            if (in_array($method, $methods)) {
                addCheck("מתודה $method", 'pass');
            } else {
                addCheck("מתודה $method", 'fail');
                addIssue('critical', "מתודה $method חסרה במחלקת SmartSelect");
            }
        }
    } else {
        addCheck('מחלקת SmartSelect קיימת', 'fail');
        addIssue('critical', 'מחלקת SmartSelect לא מוגדרת');
    }
}

// ======================================
// בדיקה 4: בדיקת חיבור למסד נתונים
// ======================================
$configPath = __DIR__ . '/dashboard/dashboards/cemeteries/config.php';
if (file_exists($configPath)) {
    addCheck('קובץ config.php', 'pass');
    
    require_once $configPath;
    
    if (function_exists('getDBConnection')) {
        addCheck('פונקציית getDBConnection', 'pass');
        
        try {
            $conn = getDBConnection();
            addCheck('חיבור למסד נתונים', 'pass');
            
            // בדוק טבלאות
            $tables = ['customers', 'countries', 'cities'];
            foreach ($tables as $table) {
                $stmt = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    addCheck("טבלה $table", 'pass');
                } else {
                    addCheck("טבלה $table", 'fail');
                    addIssue('critical', "טבלה $table לא קיימת במסד הנתונים");
                }
            }
            
        } catch (Exception $e) {
            addCheck('חיבור למסד נתונים', 'fail', $e->getMessage());
            addIssue('critical', 'לא ניתן להתחבר למסד נתונים', $e->getMessage());
        }
    } else {
        addCheck('פונקציית getDBConnection', 'fail');
    }
} else {
    addCheck('קובץ config.php', 'fail');
    addIssue('critical', 'קובץ config.php חסר');
}

// ======================================
// בדיקה 5: בדיקת customer-form.php
// ======================================
if (file_exists($requiredFiles['customer-form.php'])) {
    $formContent = file_get_contents($requiredFiles['customer-form.php']);
    
    // בדוק אם יש require של SmartSelect
    if (strpos($formContent, 'SmartSelect.php') !== false || 
        strpos($formContent, "SmartSelect::create") !== false) {
        addCheck('SmartSelect נטען בטופס', 'pass');
    } else {
        addCheck('SmartSelect נטען בטופס', 'warning');
        addIssue('warning', 'SmartSelect אולי לא נטען כראוי בטופס הלקוח');
    }
    
    // בדוק אם יש טעינת JS
    if (strpos($formContent, 'smart-select.js') !== false) {
        addCheck('smart-select.js נטען בטופס', 'pass');
    } else {
        addCheck('smart-select.js נטען בטופס', 'fail');
        addIssue('critical', 'קובץ JS לא נטען בטופס', 
            '<script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>');
    }
    
    // בדוק אם יש טעינת CSS
    if (strpos($formContent, 'smart-select.css') !== false) {
        addCheck('smart-select.css נטען בטופס', 'pass');
    } else {
        addCheck('smart-select.css נטען בטופס', 'warning');
    }
}

// ======================================
// הצגת תוצאות
// ======================================

// סיכום
$totalChecks = count($checks);
$passedChecks = count(array_filter($checks, fn($c) => $c['status'] === 'pass'));
$failedChecks = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));
$warningChecks = count(array_filter($checks, fn($c) => $c['status'] === 'warning'));

echo '<div class="summary">
    <div class="stat-card">
        <div class="stat-label">סה"כ בדיקות</div>
        <div class="stat-number" style="color: #667eea;">' . $totalChecks . '</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">עברו בהצלחה</div>
        <div class="stat-number" style="color: #28a745;">' . $passedChecks . '</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">נכשלו</div>
        <div class="stat-number" style="color: #dc3545;">' . $failedChecks . '</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">אזהרות</div>
        <div class="stat-number" style="color: #ffc107;">' . $warningChecks . '</div>
    </div>
</div>';

// תוצאות בדיקות
echo '<div class="section">
    <h2>📋 תוצאות בדיקות</h2>';

foreach ($checks as $check) {
    $statusClass = $check['status'];
    $icon = $check['status'] === 'pass' ? '✅' : ($check['status'] === 'fail' ? '❌' : '⚠️');
    
    echo '<div class="check ' . $statusClass . '">
        <span class="icon">' . $icon . '</span>
        <div style="flex: 1;">
            <strong>' . htmlspecialchars($check['name']) . '</strong>';
    
    if (!empty($check['message'])) {
        echo '<br><small style="color: #666;">' . htmlspecialchars($check['message']) . '</small>';
    }
    
    echo '</div>
    </div>';
}

echo '</div>';

// בעיות שנמצאו
if (!empty($issues)) {
    echo '<div class="section">
        <h2>🚨 בעיות שנמצאו</h2>';
    
    foreach ($issues as $issue) {
        $issueClass = $issue['severity'] === 'critical' ? 'critical' : '';
        $icon = $issue['severity'] === 'critical' ? '🔴' : '🟡';
        
        echo '<div class="issue ' . $issueClass . '">
            <h4>' . $icon . ' ' . htmlspecialchars($issue['message']) . '</h4>';
        
        if (!empty($issue['solution'])) {
            echo '<div class="solution">
                <strong>פתרון:</strong><br>
                ' . htmlspecialchars($issue['solution']) . '
            </div>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
} else {
    echo '<div class="section" style="background: #d4edda; border: 2px solid #28a745;">
        <h2 style="color: #155724; border-color: #155724;">✅ כל הבדיקות עברו בהצלחה!</h2>
        <p style="color: #155724;">המערכת מוכנה לשימוש.</p>
    </div>';
}

// המלצות לתיקון
if (!empty($issues)) {
    echo '<div class="section">
        <h2>🔧 המלצות לתיקון</h2>
        <ol style="line-height: 2;">';
    
    $criticalIssues = array_filter($issues, fn($i) => $i['severity'] === 'critical');
    
    if (!empty($criticalIssues)) {
        echo '<li><strong>טפל תחילה בבעיות קריטיות (🔴)</strong></li>';
    }
    
    echo '<li>וודא שכל הקבצים קיימים בנתיבים הנכונים</li>
        <li>בדוק שה-JS וה-CSS נטענים בטופס הלקוח</li>
        <li>וודא שהחיבור למסד הנתונים תקין</li>
        <li>הרץ את הסקריפט: <code>complete-fix.sh</code> לתיקון אוטומטי</li>
        </ol>
    </div>';
}

// קוד לדוגמה לשילוב נכון
echo '<div class="section">
    <h2>💡 דוגמה לשימוש נכון</h2>
    <p>כך צריך להראות טופס הלקוח עם SmartSelect:</p>
    <div class="code">' . htmlspecialchars('<?php
// בתחילת הטופס
require_once __DIR__ . \'/SmartSelect.php\';
require_once __DIR__ . \'/FormBuilder.php\';

// יצירת SmartSelect למדינות
$countrySelect = SmartSelect::create(\'countryId\', \'מדינה\', $countries, [
    \'searchable\' => true,
    \'placeholder\' => \'חפש מדינה...\',
    \'display_mode\' => \'advanced\'
]);

echo $countrySelect->render();
?>

<!-- בסוף הטופס -->
<script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>
<script>
document.addEventListener(\'DOMContentLoaded\', function() {
    SmartSelectManager.init(\'countryId\', {
        searchable: true
    });
});
</script>') . '</div>
</div>';

echo '</body></html>';
?>