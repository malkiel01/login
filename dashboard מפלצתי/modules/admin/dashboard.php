<?php
/**
 * Admin Dashboard Module
 * מודול דשבורד למנהלים
 */

class AdminDashboard {
    private $data;
    private $db;
    private $permissions;
    
    /**
     * Constructor
     */
    public function __construct($data) {
        $this->data = $data;
        $this->db = DatabaseManager::getInstance();
        $this->permissions = new PermissionManager();
    }
    
    /**
     * הצגת הדשבורד
     */
    public function display() {
        // טעינת נתונים
        $stats = $this->getStats();
        $recentUsers = $this->getRecentUsers();
        $activityLog = $this->getActivityLog();
        $systemStatus = $this->getSystemStatus();
        
        // טעינת template
        $this->render('admin-dashboard', [
            'user' => $this->data['user'],
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'activityLog' => $activityLog,
            'systemStatus' => $systemStatus,
            'permissions' => $this->data['permissions']
        ]);
    }
    
    /**
     * קבלת סטטיסטיקות
     */
    private function getStats() {
        $stats = $this->db->getDashboardStats();
        
        // הוספת סטטיסטיקות מתקדמות למנהלים
        $stats['server_load'] = sys_getloadavg()[0];
        $stats['disk_usage'] = round(disk_free_space('/') / disk_total_space('/') * 100, 2);
        $stats['memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2);
        $stats['active_sessions'] = $this->getActiveSessions();
        
        return $stats;
    }
    
    /**
     * קבלת משתמשים אחרונים
     */
    private function getRecentUsers() {
        return $this->db->getAllUsers(10);
    }
    
    /**
     * קבלת לוג פעילות
     */
    private function getActivityLog() {
        return $this->db->getActivityLog(50);
    }
    
    /**
     * קבלת סטטוס מערכת
     */
    private function getSystemStatus() {
        return [
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->db->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'ssl_enabled' => !empty($_SERVER['HTTPS']),
            'timezone' => date_default_timezone_get(),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'max_post_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit')
        ];
    }
    
    /**
     * קבלת sessions פעילים
     */
    private function getActiveSessions() {
        $sessionPath = session_save_path();
        if (empty($sessionPath)) {
            $sessionPath = sys_get_temp_dir();
        }
        
        $sessions = glob($sessionPath . '/sess_*');
        return count($sessions);
    }
    
    /**
     * רינדור template
     */
    private function render($template, $data) {
        extract($data);
        
        // כותרת הדף
        $pageTitle = 'דשבורד מנהל - ' . SITE_NAME;
        
        // טעינת dependencies
        $dependencies = DependencyLoader::renderHTML('admin');
        $preloads = DependencyLoader::renderPreloads();
        $inlineCSS = DependencyLoader::renderInlineCSS('admin');
        
        // טעינת layout
        include TEMPLATES_PATH . '/layouts/header.php';
        include TEMPLATES_PATH . '/admin/' . $template . '.php';
        include TEMPLATES_PATH . '/layouts/footer.php';
    }
}
?>
