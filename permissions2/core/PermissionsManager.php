<?php
/**
 * Permissions Manager - מנהל הרשאות מרכזי
 * permissions/core/PermissionsManager.php
 */

namespace Permissions\Core;

class PermissionsManager {
    
    // סוגי הרשאות
    const PERMISSION_NOTIFICATION = 'notification';
    const PERMISSION_PUSH = 'push';
    const PERMISSION_CAMERA = 'camera';
    const PERMISSION_MICROPHONE = 'microphone';
    const PERMISSION_LOCATION = 'geolocation';
    const PERMISSION_STORAGE = 'persistent-storage';
    const PERMISSION_CLIPBOARD = 'clipboard-read';
    const PERMISSION_BACKGROUND_SYNC = 'background-sync';
    const PERMISSION_BACKGROUND_FETCH = 'background-fetch';
    const PERMISSION_MIDI = 'midi';
    const PERMISSION_BLUETOOTH = 'bluetooth';
    const PERMISSION_ACCELEROMETER = 'accelerometer';
    const PERMISSION_GYROSCOPE = 'gyroscope';
    const PERMISSION_MAGNETOMETER = 'magnetometer';
    const PERMISSION_AMBIENT_LIGHT = 'ambient-light-sensor';
    
    // מצבי הרשאה
    const STATUS_GRANTED = 'granted';
    const STATUS_DENIED = 'denied';
    const STATUS_PROMPT = 'prompt';
    const STATUS_DEFAULT = 'default';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_NOT_SUPPORTED = 'not_supported';
    
    private $userId;
    private $storage;
    private $handlers = [];
    
    /**
     * Constructor
     */
    public function __construct($userId = null) {
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        $this->storage = new PermissionStorage();
        $this->initializeHandlers();
    }
    
    /**
     * אתחול handlers
     */
    private function initializeHandlers() {
        $this->handlers = [
            self::PERMISSION_NOTIFICATION => 'NotificationHandler',
            self::PERMISSION_PUSH => 'PushHandler',
            self::PERMISSION_CAMERA => 'MediaHandler',
            self::PERMISSION_MICROPHONE => 'MediaHandler',
            self::PERMISSION_LOCATION => 'LocationHandler',
            self::PERMISSION_STORAGE => 'StorageHandler',
            self::PERMISSION_BACKGROUND_SYNC => 'BackgroundHandler',
        ];
    }
    
    /**
     * בדיקת כל ההרשאות
     */
    public function checkAllPermissions() {
        $permissions = [];
        $allTypes = $this->getAllPermissionTypes();
        
        foreach ($allTypes as $type => $info) {
            $permissions[$type] = $this->checkPermission($type);
        }
        
        return $permissions;
    }
    
    /**
     * בדיקת הרשאה ספציפית
     */
    public function checkPermission($type) {
        // קבלת סטטוס מה-DB
        $dbStatus = $this->storage->getPermissionStatus($this->userId, $type);
        
        return [
            'type' => $type,
            'name' => $this->getPermissionName($type),
            'description' => $this->getPermissionDescription($type),
            'status' => $dbStatus['status'] ?? self::STATUS_DEFAULT,
            'last_checked' => $dbStatus['last_checked'] ?? null,
            'last_prompted' => $dbStatus['last_prompted'] ?? null,
            'denied_count' => $dbStatus['denied_count'] ?? 0,
            'browser_support' => $this->checkBrowserSupport($type),
            'required_https' => $this->requiresHTTPS($type),
            'icon' => $this->getPermissionIcon($type),
            'priority' => $this->getPermissionPriority($type)
        ];
    }
    
