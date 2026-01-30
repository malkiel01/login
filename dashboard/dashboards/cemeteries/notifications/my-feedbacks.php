<?php
/**
 * My Feedbacks - View feedback on notifications I sent
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../config.php';
requireAuth();

$pageTitle = 'פידבקים שקיבלתי';
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo DASHBOARD_NAME; ?></title>
    <link rel="stylesheet" href="css/notifications.css">
    <style>
        .feedbacks-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary, #1e293b);
            margin: 0;
        }

        .back-link {
            color: var(--primary-color, #667eea);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-primary, white);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.1));
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary, #1e293b);
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-muted, #64748b);
            margin-top: 4px;
        }

        .feedbacks-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .feedback-item {
            background: var(--bg-primary, white);
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.1));
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .feedback-icon {
            font-size: 32px;
            flex-shrink: 0;
        }

        .feedback-content {
            flex: 1;
            min-width: 0;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .feedback-title {
            font-weight: 600;
            color: var(--text-primary, #1e293b);
            margin: 0;
        }

        .feedback-time {
            font-size: 13px;
            color: var(--text-muted, #64748b);
            white-space: nowrap;
        }

        .feedback-body {
            color: var(--text-secondary, #475569);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .feedback-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: var(--text-muted, #64748b);
        }

        .feedback-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .feedback-status.viewed {
            background: #e0f2fe;
            color: #0369a1;
        }

        .feedback-status.approved {
            background: #dcfce7;
            color: #16a34a;
        }

        .feedback-status.rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted, #64748b);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-color, #e2e8f0);
            border-top-color: var(--primary-color, #667eea);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }

        .pagination button {
            padding: 8px 16px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: var(--bg-primary, white);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pagination button:hover:not(:disabled) {
            background: var(--bg-secondary, #f8fafc);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .current {
            background: var(--primary-color, #667eea);
            color: white;
            border-color: var(--primary-color, #667eea);
        }
    </style>
</head>
<body>
    <div class="feedbacks-container">
        <div class="page-header">
            <h1 class="page-title">📬 <?php echo $pageTitle; ?></h1>
            <a href="index.php" class="back-link">← חזרה להתראות</a>
        </div>

        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon">👁️</div>
                <div class="stat-value" id="statViewed">-</div>
                <div class="stat-label">נצפו</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value" id="statApproved">-</div>
                <div class="stat-label">אושרו</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value" id="statRejected">-</div>
                <div class="stat-label">נדחו</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value" id="statTotal">-</div>
                <div class="stat-label">התראות עם פידבק</div>
            </div>
        </div>

        <div id="feedbacksList" class="feedbacks-list">
            <div class="loading">
                <div class="spinner"></div>
                <div>טוען פידבקים...</div>
            </div>
        </div>

        <div id="pagination" class="pagination" style="display: none;"></div>
    </div>

    <script>
    const FeedbacksPage = {
        currentPage: 1,
        totalPages: 1,
        apiUrl: 'api/feedback-api.php',

        async init() {
            await Promise.all([
                this.loadStats(),
                this.loadFeedbacks()
            ]);
        },

        async loadStats() {
            try {
                const response = await fetch(`${this.apiUrl}?action=stats`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('statViewed').textContent = data.stats.viewed;
                    document.getElementById('statApproved').textContent = data.stats.approved;
                    document.getElementById('statRejected').textContent = data.stats.rejected;
                    document.getElementById('statTotal').textContent = data.stats.unique_notifications;
                }
            } catch (e) {
                console.error('Error loading stats:', e);
            }
        },

        async loadFeedbacks(page = 1) {
            this.currentPage = page;
            const listEl = document.getElementById('feedbacksList');

            try {
                const response = await fetch(`${this.apiUrl}?action=list&page=${page}&limit=20`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error);
                }

                if (data.data.length === 0) {
                    listEl.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h3>אין פידבקים עדיין</h3>
                            <p>כשתשלח התראות עם "פידבק לשולח" מופעל, תראה כאן מתי הן נצפו ונענו</p>
                        </div>
                    `;
                    document.getElementById('pagination').style.display = 'none';
                    return;
                }

                listEl.innerHTML = data.data.map(feedback => this.renderFeedback(feedback)).join('');

                // Pagination
                this.totalPages = data.pagination.pages;
                this.renderPagination(data.pagination);

            } catch (e) {
                console.error('Error loading feedbacks:', e);
                listEl.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">⚠️</div>
                        <h3>שגיאה בטעינת הפידבקים</h3>
                        <p>${e.message}</p>
                    </div>
                `;
            }
        },

        renderFeedback(feedback) {
            const statusClass = feedback.event_type.replace('feedback_', '');
            const time = new Date(feedback.created_at).toLocaleString('he-IL');

            return `
                <div class="feedback-item">
                    <div class="feedback-icon">${feedback.event_icon}</div>
                    <div class="feedback-content">
                        <div class="feedback-header">
                            <h4 class="feedback-title">${this.escapeHtml(feedback.notification_title || 'התראה #' + feedback.notification_id)}</h4>
                            <span class="feedback-time">${time}</span>
                        </div>
                        <div class="feedback-body">${this.escapeHtml(feedback.notification_body || '')}</div>
                        <div class="feedback-meta">
                            <span class="feedback-status ${statusClass}">
                                ${feedback.event_icon} ${feedback.event_type_display}
                            </span>
                            <span>על ידי: ${this.escapeHtml(feedback.triggered_by_name || feedback.triggered_by_username || 'משתמש #' + feedback.triggered_by_user_id)}</span>
                        </div>
                    </div>
                </div>
            `;
        },

        renderPagination(pagination) {
            const paginationEl = document.getElementById('pagination');

            if (pagination.pages <= 1) {
                paginationEl.style.display = 'none';
                return;
            }

            paginationEl.style.display = 'flex';

            let html = `
                <button onclick="FeedbacksPage.loadFeedbacks(${pagination.page - 1})" ${pagination.page <= 1 ? 'disabled' : ''}>
                    הקודם
                </button>
            `;

            for (let i = 1; i <= pagination.pages; i++) {
                if (i === pagination.page) {
                    html += `<button class="current">${i}</button>`;
                } else if (Math.abs(i - pagination.page) <= 2 || i === 1 || i === pagination.pages) {
                    html += `<button onclick="FeedbacksPage.loadFeedbacks(${i})">${i}</button>`;
                } else if (Math.abs(i - pagination.page) === 3) {
                    html += `<span>...</span>`;
                }
            }

            html += `
                <button onclick="FeedbacksPage.loadFeedbacks(${pagination.page + 1})" ${pagination.page >= pagination.pages ? 'disabled' : ''}>
                    הבא
                </button>
            `;

            paginationEl.innerHTML = html;
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', () => FeedbacksPage.init());
    </script>
</body>
</html>
