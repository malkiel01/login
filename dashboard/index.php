<?php
session_start();

require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// // בדיקת התחברות
// if (!isLoggedIn()) {
    //     header('Location: /auth/login.php');
    //     exit;
    // }
    
    // // קבלת סוג הדשבורד של המשתמש
    // $userId = $_SESSION['user_id'];
    // $dashboardType = getUserDashboardType($userId);
    
    // // ניתוב לדשבורד המתאים
    // require_once 'router.php';
    // $router = new DashboardRouter($dashboardType);
    // $router->route();
    echo '77'
?>