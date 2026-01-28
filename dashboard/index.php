<?php
// dashboard/index.php - נקודת כניסה ראשית

// שימוש ב-middleware לבדיקת auth (תומך גם ב-token)
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת התחברות (כולל שחזור מ-token עבור PWA/iOS)
requireAuth();

// טעינת קובץ הקונפיג של הפרויקט
require_once '../config.php';
$pdo = getDBConnection();

// עדכן last_login פעם אחת כאן
$stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);

// קבלת סוג הדשבורד (הפונקציה מוגדרת ב-middleware.php)
$userId = $_SESSION['user_id'];
$dashboardType = getUserDashboardType($userId);

// טען config של הדשבורד
require_once __DIR__ . '/config.php';

// בדוק אם יש redirect מוגדר בקונפיג
if (defined('DASHBOARD_TYPES') && isset(DASHBOARD_TYPES[$dashboardType]['redirect'])) {
    $redirect = DASHBOARD_TYPES[$dashboardType]['redirect'];
    header('Location: ' . $redirect);
    exit;
}

// בדיקה איזה קובץ דשבורד להציג
$dashboardFile = __DIR__ . '/dashboards/' . $dashboardType . '.php';

// אם הקובץ לא קיים, הצג דשבורד זמני
if (!file_exists($dashboardFile)) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="he">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>דשבורד</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                direction: rtl;
            }
            
            .dashboard-box {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 600px;
                width: 90%;
            }
            
            .dashboard-icon {
                font-size: 60px;
                margin-bottom: 20px;
            }
            
            h1 {
                color: #333;
                margin-bottom: 10px;
            }
            
            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            
            .info-box {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                text-align: right;
            }
            
            .info-box strong {
                color: #667eea;
            }
            
            .buttons {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin-top: 30px;
            }
            
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 10px;
                transition: all 0.3s;
            }
            
            .btn:hover {
                background: #5a67d8;
                transform: translateY(-2px);
            }
            
            .btn-secondary {
                background: #6b7280;
            }
            
            .btn-secondary:hover {
                background: #4b5563;
            }
            
            .dashboard-types {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 10px;
                margin: 20px 0;
            }
            
            .type-badge {
                padding: 10px;
                background: #f3f4f6;
                border-radius: 8px;
                font-size: 14px;
            }
            
            .type-badge.current {
                background: #667eea;
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="dashboard-box">
            <?php
            // הגדרת אייקונים לסוגי דשבורד
            $dashboardIcons = [
                'admin' => '👨‍💼',
                'manager' => '📈',
                'employee' => '💼',
                'client' => '🏢',
                'default' => '🏠',
                'cemetery_manager' => '🪦'
            ];
            
            $icon = $dashboardIcons[$dashboardType] ?? '📊';
            ?>
            
            <div class="dashboard-icon"><?php echo $icon; ?></div>
            <h1>דשבורד <?php echo ucfirst($dashboardType); ?></h1>
            
            <p>
                <?php if ($dashboardType == 'default'): ?>
                    זהו דשבורד ברירת המחדל. פנה למנהל המערכת לקבלת הרשאות נוספות.
                <?php else: ?>
                    סוג הדשבורד שלך: <strong><?php echo $dashboardType; ?></strong>
                <?php endif; ?>
            </p>
            
            <div class="info-box">
                <p><strong>משתמש:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'משתמש ' . $_SESSION['user_id']); ?></p>
                <p><strong>מספר משתמש:</strong> <?php echo $_SESSION['user_id']; ?></p>
                <p><strong>סוג דשבורד:</strong> <?php echo $dashboardType; ?></p>
            </div>
            
            <div class="dashboard-types">
                <?php 
                $types = ['default', 'admin', 'manager', 'employee', 'client'];
                foreach ($types as $type): 
                ?>
                    <div class="type-badge <?php echo $type == $dashboardType ? 'current' : ''; ?>">
                        <?php echo $dashboardIcons[$type] ?? '📊'; ?> <?php echo $type; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p style="background: #fef3c7; padding: 15px; border-radius: 8px; color: #92400e;">
                💡 <strong>שים לב:</strong> כדי ליצור דשבורד מותאם אישית, צור קובץ 
                <code style="background: #fff; padding: 2px 6px; border-radius: 4px;">
                    dashboards/<?php echo $dashboardType; ?>.php
                </code>
            </p>
            
            <div class="buttons">
                <a href="#" onclick="performLogout(); return false;" class="btn btn-secondary">יציאה</a>
                <?php if ($dashboardType == 'admin'): ?>
                    <a href="/dashboard/permissions/manage.php" class="btn">ניהול הרשאות</a>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // סקריפטים לאימות עמיד (PWA/iOS)
        echo getTokenInitScript();
        echo getLogoutScript();
        ?>

        <!-- Approval Modal for notifications -->
        <script src="/js/biometric-auth.js"></script>
        <script src="/js/approval-modal.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// אם הקובץ קיים, טען אותו
require_once $dashboardFile;