<?php
/**
 * Dashboard Entry Point
 * קובץ ראשי מינימלי - רק טוען את המערכת
 */

// הפעלת דיווח שגיאות בפיתוח
if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// התחלת session
session_start();

// טעינת מערכת האתחול
require_once __DIR__ . '/bootstrap/loader.php';

// יצירת instance של הדשבורד
$dashboard = new DashboardLoader();

try {
    // אתחול המערכת
    $dashboard->init();
    
    // רינדור הדשבורד
    $dashboard->render();
    
} catch (Exception $e) {
    // טיפול בשגיאות
    error_log('Dashboard Error: ' . $e->getMessage());
    
    // הפניה לדף שגיאה
    if (getenv('APP_ENV') === 'production') {
        header('Location: /error/500.php');
    } else {
        die('Error: ' . $e->getMessage());
    }
}
?>
