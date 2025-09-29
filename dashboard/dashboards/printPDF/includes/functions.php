<?php
/**
 * PDF Editor Functions
 * Location: /dashboard/dashboards/printPDF/includes/functions.php
 * 
 * משתמש בחיבור הקיים מהקובץ הראשי - בדיוק כמו בבתי עלמין!
 */

/**
 * Get PDF Editor Database Connection
 * משתמש בפונקציה הקיימת מהקובץ הראשי
 */
function getPDFEditorDB() {
    static $initialized = false;
    
    // קבל את החיבור מהפונקציה הראשית
    $db = getDBConnection();
    
    // צור טבלאות רק בפעם הראשונה
    if (!$initialized && $db) {
        createPDFEditorTables($db);
        $initialized = true;
    }
    
    return $db;
}

/**
 * Create PDF Editor tables if not exists
 */
function createPDFEditorTables($db) {
    if (!$db) return;
    
    try {
        // טבלת פרויקטים
        $db->exec("
            CREATE TABLE IF NOT EXISTS `pdf_editor_projects` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `project_id` VARCHAR(100) UNIQUE NOT NULL,
                `user_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `data` LONGTEXT,
                `thumbnail` TEXT,
                `is_template` BOOLEAN DEFAULT 0,
                `template_category` VARCHAR(50),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_project_id (project_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // טבלת שמירה אוטומטית
        $db->exec("
            CREATE TABLE IF NOT EXISTS `pdf_editor_autosave` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `project_id` VARCHAR(100) NOT NULL,
                `state_data` LONGTEXT,
                `saved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_project (project_id),
                FOREIGN KEY (project_id) REFERENCES pdf_editor_projects(project_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // טבלת שיתוף
        $db->exec("
            CREATE TABLE IF NOT EXISTS `pdf_editor_shares` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `project_id` VARCHAR(100) NOT NULL,
                `share_token` VARCHAR(64) UNIQUE NOT NULL,
                `expires_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (share_token),
                FOREIGN KEY (project_id) REFERENCES pdf_editor_projects(project_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // טבלת לוג פעילות
        $db->exec("
            CREATE TABLE IF NOT EXISTS `pdf_editor_activity_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `action` VARCHAR(100) NOT NULL,
                `module` VARCHAR(50) NOT NULL,
                `details` TEXT,
                `metadata` JSON,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
    } catch (PDOException $e) {
        error_log('Failed to create PDF Editor tables: ' . $e->getMessage());
    }
}

/**
 * Log activity to database
 */
function logActivity($action, $module = 'pdf_editor', $details = '', $metadata = []) {
    try {
        $db = getPDFEditorDB();
        if (!$db) return;
        
        $userId = $_SESSION['user_id'] ?? 0;
        
        $stmt = $db->prepare("
            INSERT INTO pdf_editor_activity_log 
            (user_id, action, module, details, metadata, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $module,
            $details,
            json_encode($metadata)
        ]);
        
    } catch (Exception $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}
?>