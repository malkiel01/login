<?php
 /**
  * API Routes
  * הגדרת נתיבי API
  */

 class APIRouter {
     private $routes = [];
     private $middlewares = [];
     
     public function __construct() {
         $this->defineRoutes();
     }
     
     /**
      * הגדרת הנתיבים
      */
     private function defineRoutes() {
         // User routes
         $this->get('/user', 'UserController@getCurrentUser');
         $this->get('/user/{id}', 'UserController@getUser');
         $this->put('/user/{id}', 'UserController@updateUser');
         $this->delete('/user/{id}', 'UserController@deleteUser');
         
         // Users collection
         $this->get('/users', 'UserController@getUsers');
         $this->post('/users', 'UserController@createUser');
         $this->get('/users/search', 'UserController@searchUsers');
         
         // Stats
         $this->get('/stats', 'StatsController@getStats');
         $this->get('/stats/chart/{type}', 'StatsController@getChartData');
         
         // Activity
         $this->get('/activity', 'ActivityController@getActivity');
         $this->post('/activity', 'ActivityController@logActivity');
         
         // Session
         $this->get('/session', 'SessionController@getInfo');
         $this->post('/session/refresh', 'SessionController@refresh');
         $this->delete('/session', 'SessionController@logout');
         
         // System (admin only)
         $this->get('/system', 'SystemController@getInfo', ['admin']);
         $this->post('/system/cache/clear', 'SystemController@clearCache', ['admin']);
         $this->post('/system/backup', 'SystemController@backup', ['admin']);
         
         // Export
         $this->get('/export/{type}', 'ExportController@export');
     }
     
     /**
      * הוספת middleware
      */
     public function middleware($middleware) {
         $this->middlewares[] = $middleware;
     }
     
     /**
      * הגדרת GET route
      */
     public function get($path, $handler, $permissions = []) {
         $this->addRoute('GET', $path, $handler, $permissions);
     }
     
     /**
      * הגדרת POST route
      */
     public function post($path, $handler, $permissions = []) {
         $this->addRoute('POST', $path, $handler, $permissions);
     }
     
     /**
      * הגדרת PUT route
      */
     public function put($path, $handler, $permissions = []) {
         $this->addRoute('PUT', $path, $handler, $permissions);
     }
     
     /**
      * הגדרת DELETE route
      */
     public function delete($path, $handler, $permissions = []) {
         $this->addRoute('DELETE', $path, $handler, $permissions);
     }
     
     /**
      * הוספת route
      */
     private function addRoute($method, $path, $handler, $permissions) {
         $this->routes[] = [
             'method' => $method,
             'path' => $path,
             'handler' => $handler,
             'permissions' => $permissions
         ];
     }
     
     /**
      * ניתוב בקשה
      */
     public function dispatch() {
         $method = $_SERVER['REQUEST_METHOD'];
         $path = $this->getPath();
         
         // הרצת middlewares
         foreach ($this->middlewares as $middleware) {
             $middleware->handle();
         }
         
         // חיפוש route מתאים
         foreach ($this->routes as $route) {
             if ($route['method'] !== $method) {
                 continue;
             }
             
             $params = $this->matchRoute($route['path'], $path);
             if ($params !== false) {
                 // בדיקת הרשאות
                 if (!empty($route['permissions'])) {
                     $this->checkPermissions($route['permissions']);
                 }
                 
                 // הפעלת handler
                 return $this->executeHandler($route['handler'], $params);
             }
         }
         
         throw new Exception('Route not found', 404);
     }
     
     /**
      * קבלת path
      */
     private function getPath() {
         $path = $_SERVER['REQUEST_URI'];
         $path = parse_url($path, PHP_URL_PATH);
         $path = str_replace('/dashboard/api', '', $path);
         return $path ?: '/';
     }
     
     /**
      * התאמת route
      */
     private function matchRoute($routePath, $requestPath) {
         // המרה לביטוי רגולרי
         $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
         $pattern = '#^' . $pattern . '$#';
         
         if (preg_match($pattern, $requestPath, $matches)) {
             $params = [];
             foreach ($matches as $key => $value) {
                 if (!is_numeric($key)) {
                     $params[$key] = $value;
                 }
             }
             return $params;
         }
         
         return false;
     }
     
     /**
      * בדיקת הרשאות
      */
     private function checkPermissions($requiredPermissions) {
         $userPermissions = $_SESSION['permissions'] ?? [];
         
         foreach ($requiredPermissions as $permission) {
             if (!in_array($permission, $userPermissions)) {
                 throw new Exception('Insufficient permissions', 403);
             }
         }
     }
     
     /**
      * הפעלת handler
      */
     private function executeHandler($handler, $params) {
         list($controller, $method) = explode('@', $handler);
         
         $controllerClass = $controller;
         if (!class_exists($controllerClass)) {
             require_once __DIR__ . '/controllers/' . $controller . '.php';
         }
         
         $instance = new $controllerClass();
         
         // קבלת input
         $input = json_decode(file_get_contents('php://input'), true);
         
         // הפעלת method
         return $instance->$method($params, $input);
     }
 }
?>