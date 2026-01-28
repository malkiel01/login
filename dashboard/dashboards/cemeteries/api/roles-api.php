<?php
/**
 * Roles API - ניהול תפקידים והרשאות
 *
 * Endpoints:
 * GET    ?action=list              - רשימת תפקידים
 * GET    ?action=get&id=X          - פרטי תפקיד + הרשאות
 * GET    ?action=permissions       - כל ההרשאות האפשריות
 * POST   action=create             - יצירת תפקיד
 * POST   action=update             - עדכון תפקיד
 * POST   action=delete             - מחיקת תפקיד
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/api-auth.php';

// בדוק הרשאות לניהול תפקידים
if (!isAdmin() && !hasModulePermission('roles', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'אין הרשאה לניהול תפקידים']);
    exit;
}

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            requireViewPermission('roles');
            handleList($pdo);
            break;
        case 'get':
            requireViewPermission('roles');
            handleGet($pdo);
            break;
        case 'permissions':
            requireViewPermission('roles');
            handlePermissions($pdo);
            break;
        case 'create':
            requireCreatePermission('roles');
            handleCreate($pdo);
            break;
        case 'update':
            requireEditPermission('roles');
            handleUpdate($pdo);
            break;
        case 'delete':
            requireDeletePermission('roles');
            handleDelete($pdo);
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
    if (!isAdmin() && !hasModulePermission('roles', $action)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => "אין הרשאה ל{$action}"]);
        exit;
    }
}

/**
 * רשימת תפקידים
 */
function handleList(PDO $pdo): void {
    $stmt = $pdo->query("
        SELECT
            r.*,
            COUNT(DISTINCT u.id) as users_count,
            COUNT(DISTINCT rp.permission_id) as permissions_count
        FROM roles r
        LEFT JOIN users u ON u.role_id = r.id
        LEFT JOIN role_permissions rp ON rp.role_id = r.id
        GROUP BY r.id
        ORDER BY r.is_system DESC, r.display_name
    ");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $roles
    ]);
}

/**
 * פרטי תפקיד + הרשאות
 */
function handleGet(PDO $pdo): void {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('נדרש מזהה תפקיד');
    }

    // פרטי תפקיד
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception('תפקיד לא נמצא');
    }

    // הרשאות התפקיד
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.module,
            p.action,
            p.display_name,
            p.description,
            p.sort_order
        FROM role_permissions rp
        INNER JOIN permissions p ON p.id = rp.permission_id
        WHERE rp.role_id = ?
        ORDER BY p.sort_order, p.module, p.action
    ");
    $stmt->execute([$id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // קבץ לפי מודול
    $permissionsByModule = [];
    foreach ($permissions as $p) {
        if (!isset($permissionsByModule[$p['module']])) {
            $permissionsByModule[$p['module']] = [];
        }
        $permissionsByModule[$p['module']][] = $p['action'];
    }

    $role['permissions'] = $permissions;
    $role['permissions_by_module'] = $permissionsByModule;

    echo json_encode([
        'success' => true,
        'data' => $role
    ]);
}

/**
 * כל ההרשאות האפשריות
 */
function handlePermissions(PDO $pdo): void {
    $stmt = $pdo->query("
        SELECT
            id,
            module,
            action,
            display_name,
            description,
            sort_order
        FROM permissions
        ORDER BY sort_order, module, action
    ");
    $allPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // קבץ לפי מודול
    $modules = [];
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
        'map' => 'מפות',
        'residency' => 'הגדרות תושבות',
        'countries' => 'מדינות',
        'cities' => 'ערים',
        'notifications' => 'התראות'
    ];

    $actionNames = [
        'view' => 'צפייה',
        'create' => 'יצירה',
        'edit' => 'עריכה',
        'delete' => 'מחיקה',
        'export' => 'ייצוא',
        'approve' => 'אישור',
        'upload' => 'העלאה'
    ];

    foreach ($allPermissions as $p) {
        $module = $p['module'];
        if (!isset($modules[$module])) {
            $modules[$module] = [
                'name' => $module,
                'display_name' => $moduleNames[$module] ?? $module,
                'actions' => []
            ];
        }
        $modules[$module]['actions'][] = [
            'id' => $p['id'],
            'action' => $p['action'],
            'display_name' => $actionNames[$p['action']] ?? $p['action'],
            'full_display_name' => $p['display_name'],
            'description' => $p['description']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($modules),
        'all' => $allPermissions
    ]);
}

/**
 * יצירת תפקיד חדש
 */
function handleCreate(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (empty($data['name']) || empty($data['display_name'])) {
        throw new Exception('שם התפקיד הוא חובה');
    }

    // בדוק אם השם קיים
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$data['name']]);
    if ($stmt->fetch()) {
        throw new Exception('שם תפקיד זה כבר קיים');
    }

    $pdo->beginTransaction();

    try {
        // צור תפקיד
        $stmt = $pdo->prepare("
            INSERT INTO roles (name, display_name, description, layout, is_system)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $data['name'],
            $data['display_name'],
            $data['description'] ?? null,
            $data['layout'] ?? 'full'
        ]);

        $roleId = $pdo->lastInsertId();

        // הוסף הרשאות
        if (!empty($data['permissions'])) {
            saveRolePermissions($pdo, $roleId, $data['permissions']);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'התפקיד נוצר בהצלחה',
            'data' => ['id' => $roleId]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * עדכון תפקיד
 */
function handleUpdate(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        throw new Exception('נדרש מזהה תפקיד');
    }

    // בדוק שהתפקיד קיים
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception('תפקיד לא נמצא');
    }

    $pdo->beginTransaction();

    try {
        // עדכן פרטי תפקיד (רק אם לא תפקיד מערכת)
        if (!$role['is_system']) {
            $updates = [];
            $params = [];

            $allowedFields = ['display_name', 'description', 'layout'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE roles SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        }

        // עדכן הרשאות
        if (array_key_exists('permissions', $data)) {
            saveRolePermissions($pdo, $id, $data['permissions']);
        }

        $pdo->commit();

        // נקה cache לכל המשתמשים עם תפקיד זה
        // (בפועל זה יתעדכן ב-session הבא שלהם)

        echo json_encode([
            'success' => true,
            'message' => 'התפקיד עודכן בהצלחה'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * מחיקת תפקיד
 */
function handleDelete(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        throw new Exception('נדרש מזהה תפקיד');
    }

    // בדוק שהתפקיד קיים ולא תפקיד מערכת
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception('תפקיד לא נמצא');
    }

    if ($role['is_system']) {
        throw new Exception('לא ניתן למחוק תפקיד מערכת');
    }

    // בדוק אם יש משתמשים עם תפקיד זה
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count > 0) {
        throw new Exception("לא ניתן למחוק - יש {$count} משתמשים עם תפקיד זה");
    }

    // מחק
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'התפקיד נמחק בהצלחה'
    ]);
}

/**
 * שמירת הרשאות לתפקיד
 */
function saveRolePermissions(PDO $pdo, int $roleId, array $permissions): void {
    // מחק הרשאות קיימות
    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);

    if (empty($permissions)) {
        return;
    }

    // הכנס הרשאות חדשות
    $stmt = $pdo->prepare("
        INSERT INTO role_permissions (role_id, permission_id)
        VALUES (?, ?)
    ");

    foreach ($permissions as $permissionId) {
        // אם זה מערך עם id
        if (is_array($permissionId)) {
            $permissionId = $permissionId['id'] ?? $permissionId['permission_id'] ?? null;
        }

        if ($permissionId) {
            $stmt->execute([$roleId, $permissionId]);
        }
    }
}
