<?php
/**
 * Notification Logs - מרכז לוגים להתראות
 * @version 1.0.0
 * @created 2026-01-29
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// Permission check
requireDashboard(['cemetery_manager', 'admin']);

if (!isAdmin() && !hasModulePermission('notifications', 'view')) {
    http_response_code(403);
    die('אין לך הרשאה לצפות בדף זה');
}

// Load user settings
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';
require_once __DIR__ . '/core/UserAgentParser.php';
$userSettingsConn = getDBConnection();
$userId = getCurrentUserId();

$detectedDeviceType = UserAgentParser::detectDeviceType();
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
    <title>מרכז לוגים - התראות</title>
    <link rel="icon" href="data:,">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/notifications/css/notifications.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/notifications/css/logs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo implode(' ', $bodyClasses); ?>" style="--font-size-base: <?php echo $fontSize; ?>px;">
    <div class="logs-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-right">
                <a href="index.php" class="btn btn-secondary btn-back">
                    <i class="fas fa-arrow-right"></i>
                    חזרה לניהול התראות
                </a>
                <h1 class="page-title">
                    <i class="fas fa-clipboard-list"></i>
                    מרכז לוגים
                </h1>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-secondary" onclick="LogsManager.exportCSV()">
                    <i class="fas fa-download"></i>
                    <span>ייצוא CSV</span>
                </button>
                <button type="button" class="btn btn-secondary" onclick="LogsManager.refresh()">
                    <i class="fas fa-sync-alt"></i>
                    <span>רענן</span>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="statSendAttempts">-</span>
                    <span class="stat-label">ניסיונות שליחה</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="statDelivered">-</span>
                    <span class="stat-label">נמסרו בהצלחה</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="statFailed">-</span>
                    <span class="stat-label">נכשלו</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value" id="statDeliveryRate">-</span>
                    <span class="stat-label">אחוז הצלחה</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-row">
                <div class="filter-group">
                    <label>תקופה</label>
                    <select id="filterPeriod" onchange="LogsManager.onPeriodChange()">
                        <option value="1h">שעה אחרונה</option>
                        <option value="24h" selected>24 שעות</option>
                        <option value="7d">7 ימים</option>
                        <option value="30d">30 ימים</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>סוג אירוע</label>
                    <select id="filterEventType" onchange="LogsManager.applyFilters()">
                        <option value="">הכל</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>מזהה התראה</label>
                    <input type="number" id="filterNotificationId" placeholder="הכנס מספר..." onchange="LogsManager.applyFilters()">
                </div>
                <div class="filter-group">
                    <label>מזהה משתמש</label>
                    <input type="number" id="filterUserId" placeholder="הכנס מספר..." onchange="LogsManager.applyFilters()">
                </div>
                <button type="button" class="btn btn-secondary btn-clear-filters" onclick="LogsManager.clearFilters()">
                    <i class="fas fa-times"></i>
                    נקה פילטרים
                </button>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="logs-table-container">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>זמן</th>
                        <th>אירוע</th>
                        <th>התראה</th>
                        <th>משתמש</th>
                        <th>מכשיר</th>
                        <th>סטטוס</th>
                        <th>פרטים</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <tr class="loading-row">
                        <td colspan="7">
                            <div class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>טוען לוגים...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Load More -->
        <div class="load-more-container" id="loadMoreContainer" style="display: none;">
            <button type="button" class="btn btn-secondary" onclick="LogsManager.loadMore()">
                <i class="fas fa-chevron-down"></i>
                טען עוד
            </button>
        </div>

        <!-- Log Detail Modal -->
        <div class="modal-overlay" id="logDetailModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>פרטי לוג</h3>
                    <button type="button" class="modal-close" onclick="LogsManager.closeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="logDetailBody">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script>
    const LogsManager = {
        logs: [],
        offset: 0,
        limit: 50,
        eventTypes: {},
        filters: {},

        async init() {
            await this.loadEventTypes();
            await this.loadStats();
            await this.loadLogs();
        },

        async loadEventTypes() {
            try {
                const response = await fetch('api/logs-api.php?action=event_types');
                const data = await response.json();
                if (data.success) {
                    this.eventTypes = data.event_types;
                    this.populateEventTypeFilter();
                }
            } catch (error) {
                console.error('Error loading event types:', error);
            }
        },

        populateEventTypeFilter() {
            const select = document.getElementById('filterEventType');
            for (const [value, label] of Object.entries(this.eventTypes)) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            }
        },

        async loadStats() {
            const period = document.getElementById('filterPeriod').value;
            try {
                const response = await fetch(`api/logs-api.php?action=stats&period=${period}`);
                const data = await response.json();
                if (data.success) {
                    const stats = data.stats;
                    const counts = stats.event_counts || {};

                    document.getElementById('statSendAttempts').textContent =
                        (counts.send_attempt || 0).toLocaleString();
                    document.getElementById('statDelivered').textContent =
                        (counts.delivered || 0).toLocaleString();
                    document.getElementById('statFailed').textContent =
                        (counts.failed || 0).toLocaleString();
                    document.getElementById('statDeliveryRate').textContent =
                        stats.delivery_rate + '%';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        },

        async loadLogs(append = false) {
            if (!append) {
                this.offset = 0;
                document.getElementById('logsTableBody').innerHTML = `
                    <tr class="loading-row">
                        <td colspan="7">
                            <div class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>טוען לוגים...</span>
                            </div>
                        </td>
                    </tr>
                `;
            }

            const params = new URLSearchParams({
                action: 'list',
                limit: this.limit,
                offset: this.offset,
                ...this.filters
            });

            // Add date filters based on period
            const period = document.getElementById('filterPeriod').value;
            const now = new Date();
            let dateFrom;
            switch (period) {
                case '1h':
                    dateFrom = new Date(now - 60 * 60 * 1000);
                    break;
                case '24h':
                    dateFrom = new Date(now - 24 * 60 * 60 * 1000);
                    break;
                case '7d':
                    dateFrom = new Date(now - 7 * 24 * 60 * 60 * 1000);
                    break;
                case '30d':
                    dateFrom = new Date(now - 30 * 24 * 60 * 60 * 1000);
                    break;
            }
            if (dateFrom) {
                params.set('date_from', dateFrom.toISOString().slice(0, 19).replace('T', ' '));
            }

            try {
                const response = await fetch(`api/logs-api.php?${params}`);
                const data = await response.json();

                if (data.success) {
                    if (append) {
                        this.logs = [...this.logs, ...data.logs];
                    } else {
                        this.logs = data.logs;
                    }
                    this.renderLogs(append);
                    document.getElementById('loadMoreContainer').style.display =
                        data.hasMore ? 'block' : 'none';
                    this.offset += data.logs.length;
                }
            } catch (error) {
                console.error('Error loading logs:', error);
                document.getElementById('logsTableBody').innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="error-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>שגיאה בטעינת לוגים</span>
                            </div>
                        </td>
                    </tr>
                `;
            }
        },

        renderLogs(append = false) {
            const tbody = document.getElementById('logsTableBody');

            if (!append) {
                tbody.innerHTML = '';
            }

            if (this.logs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <span>אין לוגים להצגה</span>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            const startIndex = append ? this.logs.length - (this.offset - (this.logs.length - this.limit)) : 0;
            const logsToRender = append ? this.logs.slice(-this.limit) : this.logs;

            for (const log of logsToRender) {
                tbody.appendChild(this.createLogRow(log));
            }
        },

        createLogRow(log) {
            const tr = document.createElement('tr');
            tr.className = `log-row log-${log.event_type}`;
            tr.onclick = () => this.showLogDetail(log);

            const eventLabel = this.eventTypes[log.event_type] || log.event_type;
            const eventBadgeClass = this.getEventBadgeClass(log.event_type);

            const deviceInfo = [log.device_type, log.os, log.browser]
                .filter(Boolean)
                .join(' / ') || '-';

            let statusHtml = '';
            if (log.http_status) {
                const statusClass = log.http_status >= 200 && log.http_status < 300 ? 'status-success' : 'status-error';
                statusHtml = `<span class="status-badge ${statusClass}">${log.http_status}</span>`;
            } else if (log.error_message) {
                statusHtml = `<span class="status-badge status-error">שגיאה</span>`;
            } else if (log.event_type === 'delivered') {
                statusHtml = `<span class="status-badge status-success">OK</span>`;
            }

            tr.innerHTML = `
                <td class="log-time">${this.formatTime(log.created_at)}</td>
                <td><span class="event-badge ${eventBadgeClass}">${eventLabel}</span></td>
                <td>${log.notification_id || '-'}</td>
                <td>${log.user_name || log.user_id || '-'}</td>
                <td class="log-device">${deviceInfo}</td>
                <td>${statusHtml}</td>
                <td>
                    <button class="btn-view-details" onclick="event.stopPropagation(); LogsManager.showLogDetail(${JSON.stringify(log).replace(/"/g, '&quot;')})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            `;

            return tr;
        },

        getEventBadgeClass(eventType) {
            const classes = {
                'created': 'badge-info',
                'scheduled': 'badge-info',
                'send_attempt': 'badge-neutral',
                'delivered': 'badge-success',
                'failed': 'badge-danger',
                'retry': 'badge-warning',
                'viewed': 'badge-info',
                'clicked': 'badge-info',
                'read': 'badge-success',
                'approval_sent': 'badge-approval',
                'approved': 'badge-success',
                'rejected': 'badge-danger',
                'expired': 'badge-warning',
                'subscription_created': 'badge-info',
                'subscription_removed': 'badge-warning',
                'test_started': 'badge-neutral',
                'test_completed': 'badge-neutral'
            };
            return classes[eventType] || 'badge-neutral';
        },

        formatTime(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'עכשיו';
            if (diff < 3600) return `לפני ${Math.floor(diff / 60)} דקות`;
            if (diff < 86400) return `לפני ${Math.floor(diff / 3600)} שעות`;

            return date.toLocaleString('he-IL', {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        showLogDetail(log) {
            const modal = document.getElementById('logDetailModal');
            const body = document.getElementById('logDetailBody');

            const eventLabel = this.eventTypes[log.event_type] || log.event_type;
            const extraData = log.extra_data || {};

            body.innerHTML = `
                <div class="log-detail">
                    <div class="detail-section">
                        <h4>פרטי אירוע</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">סוג אירוע:</span>
                                <span class="detail-value">${eventLabel}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">זמן:</span>
                                <span class="detail-value">${new Date(log.created_at).toLocaleString('he-IL')}</span>
                            </div>
                            ${log.notification_id ? `
                            <div class="detail-item">
                                <span class="detail-label">מזהה התראה:</span>
                                <span class="detail-value">${log.notification_id}</span>
                            </div>
                            ` : ''}
                            ${log.user_id ? `
                            <div class="detail-item">
                                <span class="detail-label">משתמש:</span>
                                <span class="detail-value">${log.user_name || log.user_id}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>

                    ${log.notification_title ? `
                    <div class="detail-section">
                        <h4>תוכן ההתראה</h4>
                        <div class="notification-preview">
                            <strong>${this.escapeHtml(log.notification_title)}</strong>
                            ${log.notification_body ? `<p>${this.escapeHtml(log.notification_body)}</p>` : ''}
                        </div>
                    </div>
                    ` : ''}

                    ${log.device_type || log.os || log.browser ? `
                    <div class="detail-section">
                        <h4>פרטי מכשיר</h4>
                        <div class="detail-grid">
                            ${log.device_type ? `
                            <div class="detail-item">
                                <span class="detail-label">סוג מכשיר:</span>
                                <span class="detail-value">${log.device_type}</span>
                            </div>
                            ` : ''}
                            ${log.os ? `
                            <div class="detail-item">
                                <span class="detail-label">מערכת הפעלה:</span>
                                <span class="detail-value">${log.os}</span>
                            </div>
                            ` : ''}
                            ${log.browser ? `
                            <div class="detail-item">
                                <span class="detail-label">דפדפן:</span>
                                <span class="detail-value">${log.browser}</span>
                            </div>
                            ` : ''}
                            ${log.ip_address ? `
                            <div class="detail-item">
                                <span class="detail-label">כתובת IP:</span>
                                <span class="detail-value">${log.ip_address}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}

                    ${log.http_status || log.error_message ? `
                    <div class="detail-section">
                        <h4>תגובת שרת</h4>
                        <div class="detail-grid">
                            ${log.http_status ? `
                            <div class="detail-item">
                                <span class="detail-label">קוד HTTP:</span>
                                <span class="detail-value ${log.http_status >= 200 && log.http_status < 300 ? 'text-success' : 'text-danger'}">${log.http_status}</span>
                            </div>
                            ` : ''}
                            ${log.error_code ? `
                            <div class="detail-item">
                                <span class="detail-label">קוד שגיאה:</span>
                                <span class="detail-value text-danger">${log.error_code}</span>
                            </div>
                            ` : ''}
                            ${log.error_message ? `
                            <div class="detail-item full-width">
                                <span class="detail-label">הודעת שגיאה:</span>
                                <span class="detail-value text-danger">${this.escapeHtml(log.error_message)}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}

                    ${Object.keys(extraData).length > 0 ? `
                    <div class="detail-section">
                        <h4>נתונים נוספים</h4>
                        <pre class="extra-data">${JSON.stringify(extraData, null, 2)}</pre>
                    </div>
                    ` : ''}
                </div>
            `;

            modal.style.display = 'flex';
        },

        closeModal() {
            document.getElementById('logDetailModal').style.display = 'none';
        },

        escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        async applyFilters() {
            this.filters = {};

            const eventType = document.getElementById('filterEventType').value;
            const notificationId = document.getElementById('filterNotificationId').value;
            const userId = document.getElementById('filterUserId').value;

            if (eventType) this.filters.event_type = eventType;
            if (notificationId) this.filters.notification_id = notificationId;
            if (userId) this.filters.user_id = userId;

            await this.loadLogs();
        },

        async onPeriodChange() {
            await this.loadStats();
            await this.loadLogs();
        },

        clearFilters() {
            document.getElementById('filterEventType').value = '';
            document.getElementById('filterNotificationId').value = '';
            document.getElementById('filterUserId').value = '';
            this.filters = {};
            this.loadLogs();
        },

        async loadMore() {
            await this.loadLogs(true);
        },

        async refresh() {
            await this.loadStats();
            await this.loadLogs();
        },

        exportCSV() {
            const params = new URLSearchParams({
                action: 'export',
                ...this.filters
            });

            const period = document.getElementById('filterPeriod').value;
            const now = new Date();
            let dateFrom;
            switch (period) {
                case '1h':
                    dateFrom = new Date(now - 60 * 60 * 1000);
                    break;
                case '24h':
                    dateFrom = new Date(now - 24 * 60 * 60 * 1000);
                    break;
                case '7d':
                    dateFrom = new Date(now - 7 * 24 * 60 * 60 * 1000);
                    break;
                case '30d':
                    dateFrom = new Date(now - 30 * 24 * 60 * 60 * 1000);
                    break;
            }
            if (dateFrom) {
                params.set('date_from', dateFrom.toISOString().slice(0, 19).replace('T', ' '));
            }

            window.location.href = `api/logs-api.php?${params}`;
        }
    };

    // Close modal on backdrop click
    document.getElementById('logDetailModal').addEventListener('click', function(e) {
        if (e.target === this) {
            LogsManager.closeModal();
        }
    });

    // Close modal on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            LogsManager.closeModal();
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        LogsManager.init();
    });
    </script>
</body>
</html>
