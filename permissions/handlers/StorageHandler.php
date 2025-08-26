<?php
/**
 * Storage Handler
 * permissions/handlers/StorageHandler.php
 * 
 * טיפול בהרשאות אחסון
 */

namespace Permissions\Handlers;

class StorageHandler {
    
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
     * בדיקת הרשאת אחסון
     */
    public function checkPermission() {
        return $this->storage->getPermissionStatus($this->userId, 'persistent-storage');
    }
    
    /**
     * קבלת מידע על שימוש באחסון
     */
    public function getStorageInfo() {
        return [
            'quota' => [
                'total' => 'unlimited',
                'used' => 0,
                'available' => 'unlimited'
            ],
            'types' => [
                'localStorage' => true,
                'sessionStorage' => true,
                'indexedDB' => true,
                'cacheAPI' => true,
                'cookies' => true
            ],
            'persistent' => false
        ];
    }
    
    /**
     * ניקוי אחסון
     */
    public function clearStorage($type = 'all') {
        $cleared = [];
        
        // רשימת סוגי אחסון
        $types = [
            'cache' => 'Clear browser cache',
            'cookies' => 'Clear cookies',
            'localStorage' => 'Clear localStorage',
            'sessionStorage' => 'Clear sessionStorage',
            'indexedDB' => 'Clear IndexedDB'
        ];
        
        if ($type === 'all') {
            $cleared = array_keys($types);
        } else {
            $cleared[] = $type;
        }
        
        return [
            'success' => true,
            'cleared' => $cleared,
            'message' => 'Storage clearing must be done client-side'
        ];
    }
    
    /**
     * אומדן שטח אחסון
     */
    public function estimateStorageQuota() {
        return [
            'message' => 'Storage estimation must be done client-side',
            'javascript' => 'navigator.storage.estimate()',
            'fallback' => [
                'usage' => 0,
                'quota' => 5 * 1024 * 1024 // 5MB default
            ]
        ];
    }
    
    /**
     * בדיקת תמיכה באחסון
     */
    public function checkStorageSupport() {
        return [
            'localStorage' => true,
            'sessionStorage' => true,
            'indexedDB' => true,
            'webSQL' => false, // Deprecated
            'fileSystem' => false,
            'cacheStorage' => true,
            'cookies' => true
        ];
    }
    
    /**
     * קבלת הגדרות אחסון
     */
    public function getStorageSettings() {
        return [
            'persistent' => true,
            'sync' => true,
            'encryption' => false,
            'compression' => true,
            'auto_cleanup' => true,
            'max_age_days' => 30
        ];
    }
    
    /**
     * רישום שימוש באחסון
     */
    public function logStorageUsage($type, $action, $size = null) {
        $data = [
            'user_id' => $this->userId,
            'storage_type' => $type,
            'action' => $action,
            'size_bytes' => $size,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // כאן ניתן לשמור ב-DB
        return true;
    }
}