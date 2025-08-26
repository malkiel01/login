<?php
/**
 * PWA Initialization File - Enhanced Version
 * תומך בשלושה סוגי באנרים:
 * 1. auto-native - באנר נייטיב אוטומטי (מומלץ!)
 * 2. manual-native - באנר נייטיב עם כפתור
 * 3. custom - באנר מותאם אישית
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
        'banner_type' => 'auto-native',  // 'auto-native', 'manual-native', 'custom'
        'page_type' => 'general',
        'show_after_seconds' => 5,
        'minimum_visits' => 2,
        'title' => 'התקן את האפליקציה! 📱',
        'subtitle' => 'גישה מהירה, עבודה אופליין והתראות חכמות',
        'icon' => '/pwa/icons/android/android-launchericon-192-192.png',
        'install_text' => 'התקן עכשיו',
        'dismiss_text' => 'מאוחר יותר',
        'show_install_button' => false
    ];
    
    $config = array_merge($defaults, $options);
    
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
    switch ($config['banner_type']) {
        case 'auto-native':
            // באנר נייטיב אוטומטי - מומלץ!
            $html .= getAutoNativeBannerScript($config);
            break;
            
        case 'manual-native':
            // באנר נייטיב עם כפתור
            $html .= getManualNativeBannerScript($config);
            break;
            
        case 'custom':
            // באנר מותאם אישית
            $html .= getCustomBannerScript($config);
            break;
            
        default:
            // ברירת מחדל - אוטומטי
            $html .= getAutoNativeBannerScript($config);
    }
    
    // כפתור התקנה אופציונלי
    if ($config['show_install_button']) {
        $html .= getInstallButtonScript();
    }
    
    return $html;
}

/**
 * סקריפט לבאנר נייטיב אוטומטי - מומלץ!
 */
function getAutoNativeBannerScript($config) {
    return '
    <!-- PWA Auto Native Banner (Recommended) -->
    <script src="/pwa/js/pwa-auto-native-prompt.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            console.log("PWA: Using AUTO native banner - will show automatically!");
            
            // אפשר לעקוב אחרי הסטטוס
            setTimeout(function() {
                if (window.pwaAutoPrompt) {
                    if (window.pwaAutoPrompt.isInstalled()) {
                        console.log("PWA: App is already installed");
                    } else if (window.pwaAutoPrompt.wasRecentlyDismissed()) {
                        console.log("PWA: Banner was recently dismissed by user");
                    } else {
                        console.log("PWA: Waiting for browser to show native banner...");
                    }
                }
            }, 2000);
        });
    </script>
    ';
}

/**
 * סקריפט לבאנר נייטיב ידני (עם כפתור)
 */
function getManualNativeBannerScript($config) {
    return '
    <!-- PWA Manual Native Banner (With Button) -->
    <script src="/pwa/js/pwa-native-banner.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            console.log("PWA: Using MANUAL native banner - requires user click");
            
            if (window.pwaNativeBanner) {
                window.pwaNativeBanner.config.buttonText = "' . addslashes($config['install_text']) . '";
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
            console.log("PWA: Using CUSTOM banner");
            
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

/**
 * כפתור התקנה בממשק (אופציונלי)
 */
function getInstallButtonScript() {
    return '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // יצירת כפתור התקנה
            const installBtn = document.createElement("button");
            installBtn.id = "pwa-manual-install-button";
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
                z-index: 9998;
                display: none;
                transition: all 0.3s ease;
            `;
            
            document.body.appendChild(installBtn);
            
            // טיפול בבאנר נייטיב
            let deferredPrompt;
            
            window.addEventListener("beforeinstallprompt", (e) => {
                // אם רוצים כפתור ידני, מבטלים את האוטומטי
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
 * פונקציות עזר
 */
function isPWAInstalled() {
    return isset($_COOKIE['pwa_installed']) && $_COOKIE['pwa_installed'] === 'true';
}

function isStandaloneMode() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
           (isset($_GET['mode']) && $_GET['mode'] === 'standalone');
}