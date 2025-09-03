<?php
// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
$pdo = getDBConnection();

// בדיקה שהמשתמש הוא מנהל בית עלמין
$stmt = $pdo->prepare("SELECT dashboard_type FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || $result['dashboard_type'] !== 'cemetery_manager') {
    die('אין לך הרשאת מנהל בית עלמין');
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>דשבורד בתי עלמין</title>
    <!-- הוסף CSS כאן -->
</head>
<body>
    <h1>🪦 דשבורד ניהול בתי עלמין</h1>
    
    <div class="dashboard-sections">
        <a href="/cemetery/graves">ניהול קברים</a>
        <a href="/cemetery/families">ניהול משפחות</a>
        <a href="/cemetery/map">מפת בית העלמין</a>
        <a href="/cemetery/reports">דוחות</a>
    </div>
    
    <!-- התוכן של הדשבורד -->
</body>
</html>