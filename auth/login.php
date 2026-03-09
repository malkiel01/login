<?php
// הגדרות סשן ל-PWA - לפני session_start()
ini_set('session.gc_maxlifetime', 2592000);    // 30 ימים בשניות
ini_set('session.cookie_lifetime', 2592000);    // Cookie חי 30 ימים

// הגדרת פרמטרים נוספים של העוגייה
session_set_cookie_params([
    'lifetime' => 2592000,    // 30 ימים
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,         // רק HTTPS
    'httponly' => true,       // מניעת גישה מ-JavaScript
    'samesite' => 'Lax'       // הגנת CSRF
]);


// בתחילת הקובץ
require_once '../pwa/pwa-init.php';

session_start();
require_once '../config.php';  // תיקון: חזרה לתיקייה הראשית
require_once 'redirect-handler.php';
require_once '../permissions/init.php';
require_once 'rate-limiter.php';  // Rate Limiting להגנה מ-brute force
require_once 'csrf.php';          // CSRF Protection
require_once 'audit-logger.php';  // Audit Logging
require_once 'token-manager.php'; // Persistent Auth for PWA
require_once '../push/push-log.php'; // Push notification logging
// require_once '../debugs/index.php';

// מניעת קאש - חשוב ל-PWA!
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// אם המשתמש כבר מחובר, העבר לדף הראשי מיידית
// חשוב: משתמשים ב-location.replace() כדי לא להוסיף את login להיסטוריה!
// זה פותר את הבעיה של back → login → redirect → dashboard loop
if (isset($_SESSION['user_id'])) {
    // אם יש redirect parameter (למשל מ-push notification) - הפנה לשם
    $redirect = $_GET['redirect'] ?? '/dashboard/dashboards/cemeteries/';
    // Validate redirect is internal
    if (strpos($redirect, '/') !== 0) {
        $redirect = '/dashboard/dashboards/cemeteries/';
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>location.replace(' . json_encode($redirect) . ');</script>';
    echo '</head><body></body></html>';
    exit;
}

$error = '';
$success = '';
$isLocked = false;
$waitTime = 0;
$remainingAttempts = 5;

// בדיקת Rate Limit
$rateLimiter = getRateLimiter();
$clientIP = RateLimiter::getClientIP();

// בדיקה אם ה-IP חסום לחלוטין
if ($rateLimiter->isBlacklisted($clientIP)) {
    $error = 'הגישה נחסמה עקב ניסיונות רבים מדי. אנא פנה למנהל המערכת.';
    $isLocked = true;
}

// טיפול בהתחברות רגילה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && !$isLocked) {
    // בדיקת CSRF
    if (!validateCsrf()) {
        $error = 'שגיאת אבטחה. אנא רענן את הדף ונסה שוב.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // בדיקת Rate Limit לפני ניסיון
        if (!$rateLimiter->canAttempt($clientIP, $username)) {
        $waitTime = $rateLimiter->getWaitTime($clientIP, $username);
        $error = "יותר מדי ניסיונות התחברות. נסה שוב בעוד $waitTime דקות.";
        $isLocked = true;
    } elseif (empty($username) || empty($password)) {
        $error = 'יש למלא את כל השדות';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // עדכון מספר הניסיונות שנותרו
        $remainingAttempts = $rateLimiter->getRemainingAttempts($clientIP, $username);

        // אחרי אימות מוצלח של המשתמש
        if ($user && password_verify($password, $user['password'])) {
            // רישום התחברות מוצלחת וניקוי ניסיונות
            $rateLimiter->recordSuccessfulLogin($clientIP, $username);
            // בדיקה אם המשתמש סימן "זכור אותי" או שזו אפליקציית PWA
            $isPWA = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
                    strpos($_SERVER['HTTP_USER_AGENT'], 'PWA') !== false ||
                    isset($_POST['remember']);

            // יצירת token עמיד (חדש - תומך iOS PWA)
            $tokenManager = getTokenManager();
            $deviceInfo = [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'is_pwa' => $isPWA,
                'platform' => $_POST['platform'] ?? 'web'
            ];
            $tokenData = $tokenManager->generateToken($user['id'], $deviceInfo);

            // הגדרת cookie עם ה-token החדש
            setcookie(
                'auth_token',
                $tokenData['token'],
                (int)($tokenData['expires'] / 1000), // המרה מ-ms לשניות
                '/',
                $_SERVER['HTTP_HOST'],
                true,  // HTTPS only
                true   // HTTP only
            );

            // שמור גם את ה-remember_token הישן לתאימות אחורה
            if ($isPWA) {
                $updateStmt = $pdo->prepare("
                    UPDATE users
                    SET last_login = NOW(),
                        remember_token = ?,
                        remember_expiry = DATE_ADD(NOW(), INTERVAL 30 DAY)
                    WHERE id = ?
                ");
                $updateStmt->execute([$tokenData['token'], $user['id']]);
            } else {
                // התחברות רגילה
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
            }

            // שמירת פרטים בסשן
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['profile_picture'] = $user['profile_picture'];
            $_SESSION['is_pwa'] = $isPWA;
            $_SESSION['session_lifetime'] = $isPWA ? 2592000 : 7200;

            // שמור token data ב-session להעברה ל-JavaScript
            $_SESSION['token_data'] = $tokenData;

            // Audit Log - רישום התחברות מוצלחת
            AuditLogger::logLogin($user['id'], $user['username'], 'local');

            // Push Log - רישום התחברות עם מידע על המכשיר
            logUserLogin($user['id'], $user['name'] ?? $user['username']);

            handleLoginRedirect();
            exit;
        }
        // if ($user && password_verify($password, $user['password'])) {
        //     // עדכון זמן התחברות אחרון
        //     $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        //     $updateStmt->execute([$user['id']]);
            
        //     // שמירת פרטי המשתמש בסשן
        //     $_SESSION['user_id'] = $user['id'];
        //     $_SESSION['username'] = $user['username'];
        //     $_SESSION['name'] = $user['name'];
        //     $_SESSION['email'] = $user['email'];
        //     $_SESSION['profile_picture'] = $user['profile_picture'];
            
        //     // header('Location: ../dashboard/index.php');  // תיקון: חזרה לתיקייה הראשית
        //     handleLoginRedirect();
        //     exit;
        // }
        else {
            // רישום ניסיון כושל
            $rateLimiter->recordFailedAttempt($clientIP, $username);
            $remainingAttempts = $rateLimiter->getRemainingAttempts($clientIP, $username);

            // Audit Log - רישום התחברות כושלת
            AuditLogger::logLoginFailed($username, 'Invalid credentials');

            if ($remainingAttempts > 0) {
                $error = "שם משתמש או סיסמה שגויים. נותרו $remainingAttempts ניסיונות.";
            } else {
                $waitTime = $rateLimiter->getWaitTime($clientIP, $username);
                $error = "יותר מדי ניסיונות. נסה שוב בעוד $waitTime דקות.";
                $isLocked = true;
            }
        }
    }
    } // סגירת else של CSRF
}

// טיפול בהרשמה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && !$isLocked) {
    // בדיקת CSRF
    if (!validateCsrf()) {
        $error = 'שגיאת אבטחה. אנא רענן את הדף ונסה שוב.';
    } else {
        $username = trim($_POST['reg_username']);
        $email = trim($_POST['reg_email']);
        $name = trim($_POST['reg_name']);
        $password = $_POST['reg_password'];
        $confirm_password = $_POST['reg_confirm_password'];

        // בדיקת Rate Limit גם להרשמות (הגנה מפני spam)
        $registrationKey = 'register_' . $clientIP;
        if (!$rateLimiter->canAttempt($clientIP, $registrationKey)) {
        $waitTime = $rateLimiter->getWaitTime($clientIP, $registrationKey);
        $error = "יותר מדי ניסיונות הרשמה. נסה שוב בעוד $waitTime דקות.";
        $isLocked = true;
    } elseif (empty($username) || empty($email) || empty($name) || empty($password)) {
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
            $rateLimiter->recordFailedAttempt($clientIP, $registrationKey);
            $error = 'שם המשתמש או המייל כבר קיימים במערכת';
        } else {
            // יצירת המשתמש
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password, name, auth_type) VALUES (?, ?, ?, ?, 'local')");

            if ($insertStmt->execute([$username, $email, $hashedPassword, $name])) {
                // רישום הצלחה וניקוי ניסיונות
                $rateLimiter->recordSuccessfulLogin($clientIP, $registrationKey);

                // Audit Log - רישום הרשמה חדשה
                $newUserId = $pdo->lastInsertId();
                AuditLogger::logRegister($newUserId, $username, $email);

                $success = 'ההרשמה הושלמה בהצלחה! כעת תוכל להתחבר';
            } else {
                $rateLimiter->recordFailedAttempt($clientIP, $registrationKey);
                $error = 'אירעה שגיאה בהרשמה, אנא נסה שוב';
            }
        }
    }
    } // סגירת else של CSRF
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he" style="display:none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="css/styles.css" rel="stylesheet">
    
    <!-- Google Sign-In API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <!-- בתוך ה-<head> -->
    <!-- ?php echo getPWAHeaders(['title' => 'התחברות']); ? -->
    <?php echo getPWAHeaders(); ?>

    <!-- CSRF Protection -->
    <?php echo csrfMeta(); ?>
    <?php echo csrfScript(); ?>

