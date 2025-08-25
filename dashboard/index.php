<?php
// dashboard/index.php - קובץ ראשי של הדשבורד
session_start();
require_once '../../config.php';
require_once 'includes/functions.php';

// בדיקת התחברות
checkAuthentication();

// קבלת נתוני משתמש
$currentUser = getCurrentUser();
$allUsers = getAllUsers();
$stats = getDashboardStats();

// הגדרת נתיבים
define('DASHBOARD_URL', '/dashboard');
define('DASHBOARD_PATH', __DIR__);
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד ניהול - <?php echo SITE_NAME ?? 'מערכת ניהול'; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    
    <!-- PWA Support -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#667eea">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-title">
                <i class="fas fa-dashboard"></i>
                <span>דשבורד ניהול</span>
            </div>
            <div class="user-info">
                <div class="user-avatar" data-user-id="<?php echo $currentUser['id']; ?>">
                    <?php if (!empty($currentUser['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($currentUser['profile_picture']); ?>" alt="Avatar">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($currentUser['name'] ?? $currentUser['username']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> יציאה
                </a>
            </div>
        </header>

        <!-- Stats Section -->
        <section class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">סה"כ משתמשים</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['active_users']; ?></div>
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
        </section>

        <!-- Main Content Grid -->
        <main class="dashboard-content">
            <!-- Session Info Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-info-circle"></i>
                    מידע על הסשן
                </div>
                <div class="session-info" id="sessionInfo">
                    <!-- יטען באמצעות JavaScript -->
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-users"></i>
                    משתמשים במערכת
                </div>
                <div class="table-container">
                    <table class="users-table" id="usersTable">
                        <!-- יטען באמצעות JavaScript -->
                    </table>
                </div>
            </div>

            <!-- Activity Log Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-history"></i>
                    פעילות אחרונה
                </div>
                <div class="activity-log" id="activityLog">
                    <!-- יטען באמצעות JavaScript -->
                </div>
            </div>

            <!-- API Endpoints Card -->
            <div class="dashboard-card">
                <div class="card-title">
                    <i class="fas fa-code"></i>
                    API Endpoints
                </div>
                <div class="api-section" id="apiEndpoints">
                    <!-- יטען באמצעות JavaScript -->
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden Data for JavaScript -->
    <script>
        window.dashboardData = {
            currentUser: <?php echo json_encode($currentUser); ?>,
            users: <?php echo json_encode($allUsers); ?>,
            stats: <?php echo json_encode($stats); ?>,
            sessionStart: <?php echo $_SESSION['login_time'] ?? time(); ?>,
            apiBase: '<?php echo DASHBOARD_URL; ?>/api'
        };
    </script>

    <!-- JavaScript Files -->
    <script src="assets/js/dashboard.js"></script>
</body>
</html>