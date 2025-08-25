<?php
/**
 * PWA Initialization File
 * קובץ אתחול מרכזי ל-PWA
 * 
 * שימוש: 
 * require_once 'pwa/pwa-init.php';
 * echo getPWAHeaders();  // ב-<head>
 * echo getPWAScripts();  // לפני </body>
 */

/**
 * מחזיר את כל ה-meta tags וה-links ל-PWA
 * להוסיף בתוך ה-<head>
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
    
    <!-- PWA Custom Styles -->
    <link rel="stylesheet" href="/pwa/css/pwa-custom.css">
    ';
    
    return $html;
}

/**
 * מחזיר את הסקריפטים ל-PWA
 * להוסיף לפני </body>
 */
function getPWAScripts($options = []) {
    $defaults = [
        'page_type' => 'general', // 'login', 'dashboard', 'general'
        'show_after_seconds' => 5,
        'minimum_visits' => 2,
        'title' => 'התקן את האפליקציה! 📱',
        'subtitle' => 'גישה מהירה, עבודה אופליין והתראות חכמות',
        'icon' => '/pwa/icons/android/android-launchericon-192-192.png',
        'auto_init' => true
    ];
    
    $config = array_merge($defaults, $options);
    
    // הגדרות מיוחדות לפי סוג הדף
    if ($config['page_type'] === 'login') {
        $config['show_after_seconds'] = 5;
        $config['minimum_visits'] = 1;
        $config['title'] = 'התקן את האפליקציה! 📱';
        $config['subtitle'] = 'גישה מהירה לרשימות הקניות שלך, גם בלי אינטרנט';
    } elseif ($config['page_type'] === 'dashboard') {
        $config['show_after_seconds'] = 10;
        $config['minimum_visits'] = 2;
        $config['title'] = 'הפוך את הדשבורד לאפליקציה! 🚀';
        $config['subtitle'] = 'קבל התראות, עבוד אופליין וגישה מהירה מהמסך הראשי';
    }
    
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
    
    <!-- PWA Install Manager -->
    <script src="/pwa/js/pwa-install-manager.js"></script>
    ';
    
    // הוספת אתחול אוטומטי אם נדרש
    if ($config['auto_init']) {
        $html .= '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // בדיקה אם לא מותקן כבר
            const isStandalone = window.matchMedia("(display-mode: standalone)").matches;
            const isInstalled = localStorage.getItem("pwa-installed") === "true";
            
            if (!isStandalone && !isInstalled) {
                window.pwaInstallManager = new PWAInstallManager({
                    title: "' . addslashes($config['title']) . '",
                    subtitle: "' . addslashes($config['subtitle']) . '",
                    icon: "' . addslashes($config['icon']) . '",
                    showAfterSeconds: ' . $config['show_after_seconds'] . ',
                    minimumVisits: ' . $config['minimum_visits'] . '
                });
            }
        });
    </script>
        ';
    }
    
    return $html;
}

/**
 * מחזיר סקריפט להצעת התקנה מיוחדת
 * לדוגמה: אחרי פעולה מוצלחת
 */
function getPWAPromptScript($message = 'פעולה בוצעה בהצלחה! רוצה להתקין את האפליקציה?') {
    return '
    <script>
        // הצעת התקנה אחרי פעולה
        setTimeout(function() {
            if (window.pwaInstallManager && !localStorage.getItem("pwa-installed")) {
                if (confirm("' . addslashes($message) . '")) {
                    window.pwaInstallManager.forceShow();
                }
            }
        }, 2000);
    </script>
    ';
}

/**
 * בדיקה אם האפליקציה מותקנת
 */
function isPWAInstalled() {
    return isset($_COOKIE['pwa_installed']) && $_COOKIE['pwa_installed'] === 'true';
}

/**
 * בדיקה אם המשתמש במצב standalone
 */
function isStandaloneMode() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           $_SERVER['HTTP_X_REQUESTED_WITH'] === 'com.your.app';
}