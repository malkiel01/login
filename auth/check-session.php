<?php
/**
 * בדיקת session - מחזיר JSON אם המשתמש מחובר
 * משמש לבדיקה מצד הלקוח (JavaScript) כשדף נטען מקאש
 */

session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null
]);
