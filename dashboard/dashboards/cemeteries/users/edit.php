<?php
/**
 * User Edit Form - עריכת/יצירת משתמש
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות
requireDashboard(['cemetery_manager', 'admin']);

$userId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = $userId !== null;

// בדוק הרשאה ספציפית
$requiredPermission = $isEdit ? 'edit' : 'create';
if (!isAdmin() && !hasModulePermission('users', $requiredPermission)) {
    http_response_code(403);
    die('אין לך הרשאה לפעולה זו');
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
    <title><?= $isEdit ? 'עריכת משתמש' : 'משתמש חדש' ?></title>
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
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

        .form-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .form-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-secondary);
        }

        .form-group label .required {
            color: #dc2626;
        }

        .form-control {
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-light);
        }

        .form-control:disabled {
            background: var(--bg-secondary);
            cursor: not-allowed;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M2 4l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 12px center;
            padding-left: 36px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .toggle-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .toggle-container:last-child {
            border-bottom: none;
        }

        .toggle-info h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            font-weight: 500;
        }

        .toggle-info p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .toggle-switch {
            position: relative;
            width: 48px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-color);
            transition: 0.3s;
            border-radius: 26px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--color-primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }

        .permissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .permissions-table th,
        .permissions-table td {
            padding: 10px 8px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }

        .permissions-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
        }

        .permissions-table th:first-child,
        .permissions-table td:first-child {
            text-align: right;
        }

        .permissions-table tr:hover {
            background: var(--bg-hover);
        }

        .permissions-table input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .module-name {
            font-weight: 500;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 16px;
        }

        .btn {
            padding: 12px 24px;
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

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            display: none;
        }

        .dark-theme .loading-overlay {
            background: rgba(0,0,0,0.8);
        }

        @media (max-width: 600px) {
            body {
                padding: 16px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">

    <div class="loading-overlay" id="loadingOverlay">
        <div>שומר...</div>
    </div>

    <div id="alertContainer"></div>

    <form id="userForm" class="form-container">
        <input type="hidden" id="userId" value="<?= $userId ?>">

        <!-- פרטים בסיסיים -->
        <div class="form-section">
            <h3 class="section-title">פרטים בסיסיים</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>שם מלא <span class="required">*</span></label>
                    <input type="text" class="form-control" id="name" required>
                </div>
                <div class="form-group">
                    <label>אימייל <span class="required">*</span></label>
                    <input type="email" class="form-control" id="email" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>טלפון</label>
                    <input type="tel" class="form-control" id="phone">
                </div>
                <div class="form-group">
                    <label>סטטוס</label>
                    <select class="form-control" id="is_active">
                        <option value="1">פעיל</option>
                        <option value="0">לא פעיל</option>
                    </select>
                </div>
            </div>

            <?php if (!$isEdit): ?>
            <div class="form-row full">
                <div class="form-group">
                    <label>סיסמה (השאר ריק להתחברות עם Google)</label>
                    <input type="password" class="form-control" id="password" minlength="6">
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- תפקיד והרשאות -->
        <div class="form-section">
            <h3 class="section-title">תפקיד והרשאות</h3>

            <div class="form-row full">
                <div class="form-group">
                    <label>תפקיד</label>
                    <select class="form-control" id="role_id" onchange="handleRoleChange()">
                        <option value="">בחר תפקיד...</option>
                    </select>
                </div>
            </div>

            <div class="toggle-container">
                <div class="toggle-info">
                    <h4>הרשאות מותאמות אישית</h4>
                    <p>אפשר הרשאות שונות מהתפקיד הבסיסי</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="custom_permissions" onchange="toggleCustomPermissions()">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <!-- טבלת הרשאות -->
        <div class="form-section" id="permissionsSection" style="display: none;">
            <h3 class="section-title">הרשאות מפורטות</h3>
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                סמן V להוספת הרשאה או השאר ריק לשלילת הרשאה (גם אם קיימת בתפקיד)
            </p>
            <div id="permissionsTableContainer">
                טוען הרשאות...
            </div>
        </div>

        <!-- כפתורי פעולה -->
        <div class="form-section">
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeForm()">ביטול</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <?= $isEdit ? 'שמור שינויים' : 'צור משתמש' ?>
                </button>
            </div>
        </div>
    </form>

    <script>
        // State
        let roles = [];
        let allPermissions = [];
        let rolePermissions = {};
        let userCustomPermissions = [];
        const userId = <?= $userId ? $userId : 'null' ?>;
        const isEdit = <?= $isEdit ? 'true' : 'false' ?>;

        // Init
        document.addEventListener('DOMContentLoaded', async function() {
            await loadRoles();
            await loadAllPermissions();

            if (isEdit) {
                await loadUserData();
            }

            document.getElementById('userForm').addEventListener('submit', handleSubmit);
        });

        // Load roles
        async function loadRoles() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php?action=list');
                const data = await response.json();
                if (data.success) {
                    roles = data.data;
                    populateRolesSelect();
                }
            } catch (error) {
                console.error('Error loading roles:', error);
            }
        }

        // Populate roles select
        function populateRolesSelect() {
            const select = document.getElementById('role_id');
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.display_name;
                select.appendChild(option);
            });
        }

        // Load all permissions
        async function loadAllPermissions() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php?action=permissions');
                const data = await response.json();
                if (data.success) {
                    allPermissions = data.data;
                    renderPermissionsTable();
                }
            } catch (error) {
                console.error('Error loading permissions:', error);
            }
        }

        // Load user data (for edit mode)
        async function loadUserData() {
            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/users-api.php?action=get&id=${userId}`);
                const data = await response.json();

                if (data.success) {
                    const user = data.data;

                    document.getElementById('name').value = user.name || '';
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('phone').value = user.phone || '';
                    document.getElementById('is_active').value = user.is_active ? '1' : '0';
                    document.getElementById('role_id').value = user.role_id || '';
                    document.getElementById('custom_permissions').checked = user.custom_permissions == 1;

                    userCustomPermissions = user.custom_permissions_list || [];

                    if (user.custom_permissions == 1) {
                        toggleCustomPermissions();
                    }

                    // Load role permissions for comparison
                    if (user.role_id) {
                        await loadRolePermissions(user.role_id);
                    }

                    updatePermissionsUI();
                } else {
                    showAlert('error', data.error || 'שגיאה בטעינת נתוני משתמש');
                }
            } catch (error) {
                console.error('Error loading user data:', error);
                showAlert('error', 'שגיאה בטעינת נתוני משתמש');
            }
        }

        // Load permissions for a specific role
        async function loadRolePermissions(roleId) {
            if (!roleId) {
                rolePermissions = {};
                return;
            }

            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/roles-api.php?action=get&id=${roleId}`);
                const data = await response.json();
                if (data.success) {
                    rolePermissions = data.data.permissions_by_module || {};
                }
            } catch (error) {
                console.error('Error loading role permissions:', error);
            }
        }

        // Handle role change
        async function handleRoleChange() {
            const roleId = document.getElementById('role_id').value;
            await loadRolePermissions(roleId);
            updatePermissionsUI();
        }

        // Toggle custom permissions section
        function toggleCustomPermissions() {
            const section = document.getElementById('permissionsSection');
            const isChecked = document.getElementById('custom_permissions').checked;
            section.style.display = isChecked ? 'block' : 'none';
        }

        // Render permissions table
        function renderPermissionsTable() {
            const container = document.getElementById('permissionsTableContainer');
            const actions = ['view', 'create', 'edit', 'delete', 'export'];
            const actionNames = {
                'view': 'צפייה',
                'create': 'יצירה',
                'edit': 'עריכה',
                'delete': 'מחיקה',
                'export': 'ייצוא'
            };

            let html = `
                <table class="permissions-table">
                    <thead>
                        <tr>
                            <th>מודול</th>
                            ${actions.map(a => `<th>${actionNames[a]}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
            `;

            allPermissions.forEach(module => {
                html += `<tr data-module="${module.name}">`;
                html += `<td class="module-name">${module.display_name}</td>`;

                actions.forEach(action => {
                    const permAction = module.actions.find(a => a.action === action);
                    if (permAction) {
                        html += `
                            <td>
                                <input type="checkbox"
                                    data-permission-id="${permAction.id}"
                                    data-module="${module.name}"
                                    data-action="${action}"
                                    onchange="handlePermissionChange(this)">
                            </td>
                        `;
                    } else {
                        html += `<td>-</td>`;
                    }
                });

                html += `</tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;

            updatePermissionsUI();
        }

        // Update permissions checkboxes based on role and custom
        function updatePermissionsUI() {
            const checkboxes = document.querySelectorAll('.permissions-table input[type="checkbox"]');

            checkboxes.forEach(checkbox => {
                const module = checkbox.dataset.module;
                const action = checkbox.dataset.action;
                const permId = checkbox.dataset.permissionId;

                // Check if role has this permission
                const hasRolePermission = rolePermissions[module] && rolePermissions[module].includes(action);

                // Check if user has custom override
                const customPerm = userCustomPermissions.find(p => p.id == permId || p.permission_id == permId);
                const hasCustomGrant = customPerm && customPerm.granted == 1;
                const hasCustomDeny = customPerm && customPerm.granted == 0;

                // Set checkbox state
                if (document.getElementById('custom_permissions').checked) {
                    // In custom mode - show actual custom state
                    checkbox.checked = hasCustomGrant;
                    checkbox.indeterminate = false;
                } else {
                    // Show role permissions (read-only visual)
                    checkbox.checked = hasRolePermission;
                    checkbox.indeterminate = false;
                }

                // Visual indication for role permissions
                const cell = checkbox.closest('td');
                if (hasRolePermission && !document.getElementById('custom_permissions').checked) {
                    cell.style.background = 'var(--color-primary-light)';
                } else {
                    cell.style.background = '';
                }
            });
        }

        // Handle permission checkbox change
        function handlePermissionChange(checkbox) {
            const permId = checkbox.dataset.permissionId;
            const isChecked = checkbox.checked;

            // Update userCustomPermissions
            const existingIndex = userCustomPermissions.findIndex(p => (p.id == permId || p.permission_id == permId));

            if (existingIndex >= 0) {
                userCustomPermissions[existingIndex].granted = isChecked ? 1 : 0;
            } else {
                userCustomPermissions.push({
                    permission_id: permId,
                    granted: isChecked ? 1 : 0
                });
            }
        }

        // Handle form submit
        async function handleSubmit(e) {
            e.preventDefault();

            const formData = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                is_active: parseInt(document.getElementById('is_active').value),
                role_id: document.getElementById('role_id').value || null,
                custom_permissions: document.getElementById('custom_permissions').checked ? 1 : 0
            };

            if (!isEdit) {
                const password = document.getElementById('password')?.value;
                if (password) {
                    formData.password = password;
                }
            }

            // Add custom permissions if enabled
            if (formData.custom_permissions) {
                formData.permissions = userCustomPermissions.map(p => ({
                    permission_id: p.permission_id || p.id,
                    granted: p.granted
                }));
            }

            if (isEdit) {
                formData.id = userId;
                formData.action = 'update';
            } else {
                formData.action = 'create';
            }

            // Show loading
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('submitBtn').disabled = true;

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/users-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', data.message || 'נשמר בהצלחה');

                    // Notify parent window
                    if (window.parent !== window) {
                        window.parent.postMessage({ type: 'userUpdated' }, '*');
                    }

                    // Close popup after short delay
                    setTimeout(() => {
                        closeForm();
                    }, 1000);

                } else {
                    showAlert('error', data.error || 'שגיאה בשמירה');
                }

            } catch (error) {
                console.error('Error saving user:', error);
                showAlert('error', 'שגיאה בשמירה');
            } finally {
                document.getElementById('loadingOverlay').style.display = 'none';
                document.getElementById('submitBtn').disabled = false;
            }
        }

        // Close form (popup or redirect)
        function closeForm() {
            if (window.parent !== window && typeof window.parent.PopupManager !== 'undefined') {
                window.parent.PopupManager.close('user-form-popup');
            } else {
                window.location.href = '/dashboard/dashboards/cemeteries/users/';
            }
        }

        // Show alert
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