</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-shopping-cart"></i> <?php echo SITE_NAME; ?></h1>
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
                <?php if ($isLocked): ?>
                <div class="lockout-notice">
                    <i class="fas fa-lock"></i>
                    <h3>החשבון נעול זמנית</h3>
                    <p>בשל ניסיונות התחברות רבים מדי, החשבון נעול.</p>
                    <?php if ($waitTime > 0): ?>
                    <p class="wait-time">נסה שוב בעוד <strong id="countdown"><?php echo $waitTime; ?></strong> דקות</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" <?php echo $isLocked ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label for="username">שם משתמש או אימייל</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="username" name="username" required <?php echo $isLocked ? 'disabled' : ''; ?>>
                            <i class="fas fa-user"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">סיסמה</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required <?php echo $isLocked ? 'disabled' : ''; ?>>
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>

                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember" <?php echo $isLocked ? 'disabled' : ''; ?>>
                        <label for="remember">זכור אותי</label>
                    </div>

                    <button type="submit" name="login" class="btn-primary" <?php echo $isLocked ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i> התחבר
                    </button>
                </form>
                
                <!-- התחברות ביומטרית -->
                <div id="biometric-login-container" style="display:none;"></div>

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
                    <?php echo csrfField(); ?>
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

    <!-- סוף קוד בדיקת PWA לדשבורד -->
     <!-- ניהול ניתוב התחברות -->
     <?php echo getRedirectScript(); ?> 
    <!-- בקשת התראות הועברה ל-dashboard (אחרי התחברות) -->
    
    <?php if ($isLocked && $waitTime > 0): ?>
    <script>
        // Countdown timer for lockout
        (function() {
            let minutes = <?php echo $waitTime; ?>;
            let seconds = 0;
            const countdownEl = document.getElementById('countdown');

            if (countdownEl) {
                const timer = setInterval(function() {
                    if (seconds > 0) {
                        seconds--;
                    } else if (minutes > 0) {
                        minutes--;
                        seconds = 59;
                    }

                    if (minutes === 0 && seconds === 0) {
                        clearInterval(timer);
                        // Reload the page when countdown ends
                        location.reload();
                    } else {
                        countdownEl.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                    }
                }, 1000);

                // Initial display
                countdownEl.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }
        })();
    </script>
    <?php endif; ?>

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
            // שלח את הטוקן לשרת עם CSRF token
            fetch('google-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF.token()
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
     <?php 
        // דיפולטיבי
        echo getPWAScripts([
            'banner_type' => 'auto-native',  // <- השינוי הקריטי!
            'page_type' => 'login'
        ]);

        // // עם התאמת מיקום וטקסט
        // echo getPWAScripts([
        //     'banner_type' => 'manual-native',
        //     'install_text' => '📱 התקן עכשיו',
        //     'button_position' => 'bottom-left'  // bottom-left, top-right, top-left
        // ]);


        // // באנר מעוצב מותאם אישית
        // echo getPWAScripts([
        //     'banner_type' => 'custom',
        //     'page_type' => 'login',
        //     'show_after_seconds' => 5,
        //     'title' => 'הפוך אותנו לאפליקציה! 🚀',
        //     'subtitle' => 'גישה מהירה, עבודה אופליין והתראות חכמות',
        //     'icon' => '/path/to/custom-icon.png',
        //     'install_text' => 'התקן עכשיו',
        //     'dismiss_text' => 'אולי מאוחר יותר',
        //     'minimum_visits' => 2  // להציג רק אחרי 2 ביקורים
        // ]);
    ?>

    <!-- Biometric Authentication -->
    <script src="/js/biometric-auth.js"></script>
    <script src="/js/biometric-ui.js"></script>
    <script>
        // אתחול התחברות ביומטרית
        document.addEventListener('DOMContentLoaded', async function() {
            const container = document.getElementById('biometric-login-container');

            // בדוק אם יש תמיכה ואם המשתמש הגדיר ביומטרי
            if (window.biometricAuth && window.biometricAuth.isSupported) {
                const hasPlatformAuth = await window.biometricAuth.isPlatformAuthenticatorAvailable();

                if (hasPlatformAuth) {
                    // הצג את הכפתור
                    container.style.display = 'block';

                    window.biometricUI.createLoginButton(
                        container,
                        // הצלחה
                        function(result) {
                            console.log('Biometric login success:', result);
                            // הפנה לדשבורד
                            window.location.href = '/dashboard/';
                        },
                        // שגיאה
                        function(error) {
                            if (error !== 'User denied permission') {
                                console.error('Biometric login failed:', error);
                            }
                        }
                    );
                }
            }
        });
    </script>

    <!-- v18: ניקוי היסטוריה + הגנה מפני bfcache -->
    <script>
    (function() {
        // v18: אם הגענו לכאן מ-history.go() כדי לנקות היסטוריה - החלף בדשבורד!
        var needsReplace = sessionStorage.getItem('__replaceLogin');
        if (needsReplace) {
            console.log('[v18] 🔄 מחליף login.php בדשבורד');
            sessionStorage.removeItem('__replaceLogin');
            location.replace('/dashboard/dashboards/cemeteries/');
            return; // אל תציג את הדף
        }

        // הצג את הדף מיידית - הגענו לכאן רק אם לא מחוברים
        document.documentElement.style.display = '';

        // הגנה מ-bfcache - אם הדף נטען מ-cache, בדוק session
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                // הדף נטען מ-bfcache - בדוק אם מחובר
                fetch('/auth/check-session.php', { credentials: 'include' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.logged_in) {
                            location.replace('/dashboard/dashboards/cemeteries/');
                        }
                    });
            }
        });
    })();
    </script>

</body>
</html>