    /**
     * עדכון סטטוס הרשאה
     */
    public function updatePermissionStatus($type, $status, $metadata = []) {
        $data = [
            'user_id' => $this->userId,
            'permission_type' => $type,
            'status' => $status,
            'browser' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'metadata' => json_encode($metadata),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // אם נדחה, עדכן counter
        if ($status === self::STATUS_DENIED) {
            $data['denied_count'] = $this->storage->incrementDeniedCount($this->userId, $type);
        }
        
        // אם ניתן, נקה את ה-counter
        if ($status === self::STATUS_GRANTED) {
            $data['denied_count'] = 0;
            $data['granted_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->storage->updatePermissionStatus($data);
    }
    
    /**
     * האם ההרשאה נחסמה לצמיתות?
     */
    public function isPermissionBlocked($type) {
        $status = $this->checkPermission($type);
        
        // נחסם אם נדחה 3 פעמים או יותר
        if ($status['denied_count'] >= 3) {
            return true;
        }
        
        // נחסם אם הסטטוס הוא blocked
        if ($status['status'] === self::STATUS_BLOCKED) {
            return true;
        }
        
        return false;
    }
    
    /**
     * האם אפשר לבקש את ההרשאה שוב?
     */
    public function canRequestPermission($type) {
        $status = $this->checkPermission($type);
        
        // אי אפשר אם נחסם
        if ($this->isPermissionBlocked($type)) {
            return false;
        }
        
        // אי אפשר אם כבר ניתן
        if ($status['status'] === self::STATUS_GRANTED) {
            return false;
        }
        
        // בדיקת זמן מאז בקשה אחרונה (לפחות 24 שעות)
        if ($status['last_prompted']) {
            $lastPrompted = strtotime($status['last_prompted']);
            $hoursSincePrompt = (time() - $lastPrompted) / 3600;
            
            if ($hoursSincePrompt < 24) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * קבלת הרשאות קריטיות שחסרות
     */
    public function getMissingCriticalPermissions() {
        $critical = [
            self::PERMISSION_NOTIFICATION,
            self::PERMISSION_PUSH,
            self::PERMISSION_STORAGE
        ];
        
        $missing = [];
        
        foreach ($critical as $type) {
            $status = $this->checkPermission($type);
            if ($status['status'] !== self::STATUS_GRANTED) {
                $missing[] = $status;
            }
        }
        
        return $missing;
    }
    
    /**
     * קבלת סטטיסטיקות הרשאות
     */
    public function getPermissionsStats() {
        $allPermissions = $this->checkAllPermissions();
        
        $stats = [
            'total' => count($allPermissions),
            'granted' => 0,
            'denied' => 0,
            'pending' => 0,
            'blocked' => 0,
            'not_supported' => 0,
            'critical_missing' => count($this->getMissingCriticalPermissions()),
            'completion_percentage' => 0
        ];
        
        foreach ($allPermissions as $permission) {
            switch ($permission['status']) {
                case self::STATUS_GRANTED:
                    $stats['granted']++;
                    break;
                case self::STATUS_DENIED:
                    $stats['denied']++;
                    break;
                case self::STATUS_BLOCKED:
                    $stats['blocked']++;
                    break;
                case self::STATUS_NOT_SUPPORTED:
                    $stats['not_supported']++;
                    break;
                default:
                    $stats['pending']++;
            }
        }
        
        // חישוב אחוז השלמה (רק הרשאות נתמכות)
        $supported = $stats['total'] - $stats['not_supported'];
        if ($supported > 0) {
            $stats['completion_percentage'] = round(($stats['granted'] / $supported) * 100);
        }
        
        return $stats;
    }
    
    /**
     * יצירת דוח הרשאות
     */
    public function generatePermissionsReport() {
        return [
            'user_id' => $this->userId,
            'generated_at' => date('Y-m-d H:i:s'),
            'browser' => $this->getBrowserInfo(),
            'device' => $this->getDeviceInfo(),
            'permissions' => $this->checkAllPermissions(),
            'stats' => $this->getPermissionsStats(),
            'missing_critical' => $this->getMissingCriticalPermissions(),
            'recommendations' => $this->getRecommendations()
        ];
    }
    
    /**
     * קבלת המלצות לשיפור
     */
    private function getRecommendations() {
        $recommendations = [];
        $permissions = $this->checkAllPermissions();
        
        // המלצות לפי סטטוס
        foreach ($permissions as $type => $permission) {
            if ($permission['status'] === self::STATUS_DENIED && 
                $permission['denied_count'] < 3) {
                $recommendations[] = [
                    'type' => 'retry',
                    'permission' => $type,
                    'message' => "נסה לבקש שוב את הרשאת {$permission['name']}"
                ];
            }
            
            if ($permission['status'] === self::STATUS_BLOCKED) {
                $recommendations[] = [
                    'type' => 'manual',
                    'permission' => $type,
                    'message' => "הרשאת {$permission['name']} נחסמה. יש להפעיל ידנית בהגדרות הדפדפן"
                ];
            }
        }
        
        // המלצות כלליות
        if (!$this->isHTTPS()) {
            $recommendations[] = [
                'type' => 'security',
                'message' => 'מומלץ להשתמש ב-HTTPS לתמיכה מלאה בהרשאות'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * פונקציות עזר
     */
    
    private function getAllPermissionTypes() {
        return [
            self::PERMISSION_NOTIFICATION => [
                'name' => 'התראות',
                'description' => 'הצגת התראות במערכת',
                'icon' => '🔔'
            ],
            self::PERMISSION_PUSH => [
                'name' => 'התראות Push',
                'description' => 'קבלת התראות גם כשהאתר סגור',
                'icon' => '📨'
            ],
            self::PERMISSION_CAMERA => [
                'name' => 'מצלמה',
                'description' => 'גישה למצלמת המכשיר',
                'icon' => '📷'
            ],
            self::PERMISSION_MICROPHONE => [
                'name' => 'מיקרופון',
                'description' => 'הקלטת שמע',
                'icon' => '🎤'
            ],
            self::PERMISSION_LOCATION => [
                'name' => 'מיקום',
                'description' => 'גישה למיקום המכשיר',
                'icon' => '📍'
            ],
            self::PERMISSION_STORAGE => [
                'name' => 'אחסון',
                'description' => 'שמירת נתונים במכשיר',
                'icon' => '💾'
            ],
            self::PERMISSION_CLIPBOARD => [
                'name' => 'לוח העתקה',
                'description' => 'גישה ללוח ההעתקה',
                'icon' => '📋'
            ],
            self::PERMISSION_BACKGROUND_SYNC => [
                'name' => 'סנכרון רקע',
                'description' => 'סנכרון נתונים ברקע',
                'icon' => '🔄'
            ]
        ];
    }
    
    private function getPermissionName($type) {
        $types = $this->getAllPermissionTypes();
        return $types[$type]['name'] ?? $type;
    }
    
    private function getPermissionDescription($type) {
        $types = $this->getAllPermissionTypes();
        return $types[$type]['description'] ?? '';
    }
    
    private function getPermissionIcon($type) {
        $types = $this->getAllPermissionTypes();
        return $types[$type]['icon'] ?? '❓';
    }
    
    private function getPermissionPriority($type) {
        $priorities = [
            self::PERMISSION_NOTIFICATION => 1,
            self::PERMISSION_PUSH => 2,
            self::PERMISSION_STORAGE => 3,
            self::PERMISSION_LOCATION => 4,
            self::PERMISSION_CAMERA => 5,
            self::PERMISSION_MICROPHONE => 6,
        ];
        
        return $priorities[$type] ?? 99;
    }
    
    private function checkBrowserSupport($type) {
        // כאן ניתן להוסיף בדיקת תמיכה מתקדמת
        return true;
    }
    
    private function requiresHTTPS($type) {
        $httpsRequired = [
            self::PERMISSION_NOTIFICATION,
            self::PERMISSION_PUSH,
            self::PERMISSION_CAMERA,
            self::PERMISSION_MICROPHONE,
            self::PERMISSION_LOCATION,
            self::PERMISSION_CLIPBOARD
        ];
        
        return in_array($type, $httpsRequired);
    }
    
    private function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || $_SERVER['SERVER_PORT'] == 443;
    }
    
    private function getBrowserInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // זיהוי בסיסי של דפדפן
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge';
        }
        
        return 'Unknown';
    }
    
    private function getDeviceInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Mobile') !== false) {
            return 'Mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false) {
            return 'Tablet';
        }
        
        return 'Desktop';
    }
}