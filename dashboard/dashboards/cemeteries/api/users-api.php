<?php
/**
 * Users API - ניהול משתמשים
 *
 * Endpoints:
 * GET    ?action=list              - רשימת משתמשים
 * GET    ?action=get&id=X          - פרטי משתמש
 * POST   action=create             - יצירת משתמש
 * POST   action=update             - עדכון משתמש
 * POST   action=delete             - מחיקת משתמש
 * POST   action=reset_password     - איפוס סיסמה
 * POST   action=update_permissions - עדכון הרשאות מותאמות
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/api-auth.php';

// בדוק הרשאות לניהול משתמשים
if (!isAdmin() && !hasModulePermission('users', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'אין הרשאה לניהול משתמשים']);
    exit;
}

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList($pdo);
            break;
        case 'get':
            handleGet($pdo);
            break;
        case 'create':
            requirePermission('create');
            handleCreate($pdo);
            break;
        case 'update':
            requirePermission('edit');
            handleUpdate($pdo);
            break;
        case 'delete':
            requirePermission('delete');
            handleDelete($pdo);
            break;
        case 'reset_password':
            requirePermission('edit');
            handleResetPassword($pdo);
            break;
        case 'update_permissions':
            requirePermission('edit');
            handleUpdatePermissions($pdo);
            break;
        default:
            throw new Exception('פעולה לא חוקית');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * בדיקת הרשאה ספציפית
 */
function requirePermission(string $action): void {
    if (!isAdmin() && !hasModulePermission('users', $action)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => "אין הרשאה ל{$action}"]);
        exit;
    }
}

/**
 * רשימת משתמשים
 */
function handleList(PDO $pdo): void {
    $roleFilter = $_GET['role_id'] ?? null;
    $statusFilter = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $sql = "
        SELECT
            u.id,
            u.username,
            u.email,
            u.name,
            u.phone,
            u.profile_picture,
            u.auth_type,
            u.is_active,
            u.custom_permissions,
            u.role_id,
            u.created_at,
            u.last_login,
            r.name as role_name,
            r.display_name as role_display_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE 1=1
    ";
    $params = [];

    if ($roleFilter) {
        $sql .= " AND u.role_id = :role_id";
        $params['role_id'] = $roleFilter;
    }

    if ($statusFilter !== null) {
        $sql .= " AND u.is_active = :is_active";
        $params['is_active'] = $statusFilter === 'active' ? 1 : 0;
    }

    if ($search) {
        $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.username LIKE :search)";
        $params['search'] = "%{$search}%";
    }

    // ספירה כללית
    $countSql = str_replace("SELECT \n            u.id", "SELECT COUNT(*) as total", $sql);
    $countSql = preg_replace('/LEFT JOIN roles.*$/m', '', $countSql);
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // הוסף סדר ו-pagination
    $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";
    $params['limit'] = $limit;
    $params['offset'] = $offset;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $users,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * פרטי משתמש
 */
function handleGet(PDO $pdo): void {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('נדרש מזהה משתמש');
    }

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            u.email,
            u.name,
            u.phone,
            u.profile_picture,
            u.auth_type,
            u.is_active,
            u.custom_permissions,
            u.role_id,
            u.created_at,
            u.last_login,
            r.name as role_name,
            r.display_name as role_display_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('משתמש לא נמצא');
    }

    // קבל הרשאות מותאמות
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.module,
            p.action,
            p.display_name,
            upe.granted
        FROM user_permissions_extended upe
        INNER JOIN permissions p ON p.id = upe.permission_id
        WHERE upe.user_id = ?
    ");
    $stmt->execute([$id]);
    $customPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $user['custom_permissions_list'] = $customPermissions;

    echo json_encode([
        'success' => true,
        'data' => $user
    ]);
}

/**
 * יצירת משתמש חדש
 */
function handleCreate(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $required = ['name', 'email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("שדה {$field} הוא חובה");
        }
    }

    // בדוק אם האימייל קיים
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        throw new Exception('אימייל זה כבר קיים במערכת');
    }

    // צור username אוטומטי אם לא סופק
    $username = $data['username'] ?? null;
    if (!$username) {
        $username = strtok($data['email'], '@') . '_' . rand(100, 999);
    }

    // בדוק אם username קיים
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $username = $username . '_' . rand(100, 999);
    }

    // צור סיסמה אם סופקה
    $password = null;
    if (!empty($data['password'])) {
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, name, phone, password, auth_type, role_id, is_active, custom_permissions)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $username,
        $data['email'],
        $data['name'],
        $data['phone'] ?? null,
        $password,
        $password ? 'local' : 'google',
        $data['role_id'] ?? null,
        $data['is_active'] ?? 1,
        $data['custom_permissions'] ?? 0
    ]);

    $userId = $pdo->lastInsertId();

    // הוסף הרשאות מותאמות אם יש
    if (!empty($data['permissions']) && !empty($data['custom_permissions'])) {
        saveCustomPermissions($pdo, $userId, $data['permissions']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'המשתמש נוצר בהצלחה',
        'data' => ['id' => $userId]
    ]);
}

