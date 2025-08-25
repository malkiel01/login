<?php
// dashboard/includes/functions.php - פונקציות עזר לדשבורד

/**
 * בדיקת אימות משתמש
 */
function checkAuthentication() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit;
    }
}

/**
 * קבלת פרטי המשתמש הנוכחי
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * קבלת כל המשתמשים
 */
function getAllUsers($limit = null) {
    $pdo = getDBConnection();
    $sql = "SELECT id, username, name, email, auth_type, is_active, last_login, created_at 
            FROM users 
            ORDER BY created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * קבלת סטטיסטיקות לדשבורד
 */
function getDashboardStats() {
    $pdo = getDBConnection();
    
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'google_users' => 0,
        'today_logins' => 0
    ];
    
    // סה"כ משתמשים
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // משתמשים פעילים
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // משתמשי Google
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE auth_type = 'google'");
    $stats['google_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // התחברויות היום
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(last_login) = CURDATE()");
    $stats['today_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $stats;
}

/**
 * רישום פעילות במערכת
 */
function logActivity($action, $details = null) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $pdo = getDBConnection();
    
    // בדיקה אם טבלת activity_logs קיימת
    $tableExists = checkTableExists('activity_logs');
    
    if (!$tableExists) {
        // יצירת הטבלה אם לא קיימת
        createActivityLogsTable();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    return $stmt->execute([
        $_SESSION['user_id'],
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

/**
 * קבלת היסטוריית פעילות
 */
function getActivityLog($limit = 10) {
    $pdo = getDBConnection();
    
    if (!checkTableExists('activity_logs')) {
        return [];
    }
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.name 
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * בדיקה אם טבלה קיימת
 */
function checkTableExists($tableName) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    return $stmt->rowCount() > 0;
}

/**
 * יצירת טבלת activity_logs
 */
function createActivityLogsTable() {
    $pdo = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    return $pdo->exec($sql);
}

/**
 * ניקוי קלט
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * בדיקת הרשאות משתמש
 */
function checkPermission($permission) {
    // כאן ניתן להוסיף לוגיקה של הרשאות
    // לדוגמה: בדיקה מול טבלת הרשאות או תפקידים
    return true; // כרגע מחזיר true לכולם
}

/**
 * קבלת נתוני סשן
 */
function getSessionData() {
    return [
        'id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'auth_type' => $_SESSION['auth_type'] ?? 'local',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
}

/**
 * עדכון זמן פעילות אחרון
 */
function updateLastActivity() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    
    // עדכון במסד נתונים כל 5 דקות
    if (!isset($_SESSION['last_db_update']) || 
        (time() - $_SESSION['last_db_update']) > 300) {
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['last_db_update'] = time();
    }
    
    return true;
}

/**
 * בדיקת timeout של סשן
 */
function checkSessionTimeout($maxIdleTime = 7200) { // 2 שעות
    if (isset($_SESSION['last_activity'])) {
        $idleTime = time() - $_SESSION['last_activity'];
        
        if ($idleTime > $maxIdleTime) {
            // סשן פג תוקף
            session_destroy();
            return false;
        }
    }
    
    updateLastActivity();
    return true;
}

/**
 * קבלת מידע על המערכת
 */
function getSystemInfo() {
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'database_type' => 'MySQL',
        'timezone' => date_default_timezone_get(),
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
        'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB'
    ];
}

/**
 * פורמט תאריך בעברית
 */
function formatHebrewDate($timestamp, $format = 'full') {
    $date = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    
    switch ($format) {
        case 'short':
            return date('d/m/Y', $date);
        case 'time':
            return date('H:i', $date);
        case 'datetime':
            return date('d/m/Y H:i', $date);
        case 'full':
        default:
            return date('d/m/Y H:i:s', $date);
    }
}

/**
 * יצירת טוקן CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * אימות טוקן CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}