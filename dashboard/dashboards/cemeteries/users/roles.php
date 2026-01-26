<?php
/**
 * Roles Management - ניהול תפקידים
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות
requireDashboard(['cemetery_manager', 'admin']);

if (!isAdmin() && !hasModulePermission('roles', 'view')) {
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

$canCreate = isAdmin() || hasModulePermission('roles', 'create');
$canEdit = isAdmin() || hasModulePermission('roles', 'edit');
$canDelete = isAdmin() || hasModulePermission('roles', 'delete');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול תפקידים</title>
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

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .role-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 2px solid transparent;
            transition: border-color 0.2s;
            cursor: pointer;
        }

        .role-card:hover {
            border-color: var(--color-primary);
        }

        .role-card.selected {
            border-color: var(--color-primary);
        }

        .role-card.system {
            border-right: 4px solid var(--color-primary);
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .role-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        .role-code {
            font-size: 12px;
            color: var(--text-secondary);
            font-family: monospace;
        }

        .role-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .role-stats {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .role-stat {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-system {
            background: var(--color-primary-light);
            color: var(--color-primary);
        }

        .permissions-editor {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: none;
        }

        .permissions-editor.active {
            display: block;
        }

        .editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .editor-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .editor-actions {
            display: flex;
            gap: 12px;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .module-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 16px;
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .module-name {
            font-weight: 600;
            font-size: 14px;
        }

        .select-all {
            font-size: 12px;
            color: var(--color-primary);
            cursor: pointer;
        }

        .select-all:hover {
            text-decoration: underline;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
        }

        .permission-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .permission-item label {
            font-size: 13px;
            cursor: pointer;
            flex: 1;
        }

        .btn {
            padding: 10px 20px;
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

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
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
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            display: none;
        }

        .modal {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 20px 0;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-secondary);
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            body {
                padding: 16px;
            }

            .roles-grid {
                grid-template-columns: 1fr;
            }

            .permissions-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">

    <div id="alertContainer"></div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">ניהול תפקידים</h1>
            <?php if ($canCreate): ?>
            <button class="btn btn-primary" onclick="openNewRoleModal()">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14m-7-7h14"/>
                </svg>
                תפקיד חדש
            </button>
            <?php endif; ?>
        </div>

        <div class="roles-grid" id="rolesGrid">
            <div class="loading">טוען תפקידים...</div>
        </div>

        <div class="permissions-editor" id="permissionsEditor">
            <div class="editor-header">
                <h2 class="editor-title" id="editorTitle">הרשאות תפקיד</h2>
                <div class="editor-actions">
                    <?php if ($canDelete): ?>
                    <button class="btn btn-danger btn-sm" id="deleteRoleBtn" onclick="deleteRole()" style="display: none;">
                        מחק תפקיד
                    </button>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <button class="btn btn-primary" onclick="savePermissions()">שמור שינויים</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="permissions-grid" id="permissionsGrid">
            </div>
        </div>
    </div>

    <!-- Modal for new role -->
    <div class="modal-overlay" id="newRoleModal">
        <div class="modal">
            <h3 class="modal-title">תפקיד חדש</h3>
            <form id="newRoleForm">
                <div class="form-group">
                    <label>שם פנימי (באנגלית)</label>
                    <input type="text" class="form-control" id="newRoleName" pattern="[a-z_]+" required placeholder="accountant">
                </div>
                <div class="form-group">
                    <label>שם לתצוגה</label>
                    <input type="text" class="form-control" id="newRoleDisplayName" required placeholder="מנהלת חשבונות">
                </div>
                <div class="form-group">
                    <label>תיאור</label>
                    <textarea class="form-control" id="newRoleDescription" rows="3" placeholder="תיאור התפקיד..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeNewRoleModal()">ביטול</button>
                    <button type="submit" class="btn btn-primary">צור תפקיד</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // State
        let roles = [];
        let allPermissions = [];
        let selectedRole = null;
        let rolePermissions = [];

        const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
        const canDelete = <?= $canDelete ? 'true' : 'false' ?>;

        // Init
        document.addEventListener('DOMContentLoaded', async function() {
            await loadRoles();
            await loadAllPermissions();

            document.getElementById('newRoleForm').addEventListener('submit', handleNewRoleSubmit);
        });

        // Load roles
        async function loadRoles() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php?action=list');
                const data = await response.json();
                if (data.success) {
                    roles = data.data;
                    renderRolesGrid();
                }
            } catch (error) {
                console.error('Error loading roles:', error);
            }
        }

        // Load all permissions
        async function loadAllPermissions() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php?action=permissions');
                const data = await response.json();
                if (data.success) {
                    allPermissions = data.data;
                }
            } catch (error) {
                console.error('Error loading permissions:', error);
            }
        }

        // Render roles grid
        function renderRolesGrid() {
            const grid = document.getElementById('rolesGrid');

            if (roles.length === 0) {
                grid.innerHTML = '<div class="loading">לא נמצאו תפקידים</div>';
                return;
            }

            grid.innerHTML = roles.map(role => `
                <div class="role-card ${role.is_system ? 'system' : ''} ${selectedRole?.id === role.id ? 'selected' : ''}"
                     onclick="selectRole(${role.id})">
                    <div class="role-header">
                        <div>
                            <h3 class="role-name">${escapeHtml(role.display_name)}</h3>
                            <div class="role-code">${escapeHtml(role.name)}</div>
                        </div>
                        ${role.is_system ? '<span class="badge badge-system">מערכת</span>' : ''}
                    </div>
                    ${role.description ? `<p class="role-description">${escapeHtml(role.description)}</p>` : ''}
                    <div class="role-stats">
                        <span class="role-stat">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                            ${role.users_count} משתמשים
                        </span>
                        <span class="role-stat">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            ${role.permissions_count} הרשאות
                        </span>
                    </div>
                </div>
            `).join('');
        }

        // Select role
        async function selectRole(roleId) {
            selectedRole = roles.find(r => r.id === roleId);

            // Update UI
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');

            // Load role permissions
            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/roles-api.php?action=get&id=${roleId}`);
                const data = await response.json();
                if (data.success) {
                    rolePermissions = data.data.permissions.map(p => p.id);
                    renderPermissionsEditor();
                }
            } catch (error) {
                console.error('Error loading role permissions:', error);
            }
        }

        // Render permissions editor
        function renderPermissionsEditor() {
            const editor = document.getElementById('permissionsEditor');
            const grid = document.getElementById('permissionsGrid');
            const title = document.getElementById('editorTitle');
            const deleteBtn = document.getElementById('deleteRoleBtn');

            editor.classList.add('active');
            title.textContent = `הרשאות: ${selectedRole.display_name}`;

            // Show/hide delete button
            if (deleteBtn) {
                deleteBtn.style.display = selectedRole.is_system ? 'none' : 'block';
            }

            // Render permissions by module
            grid.innerHTML = allPermissions.map(module => `
                <div class="module-card">
                    <div class="module-header">
                        <span class="module-name">${module.display_name}</span>
                        <span class="select-all" onclick="toggleModuleAll('${module.name}')">סמן/בטל הכל</span>
                    </div>
                    ${module.actions.map(action => `
                        <div class="permission-item">
                            <input type="checkbox"
                                id="perm_${action.id}"
                                data-permission-id="${action.id}"
                                data-module="${module.name}"
                                ${rolePermissions.includes(action.id) ? 'checked' : ''}
                                ${!canEdit ? 'disabled' : ''}>
                            <label for="perm_${action.id}">${action.display_name}</label>
                        </div>
                    `).join('')}
                </div>
            `).join('');
        }

        // Toggle all permissions for a module
        function toggleModuleAll(moduleName) {
            if (!canEdit) return;

            const checkboxes = document.querySelectorAll(`input[data-module="${moduleName}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }

        // Save permissions
        async function savePermissions() {
            if (!selectedRole || !canEdit) return;

            const checkboxes = document.querySelectorAll('#permissionsGrid input[type="checkbox"]:checked');
            const permissionIds = Array.from(checkboxes).map(cb => parseInt(cb.dataset.permissionId));

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update',
                        id: selectedRole.id,
                        permissions: permissionIds
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'ההרשאות נשמרו בהצלחה');
                    loadRoles(); // Refresh counts
                } else {
                    showAlert('error', data.error || 'שגיאה בשמירה');
                }
            } catch (error) {
                console.error('Error saving permissions:', error);
                showAlert('error', 'שגיאה בשמירה');
            }
        }

        // Delete role
        async function deleteRole() {
            if (!selectedRole || selectedRole.is_system || !canDelete) return;

            if (!confirm(`האם אתה בטוח שברצונך למחוק את התפקיד "${selectedRole.display_name}"?`)) {
                return;
            }

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        id: selectedRole.id
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'התפקיד נמחק בהצלחה');
                    selectedRole = null;
                    document.getElementById('permissionsEditor').classList.remove('active');
                    loadRoles();
                } else {
                    showAlert('error', data.error || 'שגיאה במחיקה');
                }
            } catch (error) {
                console.error('Error deleting role:', error);
                showAlert('error', 'שגיאה במחיקה');
            }
        }

        // New role modal
        function openNewRoleModal() {
            document.getElementById('newRoleModal').style.display = 'flex';
        }

        function closeNewRoleModal() {
            document.getElementById('newRoleModal').style.display = 'none';
            document.getElementById('newRoleForm').reset();
        }

        async function handleNewRoleSubmit(e) {
            e.preventDefault();

            const formData = {
                action: 'create',
                name: document.getElementById('newRoleName').value,
                display_name: document.getElementById('newRoleDisplayName').value,
                description: document.getElementById('newRoleDescription').value,
                permissions: []
            };

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/roles-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'התפקיד נוצר בהצלחה');
                    closeNewRoleModal();
                    loadRoles();

                    // Select the new role
                    setTimeout(() => {
                        const newRoleId = data.data.id;
                        selectRole(newRoleId);
                    }, 500);

                } else {
                    showAlert('error', data.error || 'שגיאה ביצירת תפקיד');
                }
            } catch (error) {
                console.error('Error creating role:', error);
                showAlert('error', 'שגיאה ביצירת תפקיד');
            }
        }

        // Show alert
        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;

            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        // Escape HTML
        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('newRoleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewRoleModal();
            }
        });
    </script>
</body>
</html>
