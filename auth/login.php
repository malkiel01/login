<?php

// בתחילת הקובץ
require_once '../pwa/pwa-init.php';

session_start();
require_once '../config.php';  // תיקון: חזרה לתיקייה הראשית

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
            
            header('Location: ../dashboard/index.php');  // תיקון: חזרה לתיקייה הראשית
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



    <!-- קוד בדיקת PWA לדשבורד -->
    <!-- הוסף את זה בכל מקום בדשבורד שלך -->
<?php
    // <div id="pwa-test-panel" style="
    //     position: fixed;
    //     bottom: 20px;
    //     right: 20px;
    //     background: white;
    //     border: 2px solid #667eea;
    //     border-radius: 12px;
    //     padding: 20px;
    //     box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    //     z-index: 9990;
    //     max-width: 350px;
    //     direction: rtl;
    //     font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    // ">
    //     <h3 style="margin: 0 0 15px 0; color: #667eea; font-size: 18px;">
    //         🧪 בדיקת באנרי PWA
    //     </h3>
        
    //     <div style="margin-bottom: 15px; padding: 10px; background: #f3f4f6; border-radius: 8px;">
    //         <strong>מצב נוכחי:</strong>
    //         <div id="pwa-status" style="margin-top: 5px; font-size: 14px;"></div>
    //     </div>
        
    //     <div style="display: flex; flex-direction: column; gap: 10px;">
    //         <!-- כפתורי בדיקה לבאנר נייטיב -->
    //         <button onclick="testNativeBanner()" style="
    //             padding: 10px;
    //             background: linear-gradient(135deg, #10b981, #059669);
    //             color: white;
    //             border: none;
    //             border-radius: 8px;
    //             cursor: pointer;
    //             font-size: 14px;
    //             font-weight: 600;
    //             transition: all 0.3s;
    //         ">
    //             🎯 בדוק באנר נייטיב
    //         </button>
            
    //         <!-- כפתורי בדיקה לבאנר מותאם -->
    //         <button onclick="testCustomBanner()" style="
    //             padding: 10px;
    //             background: linear-gradient(135deg, #667eea, #764ba2);
    //             color: white;
    //             border: none;
    //             border-radius: 8px;
    //             cursor: pointer;
    //             font-size: 14px;
    //             font-weight: 600;
    //             transition: all 0.3s;
    //         ">
    //             🎨 בדוק באנר מותאם
    //         </button>
            
    //         <button onclick="testCustomBannerDelayed()" style="
    //             padding: 10px;
    //             background: linear-gradient(135deg, #f59e0b, #d97706);
    //             color: white;
    //             border: none;
    //             border-radius: 8px;
    //             cursor: pointer;
    //             font-size: 14px;
    //             font-weight: 600;
    //             transition: all 0.3s;
    //         ">
    //             ⏰ באנר מותאם (5 שניות)
    //         </button>
            
    //         <!-- כפתורי ניקוי -->
    //         <button onclick="clearPWAData()" style="
    //             padding: 10px;
    //             background: linear-gradient(135deg, #ef4444, #dc2626);
    //             color: white;
    //             border: none;
    //             border-radius: 8px;
    //             cursor: pointer;
    //             font-size: 14px;
    //             font-weight: 600;
    //             transition: all 0.3s;
    //         ">
    //             🗑️ נקה נתוני PWA
    //         </button>
            
    //         <!-- כפתור הסתרה -->
    //         <button onclick="document.getElementById('pwa-test-panel').style.display='none'" style="
    //             padding: 10px;
    //             background: #6b7280;
    //             color: white;
    //             border: none;
    //             border-radius: 8px;
    //             cursor: pointer;
    //             font-size: 14px;
    //             font-weight: 600;
    //             transition: all 0.3s;
    //         ">
    //             ❌ סגור פאנל בדיקות
    //         </button>
    //     </div>
        
    //     <div style="margin-top: 15px; padding: 10px; background: #fef3c7; border-radius: 8px; font-size: 12px;">
    //         <strong>💡 טיפ:</strong> הבאנר הנייטיב דורש פעולת משתמש (לחיצה) ולא יכול להופיע אוטומטית
    //     </div>
    // </div>

    // <script>
    //     // עדכון סטטוס
    //     function updatePWAStatus() {
    //         const statusEl = document.getElementById('pwa-status');
    //         const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
    //         const isInstalled = localStorage.getItem('pwa-installed') === 'true';
    //         const dismissed = localStorage.getItem('pwa-custom-dismissed');
    //         const visits = localStorage.getItem('pwa-visit-count') || '0';
            
    //         let statusHTML = '';
            
    //         if (isStandalone) {
    //             statusHTML += '<span style="color: #10b981;">✅ האפליקציה מותקנת (Standalone)</span><br>';
    //         } else if (isInstalled) {
    //             statusHTML += '<span style="color: #10b981;">✅ האפליקציה מותקנת</span><br>';
    //         } else {
    //             statusHTML += '<span style="color: #ef4444;">❌ האפליקציה לא מותקנת</span><br>';
    //         }
            
    //         statusHTML += `<span>ביקורים: ${visits}</span><br>`;
            
    //         if (dismissed === 'permanent') {
    //             statusHTML += '<span style="color: #f59e0b;">⚠️ באנר נדחה לצמיתות</span>';
    //         } else if (dismissed && !isNaN(dismissed)) {
    //             const date = new Date(parseInt(dismissed));
    //             statusHTML += `<span style="color: #f59e0b;">⏳ באנר נדחה עד: ${date.toLocaleDateString('he-IL')}</span>`;
    //         }
            
    //         statusEl.innerHTML = statusHTML;
    //     }

    //     // בדיקת באנר נייטיב
    //     function testNativeBanner() {
    //         console.log('Testing Native Banner...');
            
    //         // הסתר באנרים אחרים
    //         if (window.pwaCustomBanner) {
    //             window.pwaCustomBanner.hide();
    //         }
            
    //         // טען את הבאנר הנייטיב אם לא קיים
    //         if (!window.pwaNativeBanner) {
    //             const script = document.createElement('script');
    //             script.src = '/pwa/js/pwa-native-banner.js';
    //             script.onload = () => {
    //                 console.log('Native banner loaded');
    //                 setTimeout(() => {
    //                     if (window.pwaNativeBanner && window.pwaNativeBanner.deferredPrompt) {
    //                         window.pwaNativeBanner.showInstallPrompt();
    //                     } else {
    //                         alert('הבאנר הנייטיב דורש לחיצה על הכפתור הצף שנוצר');
    //                     }
    //                 }, 100);
    //             };
    //             document.head.appendChild(script);
    //         } else {
    //             // נסה להציג
    //             if (window.pwaNativeBanner.deferredPrompt) {
    //                 window.pwaNativeBanner.showInstallPrompt();
    //             } else {
    //                 alert('אין אפשרות להתקנה זמינה. נסה לרענן את הדף.');
    //             }
    //         }
            
    //         updatePWAStatus();
    //     }

    //     // בדיקת באנר מותאם
    //     function testCustomBanner() {
    //         console.log('Testing Custom Banner...');
            
    //         // הסתר באנרים אחרים
    //         if (window.pwaNativeBanner) {
    //             const nativeBtn = document.getElementById('pwa-native-btn');
    //             if (nativeBtn) nativeBtn.style.display = 'none';
    //         }
            
    //         // טען את הבאנר המותאם אם לא קיים
    //         if (!window.pwaCustomBanner) {
    //             const script = document.createElement('script');
    //             script.src = '/pwa/js/pwa-custom-banner.js';
    //             script.onload = () => {
    //                 console.log('Custom banner loaded');
    //                 setTimeout(() => {
    //                     if (window.pwaCustomBanner) {
    //                         window.pwaCustomBanner.forceShow();
    //                     }
    //                 }, 100);
    //             };
    //             document.head.appendChild(script);
    //         } else {
    //             // הצג מיד
    //             window.pwaCustomBanner.dismissed = false; // אפס דחייה
    //             window.pwaCustomBanner.forceShow();
    //         }
            
    //         updatePWAStatus();
    //     }

    //     // בדיקת באנר מותאם עם השהייה
    //     function testCustomBannerDelayed() {
    //         console.log('Testing Custom Banner with 5 seconds delay...');
            
    //         // הסתר באנרים אחרים
    //         if (window.pwaNativeBanner) {
    //             const nativeBtn = document.getElementById('pwa-native-btn');
    //             if (nativeBtn) nativeBtn.style.display = 'none';
    //         }
            
    //         // טען את הבאנר המותאם
    //         if (!window.pwaCustomBanner) {
    //             const script = document.createElement('script');
    //             script.src = '/pwa/js/pwa-custom-banner.js';
    //             script.onload = () => {
    //                 console.log('Custom banner loaded, showing in 5 seconds...');
    //                 setTimeout(() => {
    //                     if (window.pwaCustomBanner) {
    //                         window.pwaCustomBanner.updateConfig({
    //                             title: 'באנר בדיקה! ⏰',
    //                             subtitle: 'הבאנר הזה הופיע אחרי 5 שניות',
    //                             showDelay: 5000
    //                         });
    //                         window.pwaCustomBanner.forceShow();
    //                     }
    //                 }, 5000);
    //             };
    //             document.head.appendChild(script);
    //         } else {
    //             // עדכן והצג אחרי 5 שניות
    //             console.log('Showing custom banner in 5 seconds...');
    //             window.pwaCustomBanner.dismissed = false;
    //             window.pwaCustomBanner.updateConfig({
    //                 title: 'באנר בדיקה! ⏰',
    //                 subtitle: 'הבאנר הזה הופיע אחרי 5 שניות'
    //             });
    //             setTimeout(() => {
    //                 window.pwaCustomBanner.forceShow();
    //             }, 5000);
    //         }
            
    //         updatePWAStatus();
    //     }

    //     // ניקוי נתוני PWA
    //     function clearPWAData() {
    //         if (confirm('האם לנקות את כל נתוני ה-PWA?\nזה יאפס את כל ההגדרות והדחיות.')) {
    //             // נקה localStorage
    //             localStorage.removeItem('pwa-installed');
    //             localStorage.removeItem('pwa-install-accepted');
    //             localStorage.removeItem('pwa-install-dismissed');
    //             localStorage.removeItem('pwa-custom-dismissed');
    //             localStorage.removeItem('pwa-visit-count');
    //             localStorage.removeItem('ios-instructions-shown');
    //             localStorage.removeItem('ios-prompt-dismissed');
    //             localStorage.removeItem('ios-prompt-shown');
                
    //             // אפס באנרים אם קיימים
    //             if (window.pwaNativeBanner) {
    //                 window.pwaNativeBanner.reset();
    //             }
    //             if (window.pwaCustomBanner) {
    //                 window.pwaCustomBanner.reset();
    //             }
                
    //             console.log('PWA data cleared');
    //             alert('נתוני PWA נוקו בהצלחה! רענן את הדף לבדיקה מחדש.');
    //             updatePWAStatus();
    //         }
    //     }

    //     // עדכון סטטוס בטעינה
    //     updatePWAStatus();

    //     // עדכון סטטוס כל 2 שניות
    //     setInterval(updatePWAStatus, 2000);

    //     // הוסף כפתור להצגת הפאנל אם הוסתר
    //     if (!document.getElementById('pwa-test-toggle')) {
    //         const toggleBtn = document.createElement('button');
    //         toggleBtn.id = 'pwa-test-toggle';
    //         toggleBtn.innerHTML = '🧪';
    //         toggleBtn.style.cssText = `
    //             position: fixed;
    //             bottom: 20px;
    //             right: 20px;
    //             width: 50px;
    //             height: 50px;
    //             border-radius: 50%;
    //             background: #667eea;
    //             color: white;
    //             border: none;
    //             font-size: 24px;
    //             cursor: pointer;
    //             z-index: 9989;
    //             display: none;
    //             box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    //         `;
    //         toggleBtn.onclick = () => {
    //             const panel = document.getElementById('pwa-test-panel');
    //             panel.style.display = 'block';
    //             toggleBtn.style.display = 'none';
    //         };
    //         document.body.appendChild(toggleBtn);
            
    //         // הצג את הכפתור כשהפאנל נסגר
    //         const originalClose = document.getElementById('pwa-test-panel').querySelector('button[onclick*="display=\'none\'"]');
    //         if (originalClose) {
    //             originalClose.onclick = () => {
    //                 document.getElementById('pwa-test-panel').style.display = 'none';
    //                 document.getElementById('pwa-test-toggle').style.display = 'block';
    //             };
    //         }
    //     }

    //     console.log('PWA Test Panel Ready! 🧪');
    // </script>
