<?php
session_start();
require_once '../config.php';  // תיקון: חזרה לתיקייה הראשית

// אם המשתמש כבר מחובר, העבר לדף הראשי
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');  // תיקון: חזרה לתיקייה הראשית
    exit;
}

$error = '';
$success = '';

// טיפול בהתחברות רגילה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'יש למלא את כל השדות';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // עדכון זמן התחברות אחרון
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // שמירת פרטי המשתמש בסשן
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['profile_picture'] = $user['profile_picture'];
            
            header('Location: ../dashboard.php');  // תיקון: חזרה לתיקייה הראשית
            exit;
        } else {
            $error = 'שם משתמש או סיסמה שגויים';
        }
    }
}

// טיפול בהרשמה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $name = trim($_POST['reg_name']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    
    if (empty($username) || empty($email) || empty($name) || empty($password)) {
        $error = 'יש למלא את כל השדות';
    } elseif ($password !== $confirm_password) {
        $error = 'הסיסמאות אינן תואמות';
    } elseif (strlen($password) < 6) {
        $error = 'הסיסמה חייבת להכיל לפחות 6 תווים';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'כתובת המייל אינה תקינה';
    } else {
        $pdo = getDBConnection();
        
        // בדיקה אם המשתמש כבר קיים
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        
        if ($checkStmt->fetch()) {
            $error = 'שם המשתמש או המייל כבר קיימים במערכת';
        } else {
            // יצירת המשתמש
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password, name, auth_type) VALUES (?, ?, ?, ?, 'local')");
            
            if ($insertStmt->execute([$username, $email, $hashedPassword, $name])) {
                $success = 'ההרשמה הושלמה בהצלחה! כעת תוכל להתחבר';
            } else {
                $error = 'אירעה שגיאה בהרשמה, אנא נסה שוב';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../css/styles.css" rel="stylesheet">
    
    <!-- Google Sign-In API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    
    <!-- עדכן את הלינקים לאייקונים החדשים -->
    <link rel="icon" type="image/png" sizes="32x32" href="/family/images/icons/ios/32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/family/images/icons/ios/16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/family/images/icons/ios/180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/family/images/icons/ios/152.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/family/images/icons/ios/120.png">

    <!-- Service Worker -->
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
    <!-- PWA Installer Module -->
    <script src="pwa-installer.js"></script>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-shopping-cart"></i> <?php echo SITE_NAME; ?></h1>
            <!-- <p>ברוכים הבאים למערכת ניהול הקניות המשפחתית</p> -->
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="tab-container">
                <button class="tab-btn active" onclick="switchTab('login')">התחברות</button>
                <button class="tab-btn" onclick="switchTab('register')">הרשמה</button>
            </div>
            
            <!-- טאב התחברות -->
            <div id="login-tab" class="tab-content active">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">שם משתמש או אימייל</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="username" name="username" required>
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">סיסמה</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">זכור אותי</label>
                    </div>
                    
                    <button type="submit" name="login" class="btn-primary">
                        <i class="fas fa-sign-in-alt"></i> התחבר
                    </button>
                </form>
                
                <div class="divider">
                    <span>או</span>
                </div>
                
                <!-- כפתור Google Sign-In -->
                <div id="g_id_onload"
                     data-client_id="420537994881-gqiev5lqkp6gjj51l1arkjd5q09m5vv0.apps.googleusercontent.com"
                     data-callback="handleGoogleResponse"
                     data-auto_prompt="false">
                </div>
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="large"
                     data-theme="outline"
                     data-text="signin_with"
                     data-shape="rectangular"
                     data-logo_alignment="left">
                </div>

                <div class="forgot-password">
                    <a href="#">שכחת סיסמה?</a>
                </div>
            </div>
            
            <!-- טאב הרשמה -->
            <div id="register-tab" class="tab-content">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="reg_name">שם מלא</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="reg_name" name="reg_name" required>
                            <i class="fas fa-id-card"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_username">שם משתמש</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="reg_username" name="reg_username" required>
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_email">כתובת אימייל</label>
                        <div class="input-group">
                            <input type="email" class="form-control" id="reg_email" name="reg_email" required>
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_password">סיסמה</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="reg_password" name="reg_password" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_confirm_password">אימות סיסמה</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="reg_confirm_password" name="reg_confirm_password" required>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    
                    <button type="submit" name="register" class="btn-primary">
                        <i class="fas fa-user-plus"></i> הרשם
                    </button>
                </form>
                
                <div class="divider">
                    <span>או</span>
                </div>
                
                <!-- כפתור Google Sign-In להרשמה -->
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="large"
                     data-theme="outline"
                     data-text="signup_with"
                     data-shape="rectangular"
                     data-logo_alignment="left">
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            // הסתרת כל הטאבים
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // הצגת הטאב הנבחר
            if (tab === 'login') {
                document.getElementById('login-tab').classList.add('active');
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
            } else {
                document.getElementById('register-tab').classList.add('active');
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
            }
        }
        
        // טיפול בתגובה מ-Google
        function handleGoogleResponse(response) {
            // שלח את הטוקן לשרת
            fetch('google-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    credential: response.credential
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // הצלחה - הפנה לדף הראשי
                    window.location.href = data.redirect;
                } else {
                    // שגיאה - הצג הודעה
                    alert(data.message || 'שגיאה בהתחברות');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בתקשורת עם השרת');
            });
        }
    </script>
    <script>
        window.onload = function() {
            // מצא את ה-container
            const container = document.querySelector('.login-body');
            if (container) {
                // חשב את הרוחב הפנימי (פחות padding)
                const width = container.offsetWidth - 60; // 60 = padding משני הצדדים
                
                // הגבל למקסימום 400 (המגבלה של Google)
                const buttonWidth = Math.min(width, 400);
                
                // עדכן את כל כפתורי Google
                const googleButtons = document.querySelectorAll('.g_id_signin');
                googleButtons.forEach(button => {
                    button.setAttribute('data-width', buttonWidth);
                });
                
                // רענן את כל הכפתורים - כאן התיקון!
                if (window.google && window.google.accounts) {
                    googleButtons.forEach(button => {
                        window.google.accounts.id.renderButton(
                            button,  // עובר על כל כפתור בנפרד
                            {width: buttonWidth}
                        );
                    });
                }
            }
        };
    </script>
</body>
</html>