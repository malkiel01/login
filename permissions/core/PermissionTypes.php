<?php
/**
 * Permission Types Definitions
 * permissions/core/PermissionTypes.php
 * 
 * הגדרות וקבועים של כל סוגי ההרשאות במערכת
 */

namespace Permissions\Core;

class PermissionTypes {
    
    /**
     * סוגי הרשאות
     */
    const NOTIFICATION = 'notification';
    const PUSH = 'push';
    const CAMERA = 'camera';
    const MICROPHONE = 'microphone';
    const LOCATION = 'geolocation';
    const STORAGE = 'persistent-storage';
    const CLIPBOARD_READ = 'clipboard-read';
    const CLIPBOARD_WRITE = 'clipboard-write';
    const BACKGROUND_SYNC = 'background-sync';
    const BACKGROUND_FETCH = 'background-fetch';
    const MIDI = 'midi';
    const BLUETOOTH = 'bluetooth';
    const ACCELEROMETER = 'accelerometer';
    const GYROSCOPE = 'gyroscope';
    const MAGNETOMETER = 'magnetometer';
    const AMBIENT_LIGHT = 'ambient-light-sensor';
    const PAYMENT = 'payment';
    const IDLE_DETECTION = 'idle-detection';
    const USB = 'usb';
    const SCREEN_WAKE_LOCK = 'screen-wake-lock';
    const FILE_SYSTEM = 'file-system-access';
    
    /**
     * מצבי הרשאות
     */
    const STATUS_GRANTED = 'granted';
    const STATUS_DENIED = 'denied';
    const STATUS_PROMPT = 'prompt';
    const STATUS_DEFAULT = 'default';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_NOT_SUPPORTED = 'not_supported';
    
    /**
     * רמות חשיבות
     */
    const PRIORITY_CRITICAL = 1;
    const PRIORITY_HIGH = 2;
    const PRIORITY_MEDIUM = 3;
    const PRIORITY_LOW = 4;
    const PRIORITY_OPTIONAL = 5;
    
    /**
     * קטגוריות הרשאות
     */
    const CATEGORY_COMMUNICATION = 'communication';
    const CATEGORY_MEDIA = 'media';
    const CATEGORY_LOCATION = 'location';
    const CATEGORY_STORAGE = 'storage';
    const CATEGORY_DEVICE = 'device';
    const CATEGORY_SENSORS = 'sensors';
    const CATEGORY_SYSTEM = 'system';
    
