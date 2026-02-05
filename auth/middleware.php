<?php
/**
 * Auth Middleware - מערכת הרשאות מרכזית
 *
 * שימוש:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/middleware.php';
 *
 * // בדיקה בסיסית - רק שהמשתמש מחובר
 * requireAuth();
 *
 * // בדיקה עם הרשאה ספציפית
 * requireAuth('view_graves');
 *
 * // בדיקה עם סוג דשבורד מסוים
 * requireDashboard('cemetery_manager');
 *
 * // בדיקה עם מספר סוגי דשבורד (OR)
 * requireDashboard(['cemetery_manager', 'admin']);
 *
 * @version 1.0.0
 * @author Malkiel
 */

// התחל session אם לא התחיל
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// טען את הקונפיג הראשי
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/../config.php';
}

// טען את הגדרות הדשבורדים
if (!defined('DASHBOARD_TYPES')) {
    require_once __DIR__ . '/../dashboard/config.php';
}

// טען את מנהל ה-Tokens
if (!function_exists('getTokenManager')) {
    require_once __DIR__ . '/token-manager.php';
}

/**
 * בדיקה האם המשתמש מחובר
 * בודק גם session וגם token (עבור PWA/iOS)
 * @return bool
 */
function isLoggedIn(): bool {
    // בדיקת session רגילה
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // אם אין session, נסה לשחזר מ-token (עבור PWA/iOS)
    return tryRestoreSessionFromToken();
}

/**
 * ניסיון לשחזר session מ-token
 * @return bool
 */
function tryRestoreSessionFromToken(): bool {
    // קבל token מ-cookie או header
    $token = getAuthToken();

    if (empty($token)) {
        return false;
    }

    try {
        $tokenManager = getTokenManager();
        $userData = $tokenManager->validateToken($token);

        if (!$userData) {
            return false;
        }

        // שחזר את ה-session
        $_SESSION['user_id'] = $userData['user_id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['name'] = $userData['name'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['profile_picture'] = $userData['profile_picture'];
        $_SESSION['restored_from_token'] = true;
        $_SESSION['is_pwa'] = true;

        // אם ה-token עומד לפוג, סמן לרענון
        if ($userData['should_refresh']) {
            $_SESSION['token_needs_refresh'] = true;
        }

        return true;

    } catch (Exception $e) {
        error_log("Token restore error: " . $e->getMessage());
        return false;
    }
}

/**
 * קבלת auth token מ-cookie או header
 * @return string|null
 */
function getAuthToken(): ?string {
    // מ-cookie
    if (isset($_COOKIE['auth_token']) && !empty($_COOKIE['auth_token'])) {
        return $_COOKIE['auth_token'];
    }

    // מ-header (Authorization: Bearer)
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }

    // מ-header (X-Auth-Token)
    if (isset($headers['X-Auth-Token'])) {
        return $headers['X-Auth-Token'];
    }

    // fallback - remember_token ישן
    if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
        return $_COOKIE['remember_token'];
    }

    return null;
}

/**
 * קבלת מזהה המשתמש הנוכחי
 * @return int|null
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * קבלת סוג הדשבורד של המשתמש
 * @param int|null $userId - אם לא צוין, ישתמש במשתמש הנוכחי
 * @return string
 */
function getUserDashboardType(?int $userId = null): string {
    // אם כבר שמור ב-session
    if ($userId === null && isset($_SESSION['dashboard_type'])) {
        return $_SESSION['dashboard_type'];
    }

    $userId = $userId ?? getCurrentUserId();
    if (!$userId) {
        return 'default';
    }

    try {
        $pdo = getDBConnection();

        // בדוק אם הטבלה קיימת
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_permissions'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            return 'default';
        }

        $stmt = $pdo->prepare("
            SELECT dashboard_type
            FROM user_permissions
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $dashboardType = $result['dashboard_type'] ?? 'default';

        // שמור ב-session למהירות
        if ($userId === getCurrentUserId()) {
            $_SESSION['dashboard_type'] = $dashboardType;
        }

        return $dashboardType;

    } catch (Exception $e) {
        error_log("Auth middleware error: " . $e->getMessage());
        return 'default';
    }
}

/**
 * קבלת תפקיד המשתמש הנוכחי (למערכת בתי עלמין)
 * @return string - viewer, editor, manager, cemetery_manager
 */
function getCurrentUserRole(): string {
    $dashboardType = getUserDashboardType();

    // מיפוי dashboard_type לתפקיד
    $mapping = [
        'cemetery_manager' => 'cemetery_manager',
        'admin' => 'cemetery_manager',
        'manager' => 'manager',
        'employee' => 'editor',
        'client' => 'viewer',
        'default' => 'viewer'
    ];

    return $mapping[$dashboardType] ?? 'viewer';
}

