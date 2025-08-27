<?php
// dashboard/index.php - קובץ ראשי של הדשבורד
require_once '../pwa/pwa-init.php';
session_start();
require_once '../config.php';
require_once 'includes/functions.php';
require_once '../permissions/init.php';
require_once '../debugs/console-debug-single.php';

// require_once '../debugs/index.php';

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

    <!-- ב-head -->
    <!-- ?php echo getPWAHeaders(['title' => 'דשבורד']); ? -->
     <?php echo getPWAHeaders(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
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
                        <i class="fas fa-sign-out-alt"></i>
                        <span>יציאה</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content Wrapper -->
        <div class="main-content">
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






























    <!-- הוסף את זה בדשבורד שלך (dashboard/index.php) -->

        <!-- Widget התראות בפינה -->
        <div id="notificationWidget" style="
            position: fixed;
            top: 70px;
            left: 20px;
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-width: 250px;
            z-index: 1000;
            display: none;
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0; font-size: 16px;">🔔 התראות</h3>
                <span id="notifCount" style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">0</span>
            </div>
            <div id="notifList" style="max-height: 300px; overflow-y: auto;">
                <!-- התראות יטענו כאן -->
            </div>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb;">
                <a href="/notifications/manager.php" style="color: #667eea; text-decoration: none; font-size: 14px;">
                    צפה בכל ההתראות ←
                </a>
            </div>
        </div>

        <!-- כפתור התראות צף -->
        <button id="notificationBtn" onclick="toggleNotifications()" style="
            position: fixed;
            top: 15px;
            left: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        ">
            🔔
            <span id="notifBadge" style="
                position: absolute;
                top: -5px;
                right: -5px;
                background: #ef4444;
                color: white;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                font-size: 11px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                display: none;
            ">0</span>
        </button>

        <script>
            // מערכת התראות בדשבורד
            (function() {
                let widgetOpen = false;
                let notifications = [];
                
                // טעינת התראות בטעינה
                window.addEventListener('DOMContentLoaded', function() {
                    loadNotifications();
                    
                    // רענון כל 30 שניות
                    setInterval(loadNotifications, 30000);
                    
                    // הקשבה להודעות מ-Service Worker
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.addEventListener('message', event => {
                            if (event.data && event.data.type === 'NEW_NOTIFICATION') {
                                loadNotifications();
                            }
                        });
                    }
                });
                
                // טעינת התראות
                window.loadNotifications = function() {
                    notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
                    updateNotificationWidget();
                }
                
                // עדכון Widget
                function updateNotificationWidget() {
                    const unreadCount = notifications.filter(n => !n.read).length;
                    const badge = document.getElementById('notifBadge');
                    const count = document.getElementById('notifCount');
                    const list = document.getElementById('notifList');
                    
                    // עדכון מונים
                    if (unreadCount > 0) {
                        badge.style.display = 'flex';
                        badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                        count.textContent = unreadCount;
                    } else {
                        badge.style.display = 'none';
                        count.textContent = '0';
                    }
                    
                    // עדכון רשימה
                    const recentNotifs = notifications.slice(0, 5);
                    if (recentNotifs.length === 0) {
                        list.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">אין התראות חדשות</div>';
                    } else {
                        list.innerHTML = recentNotifs.map(n => `
                            <div onclick="openNotification('${n.id}')" style="
                                padding: 10px;
                                border-radius: 5px;
                                margin-bottom: 5px;
                                cursor: pointer;
                                background: ${n.read ? '#f9fafb' : '#e0f2fe'};
                                transition: all 0.2s;
                            " onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='${n.read ? '#f9fafb' : '#e0f2fe'}'">
                                <div style="font-weight: 600; font-size: 14px; color: #333;">${n.title}</div>
                                <div style="font-size: 12px; color: #666; margin-top: 2px;">${n.body}</div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">${formatTime(n.timestamp)}</div>
                            </div>
                        `).join('');
                    }
                }
                
                // פתח/סגור Widget
                window.toggleNotifications = function() {
                    const widget = document.getElementById('notificationWidget');
                    widgetOpen = !widgetOpen;
                    widget.style.display = widgetOpen ? 'block' : 'none';
                    
                    if (widgetOpen) {
                        loadNotifications();
                    }
                }
                
                // פתיחת התראה
                window.openNotification = function(id) {
                    const notification = notifications.find(n => n.id === id);
                    if (notification) {
                        notification.read = true;
                        localStorage.setItem('notifications', JSON.stringify(notifications));
                        
                        if (notification.url) {
                            window.location.href = notification.url;
                        } else {
                            window.location.href = '/notifications/manager.php';
                        }
                    }
                }
                
                // פורמט זמן
                function formatTime(timestamp) {
                    const date = new Date(timestamp);
                    const now = new Date();
                    const diff = now - date;
                    
                    if (diff < 60000) return 'עכשיו';
                    if (diff < 3600000) return `לפני ${Math.floor(diff/60000)} דק׳`;
                    if (diff < 86400000) return `לפני ${Math.floor(diff/3600000)} שעות`;
                    
                    return date.toLocaleDateString('he-IL');
                }
                
                // סגור Widget בלחיצה מחוץ
                document.addEventListener('click', function(e) {
                    const widget = document.getElementById('notificationWidget');
                    const btn = document.getElementById('notificationBtn');
                    
                    if (!widget.contains(e.target) && !btn.contains(e.target) && widgetOpen) {
                        toggleNotifications();
                    }
                });
            })();
        </script>


    <!-- סוף הוסף את זה בדשבורד שלך (dashboard/index.php) -->

    <!-- JavaScript Files -->
    <script src="assets/js/dashboard.js"></script>
    <!-- לפני </body> -->
    <!-- ?php echo getPWAScripts(['page_type' => 'dashboard']); ? -->
     <?php echo getPWAScripts([
        'banner_type' => 'native',
        'page_type' => 'dashboard',
        // 'show_after_seconds' => 5,
        // 'title' => 'הפוך אותנו לאפליקציה!',
        // 'subtitle' => 'התקנה מהירה, גישה נוחה'
    ]); ?>

    <script src="/push/listener.js"></script>
    <script>
        // התחל מאזין אם לא רץ
        if (window.PushListener && !PushListener.isRunning()) {
            PushListener.start();
        }
    </script>
    <script>
        // קוד חכם שזוכר מתי ביקש בפעם האחרונה
        setTimeout(function() {
            // בדוק אם כבר ביקשנו לאחרונה
            const lastPrompt = localStorage.getItem('last_notification_prompt');
            const now = Date.now();
            
            // אם ביקשנו בשעה האחרונה, אל תבקש שוב
            if (lastPrompt && (now - parseInt(lastPrompt)) < 3600000) {
                console.log('כבר ביקשנו הרשאה לאחרונה, מדלג...');
                return;
            }
            
            // בדוק אם כבר נדחה 3 פעמים
            const deniedCount = parseInt(localStorage.getItem('notification_denied_count') || '0');
            if (deniedCount >= 3) {
                console.log('המשתמש דחה 3 פעמים, מפסיק לבקש');
                return;
            }
            
            // רק אם ההרשאה במצב default (לא granted ולא denied)
            if (Notification.permission === "default") {
                if (confirm('לאפשר התראות מהאתר?')) {
                    Permissions.requestNotificationPermission().then(result => {
                        if (result) {
                            // הצלחה - נקה counters
                            localStorage.removeItem('notification_denied_count');
                        } else {
                            // נדחה - עדכן counter
                            localStorage.setItem('notification_denied_count', deniedCount + 1);
                        }
                    });
                } else {
                    // המשתמש ביטל - עדכן counter
                    localStorage.setItem('notification_denied_count', deniedCount + 1);
                }
                
                // שמור מתי ביקשנו
                localStorage.setItem('last_notification_prompt', now);
            }
        }, 3000); // 3 שניות
        // // בקשה אוטומטית אחרי 3 שניות
        // setTimeout(function() {
        //     if (Notification.permission === "default") {
        //         if (confirm('לאפשר התראות מהאתר?')) {
        //             Permissions.requestNotificationPermission();
        //         }
        //     }
        // }, 2000);
    </script>
    

</body>
</html>