    /**
     * מפת הרשאות מלאה
     */
    private static $permissions = [
        self::NOTIFICATION => [
            'name' => 'התראות',
            'name_en' => 'Notifications',
            'description' => 'הצגת התראות במערכת ההפעלה',
            'icon' => '🔔',
            'category' => self::CATEGORY_COMMUNICATION,
            'priority' => self::PRIORITY_CRITICAL,
            'requires_https' => true,
            'requires_user_gesture' => true,
            'browser_support' => ['chrome' => 42, 'firefox' => 44, 'safari' => 7, 'edge' => 14],
            'api' => 'Notification API',
            'fallback' => 'alert',
            'related_permissions' => [self::PUSH]
        ],
        
        self::PUSH => [
            'name' => 'התראות Push',
            'name_en' => 'Push Notifications',
            'description' => 'קבלת התראות גם כשהאתר סגור',
            'icon' => '📨',
            'category' => self::CATEGORY_COMMUNICATION,
            'priority' => self::PRIORITY_HIGH,
            'requires_https' => true,
            'requires_user_gesture' => false,
            'requires_service_worker' => true,
            'browser_support' => ['chrome' => 50, 'firefox' => 44, 'safari' => 11.1, 'edge' => 17],
            'api' => 'Push API',
            'fallback' => 'notification',
            'dependencies' => [self::NOTIFICATION]
        ],
        
        self::CAMERA => [
            'name' => 'מצלמה',
            'name_en' => 'Camera',
            'description' => 'גישה למצלמת המכשיר לצילום וסריקה',
            'icon' => '📷',
            'category' => self::CATEGORY_MEDIA,
            'priority' => self::PRIORITY_MEDIUM,
            'requires_https' => true,
            'requires_user_gesture' => true,
            'browser_support' => ['chrome' => 53, 'firefox' => 36, 'safari' => 11, 'edge' => 12],
            'api' => 'MediaDevices API',
            'fallback' => 'file_input',
            'related_permissions' => [self::MICROPHONE]
        ],
        
        self::MICROPHONE => [
            'name' => 'מיקרופון',
            'name_en' => 'Microphone',
            'description' => 'הקלטת שמע והודעות קוליות',
            'icon' => '🎤',
            'category' => self::CATEGORY_MEDIA,
            'priority' => self::PRIORITY_MEDIUM,
            'requires_https' => true,
            'requires_user_gesture' => true,
            'browser_support' => ['chrome' => 53, 'firefox' => 36, 'safari' => 11, 'edge' => 12],
            'api' => 'MediaDevices API',
            'fallback' => null,
            'related_permissions' => [self::CAMERA]
        ],
        
        self::LOCATION => [
            'name' => 'מיקום',
            'name_en' => 'Location',
            'description' => 'גישה למיקום המכשיר למציאת חנויות קרובות',
            'icon' => '📍',
            'category' => self::CATEGORY_LOCATION,
            'priority' => self::PRIORITY_HIGH,
            'requires_https' => true,
            'requires_user_gesture' => false,
            'browser_support' => ['chrome' => 50, 'firefox' => 3.5, 'safari' => 5, 'edge' => 12],
            'api' => 'Geolocation API',
            'fallback' => 'ip_location',
            'accuracy_levels' => ['high', 'medium', 'low']
        ],
        
        self::STORAGE => [
            'name' => 'אחסון קבוע',
            'name_en' => 'Persistent Storage',
            'description' => 'שמירת נתונים באופן קבוע במכשיר',
            'icon' => '💾',
            'category' => self::CATEGORY_STORAGE,
            'priority' => self::PRIORITY_HIGH,
            'requires_https' => false,
            'requires_user_gesture' => false,
            'browser_support' => ['chrome' => 55, 'firefox' => 55, 'safari' => null, 'edge' => 79],
            'api' => 'Storage API',
            'fallback' => 'localStorage',
            'quota' => 'unlimited'
        ],
        
        self::CLIPBOARD_READ => [
            'name' => 'קריאת לוח העתקה',
            'name_en' => 'Clipboard Read',
            'description' => 'גישה לקריאת תוכן מלוח ההעתקה',
            'icon' => '📋',
            'category' => self::CATEGORY_SYSTEM,
            'priority' => self::PRIORITY_LOW,
            'requires_https' => true,
            'requires_user_gesture' => true,
            'browser_support' => ['chrome' => 66, 'firefox' => 63, 'safari' => 13.1, 'edge' => 79],
            'api' => 'Clipboard API',
            'fallback' => 'manual_paste'
        ],
        
        self::BACKGROUND_SYNC => [
            'name' => 'סנכרון רקע',
            'name_en' => 'Background Sync',
            'description' => 'סנכרון נתונים ברקע גם במצב אופליין',
            'icon' => '🔄',
            'category' => self::CATEGORY_SYSTEM,
            'priority' => self::PRIORITY_MEDIUM,
            'requires_https' => true,
            'requires_user_gesture' => false,
            'requires_service_worker' => true,
            'browser_support' => ['chrome' => 49, 'firefox' => null, 'safari' => null, 'edge' => 79],
            'api' => 'Background Sync API',
            'fallback' => 'online_sync'
        ],
        
        self::BLUETOOTH => [
            'name' => 'בלוטות\'',
            'name_en' => 'Bluetooth',
            'description' => 'חיבור למכשירי Bluetooth',
            'icon' => '📶',
            'category' => self::CATEGORY_DEVICE,
            'priority' => self::PRIORITY_OPTIONAL,
            'requires_https' => true,
            'requires_user_gesture' => true,
            'browser_support' => ['chrome' => 56, 'firefox' => null, 'safari' => null, 'edge' => 79],
            'api' => 'Web Bluetooth API',
            'fallback' => null,
            'experimental' => true
        ],
        
        self::ACCELEROMETER => [
            'name' => 'מד תאוצה',
            'name_en' => 'Accelerometer',
            'description' => 'גישה לחיישן התאוצה',
            'icon' => '📊',
            'category' => self::CATEGORY_SENSORS,
            'priority' => self::PRIORITY_OPTIONAL,
            'requires_https' => true,
            'requires_user_gesture' => false,
            'browser_support' => ['chrome' => 67, 'firefox' => null, 'safari' => null, 'edge' => 79],
            'api' => 'Generic Sensor API',
            'fallback' => 'devicemotion',
            'experimental' => true
        ]
    ];
    
