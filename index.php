<?php
/**
 * דף אינדקס - הפניה אוטומטית לדשבורד
 * index.php
 */

session_start();

// אם המשתמש מחובר - הפנה לדשבורד
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// אם לא מחובר - הפנה לדף התחברות
header('Location: auth/login.php');
exit;
?>