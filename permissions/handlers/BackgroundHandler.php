<?php
/**
 * Background Handler
 * permissions/handlers/BackgroundHandler.php
 * 
 * טיפול בהרשאות פעולות רקע
 */

namespace Permissions\Handlers;

class BackgroundHandler {
    
    private $storage;
    private $userId;
    
    /**
     * Constructor
     */
    public function __construct($userId = null) {
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        $this->storage = new \Permissions\Core\PermissionStorage();
    }
    
    /**
     * בדיקת הרשאת סנכרון רקע
     */
    public function checkBackgroundSyncPermission() {
        return $this->storage->getPermissionStatus($this->userId, 'background-sync');
    }
    
    /**
     * בדיקת הרשאת fetch רקע
     */
    public function checkBackgroundFetchPermission() {
        return $this->storage->getPermissionStatus($this->userId, 'background-fetch');
    }
    
    /**
     * רישום משימת רקע
     */
    public function registerBackgroundTask($taskName, $options = []) {
        $defaults = [
            'minInterval' => 12 * 60 * 60 * 1000, // 12 שעות
            'requiresCharging' => false,
            'requiresDeviceIdle' => false,
            'requiresNetworkConnection' => true
        ];
        
        $taskOptions = array_merge($defaults, $options);
        
        // שמירת המשימה ב-DB
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                INSERT INTO background_tasks 
                (user_id, task_name, options, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                options = VALUES(options),
                updated_at = NOW()
            ");
            
            $stmt->execute([
                $this->userId,
                $taskName,
                json_encode($taskOptions)
            ]);
            
            return [
                'success' => true,
                'task' => $taskName,
                'options' => $taskOptions
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * ביטול משימת רקע
     */
    public function unregisterBackgroundTask($taskName) {
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                DELETE FROM background_tasks 
                WHERE user_id = ? AND task_name = ?
            ");
            $stmt->execute([$this->userId, $taskName]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * קבלת רשימת משימות רקע
     */
    public function getBackgroundTasks() {
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                SELECT * FROM background_tasks 
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$this->userId]);
            $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // פענוח JSON
            foreach ($tasks as &$task) {
                if (isset($task['options'])) {
                    $task['options'] = json_decode($task['options'], true);
                }
            }
            
            return $tasks;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * רישום ביצוע משימת רקע
     */
    public function logTaskExecution($taskName, $status = 'success', $data = null) {
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                INSERT INTO background_task_logs 
                (user_id, task_name, status, execution_data, executed_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->userId,
                $taskName,
                $status,
                json_encode($data)
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log('Error logging task execution: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * קבלת היסטוריית ביצוע משימות
     */
    public function getTaskExecutionHistory($taskName = null, $limit = 50) {
        try {
            $pdo = $this->getDBConnection();
            
            if ($taskName) {
                $stmt = $pdo->prepare("
                    SELECT * FROM background_task_logs 
                    WHERE user_id = ? AND task_name = ?
                    ORDER BY executed_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$this->userId, $taskName, $limit]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM background_task_logs 
                    WHERE user_id = ?
                    ORDER BY executed_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$this->userId, $limit]);
            }
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * יצירת טבלאות אם לא קיימות
     */
    public function ensureTablesExist() {
        $sql1 = "CREATE TABLE IF NOT EXISTS background_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_name VARCHAR(100) NOT NULL,
            options JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_task (user_id, task_name),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $sql2 = "CREATE TABLE IF NOT EXISTS background_task_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_name VARCHAR(100) NOT NULL,
            status VARCHAR(50),
            execution_data JSON,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_task_name (task_name),
            INDEX idx_executed_at (executed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $pdo = $this->getDBConnection();
            $pdo->exec($sql1);
            $pdo->exec($sql2);
        } catch (\Exception $e) {
            error_log('Error creating background tables: ' . $e->getMessage());
        }
    }
    
    /**
     * קבלת חיבור DB
     */
    private function getDBConnection() {
        if (function_exists('getDBConnection')) {
            return getDBConnection();
        }
        
        require_once dirname(dirname(dirname(__DIR__))) . '/config.php';
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        return new \PDO($dsn, DB_USER, DB_PASSWORD);
    }
}