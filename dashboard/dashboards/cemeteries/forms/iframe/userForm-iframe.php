<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/userForm-iframe.php
 * Version: 2.0.0
 * Updated: 2026-01-26
 * Author: Malkiel
 * Description: טופס משתמש (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$user = null;
$userDashboardType = 'cemetery_manager'; // ברירת מחדל
$roles = [];
$permissions = [];
$userCustomPermissions = [];

try {
    $conn = getDBConnection();

    // טעינת פרופילים (roles) - רק של בתי עלמין
    try {
        $stmt = $conn->query("SELECT * FROM roles WHERE dashboard_type = 'cemetery_manager' OR dashboard_type IS NULL ORDER BY display_name");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // טבלת roles לא קיימת
        $roles = [];
    }

    // טעינת הרשאות
    try {
        $stmt = $conn->query("SELECT * FROM permissions ORDER BY sort_order, module, action");
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $permissions = [];
    }

    // קיבוץ הרשאות לפי מודול
    $permissionsByModule = [];
    foreach ($permissions as $perm) {
        $permissionsByModule[$perm['module']][] = $perm;
    }

    if ($isEditMode) {
        // טעינת משתמש
        $stmt = $conn->prepare("
            SELECT u.*,
                   up.dashboard_type,
                   r.name as role_name,
                   r.display_name as role_display_name
            FROM users u
            LEFT JOIN user_permissions up ON u.id = up.user_id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$itemId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body class="error-page">שגיאה: המשתמש לא נמצא</body></html>');
        }

        // קביעת סוג הדשבורד הנוכחי
        $userDashboardType = $user['dashboard_type'] ?? 'cemetery_manager';

        // טעינת הרשאות מותאמות
        try {
            $stmt = $conn->prepare("
                SELECT permission_id, granted
                FROM user_permissions_extended
                WHERE user_id = ?
            ");
            $stmt->execute([$itemId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userCustomPermissions[$row['permission_id']] = $row['granted'];
            }
        } catch (Exception $e) {
            // טבלה לא קיימת
        }
    }
} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body class="error-page">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת משתמש - ' . htmlspecialchars($user['name'] ?? $user['username']) : 'הוספת משתמש חדש';

// סוגי דשבורדים לבחירה
$selectableDashboards = getSelectableDashboardTypes();

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
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .dashboard-card {
            padding: 16px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            background: var(--bg-secondary);
        }
        .dashboard-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        .dashboard-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        .dashboard-card .icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        .dashboard-card .name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .dashboard-card input[type="radio"] {
            display: none;
        }

        .profile-section {
            display: none;
        }
        .profile-section.visible {
            display: block;
        }

        .profile-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .profile-card {
            padding: 16px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-secondary);
        }
        .profile-card:hover {
            border-color: var(--primary-color);
        }
        .profile-card.selected {
            border-color: var(--success-color);
            background: rgba(16, 185, 129, 0.1);
        }
        .profile-card .profile-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .profile-card .profile-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .profile-card input[type="radio"] {
            display: none;
        }

        .custom-profile-card {
            border-style: dashed;
            text-align: center;
        }
        .custom-profile-card i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

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

        .custom-permissions-section {
            display: none;
        }
        .custom-permissions-section.visible {
            display: block;
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
                            <div class="form-group">
                                <label>סטטוס</label>
                                <select name="is_active" class="form-control">
                                    <option value="1" <?= ($user['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>פעיל</option>
                                    <option value="0" <?= ($user['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>לא פעיל</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 2: בחירת דשבורד -->
                <div class="sortable-section section-purple" data-section="dashboard">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-th-large"></i> סוג דשבורד
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="dashboard-cards">
                            <?php foreach ($selectableDashboards as $key => $dashboard): ?>
                            <label class="dashboard-card <?= $userDashboardType === $key ? 'selected' : '' ?>"
                                   data-dashboard="<?= $key ?>"
                                   data-has-profiles="<?= $dashboard['has_profiles'] ? 'true' : 'false' ?>">
                                <input type="radio" name="dashboard_type" value="<?= $key ?>"
                                    <?= $userDashboardType === $key ? 'checked' : '' ?>>
                                <div class="icon"><?= $dashboard['icon'] ?></div>
                                <div class="name"><?= htmlspecialchars($dashboard['name']) ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- סקשן 3: בחירת פרופיל (רק לדשבורד עם פרופילים) -->
                <div class="sortable-section section-green profile-section <?= dashboardHasProfiles($userDashboardType) ? 'visible' : '' ?>" data-section="profile" id="profileSection">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-id-badge"></i> בחירת פרופיל
                        </span>
                    </div>
                    <div class="section-content">
                        <div class="profile-cards">
                            <?php foreach ($roles as $role): ?>
                            <label class="profile-card <?= ($user['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>"
                                   data-role-id="<?= $role['id'] ?>">
                                <input type="radio" name="role_id" value="<?= $role['id'] ?>"
                                    <?= ($user['role_id'] ?? '') == $role['id'] ? 'checked' : '' ?>>
                                <div class="profile-name"><?= htmlspecialchars($role['display_name']) ?></div>
                                <div class="profile-desc"><?= htmlspecialchars($role['description'] ?? '') ?></div>
                            </label>
                            <?php endforeach; ?>

                            <!-- פרופיל מותאם אישית -->
                            <label class="profile-card custom-profile-card <?= ($user['custom_permissions'] ?? 0) ? 'selected' : '' ?>"
                                   data-role-id="custom">
                                <input type="radio" name="role_id" value="custom"
                                    <?= ($user['custom_permissions'] ?? 0) ? 'checked' : '' ?>>
                                <i class="fas fa-plus-circle"></i>
                                <div class="profile-name">פרופיל מותאם אישית</div>
                                <div class="profile-desc">הגדר הרשאות ספציפיות</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- סקשן 4: הרשאות מותאמות (רק לפרופיל מותאם) -->
                <div class="sortable-section section-orange custom-permissions-section <?= ($user['custom_permissions'] ?? 0) ? 'visible' : '' ?>" data-section="permissions" id="customPermissionsSection">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-key"></i> הרשאות מותאמות אישית
                        </span>
                    </div>
                    <div class="section-content">
                        <input type="hidden" name="custom_permissions" id="customPermissionsFlag" value="<?= ($user['custom_permissions'] ?? 0) ? '1' : '0' ?>">
                        <div class="permissions-grid">
                            <?php foreach ($permissionsByModule as $module => $modulePermissions): ?>
                            <div class="module-permissions">
                                <div class="module-title"><?= $moduleNames[$module] ?? $module ?></div>
                                <?php foreach ($modulePermissions as $perm):
                                    $isGranted = isset($userCustomPermissions[$perm['id']]) ? $userCustomPermissions[$perm['id']] : 0;
                                ?>
                                <div class="permission-item">
                                    <input type="checkbox"
                                        name="permissions[<?= $perm['id'] ?>]"
                                        id="perm_<?= $perm['id'] ?>"
                                        value="1"
                                        <?= $isGranted ? 'checked' : '' ?>>
                                    <label for="perm_<?= $perm['id'] ?>"><?= $actionNames[$perm['action']] ?? $perm['action'] ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($isEditMode): ?>
                <!-- סקשן 5: אבטחה -->
                <div class="sortable-section section-red" data-section="security">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn collapsed" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-lock"></i> שינוי סיסמה
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

        // Toggle section collapse
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

        // Dashboard card selection
        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.dashboard-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input').checked = true;

                // Show/hide profile section
                const hasProfiles = this.dataset.hasProfiles === 'true';
                const profileSection = document.getElementById('profileSection');
                const customPermissionsSection = document.getElementById('customPermissionsSection');

                if (hasProfiles) {
                    profileSection.classList.add('visible');
                } else {
                    profileSection.classList.remove('visible');
                    customPermissionsSection.classList.remove('visible');
                    document.getElementById('customPermissionsFlag').value = '0';
                }
            });
        });

        // Profile card selection
        document.querySelectorAll('.profile-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.profile-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input').checked = true;

                // Show/hide custom permissions
                const isCustom = this.dataset.roleId === 'custom';
                const customPermissionsSection = document.getElementById('customPermissionsSection');
                const customPermissionsFlag = document.getElementById('customPermissionsFlag');

                if (isCustom) {
                    customPermissionsSection.classList.add('visible');
                    customPermissionsFlag.value = '1';
                } else {
                    customPermissionsSection.classList.remove('visible');
                    customPermissionsFlag.value = '0';
                }
            });
        });

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

            // בדיקה שנבחר דשבורד
            const dashboardType = this.dashboard_type?.value;
            if (!dashboardType) {
                showAlert('יש לבחור סוג דשבורד');
                return;
            }

            showLoading(true);

            try {
                const formData = new FormData(this);
                formData.append('action', isEditMode ? 'update' : 'create');

                // Handle custom role_id
                const roleId = formData.get('role_id');
                if (roleId === 'custom') {
                    formData.delete('role_id');
                    formData.set('role_id', '');
                }

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
    </script>
</body>
</html>
