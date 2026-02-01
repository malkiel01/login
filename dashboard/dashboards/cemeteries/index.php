<?php

// cemetery_dashboard/index.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php'; // Persistent Auth for PWA/iOS

// בדיקת הרשאות - רק cemetery_manager או admin יכולים לגשת
requireDashboard(['cemetery_manager', 'admin']);

// טעינת הגדרות משתמש מראש למניעת FOUC
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';
$userSettingsConn = getDBConnection();
$userId = getCurrentUserId();

// זיהוי סוג מכשיר מ-User-Agent (fallback אם אין cookie)
function detectDeviceType() {
    // בדוק cookie קודם
    if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
        return $_COOKIE['deviceType'];
    }
    // זיהוי לפי User-Agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'];
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return 'mobile';
        }
    }
    return 'desktop';
}
$detectedDeviceType = detectDeviceType();
$userSettingsManager = new UserSettingsManager($userSettingsConn, $userId, $detectedDeviceType);
$userPrefs = $userSettingsManager->getAllWithDefaults();

// קבלת ערכים להחלה על ה-body
$isDarkMode = isset($userPrefs['darkMode']) && ($userPrefs['darkMode']['value'] === true || $userPrefs['darkMode']['value'] === 'true');
$colorScheme = isset($userPrefs['colorScheme']) ? $userPrefs['colorScheme']['value'] : 'purple';
$fontSize = isset($userPrefs['fontSize']) ? max(10, min(30, (int)$userPrefs['fontSize']['value'])) : 14;
$isCompact = isset($userPrefs['compactMode']) && ($userPrefs['compactMode']['value'] === true || $userPrefs['compactMode']['value'] === 'true');
$sidebarCollapsed = isset($userPrefs['sidebarCollapsed']) && ($userPrefs['sidebarCollapsed']['value'] === true || $userPrefs['sidebarCollapsed']['value'] === 'true');

$bodyClasses = [];
$bodyClasses[] = $isDarkMode ? 'dark-theme' : 'light-theme';
if (!$isDarkMode) {
    $bodyClasses[] = 'color-scheme-' . $colorScheme;
}
if ($isCompact) {
    $bodyClasses[] = 'compact-mode';
}
if ($sidebarCollapsed) {
    $bodyClasses[] = 'sidebar-collapsed';
}
?>
<?php
// טען את הקונפיג תשלומים
$paymentTypesConfig = require $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/payment-types-config.php';

