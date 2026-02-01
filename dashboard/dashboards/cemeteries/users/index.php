<?php
/**
 * Users Management - ניהול משתמשים
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות
requireDashboard(['cemetery_manager', 'admin']);

// בדוק הרשאה ספציפית לניהול משתמשים
if (!isAdmin() && !hasModulePermission('users', 'view')) {
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
$canCreate = isAdmin() || hasModulePermission('users', 'create');
$canEdit = isAdmin() || hasModulePermission('users', 'edit');
$canDelete = isAdmin() || hasModulePermission('users', 'delete');
$canManageRoles = isAdmin() || hasModulePermission('roles', 'view');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול משתמשים - <?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/sidebar.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/header.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">

    <style>
        .users-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filters-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            max-width: 400px;
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-width: 150px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-primary);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .users-table th,
        .users-table td {
            padding: 14px 16px;
            text-align: right;
            border-bottom: 1px solid var(--border-color);
        }

        .users-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 13px;
            text-transform: uppercase;
        }

        .users-table tr:hover {
            background: var(--bg-hover);
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--color-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .user-email {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-role {
            background: var(--color-primary-light);
            color: var(--color-primary);
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-custom {
            background: #fef3c7;
            color: #92400e;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-primary-dark);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-hover);
        }

        .btn-icon {
            padding: 8px;
            border-radius: 6px;
            background: transparent;
            color: var(--text-secondary);
            border: none;
            cursor: pointer;
        }

        .btn-icon:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .btn-icon.danger:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-icon svg {
            width: 18px;
            height: 18px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
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
            color: var(--text-secondary);
        }

        .stats-bar {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: var(--bg-primary);
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            min-width: 150px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-primary);
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .back-link:hover {
            color: var(--color-primary);
        }

        @media (max-width: 768px) {
            .users-container {
                padding: 16px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filters-bar {
                flex-direction: column;
            }

            .search-input {
                max-width: 100%;
            }

            .users-table {
                display: block;
                overflow-x: auto;
            }

            .stats-bar {
                flex-direction: column;
            }

            .stat-card {
                width: 100%;
            }
        }
    </style>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>" data-color-scheme="<?= $isDarkMode ? '' : $colorScheme ?>" style="--base-font-size: <?= $fontSize ?>px;">

    <div class="users-container">
        <a href="/dashboard/dashboards/cemeteries/" class="back-link">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
            חזרה לדשבורד
        </a>

        <div class="page-header">
            <h1 class="page-title">ניהול משתמשים</h1>
            <div class="header-actions">
                <?php if ($canManageRoles): ?>
                <button class="btn btn-secondary" onclick="openApprovalSettings()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4"/>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    הגדרות אישור
                </button>
                <button class="btn btn-secondary" onclick="openRolesManager()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    ניהול תפקידים
                </button>
                <?php endif; ?>

                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="openUserForm()">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14m-7-7h14"/>
                    </svg>
                    משתמש חדש
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-bar" id="statsBar">
            <div class="stat-card">
                <div class="stat-value" id="totalUsers">-</div>
                <div class="stat-label">סה"כ משתמשים</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="activeUsers">-</div>
                <div class="stat-label">משתמשים פעילים</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="totalRoles">-</div>
                <div class="stat-label">תפקידים</div>
            </div>
        </div>

        <div class="filters-bar">
            <input type="text" class="search-input" id="searchInput" placeholder="חיפוש משתמש..." onkeyup="handleSearch(this.value)">
            <select class="filter-select" id="roleFilter" onchange="handleFilter()">
                <option value="">כל התפקידים</option>
            </select>
            <select class="filter-select" id="statusFilter" onchange="handleFilter()">
                <option value="">כל הסטטוסים</option>
                <option value="active">פעילים</option>
                <option value="inactive">לא פעילים</option>
            </select>
        </div>

        <div id="usersTableContainer">
            <div class="loading">טוען משתמשים...</div>
        </div>
    </div>

    <script src="/dashboard/dashboards/cemeteries/popup/popup-manager.js"></script>
    <script>
        // State
        let users = [];
        let roles = [];
        let searchTimeout = null;

        // Permissions
        const canCreate = <?= $canCreate ? 'true' : 'false' ?>;
        const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
        const canDelete = <?= $canDelete ? 'true' : 'false' ?>;

        // Init
        document.addEventListener('DOMContentLoaded', async function() {
            await loadRoles();
            await loadUsers();
            loadStats();
        });

        // Load roles for filter
        async function loadRoles() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php?action=list');
                const data = await response.json();
                if (data.success) {
                    roles = data.data;
                    populateRoleFilter();
                }
            } catch (error) {
                console.error('Error loading roles:', error);
            }
        }

        // Populate role filter dropdown
        function populateRoleFilter() {
            const select = document.getElementById('roleFilter');
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.display_name;
                select.appendChild(option);
            });
        }

        // Load users
        async function loadUsers() {
            const search = document.getElementById('searchInput').value;
            const roleId = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;

            let url = '/dashboard/dashboards/cemeteries/api/users-api.php?action=list';
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (roleId) url += `&role_id=${roleId}`;
            if (status) url += `&status=${status}`;

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    users = data.data;
                    renderUsersTable();
                } else {
                    showError(data.error || 'שגיאה בטעינת משתמשים');
                }
            } catch (error) {
                console.error('Error loading users:', error);
                showError('שגיאה בטעינת משתמשים');
            }
        }

        // Load stats
        async function loadStats() {
            try {
                // Total users
                const usersRes = await fetch('/dashboard/dashboards/cemeteries/api/users-api.php?action=list&limit=1');
                const usersData = await usersRes.json();
                if (usersData.success) {
                    document.getElementById('totalUsers').textContent = usersData.total;
                }

                // Active users
                const activeRes = await fetch('/dashboard/dashboards/cemeteries/api/users-api.php?action=list&status=active&limit=1');
                const activeData = await activeRes.json();
                if (activeData.success) {
                    document.getElementById('activeUsers').textContent = activeData.total;
                }

                // Total roles
                document.getElementById('totalRoles').textContent = roles.length;

            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Render users table
        function renderUsersTable() {
            const container = document.getElementById('usersTableContainer');

            if (users.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <p>לא נמצאו משתמשים</p>
                    </div>
                `;
                return;
            }

            let html = `
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>משתמש</th>
                            <th>תפקיד</th>
                            <th>סטטוס</th>
                            <th>התחברות אחרונה</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            users.forEach(user => {
                const initials = getInitials(user.name || user.email);
                const lastLogin = user.last_login ? formatDate(user.last_login) : 'מעולם לא';

                html += `
                    <tr>
                        <td>
                            <div class="user-info">
                                ${user.profile_picture
                                    ? `<img src="${escapeHtml(user.profile_picture)}" class="user-avatar" alt="">`
                                    : `<div class="user-avatar-placeholder">${initials}</div>`
                                }
                                <div>
                                    <div class="user-name">${escapeHtml(user.name || user.username)}</div>
                                    <div class="user-email">${escapeHtml(user.email)}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-role">${escapeHtml(user.role_display_name || 'ללא תפקיד')}</span>
                            ${user.custom_permissions ? '<span class="badge badge-custom">מותאם</span>' : ''}
                        </td>
                        <td>
                            <span class="badge ${user.is_active ? 'badge-active' : 'badge-inactive'}">
                                ${user.is_active ? 'פעיל' : 'לא פעיל'}
                            </span>
                        </td>
                        <td>${lastLogin}</td>
                        <td>
                            <div class="action-btns">
                                ${canEdit ? `
                                    <button class="btn-icon" onclick="openUserForm(${user.id})" title="עריכה">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                ` : ''}
                                ${canDelete ? `
                                    <button class="btn-icon danger" onclick="deleteUser(${user.id}, '${escapeHtml(user.name || user.email)}')" title="מחיקה">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Search handler
        function handleSearch(value) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadUsers();
            }, 300);
        }

        // Filter handler
        function handleFilter() {
            loadUsers();
        }

        // Open user form (create/edit)
        function openUserForm(userId = null) {
            const popupId = 'user-form-popup-' + Date.now();
            const url = userId
                ? `/dashboard/dashboards/cemeteries/forms/iframe/userForm-iframe.php?itemId=${userId}&popupId=${popupId}`
                : `/dashboard/dashboards/cemeteries/forms/iframe/userForm-iframe.php?popupId=${popupId}`;

            if (typeof PopupManager !== 'undefined') {
                PopupManager.create({
                    id: popupId,
                    type: 'iframe',
                    src: url,
                    title: userId ? 'עריכת משתמש' : 'משתמש חדש',
                    width: 900,
                    height: 750,
                    onClose: () => {
                        loadUsers();
                        loadStats();
                    }
                });
            } else {
                window.location.href = url;
            }
        }

        // Refresh function for child iframe
        window.refreshUsersList = function() {
            loadUsers();
            loadStats();
        };

        // Open roles manager
        function openRolesManager() {
            if (typeof PopupManager !== 'undefined') {
                PopupManager.create({
                    id: 'roles-manager-popup',
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/users/roles.php',
                    title: 'ניהול תפקידים',
                    width: 900,
                    height: 700,
                    onClose: () => {
                        loadRoles();
                        loadUsers();
                    }
                });
            } else {
                window.location.href = '/dashboard/dashboards/cemeteries/users/roles.php';
            }
        }

        // Open approval settings
        function openApprovalSettings() {
            if (typeof PopupManager !== 'undefined') {
                PopupManager.create({
                    id: 'approval-settings-popup',
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/users/approval-settings.php',
                    title: 'הגדרות אישור פעולות',
                    width: 1000,
                    height: 800,
                    onClose: () => {
                        loadUsers();
                    }
                });
            } else {
                window.location.href = '/dashboard/dashboards/cemeteries/users/approval-settings.php';
            }
        }

        // Delete user
        async function deleteUser(userId, userName) {
            if (!confirm(`האם אתה בטוח שברצונך למחוק את המשתמש "${userName}"?`)) {
                return;
            }

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/users-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: userId })
                });

                const data = await response.json();

                if (data.success) {
                    loadUsers();
                    loadStats();
                } else {
                    alert(data.error || 'שגיאה במחיקת משתמש');
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                alert('שגיאה במחיקת משתמש');
            }
        }

        // Helper functions
        function getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('he-IL') + ' ' + date.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' });
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function showError(message) {
            document.getElementById('usersTableContainer').innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #dc2626;">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M15 9l-6 6m0-6l6 6"/>
                    </svg>
                    <p>${message}</p>
                </div>
            `;
        }

        // Listen for messages from popup (for refreshing data)
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'userUpdated') {
                loadUsers();
                loadStats();
            }
        });
    </script>
</body>
</html>
