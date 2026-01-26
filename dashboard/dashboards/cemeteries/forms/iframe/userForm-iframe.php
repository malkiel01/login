<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/userForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-26
 * Author: Malkiel
 * Description: טופס משתמש (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$user = null;
$roles = [];
$permissions = [];
$userCustomPermissions = [];

try {
    $conn = getDBConnection();

    // טעינת תפקידים
    $stmt = $conn->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // טעינת הרשאות
    $stmt = $conn->query("SELECT * FROM permissions ORDER BY sort_order, module, action");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // קיבוץ הרשאות לפי מודול
    $permissionsByModule = [];
    foreach ($permissions as $perm) {
        $permissionsByModule[$perm['module']][] = $perm;
    }

    if ($isEditMode) {
        // טעינת משתמש
        $stmt = $conn->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$itemId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body class="error-page">שגיאה: המשתמש לא נמצא</body></html>');
        }

        // טעינת הרשאות מותאמות
        $stmt = $conn->prepare("
            SELECT permission_id, granted
            FROM user_permissions_extended
            WHERE user_id = ?
        ");
        $stmt->execute([$itemId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userCustomPermissions[$row['permission_id']] = $row['granted'];
        }
    }
} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body class="error-page">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת משתמש - ' . htmlspecialchars($user['name'] ?? $user['username']) : 'הוספת משתמש חדש';

$moduleNames = [
    'dashboard' => 'דשבורד',
    'purchases' => 'תיקי רכישה',
    'customers' => 'לקוחות',
    'burials' => 'קבורות',
    'graves' => 'קברים',
    'plots' => 'חלקות',
    'blocks' => 'גושים',
    'cemeteries' => 'בתי עלמין',
    'areaGraves' => 'אחוזות קבר',
    'payments' => 'תשלומים',
    'reports' => 'דוחות',
    'files' => 'קבצים',
    'settings' => 'הגדרות',
    'users' => 'משתמשים',
    'roles' => 'תפקידים',
    'map' => 'מפה',
    'residency' => 'תושבות',
    'countries' => 'מדינות',
    'cities' => 'ערים'
];

$actionNames = [
    'view' => 'צפייה',
    'create' => 'יצירה',
    'edit' => 'עריכה',
    'delete' => 'מחיקה',
    'export' => 'ייצוא',
    'upload' => 'העלאה',
    'approve' => 'אישור'
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
    <style>
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .module-permissions {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 12px;
        }
        .module-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: var(--text-primary);
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        .permission-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 0.85rem;
        }
        .permission-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }
        .permission-item label {
            margin: 0;
            cursor: pointer;
            color: var(--text-secondary);
        }
        .permission-item.granted label {
            color: var(--success-color);
            font-weight: 500;
        }
        .permission-item.denied label {
            color: var(--text-muted);
            text-decoration: line-through;
        }
        .custom-permissions-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--bg-secondary);
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .custom-permissions-toggle input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .custom-permissions-toggle label {
            font-weight: 500;
        }
        .permissions-section {
            display: none;
        }
        .permissions-section.active {
            display: block;
        }
        .role-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--bg-tertiary);
            border-radius: 8px;
            margin-top: 12px;
        }
        .role-info i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        .role-info-text {
            flex: 1;
        }
        .role-info-text strong {
            display: block;
            margin-bottom: 2px;
        }
        .role-info-text small {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="userForm" novalidate>
            <input type="hidden" name="id" value="<?= htmlspecialchars($user['id'] ?? '') ?>">

            <div class="sortable-sections" id="userFormSortableSections">
                <!-- סקשן 1: פרטים אישיים -->
                <div class="sortable-section section-blue" data-section="personal">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-user"></i> פרטים אישיים
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>שם מלא <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                    value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                                    placeholder="שם מלא">
                            </div>
                            <div class="form-group">
                                <label>שם משתמש <span class="required">*</span></label>
                                <input type="text" name="username" class="form-control" required
                                    value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                                    placeholder="שם משתמש לכניסה">
                            </div>
                            <div class="form-group">
                                <label>אימייל <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" required
                                    value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                    placeholder="example@domain.com">
                            </div>
                            <div class="form-group">
                                <label>טלפון</label>
                                <input type="tel" name="phone" class="form-control"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                    placeholder="050-0000000">
                            </div>
                            <?php if (!$isEditMode): ?>
                            <div class="form-group">
                                <label>סיסמה <span class="required">*</span></label>
                                <input type="password" name="password" class="form-control" required
                                    placeholder="סיסמה חדשה" minlength="6">
                            </div>
                            <div class="form-group">
                                <label>אימות סיסמה <span class="required">*</span></label>
                                <input type="password" name="password_confirm" class="form-control" required
                                    placeholder="הקלד שוב את הסיסמה">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- סקשן 2: תפקיד והרשאות -->
                <div class="sortable-section section-purple" data-section="role">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-user-shield"></i> תפקיד והרשאות
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>תפקיד <span class="required">*</span></label>
                                <select name="role_id" id="roleSelect" class="form-control" required onchange="onRoleChange()">
                                    <option value="">-- בחר תפקיד --</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"
                                        data-description="<?= htmlspecialchars($role['description'] ?? '') ?>"
                                        <?= ($user['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['display_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>סטטוס</label>
                                <select name="is_active" class="form-control">
                                    <option value="1" <?= ($user['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>פעיל</option>
                                    <option value="0" <?= ($user['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>לא פעיל</option>
                                </select>
                            </div>
                        </div>

                        <div class="role-info" id="roleInfo" style="display: none;">
                            <i class="fas fa-info-circle"></i>
                            <div class="role-info-text">
                                <strong id="roleInfoTitle"></strong>
                                <small id="roleInfoDesc"></small>
                            </div>
                        </div>

                        <div class="custom-permissions-toggle">
                            <input type="checkbox" id="customPermissionsEnabled" name="custom_permissions"
                                <?= ($user['custom_permissions'] ?? 0) ? 'checked' : '' ?>
                                onchange="toggleCustomPermissions()">
                            <label for="customPermissionsEnabled">הפעל הרשאות מותאמות אישית (עוקף את הרשאות התפקיד)</label>
                        </div>
                    </div>
                </div>

                <!-- סקשן 3: הרשאות מותאמות -->
                <div class="sortable-section section-green permissions-section <?= ($user['custom_permissions'] ?? 0) ? 'active' : '' ?>" data-section="permissions" id="permissionsSection">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-key"></i> הרשאות מותאמות אישית
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="permissions-grid">
                            <?php foreach ($permissionsByModule as $module => $modulePermissions): ?>
                            <div class="module-permissions">
                                <div class="module-title"><?= $moduleNames[$module] ?? $module ?></div>
                                <?php foreach ($modulePermissions as $perm):
                                    $isGranted = isset($userCustomPermissions[$perm['id']]) ? $userCustomPermissions[$perm['id']] : null;
                                    $className = $isGranted === 1 ? 'granted' : ($isGranted === 0 ? 'denied' : '');
                                ?>
                                <div class="permission-item <?= $className ?>">
                                    <input type="checkbox"
                                        name="permissions[<?= $perm['id'] ?>]"
                                        id="perm_<?= $perm['id'] ?>"
                                        value="1"
                                        <?= $isGranted === 1 ? 'checked' : '' ?>>
                                    <label for="perm_<?= $perm['id'] ?>"><?= $actionNames[$perm['action']] ?? $perm['action'] ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($isEditMode): ?>
                <!-- סקשן 4: אבטחה -->
                <div class="sortable-section section-orange" data-section="security">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn collapsed" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-lock"></i> אבטחה
                        </span>
                    </div>
                    <div class="section-content" style="display: none;">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>סיסמה חדשה</label>
                                <input type="password" name="new_password" class="form-control"
                                    placeholder="השאר ריק לשמירת הסיסמה הנוכחית" minlength="6">
                            </div>
                            <div class="form-group">
                                <label>אימות סיסמה חדשה</label>
                                <input type="password" name="new_password_confirm" class="form-control"
                                    placeholder="הקלד שוב את הסיסמה החדשה">
                            </div>
                        </div>
                        <div class="form-info">
                            <i class="fas fa-info-circle"></i>
                            <span>אם ברצונך לשנות סיסמה, הזן סיסמה חדשה. אחרת השאר את השדות ריקים.</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- כפתורי פעולה -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?= $isEditMode ? 'שמור שינויים' : 'צור משתמש' ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closePopup()">
                    <i class="fas fa-times"></i> ביטול
                </button>
            </div>
        </form>
    </div>

    <script>
        const popupId = '<?= $popupId ?>';
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const userId = <?= $isEditMode ? (int)$itemId : 'null' ?>;

        function toggleSection(btn) {
            const section = btn.closest('.sortable-section');
            const content = section.querySelector('.section-content');
            const isCollapsed = btn.classList.contains('collapsed');

            if (isCollapsed) {
                content.style.display = 'block';
                btn.classList.remove('collapsed');
            } else {
                content.style.display = 'none';
                btn.classList.add('collapsed');
            }
        }

        function onRoleChange() {
            const select = document.getElementById('roleSelect');
            const option = select.options[select.selectedIndex];
            const roleInfo = document.getElementById('roleInfo');

            if (option.value) {
                const title = option.text;
                const desc = option.dataset.description || '';
                document.getElementById('roleInfoTitle').textContent = title;
                document.getElementById('roleInfoDesc').textContent = desc;
                roleInfo.style.display = 'flex';
            } else {
                roleInfo.style.display = 'none';
            }
        }

        function toggleCustomPermissions() {
            const enabled = document.getElementById('customPermissionsEnabled').checked;
            const section = document.getElementById('permissionsSection');

            if (enabled) {
                section.classList.add('active');
            } else {
                section.classList.remove('active');
            }
        }

        function closePopup() {
            if (popupId && window.parent && window.parent.PopupManager) {
                window.parent.PopupManager.close(popupId);
            } else if (window.parent !== window) {
                window.parent.postMessage({ type: 'closePopup' }, '*');
            }
        }

        function showAlert(message, type = 'error') {
            const alertBox = document.getElementById('alertBox');
            alertBox.className = `alert alert-${type}`;
            alertBox.textContent = message;
            alertBox.style.display = 'block';
            setTimeout(() => alertBox.style.display = 'none', 5000);
        }

        function showLoading(show = true) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        document.getElementById('userForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // בדיקת סיסמאות
            const password = this.password?.value;
            const passwordConfirm = this.password_confirm?.value;
            const newPassword = this.new_password?.value;
            const newPasswordConfirm = this.new_password_confirm?.value;

            if (!isEditMode && password !== passwordConfirm) {
                showAlert('הסיסמאות אינן תואמות');
                return;
            }

            if (newPassword && newPassword !== newPasswordConfirm) {
                showAlert('הסיסמאות החדשות אינן תואמות');
                return;
            }

            showLoading(true);

            try {
                const formData = new FormData(this);
                formData.append('action', isEditMode ? 'update' : 'create');

                const response = await fetch('/dashboard/dashboards/cemeteries/api/users-api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // עדכון הדף הראשי
                    if (window.parent && window.parent.refreshUsersList) {
                        window.parent.refreshUsersList();
                    }

                    showAlert(isEditMode ? 'המשתמש עודכן בהצלחה' : 'המשתמש נוצר בהצלחה', 'success');

                    setTimeout(closePopup, 1000);
                } else {
                    showAlert(result.error || 'אירעה שגיאה');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('אירעה שגיאה בשמירה');
            } finally {
                showLoading(false);
            }
        });

        // אתחול
        document.addEventListener('DOMContentLoaded', function() {
            onRoleChange();
        });
    </script>
</body>
</html>
