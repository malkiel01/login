<?php
/**
 * Admin Functions
 * פונקציות עזר למנהלים
 */

class AdminFunctions {
    
    /**
     * יצירת משתמש חדש
     */
    public static function createUser($data) {
        $db = DatabaseManager::getInstance();
        $security = new SecurityManager();
        
        // ולידציה
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            throw new Exception('Missing required fields');
        }
        
        // בדיקת סיסמה
        $passwordValidation = $security->validatePassword($data['password']);
        if ($passwordValidation !== true) {
            throw new Exception(implode(', ', $passwordValidation));
        }
        
        // הצפנת סיסמה
        $hashedPassword = $security->hashPassword($data['password']);
        
        // הכנסה למסד נתונים
        $sql = "INSERT INTO users (username, email, password, name, role, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['name'] ?? $data['username'],
            $data['role'] ?? 'user'
        ];
        
        $db->query($sql, $params);
        
        // רישום פעילות
        $db->logActivity($_SESSION['user_id'], 'create_user', [
            'new_user' => $data['username']
        ]);
        
        return $db->getConnection()->lastInsertId();
    }
    
    /**
     * עדכון משתמש
     */
    public static function updateUser($userId, $data) {
        $db = DatabaseManager::getInstance();
        $security = new SecurityManager();
        
        $updates = [];
        $params = [];
        
        // בניית שאילתת עדכון דינמית
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        if (isset($data['email'])) {
            $updates[] = 'email = ?';
            $params[] = $data['email'];
        }
        
        if (isset($data['role'])) {
            $updates[] = 'role = ?';
            $params[] = $data['role'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $passwordValidation = $security->validatePassword($data['password']);
            if ($passwordValidation !== true) {
                throw new Exception(implode(', ', $passwordValidation));
            }
            
            $updates[] = 'password = ?';
            $params[] = $security->hashPassword($data['password']);
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = 'updated_at = NOW()';
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $db->query($sql, $params);
        
        // רישום פעילות
        $db->logActivity($_SESSION['user_id'], 'update_user', [
            'updated_user_id' => $userId,
            'fields' => array_keys($data)
        ]);
        
        return true;
    }
    
    /**
     * מחיקת משתמש
     */
    public static function deleteUser($userId) {
        if ($userId == $_SESSION['user_id']) {
            throw new Exception('Cannot delete your own account');
        }
        
        $db = DatabaseManager::getInstance();
        
        // שמירת מידע למטרת לוג
        $user = $db->getUserById($userId);
        
        // מחיקה
        $sql = "DELETE FROM users WHERE id = ?";
        $db->query($sql, [$userId]);
        
        // רישום פעילות
        $db->logActivity($_SESSION['user_id'], 'delete_user', [
            'deleted_user' => $user['username']
        ]);
        
        return true;
    }
    
    /**
     * ייצוא נתונים
     */
    public static function exportData($type, $format = 'csv') {
        $db = DatabaseManager::getInstance();
        
        switch ($type) {
            case 'users':
                $data = $db->getAllUsers();
                break;
                
            case 'activity':
                $data = $db->getActivityLog(1000);
                break;
                
            case 'stats':
                $data = [$db->getDashboardStats()];
                break;
                
            default:
                throw new Exception('Invalid export type');
        }
        
        // רישום פעילות
        $db->logActivity($_SESSION['user_id'], 'export_data', [
            'type' => $type,
            'format' => $format,
            'records' => count($data)
        ]);
        
        if ($format === 'csv') {
            return self::exportToCSV($data, $type);
        } elseif ($format === 'json') {
            return self::exportToJSON($data, $type);
        }
        
        throw new Exception('Invalid export format');
    }
    
    /**
     * ייצוא ל-CSV
     */
    private static function exportToCSV($data, $filename) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // כותרות
        fputcsv($output, array_keys($data[0]));
        
        // נתונים
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        // הגדרת headers להורדה
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
        
        // BOM עבור Excel
        echo "\xEF\xBB\xBF";
        echo $csv;
        
        exit;
    }
    
    /**
     * ייצוא ל-JSON
     */
    private static function exportToJSON($data, $filename) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.json"');
        
        echo $json;
        exit;
    }
    
    /**
     * ניקוי מטמון
     */
    public static function clearCache() {
        $cacheDir = CACHE_PATH;
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        // רישום פעילות
        $db = DatabaseManager::getInstance();
        $db->logActivity($_SESSION['user_id'], 'clear_cache', [
            'cleared_files' => count($files ?? [])
        ]);
        
        return true;
    }
    
    /**
     * גיבוי מסד נתונים
     */
    public static function backupDatabase() {
        $db = DatabaseManager::getInstance();
        $connection = $db->getConnection();
        
        $tables = [];
        $result = $connection->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $output = '';
        
        foreach ($tables as $table) {
            // מבנה הטבלה
            $result = $connection->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch(PDO::FETCH_NUM);
            $output .= "\n\n" . $row[1] . ";\n\n";
            
            // נתונים
            $result = $connection->query("SELECT * FROM `$table`");
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $values = array_map([$connection, 'quote'], array_values($row));
                $output .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
            }
        }
        
        // שמירה לקובץ
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = LOGS_PATH . '/' . $filename;
        
        file_put_contents($filepath, $output);
        
        // רישום פעילות
        $db->logActivity($_SESSION['user_id'], 'database_backup', [
            'filename' => $filename,
            'tables' => count($tables)
        ]);
        
        return $filepath;
    }
}
?>