/**
 * קבלת רשימת ההרשאות של המשתמש
 * @return array
 */
function getUserPermissions(): array {
    $dashboardType = getUserDashboardType();
    return DASHBOARD_TYPES[$dashboardType]['permissions'] ?? [];
}

/**
 * בדיקה האם למשתמש יש הרשאה מסוימת
 * @param string $permission
 * @return bool
 */
function hasPermission(string $permission): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $permissions = getUserPermissions();

    // admin עם view_all/edit_all/delete_all מקבל הכל
    if (in_array('view_all', $permissions) && strpos($permission, 'view') !== false) {
        return true;
    }
    if (in_array('edit_all', $permissions) && strpos($permission, 'edit') !== false) {
        return true;
    }
    if (in_array('delete_all', $permissions) && strpos($permission, 'delete') !== false) {
        return true;
    }

    return in_array($permission, $permissions);
}

/**
 * בדיקה האם המשתמש הוא מסוג דשבורד מסוים
 * @param string|array $allowedTypes - סוג או רשימת סוגים
 * @return bool
 */
function hasDashboardType($allowedTypes): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $userType = getUserDashboardType();

    // admin תמיד עובר
    if ($userType === 'admin') {
        return true;
    }

    if (is_array($allowedTypes)) {
        return in_array($userType, $allowedTypes);
    }

    return $userType === $allowedTypes;
}

/**
 * דרישת התחברות - מפנה ל-login אם לא מחובר
 * @param string|null $requiredPermission - הרשאה נדרשת (אופציונלי)
 * @param bool $isApi - האם זו קריאת API (מחזיר JSON במקום redirect)
 */
function requireAuth(?string $requiredPermission = null, bool $isApi = false): void {
    // בדוק התחברות
    if (!isLoggedIn()) {
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'נדרשת התחברות למערכת'
            ]);
            exit;
        }

        // שמור את ה-URL הנוכחי לחזרה אחרי login
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/dashboard';
        $_SESSION['redirect_after_login'] = $currentUrl;

        // v16: שימוש ב-location.replace() כדי לא להוסיף entries להיסטוריה
        // זה מונע את הבעיה של back → login → dashboard loop
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        echo '<script>location.replace("/auth/login.php");</script>';
        echo '</head><body></body></html>';
        exit;
    }

    // בדוק הרשאה ספציפית אם נדרשה
    if ($requiredPermission !== null && !hasPermission($requiredPermission)) {
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'אין לך הרשאה לפעולה זו'
            ]);
            exit;
        }

        http_response_code(403);
        die('
        <!DOCTYPE html>
        <html dir="rtl" lang="he">
        <head>
            <meta charset="UTF-8">
            <title>אין הרשאה</title>
            <style>
                body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f3f4f6; margin: 0; }
                .box { background: white; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; margin-bottom: 10px; }
                p { color: #6b7280; }
                a { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; }
                a:hover { background: #5a67d8; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>🚫 אין הרשאה</h1>
                <p>אין לך הרשאה לצפות בדף זה.</p>
                <p>הרשאה נדרשת: <strong>' . htmlspecialchars($requiredPermission) . '</strong></p>
                <a href="/dashboard">חזרה לדשבורד</a>
            </div>
        </body>
        </html>
        ');
    }
}

/**
 * דרישת סוג דשבורד מסוים
 * @param string|array $allowedTypes - סוג או רשימת סוגים מותרים
 * @param bool $isApi - האם זו קריאת API
 */
function requireDashboard($allowedTypes, bool $isApi = false): void {
    requireAuth(null, $isApi);

    if (!hasDashboardType($allowedTypes)) {
        $allowedStr = is_array($allowedTypes) ? implode(', ', $allowedTypes) : $allowedTypes;

        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'אין לך הרשאה לדשבורד זה'
            ]);
            exit;
        }

        http_response_code(403);
        die('
        <!DOCTYPE html>
        <html dir="rtl" lang="he">
        <head>
            <meta charset="UTF-8">
            <title>אין הרשאה</title>
            <style>
                body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f3f4f6; margin: 0; }
                .box { background: white; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; margin-bottom: 10px; }
                p { color: #6b7280; }
                a { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; }
                a:hover { background: #5a67d8; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>🚫 אין הרשאה</h1>
                <p>אין לך הרשאה לדשבורד זה.</p>
                <p>נדרש: <strong>' . htmlspecialchars($allowedStr) . '</strong></p>
                <a href="/dashboard">חזרה לדשבורד</a>
            </div>
        </body>
        </html>
        ');
    }
}

/**
 * דרישת התחברות עבור API (קיצור)
 * @param string|null $requiredPermission
 */