    /**
     * הרשאות קריטיות לפעולת האפליקציה
     */
    private static $criticalPermissions = [
        self::NOTIFICATION,
        self::PUSH,
        self::STORAGE
    ];
    
    /**
     * קבלת מידע על הרשאה
     */
    public static function getPermissionInfo($type) {
        return self::$permissions[$type] ?? null;
    }
    
    /**
     * קבלת כל ההרשאות
     */
    public static function getAllPermissions() {
        return self::$permissions;
    }
    
    /**
     * קבלת הרשאות לפי קטגוריה
     */
    public static function getPermissionsByCategory($category) {
        return array_filter(self::$permissions, function($permission) use ($category) {
            return $permission['category'] === $category;
        });
    }
    
    /**
     * קבלת הרשאות לפי עדיפות
     */
    public static function getPermissionsByPriority($priority) {
        return array_filter(self::$permissions, function($permission) use ($priority) {
            return $permission['priority'] === $priority;
        });
    }
    
    /**
     * קבלת הרשאות קריטיות
     */
    public static function getCriticalPermissions() {
        return array_intersect_key(
            self::$permissions, 
            array_flip(self::$criticalPermissions)
        );
    }
    
    /**
     * בדיקה אם הרשאה קריטית
     */
    public static function isCritical($type) {
        return in_array($type, self::$criticalPermissions);
    }
    
    /**
     * קבלת הרשאות שדורשות HTTPS
     */
    public static function getHTTPSRequiredPermissions() {
        return array_filter(self::$permissions, function($permission) {
            return $permission['requires_https'] === true;
        });
    }
    
    /**
     * קבלת הרשאות שדורשות Service Worker
     */
    public static function getServiceWorkerRequiredPermissions() {
        return array_filter(self::$permissions, function($permission) {
            return isset($permission['requires_service_worker']) && 
                   $permission['requires_service_worker'] === true;
        });
    }
    
    /**
     * בדיקת תמיכת דפדפן בהרשאה
     */
    public static function checkBrowserSupport($type, $browser, $version = null) {
        $permission = self::$permissions[$type] ?? null;
        if (!$permission) return false;
        
        $support = $permission['browser_support'][$browser] ?? null;
        if ($support === null) return false;
        
        if ($version === null) return true;
        
        return $version >= $support;
    }
    
    /**
     * קבלת תלויות של הרשאה
     */
    public static function getDependencies($type) {
        $permission = self::$permissions[$type] ?? null;
        if (!$permission) return [];
        
        return $permission['dependencies'] ?? [];
    }
    
    /**
     * קבלת הרשאות קשורות
     */
    public static function getRelatedPermissions($type) {
        $permission = self::$permissions[$type] ?? null;
        if (!$permission) return [];
        
        return $permission['related_permissions'] ?? [];
    }
    
    /**
     * קבלת fallback להרשאה
     */
    public static function getFallback($type) {
        $permission = self::$permissions[$type] ?? null;
        if (!$permission) return null;
        
        return $permission['fallback'] ?? null;
    }
    
    /**
     * תרגום סטטוס לעברית
     */
    public static function translateStatus($status) {
        $translations = [
            self::STATUS_GRANTED => 'מאושר',
            self::STATUS_DENIED => 'נדחה',
            self::STATUS_PROMPT => 'ממתין',
            self::STATUS_DEFAULT => 'ברירת מחדל',
            self::STATUS_BLOCKED => 'חסום',
            self::STATUS_NOT_SUPPORTED => 'לא נתמך'
        ];
        
        return $translations[$status] ?? $status;
    }
    
    /**
     * תרגום קטגוריה לעברית
     */
    public static function translateCategory($category) {
        $translations = [
            self::CATEGORY_COMMUNICATION => 'תקשורת',
            self::CATEGORY_MEDIA => 'מדיה',
            self::CATEGORY_LOCATION => 'מיקום',
            self::CATEGORY_STORAGE => 'אחסון',
            self::CATEGORY_DEVICE => 'התקנים',
            self::CATEGORY_SENSORS => 'חיישנים',
            self::CATEGORY_SYSTEM => 'מערכת'
        ];
        
        return $translations[$category] ?? $category;
    }
}