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
        <!-- Notification Detail Modal Popup -->
        <div class="notification-modal-overlay" id="notificationModal">
            <div class="notification-modal">
                <div class="notification-modal-header">
                    <h3 id="notificationModalTitle">פרטי התראה</h3>
                    <button class="modal-close-btn" onclick="closeNotificationModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="notification-modal-body" id="notificationModalBody">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>טוען...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Modal Popup (for approval-specific notifications) -->
        <div class="notification-modal-overlay" id="approvalModal">
            <div class="notification-modal">
                <div class="notification-modal-header">
                    <h3 id="modalTitle">פרטי אישור</h3>
                    <button class="modal-close-btn" onclick="closeApprovalModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="notification-modal-body" id="modalBody">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>טוען...</span>
                    </div>
                </div>
            </div>
        </div>

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

            // שמירת הנתונים ב-data attribute לשימוש בפופאפ
            const notificationData = encodeURIComponent(JSON.stringify(notification));

            // בדיקה אם זו התראת אישור והצגת סטטוס
            let approvalBadgeHtml = '';
            if (notification.requires_approval || (notification.url && notification.url.includes('approve.php'))) {
                const status = notification.approval_status;
                let badgeClass = '';
                let badgeIcon = '';
                let badgeText = '';

                switch (status) {
                    case 'approved':
                        badgeClass = 'approval-badge-approved';
                        badgeIcon = 'fa-check-circle';
                        badgeText = 'אושר';
                        break;
                    case 'rejected':
                        badgeClass = 'approval-badge-rejected';
                        badgeIcon = 'fa-times-circle';
                        badgeText = 'נדחה';
                        break;
                    case 'expired':
                        badgeClass = 'approval-badge-expired';
                        badgeIcon = 'fa-clock';
                        badgeText = 'פג תוקף';
                        break;
                    default:
                        badgeClass = 'approval-badge-pending';
                        badgeIcon = 'fa-hourglass-half';
                        badgeText = 'ממתין לאישור';
                }

                const respondedAt = notification.approval_responded_at
                    ? ` • ${formatTimeAgo(notification.approval_responded_at)}`
                    : '';

                approvalBadgeHtml = `
                    <div class="approval-badge ${badgeClass}">
                        <i class="fas ${badgeIcon}"></i>
                        <span>${badgeText}${respondedAt}</span>
                    </div>
                `;
            }

            return `
                <div class="notification-item ${typeClass} ${isRead ? 'read' : 'unread'}"
                     data-id="${notification.id}"
                     data-notification="${notificationData}"
                     onclick="openNotificationDetail(this)">
                    <div class="notification-icon">
                        <i class="fas ${typeIcon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-header">
                            <h3 class="notification-title">${escapeHtml(notification.title)}</h3>
                            <span class="notification-time">${timeAgo}</span>
                        </div>
                        <p class="notification-body">${escapeHtml(notification.body)}</p>
                        ${approvalBadgeHtml}
                    </div>
                    ${!isHistory && !isRead ? `
                        <button class="btn-mark-read" onclick="event.stopPropagation(); markAsRead(${notification.id})" title="סמן כנקרא">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                </div>
            `;
        }

        // חילוץ ID מה-URL של approve.php
        function extractIdFromUrl(url) {
            const match = url.match(/id=(\d+)/);
            return match ? parseInt(match[1]) : null;
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

        // ========== Notification Detail Modal Functions ==========

        function openNotificationDetail(element) {
            const notificationData = element.getAttribute('data-notification');
            if (!notificationData) return;

            const notification = JSON.parse(decodeURIComponent(notificationData));

            // בדיקה אם זו התראת אישור
            const isApprovalNotification = notification.url && notification.url.includes('approve.php');

            if (isApprovalNotification) {
                // פתיחת מודל אישור
                const scheduledId = notification.scheduled_notification_id || extractIdFromUrl(notification.url);
                openApprovalModal(scheduledId, null);
                return;
            }

            // פתיחת מודל התראה רגילה
            const modal = document.getElementById('notificationModal');
            const modalTitle = document.getElementById('notificationModalTitle');
            const modalBody = document.getElementById('notificationModalBody');

            modalTitle.textContent = notification.title || 'פרטי התראה';

            const typeIcon = getTypeIcon(notification.notification_type);
            const typeClass = notification.notification_type || 'info';
            const isRead = notification.read_at !== null;
            const createdDate = formatDateTime(notification.created_at);

            let statusHtml = '';
            if (isRead) {
                statusHtml = `
                    <div class="notification-status status-read">
                        <i class="fas fa-check-circle"></i>
                        <span>נקראה</span>
                    </div>
                `;
            } else {
                statusHtml = `
                    <div class="notification-status status-unread">
                        <i class="fas fa-bell"></i>
                        <span>לא נקראה</span>
                    </div>
                `;
            }

            let urlHtml = '';
            if (notification.url) {
                urlHtml = `
                    <div class="notification-detail-action">
                        <a href="${escapeHtml(notification.url)}" class="btn btn-primary" target="_blank">
                            <i class="fas fa-external-link-alt"></i>
                            <span>פתח קישור</span>
                        </a>
                    </div>
                `;
            }

            modalBody.innerHTML = `
                <div class="notification-detail-content">
                    <div class="notification-detail-icon ${typeClass}">
                        <i class="fas ${typeIcon}"></i>
                    </div>

                    ${statusHtml}

                    <div class="notification-detail-message">
                        <h4>תוכן ההתראה:</h4>
                        <p>${escapeHtml(notification.body) || 'אין תוכן'}</p>
                    </div>

                    <div class="notification-detail-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>תאריך: ${createdDate}</span>
                        </div>
                        ${notification.delivered_at ? `
                            <div class="meta-item">
                                <i class="fas fa-check"></i>
                                <span>נמסרה: ${formatDateTime(notification.delivered_at)}</span>
                            </div>
                        ` : ''}
                    </div>

                    ${urlHtml}

                    ${!isRead ? `
                        <div class="notification-detail-action">
                            <button class="btn btn-secondary" onclick="markAsReadAndClose(${notification.id})">
                                <i class="fas fa-check"></i>
                                <span>סמן כנקראה</span>
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;

            modal.style.display = 'flex';
        }

        function closeNotificationModal() {
            const modal = document.getElementById('notificationModal');
            modal.style.display = 'none';
        }

        async function markAsReadAndClose(notificationId) {
            await markAsRead(notificationId);
            closeNotificationModal();
        }

        // Event listeners for notification modal
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotificationModal();
            }
        });

        // ========== Approval Modal Functions ==========

        async function openApprovalModal(notificationId, event) {
            if (event) event.preventDefault();
            console.log('[MyNotifications] openApprovalModal called with id:', notificationId);

            const modal = document.getElementById('approvalModal');
            const modalBody = document.getElementById('modalBody');
            const modalTitle = document.getElementById('modalTitle');

            if (!modal) {
                console.error('[MyNotifications] Modal element not found!');
                return;
            }

            // הצגת המודל עם טעינה
            modal.style.display = 'flex';
            console.log('[MyNotifications] Modal displayed, modal element:', modal);
            console.log('[MyNotifications] Modal computed style display:', getComputedStyle(modal).display);
            console.log('[MyNotifications] Modal computed style zIndex:', getComputedStyle(modal).zIndex);
            modalBody.innerHTML = `
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>טוען פרטי אישור...</span>
                </div>
            `;

            try {
                // קריאה ל-API לקבלת פרטי ההתראה והאישור
                console.log('[MyNotifications] Fetching notification data...');
                const response = await fetch(`/dashboard/dashboards/cemeteries/notifications/api/approval-api.php?action=get_notification&id=${notificationId}`, {
                    credentials: 'include'
                });
                console.log('[MyNotifications] Response status:', response.status);
                const data = await response.json();
                console.log('[MyNotifications] Response data:', data);

                if (!data.success) {
                    throw new Error(data.error || 'שגיאה בטעינת הנתונים');
                }

                const notification = data.notification;
                const approval = data.approval;
                const isExpired = data.expired;

                // עדכון הכותרת
                modalTitle.textContent = notification.title || 'פרטי אישור';

                // בניית התוכן
                let statusHtml = '';
                let statusClass = '';
                let statusIcon = '';
                let statusText = '';

                if (approval) {
                    switch (approval.status) {
                        case 'approved':
                            statusClass = 'status-approved';
                            statusIcon = 'fa-check-circle';
                            statusText = 'אושר';
                            break;
                        case 'rejected':
                            statusClass = 'status-rejected';
                            statusIcon = 'fa-times-circle';
                            statusText = 'נדחה';
                            break;
                        case 'expired':
                            statusClass = 'status-expired';
                            statusIcon = 'fa-clock';
                            statusText = 'פג תוקף';
                            break;
                        default:
                            statusClass = 'status-pending';
                            statusIcon = 'fa-hourglass-half';
                            statusText = 'ממתין לתגובה';
                    }

                    statusHtml = `
                        <div class="approval-status ${statusClass}">
                            <i class="fas ${statusIcon}"></i>
                            <span>${statusText}</span>
                        </div>
                        ${approval.responded_at ? `
                            <div class="approval-date">
                                <i class="fas fa-calendar-alt"></i>
                                <span>תאריך תגובה: ${formatDateTime(approval.responded_at)}</span>
                            </div>
                        ` : ''}
                        ${approval.biometric_verified ? `
                            <div class="biometric-badge">
                                <i class="fas fa-fingerprint"></i>
                                <span>אומת ביומטרית</span>
                            </div>
                        ` : ''}
                    `;
                } else if (isExpired) {
                    statusHtml = `
                        <div class="approval-status status-expired">
                            <i class="fas fa-clock"></i>
                            <span>פג תוקף האישור</span>
                        </div>
                    `;
                } else {
                    statusHtml = `
                        <div class="approval-status status-pending">
                            <i class="fas fa-hourglass-half"></i>
                            <span>ממתין לתגובה</span>
                        </div>
                    `;
                }

                modalBody.innerHTML = `
                    <div class="approval-content">
                        ${statusHtml}
                        ${notification.body ? `
                            <div class="approval-message">
                                <h4>תוכן ההודעה:</h4>
                                <p>${escapeHtml(notification.body)}</p>
                            </div>
                        ` : ''}
                        ${notification.approval_message ? `
                            <div class="approval-message">
                                <h4>הודעת אישור:</h4>
                                <p>${escapeHtml(notification.approval_message)}</p>
                            </div>
                        ` : ''}
                        ${notification.creator_name ? `
                            <div class="approval-meta">
                                <i class="fas fa-user"></i>
                                <span>נשלח על ידי: ${escapeHtml(notification.creator_name)}</span>
                            </div>
                        ` : ''}
                        ${notification.approval_expires_at ? `
                            <div class="approval-meta">
                                <i class="fas fa-clock"></i>
                                <span>תוקף: ${formatDateTime(notification.approval_expires_at)}</span>
                            </div>
                        ` : ''}
                    </div>
                `;

            } catch (error) {
                console.error('Error loading approval:', error);
                modalBody.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>${error.message || 'שגיאה בטעינת הנתונים'}</span>
                    </div>
                `;
            }
        }

        function closeApprovalModal() {
            const modal = document.getElementById('approvalModal');
            modal.style.display = 'none';
        }

        // סגירה בלחיצה על הרקע
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApprovalModal();
            }
        });

        // סגירה ב-ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeApprovalModal();
            }
        });

        function formatDateTime(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleString('he-IL', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>
</body>
</html>