?>


    <!-- סוף קוד בדיקת PWA לדשבורד -->
    <?php
        // בתחילת הדף או במקום שתרצה
        require_once '../debugs/console-debug.php';
    ?>
    <script src="../debugs/pwa-debug-popup.js"></script>
    
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
    <!-- לפני </body> -->
    <!-- ?php echo getPWAScripts(['page_type' => 'login']); ? -->

    <!-- <script src="../pwa/js/pwa-hybrid-prompt.js"></script> -->
     <?php 
        echo getPWAScripts([
            'banner_type' => 'auto-native',  // <- השינוי הקריטי!
            'page_type' => 'login'
        ]);

        // echo getPWAScripts([
        //     'banner_type' => 'native',
        //     'page_type' => 'login',
        //     // 'showFloatingButton' => false  // ללא כפתור צף
        // ]);
     
        // echo getPWAScripts([
        //     'banner_type' => 'native',
        //     'install_text' => '📱 התקן עכשיו',
        //     'button_position' => 'bottom-right'
        // ]); 
        // echo getPWAScripts([
        //     'banner_type' => 'custom',  // לא native!
        //     'page_type' => 'login',
        //     'show_after_seconds' => 3
        // ]);
        // echo getPWAScripts([
        //     'banner_type' => 'custom',
        //     'page_type' => 'login',
        //     'show_after_seconds' => 5,
        //     'title' => 'הפוך אותנו לאפליקציה!',
        //     'subtitle' => 'התקנה מהירה, גישה נוחה'
        // ]); 
    ?>

</body>
</html>