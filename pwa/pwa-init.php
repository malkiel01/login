<?php
/**
 * PWA Initialization File - Flexible Version
 * תומך גם בבאנר נייטיב וגם בבאנר מותאם אישית
 * 
 * שימוש: 
 * require_once 'pwa/pwa-init.php';
 * echo getPWAHeaders();
 * echo getPWAScripts(['banner_type' => 'native']); // או 'custom'
 */

/**
 * מחזיר את כל ה-meta tags וה-links ל-PWA
 */
function getPWAHeaders($options = []) {
    $defaults = [
        'title' => 'קניות משפחתיות',
        'theme_color' => '#667eea',
        'icon_path' => '/pwa/icons/ios/'
    ];
    
    $config = array_merge($defaults, $options);
    
    $html = '
    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="' . htmlspecialchars($config['title']) . '">
    <meta name="theme-color" content="' . htmlspecialchars($config['theme_color']) . '">
    <link rel="manifest" href="/manifest.json">
    
    <!-- PWA Icons for iOS -->
    <link rel="apple-touch-icon" href="' . $config['icon_path'] . '152.png">
    <link rel="apple-touch-icon" sizes="152x152" href="' . $config['icon_path'] . '152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="' . $config['icon_path'] . '180.png">
    <link rel="apple-touch-icon" sizes="167x167" href="' . $config['icon_path'] . '167.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="' . $config['icon_path'] . '32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="' . $config['icon_path'] . '16.png">
    ';
    
    return $html;
}

/**
 * מחזיר את הסקריפטים ל-PWA
 */
function getPWAScripts($options = []) {
    $defaults = [
        'banner_type' => 'custom',  // 'native' או 'custom'
        'page_type' => 'general',
        'show_after_seconds' => 5,
        'minimum_visits' => 2,
        'title' => 'התקן את האפליקציה! 📱',
        'subtitle' => 'גישה מהירה, עבודה אופליין והתראות חכמות',
        'icon' => '/pwa/icons/android/android-launchericon-192-192.png',
        'auto_init' => true,
        'show_install_button' => false
    ];
    
    $config = array_merge($defaults, $options);
    
    // הגדרות לפי סוג הדף
    // הגדרות מיוחדות לפי סוג הדף - רק אם לא נשלחו ערכים מותאמים
    if ($config['page_type'] === 'login') {
        if (!isset($options['show_after_seconds'])) 
            $config['show_after_seconds'] = 5;
        if (!isset($options['minimum_visits'])) 
            $config['minimum_visits'] = 1;
        if (!isset($options['title'])) 
            $config['title'] = 'התקן את האפליקציה! 📱';
        if (!isset($options['subtitle'])) 
            $config['subtitle'] = 'גישה מהירה לרשימות הקניות שלך, גם בלי אינטרנט';
    } elseif ($config['page_type'] === 'dashboard') {
        if (!isset($options['show_after_seconds'])) 
            $config['show_after_seconds'] = 10;
        if (!isset($options['minimum_visits'])) 
            $config['minimum_visits'] = 2;
        if (!isset($options['title'])) 
            $config['title'] = 'הפוך את הדשבורד לאפליקציה! 🚀';
        if (!isset($options['subtitle'])) 
            $config['subtitle'] = 'קבל התראות, עבוד אופליין וגישה מהירה מהמסך הראשי';
    }


    
    // Service Worker - תמיד נטען
    $html = '
    <!-- PWA Service Worker Registration -->
    <script>
        if ("serviceWorker" in navigator) {
            window.addEventListener("load", () => {
                navigator.serviceWorker.register("/service-worker.js")
                    .then(registration => {
                        console.log("Service Worker registered:", registration);
                    })
                    .catch(error => {
                        console.log("Service Worker registration failed:", error);
                    });
            });
        }
    </script>
    ';
    
    // בחירת סוג הבאנר
    if ($config['banner_type'] === 'native') {
        // באנר נייטיב - פשוט וקל
        $html .= getNativeBannerScript($config);
    } else {
        // באנר מותאם אישית - שליטה מלאה
        $html .= getCustomBannerScript($config);
    }
    
    // כפתור התקנה אופציונלי
    if ($config['show_install_button']) {
        $html .= getInstallButtonScript();
    }
    
    return $html;
}

