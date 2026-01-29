<?php
/**
 * Notifications Management - ניהול התראות
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות
requireDashboard(['cemetery_manager', 'admin']);

// בדוק הרשאה ספציפית לניהול התראות
if (!isAdmin() && !hasModulePermission('notifications', 'view')) {
    http_response_code(403);
    die('אין לך הרשאה לצפות בדף זה');
}

// טעינת הגדרות משתמש
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';
$userSettingsConn = getDBConnection();
$userId = getCurrentUserId();

function detectDeviceType() {
    if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
        return $_COOKIE['deviceType'];
    }
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

$isDarkMode = isset($userPrefs['darkMode']) && ($userPrefs['darkMode']['value'] === true || $userPrefs['darkMode']['value'] === 'true');
$colorScheme = isset($userPrefs['colorScheme']) ? $userPrefs['colorScheme']['value'] : 'purple';
$fontSize = isset($userPrefs['fontSize']) ? max(10, min(30, (int)$userPrefs['fontSize']['value'])) : 14;

$bodyClasses = [];
$bodyClasses[] = $isDarkMode ? 'dark-theme' : 'light-theme';
if (!$isDarkMode) {
    $bodyClasses[] = 'color-scheme-' . $colorScheme;
}

// בדיקת הרשאות לפעולות שונות
$canCreate = isAdmin() || hasModulePermission('notifications', 'create');
$canEdit = isAdmin() || hasModulePermission('notifications', 'edit');
$canDelete = isAdmin() || hasModulePermission('notifications', 'delete');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול התראות - <?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css?v=2">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/sidebar.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/header.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/notifications/css/notifications.css?v=11">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo implode(' ', $bodyClasses); ?>" style="--font-size-base: <?php echo $fontSize; ?>px;">
    <div class="notifications-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">ניהול התראות</h1>
            <div class="header-actions">
                <a href="logs.php" class="btn btn-secondary">
                    <i class="fas fa-clipboard-list"></i>
                    <span>מרכז לוגים</span>
                </a>
                <a href="test-automation.php" class="btn btn-secondary">
                    <i class="fas fa-vial"></i>
                    <span>בדיקת אוטומציה</span>
                </a>
                <?php if ($canCreate): ?>
                <button type="button" class="btn btn-primary" onclick="NotificationsManager.openCreateForm()">
                    <span class="btn-icon">+</span>
                    יצירת התראה חדשה
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create/Edit Form -->
        <div class="notification-form-container" id="notificationFormContainer" style="display: none;">
            <form id="notificationForm" class="notification-form">
                <input type="hidden" id="notificationId" name="id" value="">

                <div class="form-section">
                    <h3 class="section-title">פרטי ההתראה</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">כותרת <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" required maxlength="255" placeholder="כותרת ההתראה">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="body">תוכן ההודעה <span class="required">*</span></label>
                            <textarea id="body" name="body" class="form-control" required rows="4" placeholder="תוכן ההודעה שיוצג למשתמשים"></textarea>
                        </div>
                    </div>

                    <div class="form-row two-cols">
                        <div class="form-group">
                            <label>סוג ההודעה</label>
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="notification_type" value="info" checked>
                                    <span class="radio-text type-info">מידע</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="notification_type" value="warning">
                                    <span class="radio-text type-warning">אזהרה</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="notification_type" value="urgent">
                                    <span class="radio-text type-urgent">דחוף</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="url">לינק (אופציונלי)</label>
                            <input type="url" id="url" name="url" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">נמענים</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="sendToAll" name="send_to_all" onchange="NotificationsManager.toggleUserSelection()">
                                <span>שלח לכל המשתמשים</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-row" id="userSelectionRow">
                        <div class="form-group">
                            <label>בחירת משתמשים <span class="required">*</span></label>
                            <div class="users-select-container">
                                <div class="users-search">
                                    <input type="text" id="userSearch" class="form-control" placeholder="חיפוש משתמש..." oninput="NotificationsManager.filterUsers(this.value)">
                                </div>
                                <div class="users-list" id="usersList">
                                    <!-- Users will be loaded here -->
                                </div>
                                <div class="selected-users" id="selectedUsersDisplay">
                                    <span class="no-selection">לא נבחרו משתמשים</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">בקשת אישור</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label approval-checkbox">
                                <input type="checkbox" id="requiresApproval" name="requires_approval" onchange="NotificationsManager.toggleApprovalFields()">
                                <span>דרוש אישור ביומטרי מהמשתמש</span>
                            </label>
                            <p class="form-help-text">המשתמש יתבקש לאשר עם טביעת אצבע / Face ID</p>
                        </div>
                    </div>

                    <div class="form-row approval-fields" id="approvalFields" style="display: none;">
                        <div class="form-group">
                            <label for="approvalMessage">הודעת אישור (אופציונלי)</label>
                            <textarea id="approvalMessage" name="approval_message" class="form-control" rows="2" placeholder="הודעה שתוצג למשתמש בעת בקשת האישור..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="approvalExpiry">תוקף האישור</label>
                            <select id="approvalExpiry" name="approval_expiry" class="form-control">
                                <option value="">ללא הגבלה</option>
                                <option value="1">שעה אחת</option>
                                <option value="24">24 שעות</option>
                                <option value="48">48 שעות</option>
                                <option value="168">שבוע</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">זמן שליחה</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="radio-group">
                                <label class="radio-label">
                                    <input type="radio" name="send_time" value="now" checked onchange="NotificationsManager.toggleScheduleFields()">
                                    <span class="radio-text">שלח עכשיו</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="send_time" value="scheduled" onchange="NotificationsManager.toggleScheduleFields()">
                                    <span class="radio-text">תזמן לשליחה</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-row two-cols schedule-fields" id="scheduleFields" style="display: none;">
                        <div class="form-group">
                            <label for="scheduleDate">תאריך <span class="required">*</span></label>
                            <input type="date" id="scheduleDate" name="schedule_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="scheduleTime">שעה <span class="required">*</span></label>
                            <input type="time" id="scheduleTime" name="schedule_time" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="NotificationsManager.closeForm()">ביטול</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">שלח / תזמן</button>
                </div>
            </form>
        </div>

        <!-- Notifications Table -->
        <div class="notifications-table-container">
            <div class="table-header">
                <h3>התראות מתוזמנות</h3>
                <div class="table-filters">
                    <select id="statusFilter" class="form-control" onchange="NotificationsManager.loadNotifications()">
                        <option value="">כל הסטטוסים</option>
                        <option value="pending">ממתינות</option>
                        <option value="sent">נשלחו</option>
                        <option value="cancelled">בוטלו</option>
                        <option value="failed">נכשלו</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table" id="notificationsTable">
                    <thead>
                        <tr>
                            <th>כותרת</th>
                            <th>סוג</th>
                            <th>נמענים</th>
                            <th>זמן שליחה</th>
                            <th>סטטוס</th>
                            <th>נוצר ב</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody id="notificationsTableBody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>

            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-icon">🔔</div>
                <h3>אין התראות</h3>
                <p>לא נמצאו התראות במערכת</p>
            </div>

            <div class="loading-state" id="loadingState">
                <div class="spinner"></div>
                <p>טוען נתונים...</p>
            </div>
        </div>
    </div>

    <script>
        // Global permissions
        window.canCreate = <?php echo $canCreate ? 'true' : 'false'; ?>;
        window.canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
        window.canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;
        window.currentUserId = <?php echo $userId; ?>;
    </script>
    <script src="/dashboard/dashboards/cemeteries/notifications/js/notifications.js?v=1"></script>
</body>
</html>