/**
 * עדכון משתמש
 */
function handleUpdate(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        throw new Exception('נדרש מזהה משתמש');
    }

    // בדוק שהמשתמש קיים
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new Exception('משתמש לא נמצא');
    }

    // בדוק אימייל כפול
    if (!empty($data['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            throw new Exception('אימייל זה כבר קיים במערכת');
        }
    }

    // בנה שאילתת עדכון
    $updates = [];
    $params = [];

    $allowedFields = ['name', 'email', 'phone', 'role_id', 'is_active', 'custom_permissions'];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "{$field} = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        throw new Exception('אין נתונים לעדכון');
    }

    $params[] = $id;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // עדכן הרשאות מותאמות
    if (array_key_exists('permissions', $data)) {
        saveCustomPermissions($pdo, $id, $data['permissions']);
    }

    // נקה cache של הרשאות
    clearPermissionsCache($id);

    echo json_encode([
        'success' => true,
        'message' => 'המשתמש עודכן בהצלחה'
    ]);
}

/**
 * מחיקת משתמש
 */
function handleDelete(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        throw new Exception('נדרש מזהה משתמש');
    }

    // אל תמחק את עצמך
    if ($id === getCurrentUserId()) {
        throw new Exception('לא ניתן למחוק את המשתמש שלך');
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('משתמש לא נמצא');
    }

    echo json_encode([
        'success' => true,
        'message' => 'המשתמש נמחק בהצלחה'
    ]);
}

/**
 * איפוס סיסמה
 */
function handleResetPassword(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    $newPassword = $data['password'] ?? '';

    if (!$id) {
        throw new Exception('נדרש מזהה משתמש');
    }

    if (strlen($newPassword) < 6) {
        throw new Exception('הסיסמה חייבת להכיל לפחות 6 תווים');
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ?, auth_type = 'local' WHERE id = ?");
    $stmt->execute([$hashedPassword, $id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('משתמש לא נמצא');
    }

    echo json_encode([
        'success' => true,
        'message' => 'הסיסמה עודכנה בהצלחה'
    ]);
}

/**
 * עדכון הרשאות מותאמות
 */
function handleUpdatePermissions(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        throw new Exception('נדרש מזהה משתמש');
    }

    $permissions = $data['permissions'] ?? [];
    $customPermissions = $data['custom_permissions'] ?? false;

    // עדכן את הדגל
    $stmt = $pdo->prepare("UPDATE users SET custom_permissions = ? WHERE id = ?");
    $stmt->execute([$customPermissions ? 1 : 0, $id]);

    // שמור הרשאות
    if ($customPermissions) {
        saveCustomPermissions($pdo, $id, $permissions);
    } else {
        // מחק הרשאות מותאמות
        $stmt = $pdo->prepare("DELETE FROM user_permissions_extended WHERE user_id = ?");
        $stmt->execute([$id]);
    }

    // נקה cache
    clearPermissionsCache($id);

    echo json_encode([
        'success' => true,
        'message' => 'ההרשאות עודכנו בהצלחה'
    ]);
}

/**
 * שמירת הרשאות מותאמות
 */
function saveCustomPermissions(PDO $pdo, int $userId, array $permissions): void {
    // מחק הרשאות קיימות
    $stmt = $pdo->prepare("DELETE FROM user_permissions_extended WHERE user_id = ?");
    $stmt->execute([$userId]);

    if (empty($permissions)) {
        return;
    }

    // הכנס הרשאות חדשות
    $stmt = $pdo->prepare("
        INSERT INTO user_permissions_extended (user_id, permission_id, granted)
        VALUES (?, ?, ?)
    ");

    foreach ($permissions as $permission) {
        $permissionId = $permission['permission_id'] ?? $permission['id'] ?? null;
        $granted = $permission['granted'] ?? 1;

        if ($permissionId) {
            $stmt->execute([$userId, $permissionId, $granted]);
        }
    }
}
