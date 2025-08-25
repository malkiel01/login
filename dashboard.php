<?php
session_start();
require_once 'config.php';

// בדיקה אם המשתמש מחובר
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// קבלת מידע על המשתמש הנוכחי
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// קבלת כל המשתמשים במערכת
$usersStmt = $pdo->query("SELECT id, username, name, email, auth_type, is_active, last_login FROM users ORDER BY created_at DESC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// חישוב זמן הסשן
$sessionStart = $_SESSION['login_time'] ?? time();
$sessionDuration = time() - $sessionStart;

// לוג פעילות - אופציונלי, ניתן להוסיף טבלת activity_logs במסד
$activities = [
    ['time' => date('H:i:s'), 'action' => 'התחברות למערכת', 'user' => $currentUser['username']],
    ['time' => date('H:i:s', strtotime('-5 minutes')), 'action' => 'צפייה בדשבורד', 'user' => $currentUser['username']],
    ['time' => date('H:i:s', strtotime('-10 minutes')), 'action' => 'עדכון פרופיל', 'user' => 'user1'],
];

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד ניהול - <?php echo SITE_NAME ?? 'מערכת ניהול'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .dashboard-container {
            background: #f5f7fa;
            min-height: 100vh;
            animation: fadeIn 0.5s ease;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 24px;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title i {
            color: #667eea;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details {
            text-align: left;
        }

        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .user-role {
            color: #666;
            font-size: 14px;
            margin-top: 2px;
        }

        .logout-btn {
            padding: 8px 20px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #ff3838;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-container {
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Main Content */
        .dashboard-content {
            padding: 0 30px 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: #667eea;
        }

        /* Session Info */
        .session-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .info-row:hover {
            background: #e9ecef;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        /* Users Table */
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: right;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        .users-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            color: #333;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #10b981;
            color: white;
        }

        .status-inactive {
            background: #ef4444;
            color: white;
        }

        .auth-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            background: #e0e7ff;
            color: #4c51bf;
        }

        .auth-badge.google {
            background: #fef3c7;
            color: #d97706;
        }

        /* Activity Log */
        .activity-log {
            max-height: 300px;
            overflow-y: auto;
        }

        .log-entry {
            padding: 10px;
            border-right: 3px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
            transition: all 0.2s ease;
        }

        .log-entry:hover {
            background: #e9ecef;
            transform: translateX(-5px);
        }

        .log-time {
            color: #667eea;
            font-weight: 600;
            font-size: 12px;
        }

        .log-action {
            color: #333;
            margin-top: 4px;
        }

        .log-user {
            color: #666;
            font-size: 12px;
            margin-top: 2px;
        }

        /* API Info */
        .api-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }

        .api-endpoint {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-right: 4px solid #667eea;
            transition: all 0.2s ease;
        }

        .api-endpoint:hover {
            transform: translateX(-5px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .api-method {
            display: inline-block;
            padding: 4px 8px;
            background: #667eea;
            color: white;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .api-url {
            color: #555;
            font-family: 'Courier New', monospace;
            margin-top: 10px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <i class="fas fa-dashboard"></i>
                דשבורד ניהול
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($currentUser['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($currentUser['profile_picture']); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($currentUser['name'] ?? $currentUser['username']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>
                <a href="auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> יציאה
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo count($users); ?></div>
                    <div class="stat-label">סה"כ משתמשים</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">
                        <?php echo count(array_filter($users, fn($u) => $u['is_active'])); ?>
                    </div>
                    <div class="stat-label">משתמשים פעילים</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="sessionTimer">00:00</div>
                    <div class="stat-label">זמן בסשן</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo ucfirst($currentUser['auth_type'] ?? 'local'); ?></div>
                    <div class="stat-label">סוג התחברות</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-content">
            <!-- Session Info Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i>
                    מידע על הסשן
                </div>
                <div class="session-info">
                    <div class="info-row">
                        <span class="info-label">מזהה משתמש:</span>
                        <span class="info-value">#<?php echo $currentUser['id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">תחילת סשן:</span>
                        <span class="info-value"><?php echo date('H:i:s', $sessionStart); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">התחברות אחרונה:</span>
                        <span class="info-value">
                            <?php echo $currentUser['last_login'] ? date('d/m/Y H:i', strtotime($currentUser['last_login'])) : 'לא זמין'; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">סטטוס חשבון:</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $currentUser['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $currentUser['is_active'] ? 'פעיל' : 'לא פעיל'; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-users"></i>
                    משתמשים במערכת
                </div>
                <div style="overflow-x: auto;">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>שם משתמש</th>
                                <th>סוג</th>
                                <th>סטטוס</th>
                                <th>התחברות אחרונה</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($users, 0, 5) as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="auth-badge <?php echo $user['auth_type'] === 'google' ? 'google' : ''; ?>">
                                        <?php echo $user['auth_type'] === 'google' ? 'Google' : 'רגיל'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'פעיל' : 'לא פעיל'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $user['last_login'] ? date('d/m H:i', strtotime($user['last_login'])) : 'טרם התחבר'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Log Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-history"></i>
                    פעילות אחרונה
                </div>
                <div class="activity-log">
                    <?php foreach ($activities as $activity): ?>
                    <div class="log-entry">
                        <div class="log-time"><?php echo $activity['time']; ?></div>
                        <div class="log-action"><?php echo $activity['action']; ?></div>
                        <div class="log-user">משתמש: <?php echo $activity['user']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- API Info Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-code"></i>
                    API Points
                </div>
                <div class="api-section">
                    <div class="api-endpoint">
                        <strong>פרטי משתמש</strong>
                        <span class="api-method">GET</span>
                        <div class="api-url">/api/user/<?php echo $currentUser['id']; ?></div>
                    </div>
                    <div class="api-endpoint">
                        <strong>עדכון פרופיל</strong>
                        <span class="api-method">PUT</span>
                        <div class="api-url">/api/user/<?php echo $currentUser['id']; ?>/update</div>
                    </div>
                    <div class="api-endpoint">
                        <strong>רשימת משתמשים</strong>
                        <span class="api-method">GET</span>
                        <div class="api-url">/api/users</div>
                    </div>
                    <div class="api-endpoint">
                        <strong>סטטיסטיקות</strong>
                        <span class="api-method">GET</span>
                        <div class="api-url">/api/stats</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // עדכון טיימר הסשן
        let sessionSeconds = <?php echo $sessionDuration; ?>;
        
        function updateSessionTimer() {
            sessionSeconds++;
            const hours = Math.floor(sessionSeconds / 3600);
            const minutes = Math.floor((sessionSeconds % 3600) / 60);
            const seconds = sessionSeconds % 60;
            
            const display = 
                (hours > 0 ? hours.toString().padStart(2, '0') + ':' : '') +
                minutes.toString().padStart(2, '0') + ':' +
                seconds.toString().padStart(2, '0');
            
            document.getElementById('sessionTimer').textContent = display;
        }
        
        // עדכון כל שניה
        setInterval(updateSessionTimer, 1000);
        
        // הפעלה ראשונית
        updateSessionTimer();
        
        // רענון אוטומטי כל 5 דקות (אופציונלי)
        setTimeout(() => {
            if (confirm('האם לרענן את הדף לקבלת נתונים עדכניים?')) {
                location.reload();
            }
        }, 300000); // 5 דקות
    </script>
</body>
</html>