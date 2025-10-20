<?php
/**
 * Cemetery Dashboard - Sidebar Upgrade Script
 * =============================================
 * סקריפט זה משדרג את ה-Sidebar למצב אחיד ומודרני
 * 
 * מה הסקריפט עושה:
 * 1. יצירת גיבוי מלא של כל הקבצים שישתנו
 * 2. שדרוג sidebar.php - הסרת inline styles והוספת IDs
 * 3. שדרוג sidebar.css - עיצוב אחיד ומודרני
 * 4. שדרוג כל קבצי ה-JS - הוספת active state management
 * 5. הוספת פונקציית updateAllSidebarCounts ל-main.js
 * 
 * הוראות הפעלה:
 * 1. העלה את הקובץ לתיקייה dashboards/dashboard/cemeteries/
 * 2. פתח בדפדפן: http://your-domain.com/dashboard/dashboards/cemeteries/upgrade-sidebar.php
 * 3. לחץ על "התחל שדרוג"
 * 4. המתן לסיום ובדוק את התוצאות
 * 
 * שחזור במקרה של בעיה:
 * - כל הקבצים המקוריים נשמרים בתיקייה backup_TIMESTAMP
 * - ניתן לשחזר ידנית על ידי העתקה חזרה
 */

// הגדרות
define('BACKUP_DIR', __DIR__ . '/backup_' . date('Y-m-d_H-i-s'));
define('BASE_PATH', __DIR__);

// קבצים לשדרוג
$files_to_upgrade = [
    'includes/sidebar.php',
    'css/sidebar.css',
    'js/customers-management.js',
    'js/payments-management.js',
    'js/purchases-management.js',
    'js/burials-management.js',
    'js/main.js'
];

