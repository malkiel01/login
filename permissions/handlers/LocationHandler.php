<?php
/**
 * Location Handler
 * permissions/handlers/LocationHandler.php
 * 
 * טיפול בהרשאות מיקום
 */

namespace Permissions\Handlers;

class LocationHandler {
    
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
     * בדיקת הרשאת מיקום
     */
    public function checkPermission() {
        return $this->storage->getPermissionStatus($this->userId, 'geolocation');
    }
    
    /**
     * קבלת אפשרויות דיוק מיקום
     */
    public function getAccuracyOptions() {
        return [
            'high' => [
                'enableHighAccuracy' => true,
                'timeout' => 5000,
                'maximumAge' => 0,
                'description' => 'דיוק גבוה (GPS) - צריכת סוללה גבוהה'
            ],
            'medium' => [
                'enableHighAccuracy' => false,
                'timeout' => 10000,
                'maximumAge' => 60000,
                'description' => 'דיוק בינוני (WiFi/Cell) - מאוזן'
            ],
            'low' => [
                'enableHighAccuracy' => false,
                'timeout' => 15000,
                'maximumAge' => 300000,
                'description' => 'דיוק נמוך - חיסכון בסוללה'
            ]
        ];
    }
    
    /**
     * חישוב מרחק בין שתי נקודות
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = 'km') {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        
        switch ($unit) {
            case 'km':
                return $miles * 1.609344;
            case 'm':
                return $miles * 1609.344;
            case 'mi':
                return $miles;
            default:
                return $miles * 1.609344;
        }
    }
    
    /**
     * מציאת מיקום לפי IP
     */
    public function getLocationByIP($ip = null) {
        if (!$ip) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // כאן ניתן להשתמש בשירות חיצוני כמו ipapi.co
        // לדוגמה בסיסית:
        return [
            'ip' => $ip,
            'country' => 'IL',
            'city' => 'Unknown',
            'latitude' => null,
            'longitude' => null,
            'accuracy' => 'very_low',
            'source' => 'ip_geolocation'
        ];
    }
    
    /**
     * שמירת מיקום אחרון
     */
    public function saveLastLocation($latitude, $longitude, $accuracy = null) {
        if (!$this->userId) {
            return false;
        }
        
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                INSERT INTO user_locations 
                (user_id, latitude, longitude, accuracy, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                accuracy = VALUES(accuracy),
                updated_at = NOW()
            ");
            
            $stmt->execute([
                $this->userId,
                $latitude,
                $longitude,
                $accuracy
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log('Error saving location: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * קבלת מיקום אחרון
     */
    public function getLastLocation() {
        if (!$this->userId) {
            return null;
        }
        
        try {
            $pdo = $this->getDBConnection();
            $stmt = $pdo->prepare("
                SELECT * FROM user_locations 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * יצירת טבלת locations אם לא קיימת
     */
    public function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS user_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            accuracy FLOAT,
            altitude FLOAT,
            altitude_accuracy FLOAT,
            heading FLOAT,
            speed FLOAT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id),
            INDEX idx_user_id (user_id),
            INDEX idx_coordinates (latitude, longitude)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $pdo = $this->getDBConnection();
            $pdo->exec($sql);
        } catch (\Exception $e) {
            error_log('Error creating user_locations table: ' . $e->getMessage());
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