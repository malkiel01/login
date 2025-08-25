<?php
/**
 * PWA Native Initialization
 * אתחול PWA עם באנר נייטיב בלבד
 */

/**
 * מחזיר את ה-headers ל-PWA
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
 * מחזיר את הסקריפטים הבסיסיים
 */
function getPWAScripts($options = []) {
    $defaults = [
        'use_native_prompt' => true,  // השתמש בבאנר נייטיב
        'show_install_button' => false // הצג כפתור התקנה בממשק
    ];
    
    $config = array_merge($defaults, $options);
    
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
    
    // הוסף את מנהל הבאנר הנייטיב
    if ($config['use_native_prompt']) {
        $html .= '
    <!-- PWA Native Prompt Manager -->
    <script src="/pwa/js/pwa-native-prompt.js"></script>
    ';
    }
    
    // אם רוצים כפתור התקנה בממשק
    if ($config['show_install_button']) {
        $html .= '
    <script>
        // הוסף כפתור התקנה לממשק
        document.addEventListener("DOMContentLoaded", function() {
            // מצא את המקום לכפתור (לדוגמה בהדר)
            const header = document.querySelector(".header-content");
            if (header && window.pwaPrompt && !window.pwaPrompt.isInstalled()) {
                const installBtn = document.createElement("button");
                installBtn.className = "pwa-install-trigger";
                installBtn.innerHTML = "📱 התקן אפליקציה";
                installBtn.style.cssText = `
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-size: 14px;
                    cursor: pointer;
                    margin-left: 10px;
                    display: none;
                `;
                header.appendChild(installBtn);
            }
        });
    </script>
        ';
    }
    
    return $html;
}

/**
 * בדיקה אם במצב PWA
 */
function isPWAMode() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
           (isset($_GET['mode']) && $_GET['mode'] === 'standalone');
}