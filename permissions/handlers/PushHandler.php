<?php
/**
 * Push Notification Handler
 * permissions/handlers/PushHandler.php
 * 
 * טיפול ב-Push Notifications
 */

namespace Permissions\Handlers;

class PushHandler {
    
    private $storage;
    private $userId;
    private $vapidKeys;
    
    /**
     * Constructor
     */
    public function __construct($userId = null) {
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        $this->storage = new \Permissions\Core\PermissionStorage();
        
        // VAPID Keys - צריך להגדיר ב-.env
        $this->vapidKeys = [
            'publicKey' => $_ENV['VAPID_PUBLIC_KEY'] ?? '',
            'privateKey' => $_ENV['VAPID_PRIVATE_KEY'] ?? '',
            'subject' => $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@example.com'
        ];
    }
    
    /**
     * בדיקת הרשאת Push
     */
    public function checkPermission() {
        return $this->storage->getPermissionStatus($this->userId, 'push');
    }
    
    /**
     * שמירת Subscription
     */
    public function saveSubscription($subscription) {
        if (!$this->userId || !$subscription) {
            return false;
        }
        
        // שמירה ב-DB
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                INSERT INTO push_subscriptions 
                (user_id, endpoint, public_key, auth_token, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                endpoint = VALUES(endpoint),
                public_key = VALUES(public_key),
                auth_token = VALUES(auth_token),
                updated_at = NOW()
            ");
            
            $stmt->execute([
                $this->userId,
                $subscription['endpoint'],
                $subscription['keys']['p256dh'],
                $subscription['keys']['auth']
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log('Error saving push subscription: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחת Push Notification
     */
    public function sendPush($userId, $payload) {
        // קבלת subscription
        $subscription = $this->getSubscription($userId);
        if (!$subscription) {
            return false;
        }
        
        // הכנת payload
        $notification = json_encode($payload);
        
        // כאן צריך להשתמש בספרייה כמו web-push-php
        // לדוגמה בסיסית:
        return [
            'success' => true,
            'message' => 'Push would be sent',
            'payload' => $payload
        ];
    }
    
    /**
     * קבלת Subscription של משתמש
     */
    private function getSubscription($userId) {
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                SELECT * FROM push_subscriptions 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * מחיקת Subscription
     */
    public function removeSubscription($userId) {
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
            $stmt->execute([$userId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * קבלת VAPID Public Key
     */
    public function getVapidPublicKey() {
        return $this->vapidKeys['publicKey'];
    }
    
    /**
     * יצירת טבלת subscriptions אם לא קיימת
     */
    public function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            public_key VARCHAR(255),
            auth_token VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $pdo = $this->getDBConnection();
            $pdo->exec($sql);
        } catch (\Exception $e) {
            error_log('Error creating push_subscriptions table: ' . $e->getMessage());
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