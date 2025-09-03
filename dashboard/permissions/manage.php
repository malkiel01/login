<?php
session_start();
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// רק מנהלים יכולים לגשת לדף זה
requirePermission('manage_users');

// טיפול בעדכון הרשאות
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permission'])) {
    $userId = intval($_POST['user_id']);
    $dashboardType = $_POST['dashboard_type'];
    
    // עדכון בדטבייס
    $stmt = $pdo->prepare("
        INSERT INTO user_permissions (user_id, dashboard_type) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE dashboard_type = VALUES(dashboard_type)
    ");
    $stmt->execute([$userId, $dashboardType]);
    
    $message = "ההרשאות עודכנו בהצלחה!";
}

// קבלת רשימת משתמשים
$stmt = $pdo->query("
    SELECT u.id, u.username, u.name, u.email, 
           COALESCE(up.dashboard_type, 'default') as dashboard_type
    FROM users u
    LEFT JOIN user_permissions up ON u.id = up.user_id
    ORDER BY u.name
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול הרשאות</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="permissions-container">
        <h1>⚙️ ניהול הרשאות משתמשים</h1>
        
        <?php if (isset($message)): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <table class="permissions-table">
            <thead>
                <tr>
                    <th>משתמש</th>
                    <th>אימייל</th>
                    <th>סוג דשבורד</th>
                    <th>פעולה</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <form method="POST">
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <select name="dashboard_type">
                                <option value="default" <?php echo $user['dashboard_type'] === 'default' ? 'selected' : ''; ?>>ברירת מחדל</option>
                                <option value="admin" <?php echo $user['dashboard_type'] === 'admin' ? 'selected' : ''; ?>>מנהל</option>
                                <option value="manager" <?php echo $user['dashboard_type'] === 'manager' ? 'selected' : ''; ?>>מנהל צוות</option>
                                <option value="employee" <?php echo $user['dashboard_type'] === 'employee' ? 'selected' : ''; ?>>עובד</option>
                                <option value="client" <?php echo $user['dashboard_type'] === 'client' ? 'selected' : ''; ?>>לקוח</option>
                                <option value="cemetery_manager" <?php echo $user['dashboard_type'] === 'cemetery_manager' ? 'selected' : ''; ?>>מנהל בית עלמין</option>
                            </select>
                        </td>
                        <td>
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="update_permission" class="btn-save">שמור</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <a href="../" class="btn-back">חזרה לדשבורד</a>
    </div>
</body>
</html>