// הכן הרשאות משתמש ל-JavaScript
$userPermissions = getUserPermissionsDetailed();
$isAdminUser = isAdmin();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">
    
    <!-- CSS Files - כל הקבצים כולל החדשים -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/sidebar.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/header.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/cards.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/breadcrumb.css">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/smart-select.css">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/search.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/universal-search.css?v=20260122ci">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/table-manager.css?v=20260124">

    <!-- Table Module - New Modular System with Theme Support -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/table-module/css/table-core.css?v=20260124">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/reports/graves-inventory-report.css">

    <!-- User Preferences (Dark Mode, Compact, etc.) -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">

    <!-- User Permissions for JavaScript -->
    <script>
        window.USER_PERMISSIONS = <?= json_encode($userPermissions) ?>;
        window.IS_ADMIN = <?= $isAdminUser ? 'true' : 'false' ?>;

        // פונקציית עזר לבדיקת הרשאה ספציפית
        window.hasPermission = function(module, action) {
            if (window.IS_ADMIN) return true;
            return window.USER_PERMISSIONS[module] &&
                   window.USER_PERMISSIONS[module].includes(action);
        };

        // פונקציה לבדיקה אם יש הרשאה כלשהי למודול
        window.hasAnyPermission = function(module) {
            if (window.IS_ADMIN) return true;
            return window.USER_PERMISSIONS[module] &&
                   window.USER_PERMISSIONS[module].length > 0;
        };

        // פונקציה לבדיקה אם יכול לצפות במודול
        // edit/create כוללים צפיה - אם יש לך הרשאה לערוך, וודאי שתוכל לראות
        window.canView = function(module) {
            if (window.IS_ADMIN) return true;
            if (!window.USER_PERMISSIONS[module]) return false;
            const perms = window.USER_PERMISSIONS[module];
            return perms.includes('view') || perms.includes('edit') || perms.includes('create');
        };
    </script>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>" data-color-scheme="<?= $isDarkMode ? '' : $colorScheme ?>" style="--base-font-size: <?= $fontSize ?>px;" data-device-type="<?= $detectedDeviceType ?>">
    <!-- SVG Icons - חייב להיות בתחילת ה-body -->
    <svg style="display: none;">
        <!-- Refresh Icon - Modern circular arrows -->
        <symbol id="icon-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.83 1.04 6.48 2.72"/>
            <polyline points="21 3 21 9 15 9"/>
        </symbol>
        
        <!-- Hamburger Menu -->
        <symbol id="icon-menu" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
        </symbol>
        
        <!-- Close -->
        <symbol id="icon-close" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/>
        </symbol>
        
        <!-- Plus -->
        <symbol id="icon-plus" viewBox="0 0 24 24">
            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        
        <!-- Edit -->
        <symbol id="icon-edit" viewBox="0 0 24 24">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/>
        </symbol>
        
        <!-- Delete -->
        <symbol id="icon-delete" viewBox="0 0 24 24">
            <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        
        <!-- Download -->
        <symbol id="icon-download" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5l5 5l5-5m-5 5V3"/>
        </symbol>
        
        <!-- Fullscreen -->
        <symbol id="icon-fullscreen" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
        </symbol>
        
        <!-- Expand/Collapse -->
        <symbol id="icon-expand" viewBox="0 0 24 24">
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        
        <!-- Search -->
        <symbol id="icon-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" fill="none"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
    </svg>

    <div class="dashboard-wrapper">
        <!-- Header -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/header.php'; ?>
        
        <div class="dashboard-container">
            <!-- Sidebar -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/sidebar.php'; ?>
            
            <!-- Overlay for mobile sidebar -->
            <div id="sidebarOverlay" class="sidebar-overlay"></div>
            
            <!-- Main Content -->
            <main class="main-content">
                <!-- Breadcrumb -->
                <div class="breadcrumb" id="breadcrumb">
                    <span class="breadcrumb-item">ראשי</span>
                </div>

                <!-- Entity Title + Actions -->
                <div class="entity-title-container" id="entityTitleContainer">
                    <div class="entity-title-text">
                        <h1 class="entity-title" id="entityTitle"></h1>
                        <span class="entity-subtitle" id="entitySubtitle"></span>
                    </div>
                    <div class="action-buttons">
                        <!-- כפתור הצג חיפוש - מופיע רק כשהחיפוש מכווץ -->
                        <button class="btn-show-search" onclick="UniversalSearch.expandSearchSection(window.currentType || 'cemetery')" title="הצג חיפוש">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <span>הצג חיפוש</span>
                        </button>
                        <button class="btn-search-toggle" onclick="UniversalSearch.toggleOrFocusSearch(window.currentType || 'cemetery')" title="חיפוש">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <span>חיפוש</span>
                        </button>
                        <button id="btnAdd" class="btn btn-primary btn-add" onclick="tableRenderer.openAddModal()">
                            <svg class="icon"><use xlink:href="#icon-plus"></use></svg>
                            <span>הוספה</span>
                        </button>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="data-table" id="mainTable">
                            <thead>
                                <tr id="tableHeaders">
                                    <th>טוען...</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="6" class="text-center">טוען נתונים...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Entity cards for mobile will be generated here -->
                    <div class="entity-cards" id="entityCards"></div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript Files - סדר קריטי! -->

    <!-- 1️⃣ בסיס -->
    <script src="/dashboard/dashboards/cemeteries/js/universal-search.js?v=20260122g"></script>
    <script src="/dashboard/dashboards/cemeteries/js/breadcrumb.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/main.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/sidebar-counts.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/clearDashboard.js"></script>

    <!-- Table Module v3.0.0 - מערכת טבלאות חדשה עם תמיכה בהעדפות משתמש -->
    <script src="/dashboard/dashboards/cemeteries/table-module/js/table-permissions.js?v=20260124"></script>
    <script src="/dashboard/dashboards/cemeteries/table-module/js/table-core.js?v=20260124"></script>

    <script src="/dashboard/dashboards/cemeteries/js/universal-search-init.js"></script>

    <!-- 2️⃣ מערכת פופאפים -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-manager.js"></script>

    <!-- 3️⃣ מערכת טפסים -->
    <script src="/dashboard/dashboards/cemeteries/forms/FormValidations.js"></script>
    <script src="/dashboard/dashboards/cemeteries/forms/payment-display-manager.js"></script>
    <!-- FormHandler removed - migrated to PopupManager (2026-01-23) -->

    <!-- 4️⃣ כרטיסים -->
    <script src="/dashboard/dashboards/cemeteries/js/hierarchy-cards.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/cards.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/responsive.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/unified-table-renderer.js"></script>

    <!-- 5️⃣ OperationManager ו-Utilities (קריטי - לפני המערכת החדשה!) -->
    <script src="/dashboard/dashboards/cemeteries/js/operation-manager.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-common-utils.js"></script>

    <!-- 6️⃣ 🆕 המערכת החדשה - Entity Framework (אחרי הכל!) -->
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-config.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-state-manager.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-loader.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-renderer.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-manager.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-initializer.js"></script>

    <!-- 7️⃣ הקבצים הישנים (יישארו כ-fallback) -->
    <script src="/dashboard/dashboards/cemeteries/js/cemeteries-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/blocks-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/plots-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/area-graves-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/graves-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/customers-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/purchases-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/burials-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/payments-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/residency-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/countries-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/cities-management.js"></script>

    <script src="/dashboard/dashboards/cemeteries/js/live-search.js"></script>

    <script src="/dashboard/dashboards/cemeteries/js/reports/graves-inventory-report.js"></script>

    <!-- 🗺️ מפה -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // הגדרת worker של PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <!-- Map v2 Launcher -->
    <script src="/dashboard/dashboards/cemeteries/js/map-launcher.js"></script>

    <!-- 7️⃣ אתחול -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
            if (typeof initializeEntityItems === 'function') {
                initializeEntityItems();
            }
            if (typeof handleTableResponsive === 'function') {
                handleTableResponsive();
            }
        });
    </script>

    <script>
        window.PAYMENT_TYPES_CONFIG = <?php echo json_encode($paymentTypesConfig['payment_types']); ?>;
    </script>
    <script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>

    <!-- User Settings -->
    <script src="/dashboard/dashboards/cemeteries/user-settings/js/user-settings-storage.js"></script>
    <script src="/dashboard/dashboards/cemeteries/user-settings/js/user-settings-core.js"></script>
    <script>
        // פתיחת הגדרות משתמש
        function openUserSettings() {
            if (typeof PopupManager !== 'undefined') {
                // זיהוי סוג מכשיר והעברתו לדף ההגדרות
                const deviceType = (typeof UserSettingsStorage !== 'undefined')
                    ? UserSettingsStorage.getDeviceType()
                    : (window.innerWidth < 768 ? 'mobile' : 'desktop');

                const profileLabel = deviceType === 'mobile' ? 'מובייל' : 'דסקטופ';

                PopupManager.create({
                    id: 'user-settings-popup',
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/user-settings/settings-page.php?deviceType=' + deviceType,
                    title: 'הגדרות אישיות - ' + profileLabel,
                    width: 700,
                    height: 600
                });
            }
        }

        // אתחול הגדרות משתמש בטעינת הדף
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof UserSettings !== 'undefined') {
                UserSettings.init().then(() => {
                    console.log('UserSettings initialized');
                }).catch(err => {
                    console.warn('UserSettings init failed (table may not exist yet):', err.message);
                });
            }

            // בקשת הרשאת התראות (רק אחרי התחברות!)
            setTimeout(async function() {
                // בדוק אם כבר ביקשנו לאחרונה
                const lastPrompt = localStorage.getItem('last_notification_prompt');
                const now = Date.now();

                if (lastPrompt && (now - parseInt(lastPrompt)) < 3600000) {
                    console.log('[Push] כבר ביקשנו הרשאה לאחרונה, מדלג...');
                    return;
                }

                // בדוק אם כבר נדחה 3 פעמים
                const deniedCount = parseInt(localStorage.getItem('notification_denied_count') || '0');
                if (deniedCount >= 3) {
                    console.log('[Push] המשתמש דחה 3 פעמים, מפסיק לבקש');
                    return;
                }

                // רק אם ההרשאה במצב default
                if ('Notification' in window && Notification.permission === 'default') {
                    if (confirm('האם לאפשר התראות מהמערכת?')) {
                        try {
                            const permission = await Notification.requestPermission();
                            console.log('[Push] Permission result:', permission);

                            if (permission === 'granted') {
                                // עשה subscribe
                                if (typeof PushSubscriptionManager !== 'undefined') {
                                    const result = await PushSubscriptionManager.subscribe();
                                    console.log('[Push] Subscribe result:', result);
                                }
                                localStorage.removeItem('notification_denied_count');
                            } else {
                                localStorage.setItem('notification_denied_count', deniedCount + 1);
                            }
                        } catch (err) {
                            console.error('[Push] Error:', err);
                        }
                    } else {
                        localStorage.setItem('notification_denied_count', deniedCount + 1);
                    }
                    localStorage.setItem('last_notification_prompt', now);
                }
            }, 3000); // 3 שניות אחרי טעינת הדף
        });

        // האזנה לשינויי הגדרות מהפופאפ
        window.addEventListener('message', async function(event) {
            if (event.data && event.data.type === 'userSettingChanged') {
                const { key, value } = event.data;
                console.log('Setting changed from popup:', key, value);

                if (typeof UserSettings !== 'undefined') {
                    // עדכון הערך בcache המקומי
                    UserSettings.set(key, value).catch(() => {});
                    // החלה מיידית על הממשק
                    UserSettings.applyToUI();
                }
            }

            // בקשת הרשאת התראות מ-iframe
            if (event.data && event.data.type === 'requestNotificationPermission') {
                console.log('[Main] Received notification permission request from iframe');
                try {
                    const permission = await Notification.requestPermission();
                    console.log('[Main] Permission result:', permission);

                    // אם אושר, רשום ל-push לפני שליחת התשובה!
                    if (permission === 'granted' && typeof PushSubscriptionManager !== 'undefined') {
                        const result = await PushSubscriptionManager.subscribe();
                        console.log('[Main] Push subscription result:', result);
                    }

                    // שלח תשובה חזרה ל-iframe (אחרי ה-subscribe!)
                    if (event.source) {
                        event.source.postMessage({
                            type: 'notificationPermissionResult',
                            permission: permission
                        }, '*');
                    }
                } catch (error) {
                    console.error('[Main] Error requesting permission:', error);
                    if (event.source) {
                        event.source.postMessage({
                            type: 'notificationPermissionResult',
                            permission: 'error',
                            error: error.message
                        }, '*');
                    }
                }
            }

            // בקשת סטטוס push מ-iframe
            if (event.data && event.data.type === 'getPushStatus') {
                console.log('[Main] Received push status request from iframe');
                try {
                    const permission = Notification.permission;
                    let hasSubscription = false;

                    if ('serviceWorker' in navigator && 'PushManager' in window) {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.getSubscription();
                        hasSubscription = !!subscription;
                    }

                    if (event.source) {
                        event.source.postMessage({
                            type: 'pushStatusResult',
                            permission: permission,
                            hasSubscription: hasSubscription
                        }, '*');
                    }
                    console.log('[Main] Sent push status:', { permission, hasSubscription });
                } catch (error) {
                    console.error('[Main] Error getting push status:', error);
                    if (event.source) {
                        event.source.postMessage({
                            type: 'pushStatusResult',
                            permission: 'error',
                            hasSubscription: false,
                            error: error.message
                        }, '*');
                    }
                }
            }

            // פתיחת מסך אישור מ-iframe (ההתראות שלי)
            if (event.data && event.data.type === 'OPEN_APPROVAL_MODAL') {
                console.log('[Main] Received OPEN_APPROVAL_MODAL request from iframe, notificationId:', event.data.notificationId);
                if (typeof ApprovalModal !== 'undefined' && event.data.notificationId) {
                    // הגדרת callback לכשהמודאל ייסגר - לשלוח עדכון לאייפריים
                    ApprovalModal.onClose = function() {
                        console.log('[Main] ApprovalModal closed, notifying iframe to refresh');
                        if (event.source) {
                            event.source.postMessage({ type: 'REFRESH_NOTIFICATIONS' }, '*');
                        }
                    };
                    ApprovalModal.show(event.data.notificationId);
                } else {
                    console.error('[Main] ApprovalModal not available or missing notificationId');
                }
            }

            // ביטול push מ-iframe
            if (event.data && event.data.type === 'disablePushNotifications') {
                console.log('[Main] Received disable push request from iframe');
                try {
                    let success = false;
                    if (typeof PushSubscriptionManager !== 'undefined') {
                        const result = await PushSubscriptionManager.unsubscribe();
                        success = result.success;
                    }

                    if (event.source) {
                        event.source.postMessage({
                            type: 'disablePushResult',
                            success: success
                        }, '*');
                    }
                    console.log('[Main] Disable push result:', success);
                } catch (error) {
                    console.error('[Main] Error disabling push:', error);
                    if (event.source) {
                        event.source.postMessage({
                            type: 'disablePushResult',
                            success: false,
                            error: error.message
                        }, '*');
                    }
                }
            }
        });
    </script>

    <?php
    // Persistent Auth Scripts (PWA/iOS support)
    echo getTokenInitScript();
    echo getLogoutScript();
    ?>

    <!-- Push Notification Listener -->
    <script src="/push/listener.js"></script>
    <!-- Web Push Subscription Manager -->
    <script src="/push/push-subscribe.js"></script>
    <!-- Notification Modal - Dedicated screen for all notifications -->
    <script src="/js/notification-modal.js"></script>
    <!-- Notification Templates - Different display types for notifications -->
    <script src="/dashboard/dashboards/cemeteries/notifications/templates/load-templates.js"></script>
    <!-- Approval Modal for approval-type notifications -->
    <script src="/js/biometric-auth.js"></script>
    <script src="/js/approval-modal.js"></script>
</body>
</html>
