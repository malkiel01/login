<?php
 /**
  * Security Manager
  * מנהל אבטחה מרכזי
  */

 class SecurityManager {
     private $config = [];
     private $rateLimiter = null;
     
     /**
      * Constructor
      */
     public function __construct() {
         $this->loadConfig();
         $this->initRateLimiter();
     }
     
     /**
      * טעינת הגדרות אבטחה
      */
     private function loadConfig() {
         $configFile = CONFIG_PATH . '/security.json';
         if (file_exists($configFile)) {
             $this->config = json_decode(file_get_contents($configFile), true);
         } else {
             // הגדרות ברירת מחדל
             $this->config = [
                 'require_https' => true,
                 'csrf_enabled' => true,
                 'rate_limit' => [
                     'enabled' => true,
                     'max_requests' => 60,
                     'time_window' => 60
                 ],
                 'session' => [
                     'timeout' => 3600,
                     'regenerate' => 300
                 ],
                 'password' => [
                     'min_length' => 8,
                     'require_uppercase' => true,
                     'require_lowercase' => true,
                     'require_numbers' => true,
                     'require_special' => true
                 ]
             ];
         }
     }
     
     /**
      * אתחול Rate Limiter
      */
     private function initRateLimiter() {
         if ($this->config['rate_limit']['enabled']) {
             $this->rateLimiter = new RateLimiter(
                 $this->config['rate_limit']['max_requests'],
                 $this->config['rate_limit']['time_window']
             );
         }
     }
     
     /**
      * בדיקת HTTPS
      */
     public function requiresHTTPS() {
         return $this->config['require_https'];
     }
     
     /**
      * בדיקה אם החיבור מאובטח
      */
     public function isHTTPS() {
         return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                $_SERVER['SERVER_PORT'] == 443 ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
     }
     
     /**
      * הפניה ל-HTTPS
      */
     public function redirectToHTTPS() {
         if (!$this->isHTTPS()) {
             $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
             header('Location: ' . $url);
             exit;
         }
     }
     
     /**
      * בדיקת CSRF
      */
     public function validateCSRF() {
         if (!$this->config['csrf_enabled']) {
             return true;
         }
         
         // לא בודקים CSRF ב-GET requests
         if ($_SERVER['REQUEST_METHOD'] === 'GET') {
             return true;
         }
         
         $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
         return validateCSRFToken($token);
     }
     
     /**
      * בדיקת Rate Limiting
      */
     public function checkRateLimit() {
         if (!$this->rateLimiter) {
             return true;
         }
         
         $identifier = $_SESSION['user_id'] ?? getClientIP();
         return $this->rateLimiter->check($identifier);
     }
     
     /**
      * סניטציה של קלט
      */
     public function sanitizeInput($input, $type = 'string') {
         if (is_array($input)) {
             return array_map(function($item) use ($type) {
                 return $this->sanitizeInput($item, $type);
             }, $input);
         }
         
         switch ($type) {
             case 'email':
                 return filter_var($input, FILTER_SANITIZE_EMAIL);
                 
             case 'int':
                 return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                 
             case 'float':
                 return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                 
             case 'url':
                 return filter_var($input, FILTER_SANITIZE_URL);
                 
             case 'html':
                 return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
                 
             case 'sql':
                 return addslashes($input);
                 
             default:
                 return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
         }
     }
     
     /**
      * ולידציה של סיסמה
      */
     public function validatePassword($password) {
         $errors = [];
         
         if (strlen($password) < $this->config['password']['min_length']) {
             $errors[] = "Password must be at least {$this->config['password']['min_length']} characters";
         }
         
         if ($this->config['password']['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
             $errors[] = "Password must contain at least one uppercase letter";
         }
         
         if ($this->config['password']['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
             $errors[] = "Password must contain at least one lowercase letter";
         }
         
         if ($this->config['password']['require_numbers'] && !preg_match('/[0-9]/', $password)) {
             $errors[] = "Password must contain at least one number";
         }
         
         if ($this->config['password']['require_special'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
             $errors[] = "Password must contain at least one special character";
         }
         
         return empty($errors) ? true : $errors;
     }
     
     /**
      * הצפנת נתונים
      */
     public function encrypt($data, $key = null) {
         if ($key === null) {
             $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-me';
         }
         
         $iv = openssl_random_pseudo_bytes(16);
         $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
         
         return base64_encode($iv . $encrypted);
     }
     
     /**
      * פענוח נתונים
      */
     public function decrypt($data, $key = null) {
         if ($key === null) {
             $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-me';
         }
         
         $data = base64_decode($data);
         $iv = substr($data, 0, 16);
         $encrypted = substr($data, 16);
         
         return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
     }
     
     /**
      * יצירת token אקראי
      */
     public function generateToken($length = 32) {
         return bin2hex(random_bytes($length));
     }
     
     /**
      * Hash סיסמה
      */
     public function hashPassword($password) {
         return password_hash($password, PASSWORD_DEFAULT);
     }
     
     /**
      * אימות סיסמה
      */
     public function verifyPassword($password, $hash) {
         return password_verify($password, $hash);
     }
 }

 /**
  * Rate Limiter Class
  */
 class RateLimiter {
     private $maxRequests;
     private $timeWindow;
     private $storage = [];
     
     public function __construct($maxRequests, $timeWindow) {
         $this->maxRequests = $maxRequests;
         $this->timeWindow = $timeWindow;
     }
     
     public function check($identifier) {
         $now = time();
         $key = md5($identifier);
         
         // ניקוי רשומות ישנות
         if (isset($this->storage[$key])) {
             $this->storage[$key] = array_filter($this->storage[$key], function($timestamp) use ($now) {
                 return ($now - $timestamp) < $this->timeWindow;
             });
         } else {
             $this->storage[$key] = [];
         }
         
         // בדיקת מגבלה
         if (count($this->storage[$key]) >= $this->maxRequests) {
             return false;
         }
         
         // הוספת בקשה חדשה
         $this->storage[$key][] = $now;
         return true;
     }
 }
?>