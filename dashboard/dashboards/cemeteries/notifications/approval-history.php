<?php
/**
 * Approval History Page
 * Shows historical approval operations (approved, rejected, expired, cancelled)
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// User must be logged in - v16: use location.replace to prevent history pollution
if (!isLoggedIn()) {
    $redirect = '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>location.replace(' . json_encode($redirect) . ');</script>';
    echo '</head><body></body></html>';
    exit;
}

$userId = getCurrentUserId();

// Status labels with colors
$statusLabels = [
    'approved' => ['label' => 'אושר', 'color' => '#10b981', 'bg' => '#d1fae5'],
    'rejected' => ['label' => 'נדחה', 'color' => '#dc2626', 'bg' => '#fecaca'],
    'expired' => ['label' => 'פג תוקף', 'color' => '#6b7280', 'bg' => '#e5e7eb'],
    'cancelled' => ['label' => 'בוטל', 'color' => '#f97316', 'bg' => '#fed7aa']
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>היסטוריית אישורים - <?= DASHBOARD_NAME ?></title>
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/pending-badges.css">
    <script src="/dashboard/dashboards/cemeteries/js/entity-labels.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .page-header {
            background: white;
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title svg {
            width: 28px;
            height: 28px;
            color: #6366f1;
        }
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 12px;
            color: #6b7280;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            min-width: 140px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #6366f1;
            color: white;
        }
        .btn-primary:hover {
            background: #4f46e5;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .history-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th {
            background: #f9fafb;
            padding: 12px 16px;
            text-align: right;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }
        .history-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            color: #111827;
        }
        .history-table tr:hover {
            background: #f9fafb;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .entity-action {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .entity-action .action {
            font-weight: 500;
        }
        .entity-action .entity {
            font-size: 12px;
            color: #6b7280;
        }
        .date-cell {
            white-space: nowrap;
            font-size: 13px;
            color: #6b7280;
        }
        .btn-view {
            padding: 6px 12px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            color: #374151;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-view:hover {
            background: #e5e7eb;
        }
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
        }
        .pagination-info {
            font-size: 14px;
            color: #6b7280;
        }
        .pagination-buttons {
            display: flex;
            gap: 8px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        .approvers-list {
            font-size: 12px;
            color: #6b7280;
        }
        .back-link {
            color: #6366f1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/dashboard/dashboards/cemeteries/" class="back-link">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5m7 7l-7-7 7-7"/>
            </svg>
            חזרה לדשבורד
        </a>

        <div class="page-header">
            <h1 class="page-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
                היסטוריית אישורים
            </h1>

            <div class="filters">
                <div class="filter-group">
                    <label>סטטוס</label>
                    <select id="filterStatus">
                        <option value="">הכל</option>
                        <option value="approved">אושר</option>
                        <option value="rejected">נדחה</option>
                        <option value="expired">פג תוקף</option>
                        <option value="cancelled">בוטל</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>סוג ישות</label>
                    <select id="filterEntity">
                        <option value="">הכל</option>
                        <option value="customers">לקוחות</option>
                        <option value="purchases">רכישות</option>
                        <option value="burials">קבורות</option>
                        <option value="cemeteries">בתי עלמין</option>
                        <option value="blocks">גושים</option>
                        <option value="plots">חלקות</option>
                        <option value="graves">קברים</option>
                        <option value="payments">תשלומים</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>מתאריך</label>
                    <input type="date" id="filterDateFrom">
                </div>

                <div class="filter-group">
                    <label>עד תאריך</label>
                    <input type="date" id="filterDateTo">
                </div>

                <button class="btn btn-primary" onclick="loadHistory()" style="align-self: flex-end;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    סנן
                </button>
            </div>
        </div>

        <div class="history-table-container">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>סטטוס</th>
                        <th>פעולה</th>
                        <th>מבקש</th>
                        <th>תאריך בקשה</th>
                        <th>תאריך סיום</th>
                        <th>מאשרים</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <tr>
                        <td colspan="7" class="loading">טוען נתונים...</td>
                    </tr>
                </tbody>
            </table>

            <div class="pagination" id="pagination" style="display: none;">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination-buttons">
                    <button class="btn btn-secondary" id="btnPrev" onclick="prevPage()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                        הקודם
                    </button>
                    <button class="btn btn-secondary" id="btnNext" onclick="nextPage()">
                        הבא
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m15 18-6-6 6-6"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const statusLabels = <?= json_encode($statusLabels) ?>;
        let currentOffset = 0;
        const limit = 50;
        let totalItems = 0;

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('he-IL') + ' ' + date.toLocaleTimeString('he-IL', {hour: '2-digit', minute: '2-digit'});
        }

        function getStatusBadge(status) {
            const s = statusLabels[status] || {label: status, color: '#6b7280', bg: '#e5e7eb'};
            return `<span class="status-badge" style="background: ${s.bg}; color: ${s.color};">${s.label}</span>`;
        }

        async function loadHistory() {
            const tbody = document.getElementById('historyBody');
            tbody.innerHTML = '<tr><td colspan="7" class="loading">טוען נתונים...</td></tr>';

            const params = new URLSearchParams({
                action: 'listHistory',
                limit: limit,
                offset: currentOffset
            });

            const status = document.getElementById('filterStatus').value;
            const entityType = document.getElementById('filterEntity').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;

            if (status) params.append('status', status);
            if (entityType) params.append('entityType', entityType);
            if (dateFrom) params.append('dateFrom', dateFrom);
            if (dateTo) params.append('dateTo', dateTo);

            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/entity-approval-api.php?${params}`);
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || 'שגיאה בטעינת נתונים');
                }

                totalItems = result.pagination.total;
                renderHistory(result.data);
                updatePagination();

            } catch (error) {
                console.error('Error loading history:', error);
                tbody.innerHTML = `<tr><td colspan="7" class="empty-state">שגיאה בטעינת נתונים: ${error.message}</td></tr>`;
            }
        }

        function renderHistory(data) {
            const tbody = document.getElementById('historyBody');

            if (!data || data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <div>אין היסטוריית אישורים להצגה</div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = data.map(item => {
                const entityLabel = (typeof EntityLabels !== 'undefined') ? EntityLabels.getEntity(item.entity_type) : item.entity_type;
                const actionLabel = (typeof EntityLabels !== 'undefined') ? EntityLabels.getAction(item.action) : item.action;

                return `
                    <tr>
                        <td>${getStatusBadge(item.status)}</td>
                        <td>
                            <div class="entity-action">
                                <span class="action">${actionLabel}</span>
                                <span class="entity">${entityLabel}</span>
                            </div>
                        </td>
                        <td>${item.requester_name || '-'}</td>
                        <td class="date-cell">${formatDate(item.created_at)}</td>
                        <td class="date-cell">${formatDate(item.completed_at)}</td>
                        <td class="approvers-list">${item.approvers_summary || '-'}</td>
                        <td>
                            <button class="btn-view" onclick="viewDetails(${item.id})">
                                צפייה
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updatePagination() {
            const pagination = document.getElementById('pagination');
            const info = document.getElementById('paginationInfo');
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');

            if (totalItems === 0) {
                pagination.style.display = 'none';
                return;
            }

            pagination.style.display = 'flex';

            const start = currentOffset + 1;
            const end = Math.min(currentOffset + limit, totalItems);
            info.textContent = `מציג ${start}-${end} מתוך ${totalItems}`;

            btnPrev.disabled = currentOffset === 0;
            btnNext.disabled = currentOffset + limit >= totalItems;
        }

        function prevPage() {
            if (currentOffset > 0) {
                currentOffset -= limit;
                loadHistory();
            }
        }

        function nextPage() {
            if (currentOffset + limit < totalItems) {
                currentOffset += limit;
                loadHistory();
            }
        }

        function viewDetails(pendingId) {
            window.open(`/dashboard/dashboards/cemeteries/notifications/entity-approve.php?id=${pendingId}`, '_blank');
        }

        // Load on page ready
        document.addEventListener('DOMContentLoaded', loadHistory);
    </script>
</body>
</html>