/**
 * סקריפט לבאנר נייטיב
 */
function getNativeBannerScript($config) {
    return '
    <!-- PWA Native Banner -->
    <script src="/pwa/js/pwa-native-prompt.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // הבאנר הנייטיב יופיע אוטומטית
            console.log("PWA: Using native banner");
            
            // רק ל-iOS נוסיף התראה
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                const isStandalone = window.matchMedia("(display-mode: standalone)").matches;
                if (!isStandalone && !localStorage.getItem("ios-prompt-shown")) {
                    setTimeout(() => {
                        if (confirm("להוסיף את האפליקציה למסך הבית?\\n\\nלחץ על כפתור השיתוף ⬆️ ואז \\"הוסף למסך הבית\\"")) {
                            localStorage.setItem("ios-prompt-shown", "true");
                        }
                    }, ' . ($config['show_after_seconds'] * 1000) . ');
                }
            }
        });
    </script>
    ';
}

/**
 * סקריפט לבאנר מותאם אישית
 */
function getCustomBannerScript($config) {
    return '
    <!-- PWA Custom Banner -->
    <script src="/pwa/js/pwa-install-manager.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // יצירת המנהל - כי הוא לא נוצר אוטומטית
            const isStandalone = window.matchMedia("(display-mode: standalone)").matches;
            const isInstalled = localStorage.getItem("pwa-installed") === "true";
            
            if (!isStandalone && !isInstalled) {
                // צור את המנהל עם ההגדרות
                window.pwaInstallManager = new PWAInstallManager({
                    title: "' . addslashes($config['title']) . '",
                    subtitle: "' . addslashes($config['subtitle']) . '",
                    icon: "' . addslashes($config['icon']) . '",
                    showAfterSeconds: ' . $config['show_after_seconds'] . ',
                    minimumVisits: ' . $config['minimum_visits'] . '
                });
                
                console.log("PWA: Custom banner created and configured");
            }
        });
    </script>
    ';
}

/**
 * כפתור התקנה בממשק
 */
function getInstallButtonScript() {
    return '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // יצירת כפתור התקנה
            const installBtn = document.createElement("button");
            installBtn.id = "pwa-install-button";
            installBtn.innerHTML = "📱 התקן";
            installBtn.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 50px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                z-index: 9999;
                display: none;
                transition: all 0.3s ease;
            `;
            
            installBtn.onmouseover = () => {
                installBtn.style.transform = "scale(1.1)";
            };
            
            installBtn.onmouseout = () => {
                installBtn.style.transform = "scale(1)";
            };
            
            document.body.appendChild(installBtn);
            
            // הצג כפתור אם ניתן להתקין
            let deferredPrompt;
            
            window.addEventListener("beforeinstallprompt", (e) => {
                e.preventDefault();
                deferredPrompt = e;
                installBtn.style.display = "block";
            });
            
            installBtn.addEventListener("click", async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    if (outcome === "accepted") {
                        installBtn.style.display = "none";
                    }
                    deferredPrompt = null;
                } else if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                    alert("להתקנה:\\n1. לחץ על כפתור השיתוף ⬆️\\n2. בחר \\"הוסף למסך הבית\\"");
                }
            });
            
            // הסתר אם מותקן
            window.addEventListener("appinstalled", () => {
                installBtn.style.display = "none";
            });
        });
    </script>
    ';
}

/**
 * בדיקות עזר
 */
function isPWAInstalled() {
    return isset($_COOKIE['pwa_installed']) && $_COOKIE['pwa_installed'] === 'true';
}

function isStandaloneMode() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
           (isset($_GET['mode']) && $_GET['mode'] === 'standalone');
}