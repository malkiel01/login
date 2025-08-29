<?php
 /**
  * Dashboard Loader
  * מטעין ראשי של המערכת
  */

 class DashboardLoader {
     private $user = null;
     private $userType = 'guest';
     private $permissions = [];
     private $modules = [];
     private $config = [];
     
     /**
      * אתחול המערכת
      */
     public function init() {
         // 1. טעינת קבצי הליבה
         $this->loadCoreFiles();
         
         // 2. בדיקת אבטחה בסיסית
         $this->performSecurityChecks();
         
         // 3. טעינת הגדרות
         $this->loadConfiguration();
         
         // 4. אימות משתמש
         $this->authenticateUser();
         
         // 5. טעינת הרשאות
         $this->loadPermissions();
         
         // 6. טעינת מודולים
         $this->loadModules();
         
         // 7. אתחול API
         $this->initializeAPI();
         
         // 8. רישום פעילות
         $this->logActivity('dashboard_load');
     }
     
     /**
      * טעינת קבצי הליבה
      */
     private function loadCoreFiles() {
         $coreFiles = [
             __DIR__ . '/../config/settings.php',
             __DIR__ . '/../modules/core/init.php',
             __DIR__ . '/../modules/core/security.php',
             __DIR__ . '/../modules/core/database.php',
             __DIR__ . '/../modules/core/dependencies.php',
             __DIR__ . '/permissions.php',
             __DIR__ . '/router.php'
         ];
         
         foreach ($coreFiles as $file) {
             if (file_exists($file)) {
                 require_once $file;
             } else {
                 throw new Exception("Core file not found: $file");
             }
         }
     }
     
     /**
      * בדיקות אבטחה
      */
     private function performSecurityChecks() {
         $security = new SecurityManager();
         
         // בדיקת HTTPS
         if ($security->requiresHTTPS() && !$security->isHTTPS()) {
             $security->redirectToHTTPS();
         }
         
         // בדיקת CSRF
         if (!$security->validateCSRF()) {
             throw new Exception('CSRF validation failed');
         }
         
         // בדיקת Rate Limiting
         if (!$security->checkRateLimit()) {
             throw new Exception('Rate limit exceeded');
         }
     }
     
     /**
      * טעינת הגדרות
      */
     private function loadConfiguration() {
         // טעינת קובץ JSON של הגדרות
         $configFiles = [
             'permissions' => __DIR__ . '/../config/permissions.json',
             'modules' => __DIR__ . '/../config/modules.json',
             'roles' => __DIR__ . '/../config/roles.json'
         ];
         
         foreach ($configFiles as $key => $file) {
             if (file_exists($file)) {
                 $this->config[$key] = json_decode(file_get_contents($file), true);
             }
         }
     }
     
     /**
      * אימות משתמש
      */
     private function authenticateUser() {
         // בדיקה אם המשתמש מחובר
         if (!isset($_SESSION['user_id'])) {
             // הפניה לדף התחברות
             header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
             exit;
         }
         
         // טעינת נתוני משתמש
         $db = DatabaseManager::getInstance();
         $this->user = $db->getUserById($_SESSION['user_id']);
         
         if (!$this->user) {
             session_destroy();
             header('Location: /auth/login.php');
             exit;
         }
         
         // קביעת סוג משתמש
         $this->userType = $this->user['role'] ?? 'user';
         
         // עדכון זמן פעילות אחרון
         $db->updateLastActivity($_SESSION['user_id']);
     }
     
     /**
      * טעינת הרשאות
      */
     private function loadPermissions() {
         $permissionManager = new PermissionManager($this->config['permissions']);
         $this->permissions = $permissionManager->getPermissionsForRole($this->userType);
         
         // שמירת הרשאות ב-session
         $_SESSION['permissions'] = $this->permissions;
     }
     
     /**
      * טעינת מודולים לפי סוג משתמש
      */
     private function loadModules() {
         $modulesConfig = $this->config['modules'][$this->userType] ?? [];
         
         foreach ($modulesConfig as $moduleName) {
             $modulePath = __DIR__ . "/../modules/{$this->userType}/{$moduleName}.php";
             
             if (file_exists($modulePath)) {
                 require_once $modulePath;
                 $this->modules[] = $moduleName;
             }
         }
         
         // טעינת מודול ברירת מחדל אם לא נטענו מודולים
         if (empty($this->modules)) {
             $defaultPath = __DIR__ . "/../modules/{$this->userType}/dashboard.php";
             if (file_exists($defaultPath)) {
                 require_once $defaultPath;
                 $this->modules[] = 'dashboard';
             }
         }
     }
     
     /**
      * אתחול API
      */
     private function initializeAPI() {
         // הגדרת נתיבי API
         $_SESSION['api_base'] = '/dashboard/api/';
         $_SESSION['api_token'] = bin2hex(random_bytes(32));
     }
     
     /**
      * רישום פעילות
      */
     private function logActivity($action) {
         $db = DatabaseManager::getInstance();
         $db->logActivity($_SESSION['user_id'], $action, [
             'ip' => $_SERVER['REMOTE_ADDR'],
             'user_agent' => $_SERVER['HTTP_USER_AGENT'],
             'module' => implode(',', $this->modules)
         ]);
     }
     
     /**
      * רינדור הדשבורד
      */
     public function render() {
         // קביעת class הדשבורד לפי סוג משתמש
         $dashboardClass = ucfirst($this->userType) . 'Dashboard';
         
         if (!class_exists($dashboardClass)) {
             $dashboardClass = 'DefaultDashboard';
         }
         
         // יצירת instance ורינדור
         $dashboard = new $dashboardClass([
             'user' => $this->user,
             'permissions' => $this->permissions,
             'modules' => $this->modules,
             'config' => $this->config
         ]);
         
         $dashboard->display();
     }
 }
?>