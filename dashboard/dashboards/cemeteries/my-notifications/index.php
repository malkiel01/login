<?php
/**
 * My Notifications - ההתראות שלי
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות - כל משתמש מחובר יכול לראות את ההתראות שלו
requireDashboard(['cemetery_manager', 'admin', 'manager', 'employee', 'client']);

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
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ההתראות שלי</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Base CSS -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">

    <!-- Page CSS -->
    <link rel="stylesheet" href="css/my-notifications.css">

    <style>
        :root {
            --base-font-size: <?= $fontSize ?>px;
        }
    </style>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
    <div class="notifications-page">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> ההתראות שלי</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="markAllAsRead()" id="btnMarkAllRead">
                    <i class="fas fa-check-double"></i>
                    <span>סמן הכל כנקרא</span>
                </button>
                <button class="btn btn-icon" onclick="refreshNotifications()" title="רענן">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <!-- Unread Notifications Section -->
        <div class="notifications-section" id="unreadSection">
            <div class="section-header">
                <h2><i class="fas fa-exclamation-circle"></i> התראות חדשות</h2>
                <span class="unread-badge" id="unreadCount">0</span>
            </div>
            <div class="notifications-list" id="unreadList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>טוען התראות...</span>
                </div>
            </div>
        </div>

        <!-- History Section -->
        <div class="notifications-section history-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> היסטוריית התראות</h2>
            </div>
            <div class="notifications-list" id="historyList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>טוען היסטוריה...</span>
                </div>
            </div>

            <!-- Load More Button -->
            <div class="load-more-container" id="loadMoreContainer" style="display: none;">
                <button class="btn btn-secondary" onclick="loadMoreHistory()" id="btnLoadMore">
                    <i class="fas fa-chevron-down"></i> טען עוד
                </button>
            </div>
        </div>
    </div>

    <script>
        const API_URL = 'api/my-notifications-api.php';
        let historyOffset = 0;
        const PAGE_SIZE = 20;

        // Load notifications on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadUnreadNotifications();
            loadHistoryNotifications();
        });

        async function loadUnreadNotifications() {
            const listEl = document.getElementById('unreadList');
            const countEl = document.getElementById('unreadCount');
            const sectionEl = document.getElementById('unreadSection');

            try {
                const response = await fetch(`${API_URL}?action=get_unread`, {
                    credentials: 'include'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load');
                }

                const notifications = data.notifications || [];
                countEl.textContent = notifications.length;

                if (notifications.length === 0) {
                    listEl.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <span>אין התראות חדשות</span>
                        </div>
                    `;
                    document.getElementById('btnMarkAllRead').style.display = 'none';
                } else {
                    listEl.innerHTML = notifications.map(n => renderNotification(n, false)).join('');
                    document.getElementById('btnMarkAllRead').style.display = 'inline-flex';
                }

            } catch (error) {
                console.error('Error loading unread:', error);
                listEl.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>שגיאה בטעינת התראות</span>
                    </div>
                `;
            }
        }

        async function loadHistoryNotifications(append = false) {
            const listEl = document.getElementById('historyList');
            const loadMoreBtn = document.getElementById('loadMoreContainer');

            if (!append) {
                historyOffset = 0;
            }

            try {
                const response = await fetch(`${API_URL}?action=get_history&offset=${historyOffset}&limit=${PAGE_SIZE}`, {
                    credentials: 'include'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load');
                }

                const notifications = data.notifications || [];

                if (!append) {
                    if (notifications.length === 0) {
                        listEl.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <span>אין היסטוריית התראות</span>
                            </div>
                        `;
                        loadMoreBtn.style.display = 'none';
                    } else {
                        listEl.innerHTML = notifications.map(n => renderNotification(n, true)).join('');
                    }
                } else {
                    listEl.insertAdjacentHTML('beforeend', notifications.map(n => renderNotification(n, true)).join(''));
                }

                // Show/hide load more button
                loadMoreBtn.style.display = data.hasMore ? 'block' : 'none';
                historyOffset += notifications.length;

            } catch (error) {
                console.error('Error loading history:', error);
                if (!append) {
                    listEl.innerHTML = `
                        <div class="error-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>שגיאה בטעינת היסטוריה</span>
                        </div>
                    `;
                }
            }
        }

        function renderNotification(notification, isHistory) {
            const typeIcon = getTypeIcon(notification.notification_type);
            const typeClass = notification.notification_type || 'info';
            const timeAgo = formatTimeAgo(notification.created_at);
            const isRead = notification.read_at !== null;

            return `
                <div class="notification-item ${typeClass} ${isRead ? 'read' : 'unread'}" data-id="${notification.id}">
                    <div class="notification-icon">
                        <i class="fas ${typeIcon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-header">
                            <h3 class="notification-title">${escapeHtml(notification.title)}</h3>
                            <span class="notification-time">${timeAgo}</span>
                        </div>
                        <p class="notification-body">${escapeHtml(notification.body)}</p>
                        ${notification.url ? `
                            <a href="${escapeHtml(notification.url)}" class="notification-link">
                                <i class="fas fa-external-link-alt"></i> פתח
                            </a>
                        ` : ''}
                    </div>
                    ${!isHistory && !isRead ? `
                        <button class="btn-mark-read" onclick="markAsRead(${notification.id})" title="סמן כנקרא">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                </div>
            `;
        }

        function getTypeIcon(type) {
            switch (type) {
                case 'warning': return 'fa-exclamation-triangle';
                case 'urgent': return 'fa-bell';
                default: return 'fa-info-circle';
            }
        }

        function formatTimeAgo(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'עכשיו';
            if (diff < 3600) return `לפני ${Math.floor(diff / 60)} דקות`;
            if (diff < 86400) return `לפני ${Math.floor(diff / 3600)} שעות`;
            if (diff < 604800) return `לפני ${Math.floor(diff / 86400)} ימים`;

            return date.toLocaleDateString('he-IL', {
                day: 'numeric',
                month: 'short',
                year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        async function markAsRead(notificationId) {
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'mark_read',
                        notification_id: notificationId
                    })
                });

                const data = await response.json();
                if (data.success) {
                    // Refresh lists
                    await loadUnreadNotifications();
                    await loadHistoryNotifications();
                }
            } catch (error) {
                console.error('Error marking as read:', error);
            }
        }

        async function markAllAsRead() {
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'mark_all_read'
                    })
                });

                const data = await response.json();
                if (data.success) {
                    // Refresh lists
                    await loadUnreadNotifications();
                    await loadHistoryNotifications();
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }

        function loadMoreHistory() {
            loadHistoryNotifications(true);
        }

        function refreshNotifications() {
            loadUnreadNotifications();
            loadHistoryNotifications();
        }
    </script>
</body>
</html>
