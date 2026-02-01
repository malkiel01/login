<?php
/**
 * Approval Settings Management - ניהול הגדרות אישור
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות - רק אדמין
requireDashboard(['cemetery_manager', 'admin']);

if (!isAdmin() && !hasModulePermission('users', 'edit')) {
    http_response_code(403);
    die('אין לך הרשאה לצפות בדף זה');
}

// טעינת הגדרות משתמש
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';
$userSettingsConn = getDBConnection();
$currentUserId = getCurrentUserId();

function detectDeviceType() {
    if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
        return $_COOKIE['deviceType'];
    }
    return 'desktop';
}

$userSettingsManager = new UserSettingsManager($userSettingsConn, $currentUserId, detectDeviceType());
$userPrefs = $userSettingsManager->getAllWithDefaults();

$isDarkMode = isset($userPrefs['darkMode']) && ($userPrefs['darkMode']['value'] === true || $userPrefs['darkMode']['value'] === 'true');
$colorScheme = isset($userPrefs['colorScheme']) ? $userPrefs['colorScheme']['value'] : 'purple';

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
    <title>הגדרות אישור פעולות - <?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 24px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
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

        .section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .info-box {
            background: var(--color-primary-light);
            border: 1px solid var(--color-primary);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-box h4 {
            margin: 0 0 8px 0;
            color: var(--color-primary);
        }

        .info-box ul {
            margin: 0;
            padding-right: 20px;
        }

        .info-box li {
            margin-bottom: 4px;
            font-size: 14px;
        }

        /* Users List */
        .users-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .user-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .user-card:hover {
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-card.selected {
            border-color: var(--color-primary);
            background: var(--color-primary-light);
        }

        .user-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--color-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-email {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .user-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .stat-badge {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-secondary);
        }

        .stat-badge.authorizer {
            background: #dcfce7;
            color: #166534;
        }

        .stat-badge.requires {
            background: #fef3c7;
            color: #92400e;
        }

        /* Matrix Table */
        .matrix-container {
            display: none;
            margin-top: 24px;
        }

        .matrix-container.active {
            display: block;
        }

        .selected-user-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .selected-user-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--color-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 20px;
        }

        .selected-user-info h3 {
            margin: 0 0 4px 0;
            font-size: 1.25rem;
        }

        .selected-user-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .matrix-table {
            width: 100%;
            border-collapse: collapse;
        }

        .matrix-table th,
        .matrix-table td {
            padding: 12px 16px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .matrix-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .matrix-table th:first-child,
        .matrix-table td:first-child {
            text-align: right;
            font-weight: 500;
        }

        .matrix-table tr:hover {
            background: var(--bg-hover);
        }

        .matrix-table tr:last-child td {
            border-bottom: none;
        }

        .approval-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 13px;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-width: 140px;
            cursor: pointer;
        }

        .approval-select:focus {
            outline: none;
            border-color: var(--color-primary);
        }

        .approval-select.authorizer {
            background: #dcfce7;
            border-color: #22c55e;
        }

        .approval-select.requires_approval {
            background: #fef3c7;
            border-color: #f59e0b;
        }

        .approval-select.no_approval {
            background: var(--bg-secondary);
        }

        .mandatory-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .mandatory-checkbox input {
            width: 14px;
            height: 14px;
        }

        .mandatory-checkbox.hidden {
            display: none;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-primary-dark);
        }

        .btn-primary:disabled {
            background: var(--border-color);
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-hover);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .dark-theme .loading-overlay {
            background: rgba(0,0,0,0.8);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-color);
            border-top-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Global Rules Section */
        .rules-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .rules-table th,
        .rules-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .rules-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            font-size: 13px;
        }

        .rules-table th:first-child,
        .rules-table td:first-child {
            text-align: right;
        }

        .rules-input {
            width: 60px;
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-align: center;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .toggle-small {
            position: relative;
            width: 36px;
            height: 20px;
            display: inline-block;
        }

        .toggle-small input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-small .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-color);
            transition: 0.3s;
            border-radius: 20px;
        }

        .toggle-small .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        .toggle-small input:checked + .slider {
            background-color: var(--color-primary);
        }

        .toggle-small input:checked + .slider:before {
            transform: translateX(16px);
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .users-list {
                grid-template-columns: 1fr;
            }

            .matrix-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="container">
        <a href="/dashboard/dashboards/cemeteries/users/" class="back-link">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
            חזרה לניהול משתמשים
        </a>

        <div class="page-header">
            <h1 class="page-title">הגדרות אישור פעולות</h1>
        </div>

        <div id="alertContainer"></div>

        <!-- הסבר -->
        <div class="info-box">
            <h4>מצבי אישור:</h4>
            <ul>
                <li><strong>מורשה חתימה</strong> - יכול לאשר פעולות של אחרים. כשמבצע פעולה בעצמו - נחשב כחתימתו</li>
                <li><strong>דורש אישור</strong> - הפעולות שלו ממתינות לאישור מורשה חתימה</li>
                <li><strong>ללא אישור</strong> - פעולות מתבצעות מיידית ללא צורך באישור</li>
            </ul>
        </div>

        <!-- הגדרות כלליות -->
        <div class="section">
            <h3 class="section-title">הגדרות כלליות</h3>
            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 16px;">
                הגדר כמה אישורים נדרשים לכל סוג פעולה
            </p>
            <table class="rules-table" id="rulesTable">
                <thead>
                    <tr>
                        <th>ישות / פעולה</th>
                        <th>מספר מורשים נדרש</th>
                        <th>חובת כל המורשים</th>
                        <th>תוקף (שעות)</th>
                        <th>פעיל</th>
                    </tr>
                </thead>
                <tbody id="rulesBody">
                    <!-- Filled by JS -->
                </tbody>
            </table>
            <div class="form-actions">
                <button type="button" class="btn btn-primary" onclick="saveRules()">שמור הגדרות כלליות</button>
            </div>
        </div>

        <!-- בחירת משתמש -->
        <div class="section">
            <h3 class="section-title">הגדרות למשתמש</h3>
            <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 16px;">
                בחר משתמש כדי להגדיר את הרשאות האישור שלו
            </p>
            <div class="users-list" id="usersList">
                <!-- Filled by JS -->
            </div>

            <!-- מטריצת הגדרות -->
            <div class="matrix-container" id="matrixContainer">
                <div class="selected-user-header">
                    <div class="selected-user-avatar" id="selectedUserAvatar"></div>
                    <div class="selected-user-info">
                        <h3 id="selectedUserName"></h3>
                        <p id="selectedUserEmail"></p>
                    </div>
                </div>

                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th>ישות / פעולה</th>
                            <th>יצירה</th>
                            <th>עריכה</th>
                            <th>מחיקה</th>
                        </tr>
                    </thead>
                    <tbody id="matrixBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="clearSelection()">ביטול</button>
                    <button type="button" class="btn btn-primary" onclick="saveUserSettings()" id="saveBtn">שמור הגדרות</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // State
        let users = [];
        let rules = [];
        let selectedUserId = null;
        let userSettings = [];

        const entityTypes = [
            { value: 'purchases', label: 'רכישות' },
            { value: 'burials', label: 'קבורות' },
            { value: 'customers', label: 'לקוחות' }
        ];

        const actions = [
            { value: 'create', label: 'יצירה' },
            { value: 'edit', label: 'עריכה' },
            { value: 'delete', label: 'מחיקה' }
        ];

        const approvalModes = [
            { value: 'no_approval', label: 'ללא אישור' },
            { value: 'requires_approval', label: 'דורש אישור' },
            { value: 'authorizer', label: 'מורשה חתימה' }
        ];

        // Init
        document.addEventListener('DOMContentLoaded', async function() {
            await loadRules();
            await loadUsers();
        });

        // Load global rules
        async function loadRules() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=getRules');
                const data = await response.json();
                if (data.success) {
                    rules = data.data;
                    renderRulesTable();
                }
            } catch (error) {
                console.error('Error loading rules:', error);
            }
        }

        // Render rules table
        function renderRulesTable() {
            const tbody = document.getElementById('rulesBody');
            let html = '';

            entityTypes.forEach(entity => {
                actions.forEach(action => {
                    const rule = rules.find(r => r.entity_type === entity.value && r.action === action.value) || {
                        required_approvals: 1,
                        require_all_mandatory: 1,
                        expires_hours: 48,
                        is_active: 1
                    };

                    html += `
                        <tr data-entity="${entity.value}" data-action="${action.value}">
                            <td>${entity.label} - ${action.label}</td>
                            <td>
                                <input type="number" class="rules-input" min="1" max="10"
                                       value="${rule.required_approvals}"
                                       data-field="required_approvals">
                            </td>
                            <td>
                                <label class="toggle-small">
                                    <input type="checkbox" ${rule.require_all_mandatory ? 'checked' : ''}
                                           data-field="require_all_mandatory">
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>
                                <input type="number" class="rules-input" min="1" max="720"
                                       value="${rule.expires_hours}"
                                       data-field="expires_hours">
                            </td>
                            <td>
                                <label class="toggle-small">
                                    <input type="checkbox" ${rule.is_active ? 'checked' : ''}
                                           data-field="is_active">
                                    <span class="slider"></span>
                                </label>
                            </td>
                        </tr>
                    `;
                });
            });

            tbody.innerHTML = html;
        }

        // Save rules
        async function saveRules() {
            showLoading(true);

            const rulesData = [];
            const rows = document.querySelectorAll('#rulesBody tr');

            rows.forEach(row => {
                rulesData.push({
                    entity_type: row.dataset.entity,
                    action: row.dataset.action,
                    required_approvals: parseInt(row.querySelector('[data-field="required_approvals"]').value),
                    require_all_mandatory: row.querySelector('[data-field="require_all_mandatory"]').checked ? 1 : 0,
                    expires_hours: parseInt(row.querySelector('[data-field="expires_hours"]').value),
                    is_active: row.querySelector('[data-field="is_active"]').checked ? 1 : 0
                });
            });

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=saveRules', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ rules: rulesData })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'ההגדרות הכלליות נשמרו בהצלחה');
                } else {
                    showAlert('error', data.error || 'שגיאה בשמירה');
                }
            } catch (error) {
                console.error('Error saving rules:', error);
                showAlert('error', 'שגיאה בשמירה');
            } finally {
                showLoading(false);
            }
        }

        // Load users
        async function loadUsers() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/users-api.php?action=list&status=active');
                const data = await response.json();
                if (data.success) {
                    users = data.data;
                    await loadAllUserSettings();
                    renderUsersList();
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        // Load all users' approval settings for display
        async function loadAllUserSettings() {
            // This will be used to show badges on user cards
            for (const user of users) {
                try {
                    const response = await fetch(`/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=getSettings&userId=${user.id}`);
                    const data = await response.json();
                    if (data.success) {
                        user.approvalSettings = data.data || [];
                    }
                } catch (error) {
                    user.approvalSettings = [];
                }
            }
        }

        // Render users list
        function renderUsersList() {
            const container = document.getElementById('usersList');

            let html = '';
            users.forEach(user => {
                const initials = getInitials(user.name || user.email);
                const authorizerCount = (user.approvalSettings || []).filter(s => s.approval_mode === 'authorizer').length;
                const requiresCount = (user.approvalSettings || []).filter(s => s.approval_mode === 'requires_approval').length;

                html += `
                    <div class="user-card ${selectedUserId === user.id ? 'selected' : ''}"
                         onclick="selectUser(${user.id})">
                        <div class="user-card-header">
                            <div class="user-avatar">${initials}</div>
                            <div>
                                <div class="user-name">${escapeHtml(user.name || user.username)}</div>
                                <div class="user-email">${escapeHtml(user.email)}</div>
                            </div>
                        </div>
                        <div class="user-stats">
                            ${authorizerCount > 0 ? `<span class="stat-badge authorizer">מורשה: ${authorizerCount}</span>` : ''}
                            ${requiresCount > 0 ? `<span class="stat-badge requires">דורש אישור: ${requiresCount}</span>` : ''}
                            ${authorizerCount === 0 && requiresCount === 0 ? `<span class="stat-badge">ללא הגדרות</span>` : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Select user
        async function selectUser(userId) {
            selectedUserId = userId;
            const user = users.find(u => u.id === userId);

            if (!user) return;

            // Update cards
            document.querySelectorAll('.user-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');

            // Show matrix
            document.getElementById('matrixContainer').classList.add('active');

            // Update header
            document.getElementById('selectedUserAvatar').textContent = getInitials(user.name || user.email);
            document.getElementById('selectedUserName').textContent = user.name || user.username;
            document.getElementById('selectedUserEmail').textContent = user.email;

            // Load user settings
            await loadUserSettings(userId);
            renderMatrix();
        }

        // Load user settings
        async function loadUserSettings(userId) {
            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=getSettings&userId=${userId}`);
                const data = await response.json();
                if (data.success) {
                    userSettings = data.data || [];
                }
            } catch (error) {
                console.error('Error loading user settings:', error);
                userSettings = [];
            }
        }

        // Render matrix
        function renderMatrix() {
            const tbody = document.getElementById('matrixBody');
            let html = '';

            entityTypes.forEach(entity => {
                html += `<tr>`;
                html += `<td><strong>${entity.label}</strong></td>`;

                actions.forEach(action => {
                    const setting = userSettings.find(s =>
                        s.entity_type === entity.value && s.action === action.value
                    );
                    const currentMode = setting?.approval_mode || 'no_approval';
                    const isMandatory = setting?.is_mandatory == 1;

                    html += `
                        <td>
                            <select class="approval-select ${currentMode}"
                                    data-entity="${entity.value}"
                                    data-action="${action.value}"
                                    onchange="handleModeChange(this)">
                                ${approvalModes.map(mode => `
                                    <option value="${mode.value}" ${currentMode === mode.value ? 'selected' : ''}>
                                        ${mode.label}
                                    </option>
                                `).join('')}
                            </select>
                            <label class="mandatory-checkbox ${currentMode !== 'authorizer' ? 'hidden' : ''}"
                                   data-entity="${entity.value}"
                                   data-action="${action.value}">
                                <input type="checkbox" ${isMandatory ? 'checked' : ''}>
                                חובה
                            </label>
                        </td>
                    `;
                });

                html += `</tr>`;
            });

            tbody.innerHTML = html;
        }

        // Handle mode change
        function handleModeChange(select) {
            const mode = select.value;
            const entity = select.dataset.entity;
            const action = select.dataset.action;

            // Update select style
            select.className = 'approval-select ' + mode;

            // Show/hide mandatory checkbox
            const mandatoryLabel = document.querySelector(
                `.mandatory-checkbox[data-entity="${entity}"][data-action="${action}"]`
            );
            if (mandatoryLabel) {
                mandatoryLabel.classList.toggle('hidden', mode !== 'authorizer');
            }
        }

        // Save user settings
        async function saveUserSettings() {
            if (!selectedUserId) return;

            showLoading(true);

            const settings = [];
            const selects = document.querySelectorAll('#matrixBody .approval-select');

            selects.forEach(select => {
                const entity = select.dataset.entity;
                const action = select.dataset.action;
                const mode = select.value;

                const mandatoryCheckbox = document.querySelector(
                    `.mandatory-checkbox[data-entity="${entity}"][data-action="${action}"] input`
                );
                const isMandatory = mandatoryCheckbox?.checked ? 1 : 0;

                settings.push({
                    entity_type: entity,
                    action: action,
                    approval_mode: mode,
                    is_mandatory: isMandatory
                });
            });

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=saveSettings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userId: selectedUserId,
                        settings: settings
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'ההגדרות נשמרו בהצלחה');

                    // Update local data
                    const user = users.find(u => u.id === selectedUserId);
                    if (user) {
                        user.approvalSettings = settings;
                    }
                    renderUsersList();
                } else {
                    showAlert('error', data.error || 'שגיאה בשמירה');
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                showAlert('error', 'שגיאה בשמירה');
            } finally {
                showLoading(false);
            }
        }

        // Clear selection
        function clearSelection() {
            selectedUserId = null;
            document.getElementById('matrixContainer').classList.remove('active');
            document.querySelectorAll('.user-card').forEach(card => {
                card.classList.remove('selected');
            });
        }

        // Helper functions
        function getInitials(name) {
            return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;

            if (type === 'success') {
                setTimeout(() => {
                    container.innerHTML = '';
                }, 3000);
            }
        }
    </script>
</body>
</html>
