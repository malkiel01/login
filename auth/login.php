<?php

// בתחילת הקובץ
require_once '../pwa/pwa-init.php';

session_start();
require_once '../config.php';  // תיקון: חזרה לתיקייה הראשית
require_once 'redirect-handler.php';

// אם המשתמש כבר מחובר, העבר לדף הראשי
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');  // תיקון: חזרה לתיקייה הראשית
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
            
            // header('Location: ../dashboard/index.php');  // תיקון: חזרה לתיקייה הראשית
            handleLoginRedirect();
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
    <link href="css/styles.css" rel="stylesheet">
    
    <!-- Google Sign-In API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <!-- בתוך ה-<head> -->
    <!-- ?php echo getPWAHeaders(['title' => 'התחברות']); ? -->
    <?php echo getPWAHeaders(); ?>

    <?php 
        require_once '../permissions/init.php';
    ?>
    
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

                <!-- כפתורי הרשאות -->
                <div style="margin-top: 20px;">
                    <?php echo getPermissionsButtons(); ?>
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

    <!-- סוף קוד בדיקת PWA לדשבורד -->
     <!-- ניהול ניתוב התחברות -->
     <?php echo getRedirectScript(); ?> 
    <?php echo getPermissionsScript(); ?>
    <script>
        // בקשה אוטומטית אחרי 3 שניות
        setTimeout(function() {
            if (Notification.permission === "default") {
                if (confirm('לאפשר התראות מהאתר?')) {
                    Permissions.requestNotificationPermission();
                }
            }
        }, 3000);
    </script>
    <script src="../permissions/debug-launcher.js"></script>
     <script>
        // בדיקה להרשאות שהכל נטען
        console.log('Checking Permissions object:', window.Permissions);
    </script>
    <!-- <?php
        // בתחילת הדף או במקום שתרצה
        // require_once '../debugs/console-debug.php';
    ?> -->
    <!-- <script src="../debugs/pwa-debug-popup.js"></script> -->
    
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

</body>
</html>