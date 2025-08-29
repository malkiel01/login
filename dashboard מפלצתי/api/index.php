<?php
 /**
  * API Entry Point
  * נקודת כניסה ל-API
  */

 session_start();
 require_once '../bootstrap/loader.php';

 // הגדרת Headers
 header('Content-Type: application/json; charset=utf-8');
 header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
 header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
 header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

 // טיפול ב-OPTIONS request
 if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
     http_response_code(200);
     exit;
 }

 // אתחול
 $loader = new DashboardLoader();
 $loader->init();

 // טעינת router
 require_once 'routes.php';
 require_once 'middleware.php';

 // יצירת router
 $router = new APIRouter();

 // הגדרת middleware
 $router->middleware(new AuthMiddleware());
 $router->middleware(new RateLimitMiddleware());
 $router->middleware(new LoggingMiddleware());

 // ניתוב הבקשה
 try {
     $response = $router->dispatch();
     echo json_encode($response);
 } catch (Exception $e) {
     http_response_code($e->getCode() ?: 500);
     echo json_encode([
         'success' => false,
         'error' => $e->getMessage()
     ]);
 }
?>