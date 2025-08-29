<?php
 /**
  * Core Initialization
  * אתחול מערכת הליבה
  */

 // הגדרת קבועים
 define('DASHBOARD_VERSION', '2.0.0');
 define('DASHBOARD_PATH', dirname(dirname(__DIR__)));
 define('DASHBOARD_URL', '/dashboard');
 define('MODULES_PATH', DASHBOARD_PATH . '/modules');
 define('TEMPLATES_PATH', DASHBOARD_PATH . '/templates');
 define('ASSETS_PATH', DASHBOARD_PATH . '/assets');
 define('CONFIG_PATH', DASHBOARD_PATH . '/config');
 define('LOGS_PATH', DASHBOARD_PATH . '/logs');

 // הגדרת איזור זמן
 date_default_timezone_set('Asia/Jerusalem');

 // הגדרת encoding
 mb_internal_encoding('UTF-8');

 // AutoLoader
 spl_autoload_register(function ($class) {
     $paths = [
         MODULES_PATH . '/core/',
         DASHBOARD_PATH . '/bootstrap/',
         DASHBOARD_PATH . '/includes/'
     ];
     
     foreach ($paths as $path) {
         $file = $path . $class . '.php';
         if (file_exists($file)) {
             require_once $file;
             return;
         }
     }
 });

 // Error Handler
 set_error_handler(function ($severity, $message, $file, $line) {
     if (!(error_reporting() & $severity)) {
         return false;
     }
     
     throw new ErrorException($message, 0, $severity, $file, $line);
 });

 // Exception Handler
 set_exception_handler(function ($exception) {
     error_log($exception->getMessage());
     
     if (getenv('APP_ENV') === 'development') {
         echo '<pre>';
         echo $exception;
         echo '</pre>';
     } else {
         header('Location: /error/500.php');
     }
     exit;
 });

 // Shutdown Handler
 register_shutdown_function(function () {
     $error = error_get_last();
     if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
         error_log('Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
         
         if (getenv('APP_ENV') !== 'development') {
             header('Location: /error/500.php');
         }
     }
 });

 /**
  * פונקציות עזר גלובליות
  */

 // בדיקת AJAX request
 function isAjax() {
     return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
 }

 // קבלת IP address
 function getClientIP() {
     $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
     
     foreach ($keys as $key) {
         if (isset($_SERVER[$key])) {
             return $_SERVER[$key];
         }
     }
     
     return '0.0.0.0';
 }

 // יצירת CSRF token
 function generateCSRFToken() {
     if (!isset($_SESSION['csrf_token'])) {
         $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
     }
     return $_SESSION['csrf_token'];
 }

 // בדיקת CSRF token
 function validateCSRFToken($token) {
     return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
 }
?>