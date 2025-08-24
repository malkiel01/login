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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/family/css/dashboard.css">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/family/manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <!-- עדכן את הלינקים לאייקונים החדשים -->
    <link rel="icon" type="image/png" sizes="32x32" href="/family/images/icons/ios/32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/family/images/icons/ios/16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/family/images/icons/ios/180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/family/images/icons/ios/152.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/family/images/icons/ios/120.png">

    <!-- Service Worker Registration FIXED -->
    <script>
        // if ('serviceWorker' in navigator) {
        //     window.addEventListener('load', () => {
        //         navigator.serviceWorker.register('/family/service-worker.js', {scope: '/family/'})
        //             .then(reg => {
        //                 console.log('Service Worker registered:', reg);
                        
        //                 // בדוק אם יש עדכון
        //                 reg.addEventListener('updatefound', () => {
        //                     console.log('Service Worker update found!');
        //                 });
        //             })
        //             .catch(err => console.error('Service Worker registration failed:', err));
        //     });
            
        //     // האזן ל-install prompt
        //     let deferredPrompt;
        //     let installButton = null;
            
        //     window.addEventListener('beforeinstallprompt', (e) => {
        //         console.log('beforeinstallprompt event fired!');
        //         e.preventDefault();
        //         deferredPrompt = e;
                
        //         // הצג כפתור התקנה אם קיים
        //         installButton = document.getElementById('install-pwa-btn');
        //         if (!installButton) {
        //             // צור כפתור התקנה דינמי
        //             installButton = document.createElement('button');
        //             installButton.id = 'install-pwa-btn';
        //             installButton.innerHTML = '📱 התקן אפליקציה';
        //             installButton.style.cssText = `
        //                 position: fixed;
        //                 bottom: 20px;
        //                 right: 20px;
        //                 background: linear-gradient(135deg, #667eea, #764ba2);
        //                 color: white;
        //                 border: none;
        //                 padding: 15px 25px;
        //                 border-radius: 50px;
        //                 font-size: 16px;
        //                 font-weight: bold;
        //                 cursor: pointer;
        //                 box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        //                 z-index: 9999;
        //                 display: flex;
        //                 align-items: center;
        //                 gap: 8px;
        //             `;
        //             document.body.appendChild(installButton);
        //         }
                
        //         installButton.style.display = 'flex';
                
        //         installButton.onclick = async () => {
        //             if (deferredPrompt) {
        //                 deferredPrompt.prompt();
        //                 const result = await deferredPrompt.userChoice;
        //                 console.log('User response to install prompt:', result.outcome);
        //                 if (result.outcome === 'accepted') {
        //                     console.log('User accepted the install prompt');
        //                     installButton.style.display = 'none';
        //                 }
        //                 deferredPrompt = null;
        //             }
        //         };
        //     });
            
        //     // בדוק אם האפליקציה כבר מותקנת
        //     window.addEventListener('appinstalled', () => {
        //         console.log('PWA was installed');
        //         if (installButton) {
        //             installButton.style.display = 'none';
        //         }
        //     });
            
        //     // בדוק אם רץ כ-PWA
        //     if (window.matchMedia('(display-mode: standalone)').matches) {
        //         console.log('Running as PWA');
        //     } else {
        //         console.log('Running in browser');
        //     }
        // }
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/family/service-worker.js', {scope: '/family/'})
                    .then(reg => {
                        console.log('Service Worker registered:', reg);
                    })
                    .catch(err => console.error('Service Worker registration failed:', err));
            });
            
            // מערכת התראת התקנה משופרת
            let deferredPrompt;
            let installBanner = null;
            
            // סגנונות להתראה
            const bannerStyles = `
                <style>
                    .pwa-install-banner {
                        position: fixed;
                        top: -100px;
                        left: 0;
                        right: 0;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 15px 20px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                        z-index: 10000;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        transition: top 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    }
                    
                    .pwa-install-banner.show {
                        top: 0;
                    }
                    
                    .pwa-install-content {
                        display: flex;
                        align-items: center;
                        gap: 15px;
                        flex: 1;
                    }
                    
                    .pwa-install-icon {
                        width: 50px;
                        height: 50px;
                        background: white;
                        border-radius: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 28px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    
                    .pwa-install-text {
                        flex: 1;
                    }
                    
                    .pwa-install-title {
                        font-size: 18px;
                        font-weight: bold;
                        margin-bottom: 4px;
                        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
                    }
                    
                    .pwa-install-subtitle {
                        font-size: 14px;
                        opacity: 0.95;
                    }
                    
                    .pwa-install-actions {
                        display: flex;
                        gap: 10px;
                        align-items: center;
                    }
                    
                    .pwa-install-btn {
                        background: white;
                        color: #667eea;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 25px;
                        font-size: 15px;
                        font-weight: bold;
                        cursor: pointer;
                        transition: all 0.3s;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        display: flex;
                        align-items: center;
                        gap: 6px;
                    }
                    
                    .pwa-install-btn:hover {
                        transform: scale(1.05);
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    }
                    
                    .pwa-close-btn {
                        background: transparent;
                        color: white;
                        border: 2px solid rgba(255,255,255,0.3);
                        padding: 8px 16px;
                        border-radius: 20px;
                        font-size: 14px;
                        cursor: pointer;
                        transition: all 0.3s;
                    }
                    
                    .pwa-close-btn:hover {
                        background: rgba(255,255,255,0.1);
                        border-color: rgba(255,255,255,0.5);
                    }
                    
                    @media (max-width: 768px) {
                        .pwa-install-banner {
                            padding: 12px 15px;
                        }
                        
                        .pwa-install-icon {
                            width: 40px;
                            height: 40px;
                            font-size: 24px;
                        }
                        
                        .pwa-install-title {
                            font-size: 16px;
                        }
                        
                        .pwa-install-subtitle {
                            font-size: 13px;
                        }
                        
                        .pwa-install-btn {
                            padding: 8px 16px;
                            font-size: 14px;
                        }
                        
                        .pwa-close-btn {
                            padding: 6px 12px;
                            font-size: 13px;
                        }
                    }
                    
                    @keyframes slideDown {
                        from {
                            top: -100px;
                            opacity: 0;
                        }
                        to {
                            top: 0;
                            opacity: 1;
                        }
                    }
                    
                    @keyframes pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.05); }
                        100% { transform: scale(1); }
                    }
                    
                    .pwa-install-btn {
                        animation: pulse 2s infinite;
                    }
                </style>
            `;
            
            // הוסף את הסגנונות לדף
            document.head.insertAdjacentHTML('beforeend', bannerStyles);
            
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('beforeinstallprompt event fired!');
                e.preventDefault();
                deferredPrompt = e;
                
                // בדוק אם המשתמש כבר דחה את ההתקנה בעבר
                const dismissed = localStorage.getItem('pwa-install-dismissed');
                const dismissedTime = localStorage.getItem('pwa-install-dismissed-time');
                
                // אם דחה, הצג שוב רק אחרי 7 ימים
                if (dismissed && dismissedTime) {
                    const daysPassed = (Date.now() - parseInt(dismissedTime)) / (1000 * 60 * 60 * 24);
                    if (daysPassed < 7) {
                        return;
                    }
                }
                
                // צור את ההתראה
                installBanner = document.createElement('div');
                installBanner.className = 'pwa-install-banner';
                installBanner.innerHTML = `
                    <div class="pwa-install-content">
                        <div class="pwa-install-icon">📱</div>
                        <div class="pwa-install-text">
                            <div class="pwa-install-title">התקן את האפליקציה</div>
                            <div class="pwa-install-subtitle">גישה מהירה ונוחה יותר לניהול הקניות שלך</div>
                        </div>
                    </div>
                    <div class="pwa-install-actions">
                        <button class="pwa-install-btn" id="install-app-btn">
                            <span>התקן עכשיו</span>
                            <span>⚡</span>
                        </button>
                        <button class="pwa-close-btn" id="dismiss-install-btn">
                            אולי מאוחר יותר
                        </button>
                    </div>
                `;
                
                document.body.appendChild(installBanner);
                
                // הצג את ההתראה עם אנימציה
                setTimeout(() => {
                    installBanner.classList.add('show');
                }, 1000); // המתן שנייה לפני הצגה
                
                // כפתור התקנה
                document.getElementById('install-app-btn').onclick = async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const result = await deferredPrompt.userChoice;
                        console.log('User response to install prompt:', result.outcome);
                        
                        if (result.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                            // הסר את ההתראה
                            installBanner.classList.remove('show');
                            setTimeout(() => {
                                installBanner.remove();
                            }, 500);
                            
                            // הצג הודעת הצלחה
                            showSuccessMessage();
                        } else {
                            // המשתמש דחה - שמור בלוקל סטורג'
                            localStorage.setItem('pwa-install-dismissed', 'true');
                            localStorage.setItem('pwa-install-dismissed-time', Date.now().toString());
                        }
                        
                        deferredPrompt = null;
                    }
                };
                
                // כפתור ביטול
                document.getElementById('dismiss-install-btn').onclick = () => {
                    installBanner.classList.remove('show');
                    setTimeout(() => {
                        installBanner.remove();
                    }, 500);
                    
                    // שמור שהמשתמש דחה
                    localStorage.setItem('pwa-install-dismissed', 'true');
                    localStorage.setItem('pwa-install-dismissed-time', Date.now().toString());
                };
                
                // הסתר אוטומטית אחרי 30 שניות
                setTimeout(() => {
                    if (installBanner && installBanner.classList.contains('show')) {
                        installBanner.classList.remove('show');
                        setTimeout(() => {
                            if (installBanner && installBanner.parentNode) {
                                installBanner.remove();
                            }
                        }, 500);
                    }
                }, 30000);
            });
            
            // הודעת הצלחה
            function showSuccessMessage() {
                const successBanner = document.createElement('div');
                successBanner.className = 'pwa-install-banner show';
                successBanner.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                successBanner.innerHTML = `
                    <div class="pwa-install-content">
                        <div class="pwa-install-icon">✅</div>
                        <div class="pwa-install-text">
                            <div class="pwa-install-title">האפליקציה הותקנה בהצלחה!</div>
                            <div class="pwa-install-subtitle">תוכל למצוא אותה במסך הבית שלך</div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(successBanner);
                
                setTimeout(() => {
                    successBanner.classList.remove('show');
                    setTimeout(() => {
                        successBanner.remove();
                    }, 500);
                }, 5000);
            }
            
            // בדוק אם האפליקציה כבר מותקנת
            window.addEventListener('appinstalled', () => {
                console.log('PWA was installed');
                if (installBanner) {
                    installBanner.classList.remove('show');
                    setTimeout(() => {
                        installBanner.remove();
                    }, 500);
                }
            });
            
            // בדוק אם רץ כ-PWA
            if (window.matchMedia('(display-mode: standalone)').matches) {
                console.log('Running as PWA');
            } else {
                console.log('Running in browser');
            }
        }
    </script>
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
            <!-- כפתור התראות -->
            <button id="enable-notifications" class="btn btn-sm btn-outline-light me-2" 
                    onclick="enableNotifications()" style="border-radius: 20px; padding: 5px 15px;">
                <i class="fas fa-bell"></i> התראות
            </button>
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

    <script>
        // הוסף CSRF token לכל בקשות AJAX
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        
        function showCreateGroupModal() {
            document.getElementById('createGroupModal').style.display = 'block';
        }
        
        function closeCreateGroupModal() {
            document.getElementById('createGroupModal').style.display = 'none';
            document.getElementById('createGroupForm').reset();
        }
        
        function toggleOwnerParticipationType() {
            const type = document.querySelector('input[name="ownerParticipationType"]:checked').value;
            const suffix = document.getElementById('ownerValueSuffix');
            suffix.textContent = type === 'percentage' ? '%' : '₪';
        }
        
        document.getElementById('createGroupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const participationType = document.querySelector('input[name="ownerParticipationType"]:checked').value;
            const participationValue = parseFloat(document.getElementById('ownerParticipationValue').value);
            
            if (participationType === 'percentage' && participationValue > 100) {
                alert('לא ניתן להגדיר יותר מ-100% השתתפות');
                return;
            }
            
            if (participationValue <= 0) {
                alert('ערך ההשתתפות חייב להיות חיובי');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'createGroup');
            formData.append('name', document.getElementById('groupName').value);
            formData.append('description', document.getElementById('groupDescription').value);
            formData.append('participation_type', participationType);
            formData.append('participation_value', participationValue);
            formData.append('csrf_token', csrfToken);
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'group.php?id=' + data.group_id;
                } else {
                    alert(data.message || 'שגיאה ביצירת הקבוצה');
                }
            });
        });
        
        function leaveGroup(groupId) {
            if (!confirm('האם אתה בטוח שברצונך לעזוב את הקבוצה?')) return;
            
            const formData = new FormData();
            formData.append('action', 'leaveGroup');
            formData.append('group_id', groupId);
            formData.append('csrf_token', csrfToken);
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בעזיבת הקבוצה');
                }
            });
        }
        
        function respondInvitation(invitationId, response) {
            const formData = new FormData();
            formData.append('action', 'respondInvitation');
            formData.append('invitation_id', invitationId);
            formData.append('response', response);
            formData.append('csrf_token', csrfToken);
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('שגיאה בטיפול בהזמנה');
                }
            });
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('createGroupModal');
            if (event.target == modal) {
                closeCreateGroupModal();
            }
        }
    </script>

    <script src="js/notifications.js"></script>
    <script>
        function enableNotifications() {
            if (notificationManager) {
                notificationManager.requestPermission();
            }
        }
    </script>
</body>
</html>