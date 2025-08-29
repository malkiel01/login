<?php
 /**
  * Dependencies Manager
  * מנהל תלויות וטעינת משאבים
  */

 class DependencyLoader {
     private static $dependencies = [
         'global' => [
             'css' => [
                 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                 '/dashboard/assets/css/base.css',
                 '/dashboard/assets/css/dashboard.css'
             ],
             'js' => [
                 '/dashboard/assets/js/core.js',
                 '/dashboard/assets/js/dashboard.js'
             ]
         ],
         'admin' => [
             'css' => [
                 '/dashboard/modules/admin/assets/admin.css'
             ],
             'js' => [
                 '/dashboard/modules/admin/assets/admin.js',
                 '/dashboard/assets/js/api-client.js'
             ]
         ],
         'user' => [
             'css' => [
                 '/dashboard/modules/user/assets/user.css'
             ],
             'js' => [
                 '/dashboard/modules/user/assets/user.js'
             ]
         ],
         'moderator' => [
             'css' => [
                 '/dashboard/modules/moderator/assets/moderator.css'
             ],
             'js' => [
                 '/dashboard/modules/moderator/assets/moderator.js'
             ]
         ],
         'guest' => [
             'css' => [],
             'js' => []
         ]
     ];
     
     private static $cdnFallbacks = [
         'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' => 
             '/assets/vendor/font-awesome/css/all.min.css'
     ];
     
     /**
      * טעינת dependencies לפי סוג משתמש
      */
     public static function loadForUser($userType) {
         $deps = self::$dependencies['global'];
         
         if (isset(self::$dependencies[$userType])) {
             $deps['css'] = array_merge($deps['css'], self::$dependencies[$userType]['css']);
             $deps['js'] = array_merge($deps['js'], self::$dependencies[$userType]['js']);
         }
         
         return $deps;
     }
     
     /**
      * יצירת HTML tags
      */
     public static function renderHTML($userType) {
         $deps = self::loadForUser($userType);
         $html = '';
         
         // CSS
         foreach ($deps['css'] as $css) {
             $html .= self::renderCSS($css) . "\n";
         }
         
         // JavaScript
         foreach ($deps['js'] as $js) {
             $html .= self::renderJS($js) . "\n";
         }
         
         return $html;
     }
     
     /**
      * רינדור CSS
      */
     private static function renderCSS($href) {
         // בדיקה אם יש fallback
         $fallback = '';
         if (isset(self::$cdnFallbacks[$href])) {
             $fallback = ' onerror="this.onerror=null;this.href=\'' . self::$cdnFallbacks[$href] . '\'"';
         }
         
         return '<link rel="stylesheet" href="' . $href . '"' . $fallback . '>';
     }
     
     /**
      * רינדור JavaScript
      */
     private static function renderJS($src) {
         // הוספת async/defer לקבצים לא קריטיים
         $loading = '';
         if (strpos($src, 'core') === false) {
             $loading = ' defer';
         }
         
         return '<script src="' . $src . '"' . $loading . '></script>';
     }
     
     /**
      * טעינת CSS inline
      */
     public static function renderInlineCSS($userType) {
         $criticalCSS = "
         /* Critical CSS for above-the-fold content */
         * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
         .dashboard-container { min-height: 100vh; background: #f3f4f6; }
         .loading { display: flex; justify-content: center; align-items: center; height: 100vh; }
         ";
         
         return '<style>' . $criticalCSS . '</style>';
     }
     
     /**
      * Preload משאבים קריטיים
      */
     public static function renderPreloads() {
         $preloads = [
             '/dashboard/assets/css/dashboard.css' => 'style',
             '/dashboard/assets/js/core.js' => 'script',
             '/dashboard/assets/fonts/main.woff2' => 'font'
         ];
         
         $html = '';
         foreach ($preloads as $href => $as) {
             $type = $as === 'font' ? ' type="font/woff2" crossorigin' : '';
             $html .= '<link rel="preload" href="' . $href . '" as="' . $as . '"' . $type . '>' . "\n";
         }
         
         return $html;
     }
     
     /**
      * הוספת dependency דינמית
      */
     public static function add($userType, $type, $path) {
         if (!isset(self::$dependencies[$userType])) {
             self::$dependencies[$userType] = ['css' => [], 'js' => []];
         }
         
         if (!in_array($path, self::$dependencies[$userType][$type])) {
             self::$dependencies[$userType][$type][] = $path;
         }
     }
     
     /**
      * הסרת dependency
      */
     public static function remove($userType, $type, $path) {
         if (isset(self::$dependencies[$userType][$type])) {
             $key = array_search($path, self::$dependencies[$userType][$type]);
             if ($key !== false) {
                 unset(self::$dependencies[$userType][$type][$key]);
             }
         }
     }
     
     /**
      * ניקוי cache
      */
     public static function bustCache($path) {
         $version = DASHBOARD_VERSION;
         $separator = strpos($path, '?') !== false ? '&' : '?';
         return $path . $separator . 'v=' . $version;
     }
 }
?>