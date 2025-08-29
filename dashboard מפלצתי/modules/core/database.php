<?php
 /**
  * Database Manager
  * מנהל מסד נתונים מרכזי
  */

 class DatabaseManager {
     private static $instance = null;
     private $connection = null;
     private $config = [];
     
     /**
      * Singleton pattern
      */
     public static function getInstance() {
         if (self::$instance === null) {
             self::$instance = new self();
         }
         return self::$instance;
     }
     
     /**
      * Constructor
      */
     private function __construct() {
         $this->loadConfig();
         $this->connect();
     }
     
     /**
      * טעינת הגדרות
      */
     private function loadConfig() {
         // טעינת מקובץ config ראשי
         require_once dirname(dirname(dirname(__DIR__))) . '/config.php';
         
         $this->config = [
             'host' => DB_HOST ?? 'localhost',
             'database' => DB_NAME ?? 'dashboard',
             'username' => DB_USER ?? 'root',
             'password' => DB_PASS ?? '',
             'charset' => 'utf8mb4',
             'options' => [
                 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false
             ]
         ];
     }
     
     /**
      * התחברות למסד נתונים
      */
     private function connect() {
         try {
             $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset={$this->config['charset']}";
             $this->connection = new PDO(
                 $dsn,
                 $this->config['username'],
                 $this->config['password'],
                 $this->config['options']
             );
         } catch (PDOException $e) {
             error_log('Database connection failed: ' . $e->getMessage());
             throw new Exception('Database connection failed');
         }
     }
     
     /**
      * קבלת החיבור
      */
     public function getConnection() {
         return $this->connection;
     }
     
     /**
      * הרצת שאילתה
      */
     public function query($sql, $params = []) {
         try {
             $stmt = $this->connection->prepare($sql);
             $stmt->execute($params);
             return $stmt;
         } catch (PDOException $e) {
             error_log('Query failed: ' . $e->getMessage());
             throw new Exception('Query failed');
         }
     }
     
     /**
      * קבלת משתמש לפי ID
      */
     public function getUserById($userId) {
         $sql = "SELECT * FROM users WHERE id = ?";
         $stmt = $this->query($sql, [$userId]);
         return $stmt->fetch();
     }
     
     /**
      * קבלת כל המשתמשים
      */
     public function getAllUsers($limit = null, $offset = 0) {
         $sql = "SELECT id, username, name, email, role, auth_type, is_active, 
                        last_login, created_at, updated_at 
                 FROM users 
                 ORDER BY created_at DESC";
         
         if ($limit !== null) {
             $sql .= " LIMIT ? OFFSET ?";
             $stmt = $this->query($sql, [$limit, $offset]);
         } else {
             $stmt = $this->query($sql);
         }
         
         return $stmt->fetchAll();
     }
     
     /**
      * עדכון זמן פעילות אחרון
      */
     public function updateLastActivity($userId) {
         $sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
         return $this->query($sql, [$userId]);
     }
     
     /**
      * רישום פעילות
      */
     public function logActivity($userId, $action, $details = null) {
         // בדיקה אם הטבלה קיימת, אם לא - יצירה
         $this->ensureActivityLogTable();
         
         $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())";
         
         $detailsJson = $details ? json_encode($details) : null;
         $ip = $_SERVER['REMOTE_ADDR'] ?? null;
         $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
         
         return $this->query($sql, [$userId, $action, $detailsJson, $ip, $userAgent]);
     }
     
     /**
      * קבלת לוג פעילות
      */
     public function getActivityLog($limit = 50, $userId = null) {
         $sql = "SELECT a.*, u.username, u.name 
                 FROM activity_logs a
                 LEFT JOIN users u ON a.user_id = u.id";
         
         $params = [];
         
         if ($userId !== null) {
             $sql .= " WHERE a.user_id = ?";
             $params[] = $userId;
         }
         
         $sql .= " ORDER BY a.created_at DESC LIMIT ?";
         $params[] = $limit;
         
         $stmt = $this->query($sql, $params);
         return $stmt->fetchAll();
     }
     
     /**
      * קבלת סטטיסטיקות
      */
     public function getDashboardStats() {
         $stats = [];
         
         // סה"כ משתמשים
         $sql = "SELECT COUNT(*) as count FROM users";
         $result = $this->query($sql)->fetch();
         $stats['total_users'] = $result['count'];
         
         // משתמשים פעילים
         $sql = "SELECT COUNT(*) as count FROM users WHERE is_active = 1";
         $result = $this->query($sql)->fetch();
         $stats['active_users'] = $result['count'];
         
         // משתמשים לפי סוג
         $sql = "SELECT auth_type, COUNT(*) as count FROM users GROUP BY auth_type";
         $result = $this->query($sql)->fetchAll();
         foreach ($result as $row) {
             $stats['users_by_' . $row['auth_type']] = $row['count'];
         }
         
         // התחברויות היום
         $sql = "SELECT COUNT(*) as count FROM users WHERE DATE(last_login) = CURDATE()";
         $result = $this->query($sql)->fetch();
         $stats['today_logins'] = $result['count'];
         
         // משתמשים חדשים השבוע
         $sql = "SELECT COUNT(*) as count FROM users 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
         $result = $this->query($sql)->fetch();
         $stats['new_users_week'] = $result['count'];
         
         return $stats;
     }
     
     /**
      * עדכון תפקיד משתמש
      */
     public function updateUserRole($userId, $role) {
         $sql = "UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?";
         return $this->query($sql, [$role, $userId]);
     }
     
     /**
      * הענקת הרשאה למשתמש
      */
     public function grantUserPermission($userId, $permission) {
         $this->ensurePermissionsTable();
         
         $sql = "INSERT INTO user_permissions (user_id, permission, granted_at) 
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE granted_at = NOW()";
         
         return $this->query($sql, [$userId, $permission]);
     }
     
     /**
      * הסרת הרשאה ממשתמש
      */
     public function revokeUserPermission($userId, $permission) {
         $sql = "DELETE FROM user_permissions WHERE user_id = ? AND permission = ?";
         return $this->query($sql, [$userId, $permission]);
     }
     
     /**
      * וידוא קיום טבלת activity_logs
      */
     private function ensureActivityLogTable() {
         $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
             id INT AUTO_INCREMENT PRIMARY KEY,
             user_id INT NOT NULL,
             action VARCHAR(255) NOT NULL,
             details JSON,
             ip_address VARCHAR(45),
             user_agent TEXT,
             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             INDEX idx_user_id (user_id),
             INDEX idx_created_at (created_at),
             INDEX idx_action (action)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
         
         $this->query($sql);
     }
     
     /**
      * וידוא קיום טבלת הרשאות
      */
     private function ensurePermissionsTable() {
         $sql = "CREATE TABLE IF NOT EXISTS user_permissions (
             id INT AUTO_INCREMENT PRIMARY KEY,
             user_id INT NOT NULL,
             permission VARCHAR(100) NOT NULL,
             granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             granted_by INT,
             expires_at TIMESTAMP NULL,
             UNIQUE KEY unique_user_permission (user_id, permission),
             INDEX idx_user_id (user_id),
             INDEX idx_permission (permission)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
         
         $this->query($sql);
     }
     
     /**
      * עסקת מסד נתונים
      */
     public function beginTransaction() {
         return $this->connection->beginTransaction();
     }
     
     public function commit() {
         return $this->connection->commit();
     }
     
     public function rollback() {
         return $this->connection->rollback();
     }
 }
?>