function requireApiAuth(?string $requiredPermission = null): void {
    requireAuth($requiredPermission, true);
}

/**
 * דרישת סוג דשבורד עבור API (קיצור)
 * @param string|array $allowedTypes
 */
function requireApiDashboard($allowedTypes): void {
    requireDashboard($allowedTypes, true);
}

/**
 * בדיקת הרשאה עבור פעולה ומודול ספציפיים
 * (פונקציה תואמת לקוד הקיים בדשבורד בתי עלמין)
 * @param string $action - פעולה (view, edit, delete)
 * @param string $module - מודול (cemetery, grave, customer, etc.)
 * @return bool
 */
function checkPermission(string $action, string $module = 'cemetery'): bool {
    if (!isLoggedIn()) {
        return false;
    }

    // נסה קודם את המערכת החדשה
    if (hasModulePermission($module, $action)) {
        return true;
    }

    // Backward compatibility - מערכת ישנה
    // מיפוי פעולות להרשאות
    $permissionMap = [
        'view' => 'view_' . $module,
        'edit' => 'edit_' . $module,
        'delete' => 'delete_' . $module,
        'manage' => 'manage_' . $module
    ];

    $permission = $permissionMap[$action] ?? $action . '_' . $module;

    // בדוק הרשאה ספציפית
    if (hasPermission($permission)) {
        return true;
    }

    // בדוק הרשאות כלליות (view_graves, edit_graves וכו')
    $generalPermissions = [
        'view' => ['view_graves', 'view_all'],
        'edit' => ['edit_graves', 'edit_all'],
        'delete' => ['delete_all'],
        'manage' => ['manage_burials', 'manage_families', 'manage_users']
    ];

    $userPermissions = getUserPermissions();

    foreach ($generalPermissions[$action] ?? [] as $generalPerm) {
        if (in_array($generalPerm, $userPermissions)) {
            return true;
        }
    }

    return false;
}

// ============================================
// NEW PERMISSIONS SYSTEM - v2.0
// ============================================

/**
 * בדיקת הרשאה ספציפית למשתמש (מערכת חדשה)
 * @param string $module - שם המודול (purchases, customers, burials, etc.)
 * @param string $action - שם הפעולה (view, create, edit, delete, export)
 * @return bool
 */
function hasModulePermission(string $module, string $action): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        return false;
    }

    // בדוק אם יש הרשאות ב-cache
    $cacheKey = "permissions_v2_{$userId}";
    if (isset($_SESSION[$cacheKey])) {
        $permissions = $_SESSION[$cacheKey];
        return isset($permissions[$module]) && in_array($action, $permissions[$module]);
    }

    // טען הרשאות מהDB ושמור ב-cache
    $permissions = loadUserPermissionsFromDB($userId);
    $_SESSION[$cacheKey] = $permissions;

    return isset($permissions[$module]) && in_array($action, $permissions[$module]);
}

/**
 * טעינת הרשאות המשתמש מהDB
 * @param int $userId
 * @return array - מערך של [module => [actions]]
 */
function loadUserPermissionsFromDB(int $userId): array {
    try {
        $pdo = getDBConnection();

        // בדוק אם טבלת roles קיימת (מערכת חדשה)
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'roles'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            return []; // מערכת חדשה לא מותקנת
        }

        // שאילתא מורכבת - שילוב הרשאות תפקיד + הרשאות מותאמות
        $sql = "
            SELECT DISTINCT
                p.module,
                p.action
            FROM permissions p
            INNER JOIN users u ON u.id = :user_id
            LEFT JOIN role_permissions rp ON rp.permission_id = p.id AND rp.role_id = u.role_id
            LEFT JOIN user_permissions_extended upe ON upe.permission_id = p.id AND upe.user_id = u.id
            WHERE
                -- יש הרשאה מתפקיד ולא נדחתה במפורש
                (rp.role_id IS NOT NULL AND (upe.granted IS NULL OR upe.granted = 1))
                -- או הרשאה מותאמת שאושרה
                OR upe.granted = 1
            ORDER BY p.module, p.action
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // המר לפורמט [module => [actions]]
        $permissions = [];
        foreach ($results as $row) {
            if (!isset($permissions[$row['module']])) {
                $permissions[$row['module']] = [];
            }
            $permissions[$row['module']][] = $row['action'];
        }

        return $permissions;

    } catch (Exception $e) {
        error_log("loadUserPermissionsFromDB error: " . $e->getMessage());
        return [];
    }
}

/**
 * קבלת כל ההרשאות של המשתמש בפירוט
 * @return array - מערך של [module => [actions]]
 */
