<?php
 /**
  * API Middleware
  * שכבות ביניים ל-API
  */

 /**
  * Middleware לאימות
  */
 class AuthMiddleware {
     public function handle() {
         if (!isset($_SESSION['user_id'])) {
             throw new Exception('Unauthorized', 401);
         }
         
         // בדיקת תוקף session
         if (isset($_SESSION['last_activity'])) {
             $inactive = time() - $_SESSION['last_activity'];
             if ($inactive > SESSION_TIMEOUT) {
                 session_destroy();
                 throw new Exception('Session expired', 401);
             }
         }
         
         $_SESSION['last_activity'] = time();
     }
 }

 /**
  * Middleware ל-Rate Limiting
  */
 class RateLimitMiddleware {
     private $maxRequests = 60;
     private $timeWindow = 60; // seconds
     
     public function handle() {
         $identifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
         $key = 'rate_limit_' . md5($identifier);
         
         // שימוש ב-session לשמירת נתונים
         if (!isset($_SESSION[$key])) {
             $_SESSION[$key] = [];
         }
         
         // ניקוי בקשות ישנות
         $now = time();
         $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now) {
             return ($now - $timestamp) < $this->timeWindow;
         });
         
         // בדיקת מגבלה
         if (count($_SESSION[$key]) >= $this->maxRequests) {
             throw new Exception('Rate limit exceeded', 429);
         }
         
         // הוספת בקשה חדשה
         $_SESSION[$key][] = $now;
     }
 }

 /**
  * Middleware לרישום
  */
 class LoggingMiddleware {
     public function handle() {
         $logData = [
             'time' => date('Y-m-d H:i:s'),
             'method' => $_SERVER['REQUEST_METHOD'],
             'path' => $_SERVER['REQUEST_URI'],
             'ip' => $_SERVER['REMOTE_ADDR'],
             'user_id' => $_SESSION['user_id'] ?? null
         ];
         
         // רישום ב-log file
         $logFile = LOGS_PATH . '/api_' . date('Y-m-d') . '.log';
         file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND);
     }
 }

 /**
  * Middleware ל-CORS
  */
 class CORSMiddleware {
     public function handle() {
         $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
         
         // בדיקת origin מורשה
         $allowedOrigins = [
             'http://localhost',
             'https://yourdomain.com'
         ];
         
         if (in_array($origin, $allowedOrigins)) {
             header('Access-Control-Allow-Origin: ' . $origin);
         } else {
             header('Access-Control-Allow-Origin: *');
         }
         
         header('Access-Control-Allow-Credentials: true');
         header('Access-Control-Max-Age: 86400');
     }
 }
?>