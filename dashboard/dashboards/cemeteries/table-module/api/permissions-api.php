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
 * משתמש במערכת ההרשאות החדשה (hasModulePermission)
 */
function getEntityPermissions($pdo, $userId, $userRole, $entityType) {
    // מיפוי entityType ל-module name
    $moduleMap = [
        'cemetery' => 'cemeteries',
        'block' => 'blocks',
        'plot' => 'plots',
        'areaGrave' => 'areaGraves',
        'grave' => 'graves',
        'customer' => 'customers',
        'purchase' => 'purchases',
        'burial' => 'burials',
        'payment' => 'payments',
        'residency' => 'residency',
        'country' => 'countries',
        'city' => 'cities',
        'user' => 'users',
        'role' => 'roles',
        'map' => 'map',
        'report' => 'reports'
    ];

    $module = $moduleMap[$entityType] ?? $entityType;

    // Admin תמיד מקבל הכל
    if (isAdmin()) {
        return [
            'canView' => true,
            'canEdit' => true,
            'canDelete' => true,
            'canExport' => true,
            'canCreate' => true,
            'visibleColumns' => null,
            'editableColumns' => null
        ];
    }

    // בדוק הרשאות מהמערכת החדשה
    return [
        'canView' => hasModulePermission($module, 'view') || hasModulePermission($module, 'edit') || hasModulePermission($module, 'create'),
        'canEdit' => hasModulePermission($module, 'edit'),
        'canDelete' => hasModulePermission($module, 'delete'),
        'canExport' => hasModulePermission($module, 'export'),
        'canCreate' => hasModulePermission($module, 'create'),
        'visibleColumns' => null,
        'editableColumns' => null
    ];
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