function getUserPermissionsDetailed(): array {
    if (!isLoggedIn()) {
        return [];
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        return [];
    }

    $cacheKey = "permissions_v2_{$userId}";
    if (isset($_SESSION[$cacheKey])) {
        return $_SESSION[$cacheKey];
    }

    $permissions = loadUserPermissionsFromDB($userId);
    $_SESSION[$cacheKey] = $permissions;

    return $permissions;
}

/**
 * בדיקה אם למשתמש יש הרשאות מותאמות אישית
 * @return bool
 */
function hasCustomPermissions(): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        return false;
    }

    try {
        $pdo = getDBConnection();

        // בדוק אם עמודת custom_permissions קיימת
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'custom_permissions'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT custom_permissions FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (bool)($result['custom_permissions'] ?? false);

    } catch (Exception $e) {
        error_log("hasCustomPermissions error: " . $e->getMessage());
        return false;
    }
}

/**
 * קבלת תפקיד המשתמש
 * @return array|null - פרטי התפקיד או null
 */
function getUserRole(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    $userId = getCurrentUserId();
    if (!$userId) {
        return null;
    }

    // בדוק cache
    if (isset($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }

    try {
        $pdo = getDBConnection();

        // בדוק אם טבלת roles קיימת
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'roles'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT r.*
            FROM roles r
            INNER JOIN users u ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($role) {
            $_SESSION['user_role'] = $role;
        }

        return $role ?: null;

    } catch (Exception $e) {
        error_log("getUserRole error: " . $e->getMessage());
        return null;
    }
}

/**
 * בדיקה אם המשתמש הוא admin
 * @return bool
 */
function isAdmin(): bool {
    $role = getUserRole();
    return $role && $role['name'] === 'admin';
}

/**
 * ניקוי cache של הרשאות (קורא לזה אחרי עדכון הרשאות)
 * @param int|null $userId - אם לא צוין, ינקה למשתמש הנוכחי
 */
function clearPermissionsCache(?int $userId = null): void {
    $userId = $userId ?? getCurrentUserId();
    if ($userId) {
        unset($_SESSION["permissions_v2_{$userId}"]);
        unset($_SESSION['user_role']);
    }
}

/**
 * דרישת הרשאה ספציפית (מערכת חדשה)
 * @param string $module - שם המודול
 * @param string $action - שם הפעולה
 * @param bool $isApi - האם זו קריאת API
 */
function requireModulePermission(string $module, string $action, bool $isApi = false): void {
    requireAuth(null, $isApi);

    // Admin עובר תמיד
    if (isAdmin()) {
        return;
    }

    if (!hasModulePermission($module, $action)) {
        $displayName = getPermissionDisplayName($module, $action);

        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'אין לך הרשאה לפעולה זו',
                'required' => "{$module}.{$action}"
            ]);
            exit;
        }

        http_response_code(403);
        die('
        <!DOCTYPE html>
        <html dir="rtl" lang="he">
        <head>
            <meta charset="UTF-8">
            <title>אין הרשאה</title>
            <style>
                body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f3f4f6; margin: 0; }
                .box { background: white; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; margin-bottom: 10px; }
                p { color: #6b7280; }
                a { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; }
                a:hover { background: #5a67d8; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>אין הרשאה</h1>
                <p>אין לך הרשאה לפעולה זו.</p>
                <p>הרשאה נדרשת: <strong>' . htmlspecialchars($displayName) . '</strong></p>
                <a href="/dashboard">חזרה לדשבורד</a>
            </div>
        </body>
        </html>
        ');
    }
}

/**
 * קבלת שם תצוגה של הרשאה
 * @param string $module
 * @param string $action
 * @return string
 */
function getPermissionDisplayName(string $module, string $action): string {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT display_name FROM permissions WHERE module = ? AND action = ?");
        $stmt->execute([$module, $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['display_name'] ?? "{$module}.{$action}";
    } catch (Exception $e) {
        return "{$module}.{$action}";
    }
}

/**
 * קבלת רשימת כל המודולים וההרשאות שלהם
 * @return array
 */
function getAllModulesWithPermissions(): array {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT module, action, display_name, description, sort_order
            FROM permissions
            ORDER BY sort_order, module, action
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $modules = [];
        foreach ($results as $row) {
            if (!isset($modules[$row['module']])) {
                $modules[$row['module']] = [
                    'name' => $row['module'],
                    'actions' => []
                ];
            }
            $modules[$row['module']]['actions'][$row['action']] = [
                'name' => $row['action'],
                'display_name' => $row['display_name'],
                'description' => $row['description']
            ];
        }

        return $modules;

    } catch (Exception $e) {
        error_log("getAllModulesWithPermissions error: " . $e->getMessage());
        return [];
    }
}
