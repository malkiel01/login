<?php
/*
 * File: table-module/api/permissions-api.php
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: API להרשאות טבלאות
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

// בדיקת התחברות
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

try {
    $pdo = getDBConnection();
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();

    switch ($action) {
        case 'get':
            $entityType = $_GET['entityType'] ?? null;
            if (!$entityType) {
                throw new Exception('Missing entityType parameter');
            }
            $permissions = getEntityPermissions($pdo, $userId, $userRole, $entityType);
            echo json_encode(['success' => true, 'permissions' => $permissions]);
            break;

        case 'getAll':
            $permissions = getAllPermissions($pdo, $userId, $userRole);
            echo json_encode(['success' => true, 'permissions' => $permissions]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * קבלת הרשאות ל-entity ספציפי
 */
function getEntityPermissions($pdo, $userId, $userRole, $entityType) {
    // ברירות מחדל לפי role
    $defaults = getRoleDefaults($userRole);

    // בדיקה אם יש הרשאות מותאמות אישית
    $stmt = $pdo->prepare("
        SELECT canView, canEdit, canDelete, canExport, canCreate, visibleColumns, editableColumns
        FROM table_permissions
        WHERE userId = :userId AND entityType = :entityType
        LIMIT 1
    ");
    $stmt->execute(['userId' => $userId, 'entityType' => $entityType]);
    $custom = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($custom) {
        return [
            'canView' => (bool)$custom['canView'],
            'canEdit' => (bool)$custom['canEdit'],
            'canDelete' => (bool)$custom['canDelete'],
            'canExport' => (bool)$custom['canExport'],
            'canCreate' => (bool)$custom['canCreate'],
            'visibleColumns' => $custom['visibleColumns'] ? json_decode($custom['visibleColumns'], true) : null,
            'editableColumns' => $custom['editableColumns'] ? json_decode($custom['editableColumns'], true) : null
        ];
    }

    // החזר ברירות מחדל לפי role
    return $defaults;
}

/**
 * קבלת כל ההרשאות
 */
function getAllPermissions($pdo, $userId, $userRole) {
    $entityTypes = [
        'cemetery', 'block', 'plot', 'areaGrave', 'grave',
        'customer', 'purchase', 'burial', 'payment',
        'residency', 'country', 'city'
    ];

    $permissions = [];

    foreach ($entityTypes as $entityType) {
        $permissions[$entityType] = getEntityPermissions($pdo, $userId, $userRole, $entityType);
    }

    return $permissions;
}

/**
 * ברירות מחדל לפי role
 */
function getRoleDefaults($role) {
    switch ($role) {
        case 'admin':
            return [
                'canView' => true,
                'canEdit' => true,
                'canDelete' => true,
                'canExport' => true,
                'canCreate' => true,
                'visibleColumns' => null,
                'editableColumns' => null
            ];

        case 'cemetery_manager':
            return [
                'canView' => true,
                'canEdit' => true,
                'canDelete' => false, // לא יכול למחוק
                'canExport' => true,
                'canCreate' => true,
                'visibleColumns' => null,
                'editableColumns' => null
            ];

        case 'viewer':
            return [
                'canView' => true,
                'canEdit' => false,
                'canDelete' => false,
                'canExport' => true,
                'canCreate' => false,
                'visibleColumns' => null,
                'editableColumns' => null
            ];

        default:
            return [
                'canView' => true,
                'canEdit' => false,
                'canDelete' => false,
                'canExport' => false,
                'canCreate' => false,
                'visibleColumns' => null,
                'editableColumns' => null
            ];
    }
}
