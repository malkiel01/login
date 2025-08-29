<?php
// dashboard/dashboards/admin.php - דשבורד מנהל מערכת
// אין צורך ב-session_start() - זה כבר נקרא ב-index.php!

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

require_once '../../config.php';
$pdo = getDBConnection();

// בדיקה שהמשתמש הוא אכן מנהל
$stmt = $pdo->prepare("SELECT dashboard_type FROM user_permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || $result['dashboard_type'] !== 'admin') {
    die('
        <div style="text-align: center; padding: 50px; font-family: Arial; direction: rtl;">
            <h2>אין לך הרשאת מנהל 🔒</h2>
            <p>פנה למנהל המערכת לקבלת הרשאות</p>
            <a href="/dashboard/" style="color: blue;">חזור לדשבורד</a>
        </div>
    ');
}

// טיפול בפעולות POST
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // עדכון הרשאות משתמש
    if (isset($_POST['update_permission'])) {
        $userId = intval($_POST['user_id']);
        $dashboardType = $_POST['dashboard_type'];
        
        $stmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, dashboard_type) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE dashboard_type = VALUES(dashboard_type)
        ");
        $stmt->execute([$userId, $dashboardType]);
        $message = "ההרשאות עודכנו בהצלחה!";
        $messageType = "success";
    }
    
    // שינוי סטטוס משתמש (פעיל/לא פעיל)
    if (isset($_POST['toggle_status'])) {
        $userId = intval($_POST['user_id']);
        $newStatus = intval($_POST['new_status']);
        
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        
        $statusText = $newStatus ? "הופעל" : "הושבת";
        $message = "המשתמש $statusText בהצלחה!";
        $messageType = "success";
    }
    
    // מחיקת משתמש
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);
        
        if ($userId != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $message = "המשתמש נמחק בהצלחה!";
            $messageType = "success";
        } else {
            $message = "לא ניתן למחוק את עצמך!";
            $messageType = "error";
        }
    }
}

// קבלת רשימת כל המשתמשים
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.username,
        u.name,
        u.email,
        u.is_active,
        u.last_login,
        u.created_at,
        u.auth_type,
        COALESCE(up.dashboard_type, 'default') as dashboard_type,
        CASE 
            WHEN u.last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1
            ELSE 0
        END as is_online
    FROM users u
    LEFT JOIN user_permissions up ON u.id = up.user_id
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// סטטיסטיקות
$totalUsers = count($users);
$activeUsers = count(array_filter($users, function($u) { return $u['is_active']; }));
$onlineUsers = count(array_filter($users, function($u) { return $u['is_online']; }));
$adminUsers = count(array_filter($users, function($u) { return $u['dashboard_type'] === 'admin'; }));
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד מנהל מערכת</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f3f4f6;
            direction: rtl;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.blue { background: #dbeafe; color: #2563eb; }
        .stat-icon.green { background: #d1fae5; color: #059669; }
        .stat-icon.yellow { background: #fed7aa; color: #ea580c; }
        .stat-icon.purple { background: #e9d5ff; color: #9333ea; }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .users-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .users-table th {
            background: #f9fafb;
            font-weight: 600;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        
        .dashboard-select {
            padding: 5px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .status-online { background: #dbeafe; color: #1e40af; }
        .status-offline { background: #f3f4f6; color: #6b7280; }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="header-content">
            <h1>👨‍💼 דשבורד מנהל מערכת</h1>
            <div>
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'מנהל'); ?></span> | 
                <a href="/auth/logout.php" style="color: white;">יציאה</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div>
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>סה"כ משתמשים</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">✓</div>
                <div>
                    <h3><?php echo $activeUsers; ?></h3>
                    <p>משתמשים פעילים</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow">🟢</div>
                <div>
                    <h3><?php echo $onlineUsers; ?></h3>
                    <p>מחוברים כעת</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">👨‍💼</div>
                <div>
                    <h3><?php echo $adminUsers; ?></h3>
                    <p>מנהלי מערכת</p>
                </div>
            </div>
        </div>
        
        <div class="users-section">
            <h2 style="margin-bottom: 20px;">👥 ניהול משתמשים</h2>
            
            <table class="users-table">
                <thead>
                    <tr>
                        <th>משתמש</th>
                        <th>אימייל</th>
                        <th>סטטוס</th>
                        <th>מחובר</th>
                        <th>התחברות אחרונה</th>
                        <th>סוג דשבורד</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></strong><br>
                                    <small style="color: #6b7280;">@<?php echo htmlspecialchars($user['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $user['is_active'] ? 'פעיל' : 'לא פעיל'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $user['is_online'] ? 'status-online' : 'status-offline'; ?>">
                                <?php echo $user['is_online'] ? 'מחובר' : 'מנותק'; ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            if ($user['last_login']) {
                                $lastLogin = new DateTime($user['last_login']);
                                $now = new DateTime();
                                $diff = $now->diff($lastLogin);
                                
                                if ($diff->days == 0) {
                                    if ($diff->h == 0) {
                                        echo "לפני {$diff->i} דקות";
                                    } else {
                                        echo "לפני {$diff->h} שעות";
                                    }
                                } elseif ($diff->days == 1) {
                                    echo "אתמול";
                                } else {
                                    echo "לפני {$diff->days} ימים";
                                }
                            } else {
                                echo "לא התחבר";
                            }
                            ?>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="dashboard_type" class="dashboard-select" onchange="this.form.submit()">
                                    <option value="default" <?php echo $user['dashboard_type'] === 'default' ? 'selected' : ''; ?>>ברירת מחדל</option>
                                    <option value="admin" <?php echo $user['dashboard_type'] === 'admin' ? 'selected' : ''; ?>>מנהל</option>
                                    <option value="manager" <?php echo $user['dashboard_type'] === 'manager' ? 'selected' : ''; ?>>מנהל צוות</option>
                                    <option value="employee" <?php echo $user['dashboard_type'] === 'employee' ? 'selected' : ''; ?>>עובד</option>
                                    <option value="client" <?php echo $user['dashboard_type'] === 'client' ? 'selected' : ''; ?>>לקוח</option>
                                </select>
                                <input type="hidden" name="update_permission" value="1">
                            </form>
                        </td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" name="toggle_status" class="btn <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <?php echo $user['is_active'] ? 'השבת' : 'הפעל'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('למחוק?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger">מחק</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #6b7280;">אתה</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>