// בדיקת הרצה
$is_running = isset($_GET['action']) && $_GET['action'] === 'upgrade';

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>שדרוג Sidebar - Cemetery Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #1e293b;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .warning h3 {
            color: #92400e;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .warning ul {
            color: #78350f;
            margin-right: 20px;
        }
        
        .warning li {
            margin-bottom: 5px;
        }
        
        .files-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .files-list h3 {
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .file-item.exists {
            border-color: #22c55e;
        }
        
        .file-item.missing {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .file-icon {
            font-size: 1.5rem;
            margin-left: 10px;
        }
        
        .file-name {
            flex: 1;
            color: #475569;
            font-family: 'Courier New', monospace;
        }
        
        .file-status {
            font-size: 0.875rem;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 600;
        }
        
        .file-status.exists {
            background: #dcfce7;
            color: #166534;
        }
        
        .file-status.missing {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .button {
            width: 100%;
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .button-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .button-primary:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        }
        
        .log-container {
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
            display: none;
        }
        
        .log-container.show {
            display: block;
        }
        
        .log-line {
            padding: 4px 0;
            border-bottom: 1px solid #334155;
        }
        
        .log-line:last-child {
            border-bottom: none;
        }
        
        .log-success {
            color: #4ade80;
        }
        
        .log-error {
            color: #f87171;
        }
        
        .log-warning {
            color: #fbbf24;
        }
        
        .log-info {
            color: #60a5fa;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin: 20px 0;
            display: none;
        }
        
        .progress-bar.show {
            display: block;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 שדרוג Sidebar</h1>
            <p>Cemetery Management Dashboard</p>
        </div>
        
        <div class="warning">
            <h3>⚠️ חשוב לקרוא לפני השדרוג!</h3>
            <ul>
                <li>הסקריפט יוצר גיבוי אוטומטי לפני כל שינוי</li>
                <li>הגיבוי נשמר בתיקייה: <code>backup_<?php echo date('Y-m-d_H-i-s'); ?></code></li>
                <li>מומלץ להריץ את השדרוג בסביבת פיתוח תחילה</li>
                <li>וודא שיש לך גישת FTP/SSH לשחזור במקרה הצורך</li>
            </ul>
        </div>
        
        <div class="files-list">
            <h3>📁 קבצים שישודרגו:</h3>
            <?php foreach ($files_to_upgrade as $file): ?>
                <?php 
                $file_path = BASE_PATH . '/' . $file;
                $exists = file_exists($file_path);
                ?>
                <div class="file-item <?php echo $exists ? 'exists' : 'missing'; ?>">
                    <span class="file-icon"><?php echo $exists ? '✅' : '❌'; ?></span>
                    <span class="file-name"><?php echo $file; ?></span>
                    <span class="file-status <?php echo $exists ? 'exists' : 'missing'; ?>">
                        <?php echo $exists ? 'קיים' : 'חסר'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!$is_running): ?>
            <button class="button button-primary" onclick="startUpgrade()">
                🚀 התחל שדרוג
            </button>
        <?php endif; ?>
        
        <div class="progress-bar" id="progressBar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <div class="log-container" id="logContainer"></div>
    </div>
    
    <script>
        function startUpgrade() {
            document.getElementById('progressBar').classList.add('show');
            document.getElementById('logContainer').classList.add('show');
            
            window.location.href = '?action=upgrade';
        }
        
        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const logLine = document.createElement('div');
            logLine.className = `log-line log-${type}`;
            logLine.textContent = `[${new Date().toLocaleTimeString('he-IL')}] ${message}`;
            logContainer.appendChild(logLine);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        function updateProgress(percent) {
            document.getElementById('progressFill').style.width = percent + '%';
        }
    </script>
</body>
</html>

<?php

if ($is_running) {
    echo "<script>";
    echo "document.getElementById('progressBar').classList.add('show');";
    echo "document.getElementById('logContainer').classList.add('show');";
    echo "</script>";
    
    flush();
    ob_flush();
    
    runUpgrade();
}

function runUpgrade() {
    global $files_to_upgrade;
    
    logMessage("🚀 מתחיל תהליך שדרוג...", 'info');
    updateProgress(5);
    
    // שלב 1: יצירת תיקיית גיבוי
    if (!createBackupDirectory()) {
        logMessage("❌ שגיאה: לא ניתן ליצור תיקיית גיבוי!", 'error');
        return false;
    }
    updateProgress(10);
    
    // שלב 2: גיבוי קבצים
    if (!backupFiles($files_to_upgrade)) {
        logMessage("❌ שגיאה בגיבוי קבצים!", 'error');
        return false;
    }
    updateProgress(25);
    
    // שלב 3: שדרוג sidebar.php
    logMessage("📝 משדרג sidebar.php...", 'info');
    if (upgradeSidebarPHP()) {
        logMessage("✅ sidebar.php שודרג בהצלחה", 'success');
    } else {
        logMessage("⚠️ sidebar.php לא נמצא או אירעה שגיאה", 'warning');
    }
    updateProgress(40);
    
    // שלב 4: שדרוג sidebar.css
    logMessage("🎨 משדרג sidebar.css...", 'info');
    if (upgradeSidebarCSS()) {
        logMessage("✅ sidebar.css שודרג בהצלחה", 'success');
    } else {
        logMessage("⚠️ sidebar.css לא נמצא או אירעה שגיאה", 'warning');
    }
    updateProgress(55);
    
    // שלב 5: שדרוג קבצי JavaScript
    $js_files = [
        'js/customers-management.js',
        'js/payments-management.js',
        'js/purchases-management.js',
        'js/burials-management.js'
    ];
    
    $progress = 55;
    $step = 10 / count($js_files);
    
    foreach ($js_files as $js_file) {
        logMessage("📜 משדרג $js_file...", 'info');
        if (upgradeJSFile($js_file)) {
            logMessage("✅ $js_file שודרג בהצלחה", 'success');
        } else {
            logMessage("⚠️ $js_file לא נמצא או אירעה שגיאה", 'warning');
        }
        $progress += $step;
        updateProgress($progress);
    }
    
    // שלב 6: שדרוג main.js
    logMessage("⚙️ משדרג main.js...", 'info');
    if (upgradeMainJS()) {
        logMessage("✅ main.js שודרג בהצלחה", 'success');
    } else {
        logMessage("⚠️ main.js לא נמצא או אירעה שגיאה", 'warning');
    }
    updateProgress(90);
    
    // סיום
    updateProgress(100);
    logMessage("🎉 תהליך השדרוג הושלם בהצלחה!", 'success');
    logMessage("📁 הגיבוי נשמר ב: " . BACKUP_DIR, 'info');
    logMessage("🔄 רענן את הדף כדי לראות את השינויים", 'info');
}

function createBackupDirectory() {
    if (!file_exists(BACKUP_DIR)) {
        if (!mkdir(BACKUP_DIR, 0755, true)) {
            return false;
        }
        logMessage("✅ תיקיית גיבוי נוצרה: " . basename(BACKUP_DIR), 'success');
    }
    return true;
}

function backupFiles($files) {
    foreach ($files as $file) {
        $source = BASE_PATH . '/' . $file;
        
        if (!file_exists($source)) {
            logMessage("⚠️ קובץ לא נמצא: $file", 'warning');
            continue;
        }
        
        $backup_path = BACKUP_DIR . '/' . dirname($file);
        if (!file_exists($backup_path)) {
            mkdir($backup_path, 0755, true);
        }
        
        $destination = BACKUP_DIR . '/' . $file;
        
        if (copy($source, $destination)) {
            logMessage("💾 גיבוי: $file", 'success');
        } else {
            logMessage("❌ שגיאה בגיבוי: $file", 'error');
            return false;
        }
    }
    return true;
}

function upgradeSidebarPHP() {
    $file = BASE_PATH . '/includes/sidebar.php';
    if (!file_exists($file)) return false;
    
    $content = file_get_contents($file);
    
    // הסרת inline styles והוספת IDs
    $replacements = [
        // לקוחות
        ['<div class="hierarchy-header" onclick="loadCustomers()" style="background: #f7fafc; cursor: pointer;">',
         '<div class="hierarchy-header" id="customersItem" onclick="loadCustomers()">'],
        
        // רכישות
        ['<div class="hierarchy-header" onclick="loadPurchases()" style="background: #f7fafc; cursor: pointer;">',
         '<div class="hierarchy-header" id="purchasesItem" onclick="loadPurchases()">'],
        
        // קבורות
        ['<div class="hierarchy-header" onclick="loadBurials()" style="background: #f7fafc; cursor: pointer;">',
         '<div class="hierarchy-header" id="burialsItem" onclick="loadBurials()">'],
        
        // תשלומים
        ['<div class="hierarchy-header" onclick="loadPayments()" style="background: #f0f9ff; border: 1px solid #3b82f6; cursor: pointer;">',
         '<div class="hierarchy-header" id="paymentsItem" onclick="loadPayments()">'],
        
        // הסרת כל style attributes אחרים מ-hierarchy-header
        ['/style="[^"]*"/i', '']
    ];
    
    foreach ($replacements as $replacement) {
        if (count($replacement) == 2) {
            $content = str_replace($replacement[0], $replacement[1], $content);
        } else {
            $content = preg_replace($replacement[0], $replacement[1], $content);
        }
    }
    
    return file_put_contents($file, $content) !== false;
}

function upgradeSidebarCSS() {
    $file = BASE_PATH . '/css/sidebar.css';
    if (!file_exists($file)) return false;
    
    $content = file_get_contents($file);
    
    // מציאת הקטע של hierarchy-header ומחיקתו
    $content = preg_replace(
        '/\/\* .*?hierarchy.*?items.*?\*\/.*?\.hierarchy-header\s*{.*?}/is',
        '',
        $content
    );
    
    // הוספת CSS החדש
    $new_css = <<<'CSS'

/* ============================================
   SIDEBAR MENU ITEMS - UNIFIED DESIGN
   ============================================ */

/* Base State - Clean and Modern */
.hierarchy-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    margin-bottom: 0.5rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    color: #475569;
    font-weight: 500;
}

/* Hover State - Subtle Blue Highlight */
.hierarchy-header:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
    color: #0369a1;
    transform: translateX(-3px);
    box-shadow: 0 2px 8px rgba(56, 189, 248, 0.15);
}

/* Active State - Bold Blue Gradient */
.hierarchy-header.active {
    background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%);
    color: white;
    font-weight: 600;
    border: none;
    transform: translateX(-5px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

/* Active State - Icon Animation */
.hierarchy-header.active .hierarchy-icon {
    transform: scale(1.15);
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

/* Active State - Title Styling */
.hierarchy-header.active .hierarchy-title {
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Active State - Count Badge */
.hierarchy-header.active .hierarchy-count {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.5);
    font-weight: 700;
}

/* Disabled State */
.hierarchy-header.disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
    opacity: 0.6;
}

.hierarchy-header.disabled:hover {
    transform: none;
    box-shadow: none;
}

/* ============================================
   ICON STYLING
   ============================================ */

.hierarchy-icon {
    font-size: 1.5rem;
    transition: transform 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.hierarchy-header:hover .hierarchy-icon {
    transform: scale(1.1) rotate(-5deg);
}

/* ============================================
   TITLE STYLING
   ============================================ */

.hierarchy-title {
    flex: 1;
    font-size: 0.9375rem;
    line-height: 1.4;
    transition: color 0.3s ease;
}

/* ============================================
   COUNT BADGE STYLING
   ============================================ */

.hierarchy-count {
    background: #e2e8f0;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    min-width: 28px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.hierarchy-header:hover .hierarchy-count {
    background: #bae6fd;
    color: #0369a1;
    border-color: #7dd3fc;
    transform: scale(1.05);
}

/* Loading State for Count */
.hierarchy-count.loading {
    background: #f1f5f9;
    color: transparent;
    position: relative;
    overflow: hidden;
}

.hierarchy-count.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, 
        transparent, 
        rgba(255, 255, 255, 0.5), 
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    to { left: 100%; }
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */

@media (max-width: 768px) {
    .hierarchy-header {
        padding: 0.75rem 0.875rem;
    }
    
    .hierarchy-icon {
        font-size: 1.25rem;
        width: 28px;
        height: 28px;
    }
    
    .hierarchy-title {
        font-size: 0.875rem;
    }
    
    .hierarchy-count {
        font-size: 0.75rem;
        padding: 0.2rem 0.6rem;
    }
}

/* ============================================
   COLLAPSED SIDEBAR STATE
   ============================================ */

.dashboard-sidebar.collapsed .hierarchy-title {
    display: none;
}

.dashboard-sidebar.collapsed .hierarchy-count {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 20px;
    padding: 0.15rem 0.4rem;
    font-size: 0.7rem;
}

.dashboard-sidebar.collapsed .hierarchy-header {
    justify-content: center;
    padding: 0.75rem;
}
CSS;
    
    $content .= $new_css;
    
    return file_put_contents($file, $content) !== false;
}

function upgradeJSFile($filename) {
    $file = BASE_PATH . '/' . $filename;
    if (!file_exists($file)) return false;
    
    $content = file_get_contents($file);
    
    // בדיקה אם כבר יש את הקוד
    if (strpos($content, 'Mark current item as active') !== false) {
        return true; // כבר שודרג
    }
    
    // קוד להוספה
    $code_to_add = <<<'JS'

    // Clear all sidebar selections
    if (typeof clearAllSidebarSelections === 'function') {
        clearAllSidebarSelections();
    }

    // ✅ Mark current item as active
    document.querySelectorAll('.hierarchy-header').forEach(header => {
        header.classList.remove('active');
    });
JS;
    
    // הוספת הקוד המתאים לכל קובץ
    if (strpos($filename, 'customers') !== false) {
        $code_to_add .= <<<'JS'

    const customersHeader = document.querySelector('[onclick="loadCustomers()"]');
    if (customersHeader) {
        customersHeader.classList.add('active');
    }
JS;
    } elseif (strpos($filename, 'payments') !== false) {
        $code_to_add .= <<<'JS'

    const paymentsHeader = document.querySelector('[onclick="loadPayments()"]');
    if (paymentsHeader) {
        paymentsHeader.classList.add('active');
    }
JS;
    } elseif (strpos($filename, 'purchases') !== false) {
        $code_to_add .= <<<'JS'

    const purchasesHeader = document.querySelector('[onclick="loadPurchases()"]');
    if (purchasesHeader) {
        purchasesHeader.classList.add('active');
    }
JS;
    } elseif (strpos($filename, 'burials') !== false) {
        $code_to_add .= <<<'JS'

    const burialsHeader = document.querySelector('[onclick="loadBurials()"]');
    if (burialsHeader) {
        burialsHeader.classList.add('active');
    }
JS;
    }
    
    // מציאת הפונקציה הראשית והוספת הקוד אחרי DashboardCleaner.clear()
    $patterns = [
        '/DashboardCleaner\.clear\(\);/i',
        '/contentArea\.innerHTML = .*?;/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insert_position = $matches[0][1] + strlen($matches[0][0]);
            $content = substr_replace($content, $code_to_add, $insert_position, 0);
            break;
        }
    }
    
    return file_put_contents($file, $content) !== false;
}

function upgradeMainJS() {
    $file = BASE_PATH . '/js/main.js';
    if (!file_exists($file)) return false;
    
    $content = file_get_contents($file);
    
    // בדיקה אם כבר יש את הפונקציה
    if (strpos($content, 'updateAllSidebarCounts') !== false) {
        return true;
    }
    
    // הפונקציה החדשה
    $new_function = <<<'JS'

/**
 * Update all sidebar item counts dynamically
 * Called on page load and after data changes
 */
async function updateAllSidebarCounts() {
    console.log('🔄 Updating sidebar counts...');
    
    try {
        // Show loading state
        document.querySelectorAll('.hierarchy-count').forEach(el => {
            el.classList.add('loading');
        });
        
        // 1. Customers Count
        try {
            const customersRes = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=stats');
            const customersData = await customersRes.json();
            if (customersData.success && customersData.data.by_status) {
                const total = Object.values(customersData.data.by_status)
                    .reduce((sum, count) => sum + parseInt(count || 0), 0);
                const countEl = document.getElementById('customersCount');
                if (countEl) {
                    countEl.textContent = total;
                    countEl.classList.remove('loading');
                }
            }
        } catch (e) {
            console.warn('Failed to load customers count:', e);
        }
        
        // 2. Purchases Count
        try {
            const purchasesRes = await fetch('/dashboard/dashboards/cemeteries/api/purchases-api.php?action=stats');
            const purchasesData = await purchasesRes.json();
            if (purchasesData.success && purchasesData.data.totals) {
                const countEl = document.getElementById('purchasesCount');
                if (countEl) {
                    countEl.textContent = purchasesData.data.totals.total_purchases || 0;
                    countEl.classList.remove('loading');
                }
            }
        } catch (e) {
            console.warn('Failed to load purchases count:', e);
        }
        
        // 3. Burials Count
        try {
            const burialsRes = await fetch('/dashboard/dashboards/cemeteries/api/burials-api.php?action=stats');
            const burialsData = await burialsRes.json();
            if (burialsData.success && burialsData.data.this_year) {
                const countEl = document.getElementById('burialsCount');
                if (countEl) {
                    countEl.textContent = burialsData.data.this_year || 0;
                    countEl.classList.remove('loading');
                }
            }
        } catch (e) {
            console.warn('Failed to load burials count:', e);
        }
        
        // 4. Payments Count
        try {
            const paymentsRes = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=list&limit=1');
            const paymentsData = await paymentsRes.json();
            if (paymentsData.success && paymentsData.pagination) {
                const countEl = document.getElementById('paymentsCount');
                if (countEl) {
                    countEl.textContent = paymentsData.pagination.totalAll || 0;
                    countEl.classList.remove('loading');
                }
            }
        } catch (e) {
            console.warn('Failed to load payments count:', e);
        }
        
        console.log('✅ Sidebar counts updated successfully');
        
    } catch (error) {
        console.error('❌ Error updating sidebar counts:', error);
    }
}

// Export for global access
window.updateAllSidebarCounts = updateAllSidebarCounts;

// Auto-update counts every 5 minutes
setInterval(updateAllSidebarCounts, 5 * 60 * 1000);

JS;
    
    // הוספה בסוף הקובץ
    $content .= $new_function;
    
    // עדכון initDashboard אם קיים
    if (strpos($content, 'function initDashboard()') !== false) {
        $content = preg_replace(
            '/(function initDashboard\(\)\s*{[^}]*)(})/s',
            '$1    // Update sidebar counts
    updateAllSidebarCounts();
$2',
            $content
        );
    }
    
    return file_put_contents($file, $content) !== false;
}

function logMessage($message, $type = 'info') {
    $color = [
        'success' => 'success',
        'error' => 'error',
        'warning' => 'warning',
        'info' => 'info'
    ];
    
    echo "<script>addLog('" . addslashes($message) . "', '" . $color[$type] . "');</script>";
    flush();
    ob_flush();
    usleep(100000); // 0.1 שניה
}

function updateProgress($percent) {
    echo "<script>updateProgress($percent);</script>";
    flush();
    ob_flush();
}

?>