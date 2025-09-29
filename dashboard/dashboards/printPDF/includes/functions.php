<?php
/**
 * PDF Editor Functions - FIXED VERSION
 */

function getPDFEditorDB() {
    // פשוט החזר את החיבור הקיים, בלי ליצור טבלאות אוטומטית
    return getDBConnection();
}

// function createPDFEditorTables($db) {
//     if (!$db) return;
    
//     try {
//         // בדוק אם הטבלאות כבר קיימות לפני יצירה
//         $result = $db->query("SHOW TABLES LIKE 'pdf_editor_projects'");
//         if ($result->rowCount() > 0) {
//             return; // הטבלאות כבר קיימות
//         }
        
//         // צור טבלאות בלי FOREIGN KEY constraints
//         $db->exec("
//             CREATE TABLE IF NOT EXISTS `pdf_editor_projects` (
//                 `id` INT AUTO_INCREMENT PRIMARY KEY,
//                 `project_id` VARCHAR(100) UNIQUE NOT NULL,
//                 `user_id` INT NOT NULL,
//                 `name` VARCHAR(255) NOT NULL,
//                 `data` LONGTEXT,
//                 `thumbnail` TEXT,
//                 `is_template` BOOLEAN DEFAULT 0,
//                 `template_category` VARCHAR(50),
//                 `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//                 `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//                 INDEX idx_user_id (user_id),
//                 INDEX idx_project_id (project_id)
//             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
//         ");
        
//         // צור את שאר הטבלאות בלי FOREIGN KEY
//         $db->exec("
//             CREATE TABLE IF NOT EXISTS `pdf_editor_autosave` (
//                 `id` INT AUTO_INCREMENT PRIMARY KEY,
//                 `project_id` VARCHAR(100) NOT NULL,
//                 `state_data` LONGTEXT,
//                 `saved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//                 INDEX idx_project (project_id)
//             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
//         ");
        
//         $db->exec("
//             CREATE TABLE IF NOT EXISTS `pdf_editor_shares` (
//                 `id` INT AUTO_INCREMENT PRIMARY KEY,
//                 `project_id` VARCHAR(100) NOT NULL,
//                 `share_token` VARCHAR(64) UNIQUE NOT NULL,
//                 `expires_at` TIMESTAMP NULL,
//                 `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//                 INDEX idx_token (share_token)
//             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
//         ");
        
//         $db->exec("
//             CREATE TABLE IF NOT EXISTS `pdf_editor_activity_log` (
//                 `id` INT AUTO_INCREMENT PRIMARY KEY,
//                 `user_id` INT NOT NULL,
//                 `action` VARCHAR(100) NOT NULL,
//                 `module` VARCHAR(50) NOT NULL,
//                 `details` TEXT,
//                 `metadata` JSON,
//                 `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//                 INDEX idx_user (user_id),
//                 INDEX idx_action (action),
//                 INDEX idx_created (created_at)
//             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
//         ");
        
//     } catch (PDOException $e) {
//         error_log('Failed to create PDF Editor tables: ' . $e->getMessage());
//     }
// }

// function logActivity($action, $module = 'pdf_editor', $details = '', $metadata = []) {
//     try {
//         $db = getDBConnection(); // השתמש ישירות ב-getDBConnection
//         if (!$db) return;
        
//         $userId = $_SESSION['user_id'] ?? 0;
        
//         $stmt = $db->prepare("
//             INSERT INTO pdf_editor_activity_log 
//             (user_id, action, module, details, metadata, created_at) 
//             VALUES (?, ?, ?, ?, ?, NOW())
//         ");
        
//         $stmt->execute([
//             $userId,
//             $action,
//             $module,
//             $details,
//             json_encode($metadata)
//         ]);
        
//     } catch (Exception $e) {
//         error_log('Failed to log activity: ' . $e->getMessage());
//     }
// }
?>