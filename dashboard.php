<?php
/**
 * דף דשבורד מוגן
 * dashboard.php
 */

// בדיקת הרשאות
require_once 'includes/auth_check.php';
require_once 'config.php';

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// טיפול ביצירת קבוצה חדשה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // בדיקת CSRF כבר נעשתה ב-auth_check.php
    
    switch ($_POST['action']) {
        case 'createGroup':
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO purchase_groups (name, description, owner_id) VALUES (?, ?, ?)");
                $result = $stmt->execute([$_POST['name'], $_POST['description'], $user_id]);
                
                if ($result) {
                    $group_id = $pdo->lastInsertId();
                    
                    if ($_POST['participation_type'] == 'percentage' && $_POST['participation_value'] > 100) {
                        throw new Exception('לא ניתן להגדיר יותר מ-100% השתתפות');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO group_members (group_id, user_id, nickname, email, participation_type, participation_value) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $group_id, 
                        $user_id, 
                        $_SESSION['name'], 
                        $_SESSION['email'],
                        $_POST['participation_type'],
                        $_POST['participation_value']
                    ]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'group_id' => $group_id]);
                } else {
                    throw new Exception('שגיאה ביצירת הקבוצה');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'leaveGroup':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM group_purchases gp
                JOIN group_members gm ON gp.member_id = gm.id
                WHERE gm.group_id = ? AND gm.user_id = ?
            ");
            $stmt->execute([$_POST['group_id'], $user_id]);
            
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'לא ניתן לעזוב קבוצה עם קניות פעילות']);
            } else {
                $stmt = $pdo->prepare("UPDATE group_members SET is_active = 0 WHERE group_id = ? AND user_id = ?");
                $result = $stmt->execute([$_POST['group_id'], $user_id]);
                echo json_encode(['success' => $result]);
            }
            exit;

        case 'respondInvitation':
            $invitation_id = $_POST['invitation_id'];
            $response = $_POST['response'];
            
            try {
                $pdo->beginTransaction();
                
                // קבל את פרטי ההזמנה
                $stmt = $pdo->prepare("
                    SELECT * FROM group_invitations 
                    WHERE id = ? AND email = ? AND status = 'pending'
                ");
                $stmt->execute([$invitation_id, $_SESSION['email']]);
                $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$invitation) {
                    throw new Exception('הזמנה לא נמצאה או כבר טופלה');
                }
                
                if ($response === 'accept') {
                    // בדיקה אם המשתמש כבר היה חבר בעבר
                    $stmt = $pdo->prepare("
                        SELECT id FROM group_members 
                        WHERE group_id = ? AND user_id = ?
                    ");
                    $stmt->execute([$invitation['group_id'], $user_id]);
                    $existingMember = $stmt->fetch();
                    
                    if ($existingMember) {
                        // עדכון חבר קיים
                        $stmt = $pdo->prepare("
                            UPDATE group_members 
                            SET is_active = 1,
                                nickname = ?,
                                email = ?,
                                participation_type = ?,
                                participation_value = ?,
                                joined_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $invitation['nickname'],
                            $_SESSION['email'],
                            $invitation['participation_type'],
                            $invitation['participation_value'],
                            $existingMember['id']
                        ]);
                    } else {
                        // הוספת חבר חדש
                        $stmt = $pdo->prepare("
                            INSERT INTO group_members 
                            (group_id, user_id, nickname, email, participation_type, participation_value) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $invitation['group_id'],
                            $user_id,
                            $invitation['nickname'],
                            $_SESSION['email'],
                            $invitation['participation_type'],
                            $invitation['participation_value']
                        ]);
                    }
                }
                
                // עדכן סטטוס הזמנה
                $stmt = $pdo->prepare("
                    UPDATE group_invitations 
                    SET status = ?, responded_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $response === 'accept' ? 'accepted' : 'rejected', 
                    $invitation_id
                ]);
                
                $pdo->commit();
                echo json_encode(['success' => true]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// שליפת קבוצות המשתמש
$stmt = $pdo->prepare("
    SELECT 
        pg.*,
        gm.nickname,
        gm.participation_type,
        gm.participation_value,
        u.name as owner_name,
        (pg.owner_id = ?) as is_owner,
        gs.member_count,
        gs.purchase_count,
        gs.total_amount
    FROM purchase_groups pg
    JOIN group_members gm ON pg.id = gm.group_id
    JOIN users u ON pg.owner_id = u.id
    LEFT JOIN group_statistics gs ON pg.id = gs.group_id
    WHERE gm.user_id = ? AND gm.is_active = 1 AND pg.is_active = 1
    ORDER BY pg.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// שליפת הזמנות ממתינות
$stmt = $pdo->prepare("
    SELECT gi.*, pg.name as group_name
    FROM group_invitations gi
    JOIN purchase_groups pg ON gi.group_id = pg.id
    WHERE gi.email = ? AND gi.status = 'pending'
    ORDER BY gi.created_at DESC
");
$stmt->execute([$_SESSION['email']]);
$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>קבוצות הרכישה שלי - <?php echo SITE_NAME; ?></title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/family/css/dashboard.css">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/family/manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <!-- Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/family/images/icons/ios/32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/family/images/icons/ios/16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/family/images/icons/ios/180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/family/images/icons/ios/152.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/family/images/icons/ios/120.png">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-users"></i>
                קבוצות הרכישה שלי
            </a>
            <div class="navbar-user">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?php echo $_SESSION['profile_picture']; ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo mb_substr($_SESSION['name'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                </div>
                <a href="auth/logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    התנתק
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- הזמנות ממתינות -->
        <?php if (count($invitations) > 0): ?>
        <div class="invitations-section">
            <h2><i class="fas fa-envelope"></i> הזמנות ממתינות</h2>
            <div class="invitations-grid">
                <?php foreach ($invitations as $invitation): ?>
                <div class="invitation-card">
                    <h3><?php echo htmlspecialchars($invitation['group_name']); ?></h3>
                    <p>כינוי: <?php echo htmlspecialchars($invitation['nickname']); ?></p>
                    <p>השתתפות: 
                        <?php if ($invitation['participation_type'] == 'percentage'): ?>
                            <?php echo $invitation['participation_value']; ?>%
                        <?php else: ?>
                            ₪<?php echo number_format($invitation['participation_value'], 2); ?>
                        <?php endif; ?>
                    </p>
                    <div class="invitation-actions">
                        <button class="btn-accept" onclick="respondInvitation(<?php echo $invitation['id']; ?>, 'accept')">
                            <i class="fas fa-check"></i> קבל
                        </button>
                        <button class="btn-reject" onclick="respondInvitation(<?php echo $invitation['id']; ?>, 'reject')">
                            <i class="fas fa-times"></i> דחה
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- כפתור יצירת קבוצה חדשה -->
        <div class="create-group-section">
            <button class="btn-create-group" onclick="showCreateGroupModal()">
                <i class="fas fa-plus-circle"></i>
                צור קבוצת רכישה חדשה
            </button>
        </div>

        <!-- רשימת קבוצות -->
        <div class="groups-section">
            <h2><i class="fas fa-layer-group"></i> הקבוצות שלי</h2>
            
            <?php if (count($groups) == 0): ?>
            <div class="no-groups">
                <i class="fas fa-users-slash"></i>
                <p>אין לך קבוצות רכישה פעילות</p>
                <p>צור קבוצה חדשה או המתן להזמנה</p>
            </div>
            <?php else: ?>
            <div class="groups-grid">
                <?php foreach ($groups as $group): ?>
                <div class="group-card <?php echo $group['is_owner'] ? 'owner' : ''; ?>">
                    <?php if ($group['is_owner']): ?>
                    <div class="owner-badge">
                        <i class="fas fa-crown"></i> מנהל
                    </div>
                    <?php endif; ?>
                    
                    <div class="group-header">
                        <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                        <?php if ($group['description']): ?>
                        <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="group-stats">
                        <div class="stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $group['member_count'] ?? 0; ?> חברים</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-shopping-bag"></i>
                            <span><?php echo $group['purchase_count'] ?? 0; ?> קניות</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-shekel-sign"></i>
                            <span>₪<?php echo number_format($group['total_amount'] ?? 0, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="group-info">
                        <p><i class="fas fa-user"></i> מנהל: <?php echo htmlspecialchars($group['owner_name']); ?></p>
                        <p><i class="fas fa-percentage"></i> החלק שלך: 
                            <?php if ($group['participation_type'] == 'percentage'): ?>
                                <?php echo $group['participation_value']; ?>%
                            <?php else: ?>
                                ₪<?php echo number_format($group['participation_value'], 2); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="group-actions">
                        <a href="group.php?id=<?php echo $group['id']; ?>" class="btn-enter">
                            <i class="fas fa-sign-in-alt"></i> כניסה לקבוצה
                        </a>
                        <?php if (!$group['is_owner']): ?>
                        <button class="btn-leave" onclick="leaveGroup(<?php echo $group['id']; ?>)">
                            <i class="fas fa-sign-out-alt"></i> עזוב
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal ליצירת קבוצה -->
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>יצירת קבוצת רכישה חדשה</h2>
                <span class="close" onclick="closeCreateGroupModal()">&times;</span>
            </div>
            <form id="createGroupForm">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="groupName">שם הקבוצה:</label>
                    <input type="text" id="groupName" required>
                </div>
                <div class="form-group">
                    <label for="groupDescription">תיאור (אופציונלי):</label>
                    <textarea id="groupDescription" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>סוג השתתפות שלך:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="ownerParticipationType" value="percentage" checked onchange="toggleOwnerParticipationType()">
                            אחוז
                        </label>
                        <label>
                            <input type="radio" name="ownerParticipationType" value="fixed" onchange="toggleOwnerParticipationType()">
                            סכום קבוע
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="ownerParticipationValue">ערך השתתפות שלך:</label>
                    <div class="input-with-suffix">
                        <input type="number" id="ownerParticipationValue" step="0.01" required>
                        <span id="ownerValueSuffix">%</span>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> צור קבוצה
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeCreateGroupModal()">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript מחולק לקבצים -->
    <script>
        // משתנה גלובלי לCSRF
        window.APP_CONFIG = {
            csrfToken: '<?php echo $_SESSION['csrf_token']; ?>',
            userId: <?php echo $user_id; ?>,
            basePath: '/family/'
        };
    </script>
    
    <!-- טען קבצי JavaScript בסדר הנכון -->
     <!-- pwa-notifications-compact -->
    <script src="js/pwa-notifications-compact.js"></script>
    <!-- <script src="js/pwa-notifications.js"></script> -->
    <script src="js/pwa-installer.js"></script>
    <script src="js/dashboard.js"></script>

    <script>
        // בדיקת התראות חדשות כל 30 שניות
        async function checkForNotifications() {
            try {
                const response = await fetch('/api/trigger-notification.php?action=check', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({})
                });
                
                const data = await response.json();
                
                if (data.success && data.notifications && data.notifications.length > 0) {
                    // יש התראות חדשות!
                    const registration = await navigator.serviceWorker.ready;
                    
                    for (const notif of data.notifications) {
                        await registration.showNotification(notif.title, {
                            body: notif.body,
                            icon: notif.icon || '/images/icons/android/android-launchericon-192-192.png',
                            badge: '/images/icons/android/android-launchericon-96-96.png',
                            tag: 'notification-' + notif.id,
                            data: {
                                url: notif.url,
                                id: notif.id
                            },
                            vibrate: [200, 100, 200],
                            dir: 'rtl',
                            lang: 'he'
                        });
                    }
                }
            } catch (error) {
                console.error('Error checking notifications:', error);
            }
        }

        // בדוק התראות בטעינת הדף
        window.addEventListener('load', () => {
            // בדוק מיד
            checkForNotifications();
            
            // בדוק כל 30 שניות
            setInterval(checkForNotifications, 30000);
        });

        // בדוק גם כשהטאב חוזר לפוקוס
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                checkForNotifications();
            }
        });
    </script>

    <script>
        // בדיקת התראות חדשות כל 30 שניות
        function checkForNotifications() {
            fetch('/family/api/get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications && data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            showNotification(notification);
                        });
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }

        // הצגת התראה
        function showNotification(notification) {
            // אם יש תמיכה בהתראות דפדפן
            if ("Notification" in window && Notification.permission === "granted") {
                new Notification(notification.title, {
                    body: notification.body,
                    icon: notification.icon || '/family/images/icons/android/android-launchericon-192-192.png',
                    badge: notification.badge,
                    tag: 'notification-' + notification.invitation_id
                });
            }
            
            // הצג גם באנר בדף
            const banner = document.createElement('div');
            banner.style.cssText = `
                position: fixed;
                top: 80px;
                left: 20px;
                background: white;
                border-radius: 10px;
                padding: 15px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                border-right: 4px solid #667eea;
                max-width: 350px;
                z-index: 9999;
                animation: slideIn 0.5s;
                direction: rtl;
            `;
            
            banner.innerHTML = `
                <div style="display: flex; align-items: start; gap: 10px;">
                    <div style="font-size: 24px;">✉️</div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 5px 0; color: #333;">${notification.title}</h4>
                        <p style="margin: 0; color: #666; font-size: 14px;">${notification.body}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="background: none; border: none; font-size: 20px; color: #999; cursor: pointer;">×</button>
                </div>
            `;
            
            document.body.appendChild(banner);
            
            // הסר אחרי 10 שניות
            setTimeout(() => banner.remove(), 10000);
        }

        // בקש הרשאה להתראות
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }

        // בדוק התראות כל 30 שניות
        setInterval(checkForNotifications, 3000);

        // בדוק מיד בטעינה
        window.addEventListener('load', () => {
            setTimeout(checkForNotifications, 200);
        });

        // CSS לאנימציה
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(-100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    </script>

    <!-- הוסף בתחתית הדף, לפני </body> -->
    <div id="debug-panel" style="position: fixed; bottom: 10px; right: 10px; background: white; 
        border: 2px solid #667eea; border-radius: 10px; padding: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); 
        z-index: 9999; direction: rtl; font-size: 12px; max-width: 300px;">
        
        <h4 style="margin: 0 0 10px 0; color: #667eea;">🔧 פאנל דיבאג</h4>
        
        <button onclick="testNotifications()" style="background: #667eea; color: white; border: none; 
                padding: 8px 15px; border-radius: 5px; margin: 5px; width: 100%;">
            בדוק הרשאות התראות
        </button>
        
        <button onclick="createTestNotification()" style="background: #28a745; color: white; border: none; 
                padding: 8px 15px; border-radius: 5px; margin: 5px; width: 100%;">
            צור התראת בדיקה
        </button>
        
        <button onclick="checkPendingNotifications()" style="background: #ffc107; color: white; border: none; 
                padding: 8px 15px; border-radius: 5px; margin: 5px; width: 100%;">
            בדוק התראות ממתינות
        </button>
        
        <div id="debug-output" style="background: #f8f9fa; border-radius: 5px; padding: 10px; 
            margin-top: 10px; font-family: monospace; font-size: 11px; max-height: 200px; overflow-y: auto;">
            מוכן לבדיקה...
        </div>
        
        <button onclick="this.parentElement.style.display='none'" 
                style="background: #dc3545; color: white; border: none; 
                padding: 5px 10px; border-radius: 5px; margin-top: 10px; font-size: 10px;">
            סגור
        </button>
    </div>

    <script>
        function debugLog(message, data = null) {
            const output = document.getElementById('debug-output');
            const time = new Date().toLocaleTimeString('he-IL');
            let html = `<div style="border-bottom: 1px solid #dee2e6; padding: 5px 0;">`;
            html += `<strong>[${time}]</strong> ${message}`;
            if (data) {
                html += `<pre style="margin: 5px 0; font-size: 10px;">${JSON.stringify(data, null, 2)}</pre>`;
            }
            html += `</div>`;
            output.innerHTML = html + output.innerHTML;
        }

        function testNotifications() {
            debugLog('בודק הרשאות...');
            
            // בדוק תמיכה
            if (!('Notification' in window)) {
                debugLog('❌ הדפדפן לא תומך בהתראות');
                return;
            }
            
            debugLog('✅ יש תמיכה בהתראות');
            debugLog('הרשאה נוכחית: ' + Notification.permission);
            
            if (Notification.permission === 'default') {
                debugLog('מבקש הרשאה...');
                Notification.requestPermission().then(permission => {
                    debugLog('תשובה: ' + permission);
                    if (permission === 'granted') {
                        debugLog('✅ הרשאה ניתנה!');
                        // נסה התראה
                        new Notification('בדיקה', {
                            body: 'ההתראות עובדות! 🎉',
                            icon: '/family/images/icons/android/android-launchericon-192-192.png'
                        });
                    }
                });
            } else if (Notification.permission === 'granted') {
                debugLog('✅ כבר יש הרשאה');
                try {
                    new Notification('בדיקה', {
                        body: 'ההתראות עובדות! 🎉',
                        icon: '/family/images/icons/android/android-launchericon-192-192.png'
                    });
                    debugLog('✅ התראה נשלחה');
                } catch (e) {
                    debugLog('❌ שגיאה: ' + e.message);
                }
            } else {
                debugLog('❌ הרשאה נדחתה');
            }
        }

        function createTestNotification() {
            debugLog('יוצר התראת בדיקה...');
            
            const testNotif = {
                title: 'התראת בדיקה 🔔',
                body: 'זו התראה שנוצרה מהפלאפון - ' + new Date().toLocaleTimeString('he-IL'),
                icon: '/family/images/icons/android/android-launchericon-192-192.png'
            };
            
            // הצג באנר
            showMobileNotification(testNotif);
            debugLog('✅ באנר הוצג');
            
            // נסה גם התראת מערכת
            if (Notification.permission === 'granted') {
                try {
                    new Notification(testNotif.title, testNotif);
                    debugLog('✅ התראת מערכת נשלחה');
                } catch (e) {
                    debugLog('⚠️ התראת מערכת נכשלה: ' + e.message);
                }
            }
        }

        function checkPendingNotifications() {
            debugLog('בודק התראות ממתינות...');
            
            fetch('/family/api/get-notifications.php')
                .then(r => r.json())
                .then(data => {
                    debugLog('תגובה מהשרת:', data);
                    
                    if (data.success && data.count > 0) {
                        debugLog(`✅ נמצאו ${data.count} התראות`);
                        data.notifications.forEach((n, i) => {
                            debugLog(`התראה ${i+1}: ${n.title}`);
                            showMobileNotification(n);
                        });
                    } else {
                        debugLog('📭 אין התראות ממתינות');
                    }
                })
                .catch(error => {
                    debugLog('❌ שגיאה: ' + error.message);
                });
        }

        // בדיקה אוטומטית בטעינה
        window.addEventListener('load', () => {
            setTimeout(() => {
                debugLog('מערכת דיבאג פעילה');
                debugLog('דפדפן: ' + navigator.userAgent.substring(0, 50) + '...');
                debugLog('PWA: ' + (window.matchMedia('(display-mode: standalone)').matches ? 'כן' : 'לא'));
            }, 1000);
        });
    </script>
</body>
</html>