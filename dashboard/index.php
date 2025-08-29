<?php
// dashboard/index.php - נקודת כניסה ראשית
session_start();

// בדיקת התחברות - משתמש את הסשן הקיים
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// // חיבור למסד נתונים - משתמש בחיבור הקיים של הפרויקט
// require_once '../config.php';  // או איפה שהחיבור שלך נמצא

// // פונקציה פשוטה לקבלת סוג הדשבורד
// function getUserDashboardType($userId) {
//     global $conn; // או $pdo או איך שקראת למשתנה החיבור שלך
    
//     // בדיקה אם טבלת ההרשאות קיימת
//     $result = $conn->query("SHOW TABLES LIKE 'user_permissions'");
//     if ($result->num_rows == 0) {
//         // אם הטבלה לא קיימת, החזר ברירת מחדל
//         return 'default';
//     }
    
//     // קבלת סוג הדשבורד מהטבלה
//     $stmt = $conn->prepare("
//         SELECT dashboard_type 
//         FROM user_permissions 
//         WHERE user_id = ? 
//         LIMIT 1
//     ");
//     $stmt->bind_param("i", $userId);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     if ($row = $result->fetch_assoc()) {
//         return $row['dashboard_type'];
//     }
    
//     return 'default'; // ברירת מחדל אם אין רשומה
// }

// // קבלת סוג הדשבורד של המשתמש המחובר
// $userId = $_SESSION['user_id'];
// $dashboardType = getUserDashboardType($userId);

// // הצגת הדשבורד המתאים
// $dashboardFile = __DIR__ . '/dashboards/' . $dashboardType . '.php';

// if (file_exists($dashboardFile)) {
//     require_once $dashboardFile;
// } else {
//     // אם הקובץ לא קיים, הצג דשבורד ברירת מחדל
//     require_once __DIR__ . '/dashboards/default.php';
// }

echo 'test1';