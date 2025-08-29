<?php
 /**
  * Dashboard Router
  * מנהל ניתוב דינמי
  */

 class DashboardRouter {
     private $routes = [];
     private $currentRoute = null;
     private $params = [];
     
     /**
      * הוספת נתיב
      */
     public function addRoute($path, $handler, $permissions = []) {
         $this->routes[$path] = [
             'handler' => $handler,
             'permissions' => $permissions
         ];
     }
     
     /**
      * ניתוב בקשה
      */
     public function route($path = null) {
         if ($path === null) {
             $path = $_SERVER['REQUEST_URI'];
             $path = parse_url($path, PHP_URL_PATH);
             $path = str_replace('/dashboard', '', $path);
         }
         
         // חיפוש התאמה
         foreach ($this->routes as $routePath => $routeConfig) {
             if ($this->matchRoute($routePath, $path)) {
                 $this->currentRoute = $routeConfig;
                 
                 // בדיקת הרשאות
                 if (!empty($routeConfig['permissions'])) {
                     $pm = new PermissionManager();
                     if (!$pm->hasAllPermissions($routeConfig['permissions'])) {
                         $this->handleUnauthorized();
                         return;
                     }
                 }
                 
                 // הפעלת handler
                 $this->executeHandler($routeConfig['handler']);
                 return;
             }
         }
         
         // אם לא נמצא נתיב
         $this->handleNotFound();
     }
     
     /**
      * בדיקת התאמת נתיב
      */
     private function matchRoute($routePath, $requestPath) {
         // המרת נתיב לביטוי רגולרי
         $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
         $pattern = '#^' . $pattern . '$#';
         
         if (preg_match($pattern, $requestPath, $matches)) {
             // שמירת פרמטרים
             foreach ($matches as $key => $value) {
                 if (!is_numeric($key)) {
                     $this->params[$key] = $value;
                 }
             }
             return true;
         }
         
         return false;
     }
     
     /**
      * הפעלת handler
      */
     private function executeHandler($handler) {
         if (is_callable($handler)) {
             call_user_func_array($handler, [$this->params]);
         } elseif (is_string($handler)) {
             // אם זה שם של class@method
             if (strpos($handler, '@') !== false) {
                 list($class, $method) = explode('@', $handler);
                 $instance = new $class();
                 $instance->$method($this->params);
             } else {
                 // טעינת קובץ
                 include $handler;
             }
         }
     }
     
     /**
      * טיפול בחוסר הרשאה
      */
     private function handleUnauthorized() {
         http_response_code(403);
         include __DIR__ . '/../templates/errors/403.php';
     }
     
     /**
      * טיפול בנתיב לא קיים
      */
     private function handleNotFound() {
         http_response_code(404);
         include __DIR__ . '/../templates/errors/404.php';
     }
     
     /**
      * קבלת פרמטר
      */
     public function getParam($name, $default = null) {
         return $this->params[$name] ?? $default;
     }
 }
?>