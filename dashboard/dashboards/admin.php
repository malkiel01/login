<?php
// dashboard/dashboards/admin.php - דשבורד מנהל מערכת
session_start();

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

require_once '../../config.php';

// // בדיקה שהמשתמש הוא אכן מנהל
// $pdo = getDBConnection();
// $stmt = $pdo->prepare("SELECT dashboard_type FROM user_permissions WHERE user_id = ?");
// $stmt->execute([$_SESSION['user_id']]);
// $result = $stmt->fetch(PDO::FETCH_ASSOC);

// if (!$result || $result['dashboard_type'] !== 'admin') {
//     die('אין לך הרשאת מנהל');
// }

// // טיפול בפעולות POST
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
//     // עדכון הרשאות משתמש
//     if (isset($_POST['update_permission'])) {
//         $userId = intval($_POST['user_id']);
//         $dashboardType = $_POST['dashboard_type'];
        
//         $stmt = $pdo->prepare("
//             INSERT INTO user_permissions (user_id, dashboard_type) 
//             VALUES (?, ?)
//             ON DUPLICATE KEY UPDATE dashboard_type = VALUES(dashboard_type)
//         ");
//         $stmt->execute([$userId, $dashboardType]);
//         $message = "ההרשאות עודכנו בהצלחה!";
//         $messageType = "success";
//     }
    
//     // שינוי סטטוס משתמש (פעיל/לא פעיל)
//     if (isset($_POST['toggle_status'])) {
//         $userId = intval($_POST['user_id']);
//         $newStatus = intval($_POST['new_status']);
        
//         $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
//         $stmt->execute([$newStatus, $userId]);
        
//         $statusText = $newStatus ? "הופעל" : "הושבת";
//         $message = "המשתמש $statusText בהצלחה!";
//         $messageType = "success";
//     }
    
//     // מחיקת משתמש
//     if (isset($_POST['delete_user'])) {
//         $userId = intval($_POST['user_id']);
        
//         if ($userId != $_SESSION['user_id']) { // מניעת מחיקה עצמית
//             $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
//             $stmt->execute([$userId]);
//             $message = "המשתמש נמחק בהצלחה!";
//             $messageType = "success";
//         } else {
//             $message = "לא ניתן למחוק את עצמך!";
//             $messageType = "error";
//         }
//     }
// }

// // קבלת רשימת כל המשתמשים עם המידע המלא
// $stmt = $pdo->query("
//     SELECT 
//         u.id,
//         u.username,
//         u.name,
//         u.email,
//         u.is_active,
//         u.last_login,
//         u.created_at,
//         u.auth_type,
//         COALESCE(up.dashboard_type, 'default') as dashboard_type,
//         CASE 
//             WHEN u.last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1
//             ELSE 0
//         END as is_online
//     FROM users u
//     LEFT JOIN user_permissions up ON u.id = up.user_id
//     ORDER BY u.last_login DESC
// ");
// $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// // סטטיסטיקות
// $totalUsers = count($users);
// $activeUsers = count(array_filter($users, function($u) { return $u['is_active']; }));
// $onlineUsers = count(array_filter($users, function($u) { return $u['is_online']; }));
// $adminUsers = count(array_filter($users, function($u) { return $u['dashboard_type'] === 'admin'; }));
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
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-title h1 {
            font-size: 28px;
        }
        
        .user-info {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
        
        .stat-content h3 {
            font-size: 24px;
            color: #1f2937;
        }
        
        .stat-content p {
            color: #6b7280;
            font-size: 14px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        /* Users Table */
        .users-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            color: #1f2937;
        }
        
        .search-box {
            padding: 8px 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            width: 250px;
            font-size: 14px;
        }
        
        .users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .users-table thead {
            background: #f9fafb;
        }
        
        .users-table th {
            padding: 12px;
            text-align: right;
            font-weight: 600;
            color: #4b5563;
            border-bottom: 2px solid #e5e7eb;
            font-size: 14px;
        }
        
        .users-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        
        .users-table tbody tr:hover {
            background: #f9fafb;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-online {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-offline {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        /* Dashboard Type Select */
        .dashboard-select {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            cursor: pointer;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        /* User Avatar */
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .user-details {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Online Indicator */
        .online-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }
        
        .online-indicator.online {
            background: #10b981;
            animation: pulse 2s infinite;
        }
        
        .online-indicator.offline {
            background: #9ca3af;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .users-table {
                font-size: 12px;
            }
            
            .users-table th,
            .users-table td {
                padding: 8px 5px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
        }
        
        /* Logout Button */
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <!-- <div class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <span style="font-size: 40px;">👨‍💼</span>
                <div>
                    <h1>דשבורד מנהל מערכת</h1>
                    <p style="opacity: 0.9; font-size: 14px;">ניהול משתמשים והרשאות</p>
                </div>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'מנהל'); ?></strong>
                    <br>
                    <small>מנהל ראשי</small>
                </div>
                <a href="/auth/logout.php" class="logout-btn">יציאה</a>
            </div>
        </div>
    </div> -->
    
    <div class="container">
        <!-- Alert Messages -->
        <!-- < ?php if (isset($message)): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $messageType === 'success' ? '✅' : '⚠️'; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        < ?php endif; ?> -->
        
        <!-- Statistics -->
        <!-- <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-content">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>סה"כ משתמשים</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">✓</div>
                <div class="stat-content">
                    <h3><?php echo $activeUsers; ?></h3>
                    <p>משתמשים פעילים</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow">🟢</div>
                <div class="stat-content">
                    <h3><?php echo $onlineUsers; ?></h3>
                    <p>מחוברים כעת</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">👨‍💼</div>
                <div class="stat-content">
                    <h3><?php echo $adminUsers; ?></h3>
                    <p>מנהלי מערכת</p>
                </div>
            </div>
        </div> -->
        
        <!-- Users Table -->
        <!-- <div class="users-section">
            <div class="section-header">
                <div class="section-title">
                    <span>👥</span>
                    <h2>רשימת משתמשים</h2>
                </div>
                <input type="text" 
                       class="search-box" 
                       id="searchBox" 
                       placeholder="חיפוש משתמש..."
                       onkeyup="filterUsers()">
            </div>
            
            <div style="overflow-x: auto;">
                <table class="users-table" id="usersTable">
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
                                <div class="user-details">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></strong>
                                        <br>
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
                                    <span class="online-indicator <?php echo $user['is_online'] ? 'online' : 'offline'; ?>"></span>
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
                                <div class="action-buttons">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" 
                                                    name="toggle_status" 
                                                    class="btn <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $user['is_active'] ? 'השבת' : 'הפעל'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" 
                                              style="display: inline;" 
                                              onsubmit="return confirm('האם אתה בטוח שברצונך למחוק את המשתמש?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger">
                                                מחק
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #6b7280; font-size: 12px;">אתה</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div> -->
    </div>
    
    <!-- <script>
        // פונקציית חיפוש משתמשים
        function filterUsers() {
            const searchBox = document.getElementById('searchBox');
            const filter = searchBox.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            }
        }
        
        // רענון אוטומטי כל 30 שניות לעדכון סטטוס אונליין
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script> -->
</body>
</html>