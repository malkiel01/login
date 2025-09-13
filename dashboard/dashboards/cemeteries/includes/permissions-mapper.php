<?php
// dashboard/dashboards/cemeteries/includes/permissions-mapper.php
// מיפוי בין dashboard_type לבין role בהיררכיית בתי עלמין

/**
 * ממפה את סוג הדשבורד לתפקיד במערכת בתי עלמין
 */
function mapDashboardTypeToRole($dashboardType) {
    $mapping = [
        'cemetery_manager' => 'cemetery_manager',  // מנהל בית עלמין
        'admin' => 'cemetery_manager',            // גם admin יקבל הרשאות מלאות
        'manager' => 'manager',                   // מנהל צוות
        'employee' => 'editor',                   // עובד = עורך
        'client' => 'viewer',                     // לקוח = צופה
        'default' => 'viewer'                     // ברירת מחדל = צופה
    ];
    
    return $mapping[$dashboardType] ?? 'viewer';
}

/**
 * בדיקה אם למשתמש יש הרשאת מנהל בית עלמין
 */
function isCemeteryManager() {
    $dashboardType = $_SESSION['dashboard_type'] ?? 'default';
    return in_array($dashboardType, ['cemetery_manager', 'admin']);
}

/**
 * קבלת תפקיד המשתמש הנוכחי
 */
function getCurrentUserRole() {
    $dashboardType = $_SESSION['dashboard_type'] ?? 'default';
    return mapDashboardTypeToRole($dashboardType);
}

/**
 * בדיקת הרשאה ספציפית
 */
function hasPermission($permission) {
    $role = getCurrentUserRole();
    
    // טען את הקונפיג
    $config = require __DIR__ . '/../config/cemetery-hierarchy-config.php';
    $roleConfig = $config['permissions']['roles'][$role] ?? null;
    
    if (!$roleConfig) {
        return false;
    }
    
    switch ($permission) {
        case 'view':
            return $roleConfig['can_view_all'] ?? false;
        case 'edit':
            return $roleConfig['can_edit_all'] ?? false;
        case 'delete':
            return $roleConfig['can_delete_all'] ?? false;
        case 'create':
            return $roleConfig['can_create_all'] ?? false;
        default:
            return false;
    }
}

/**
 * בדיקה אם למשתמש יש גישה לשדה מסוים
 */
function canAccessField($fieldName, $type) {
    $role = getCurrentUserRole();
    
    // טען את הקונפיג
    $config = require __DIR__ . '/../config/cemetery-hierarchy-config.php';
    $typeConfig = $config[$type] ?? null;
    
    if (!$typeConfig || !isset($typeConfig['form_fields'])) {
        return false;
    }
    
    // חפש את השדה
    foreach ($typeConfig['form_fields'] as $field) {
        if ($field['name'] === $fieldName) {
            // אם אין הגדרת הרשאות, השדה פתוח לכולם
            if (!isset($field['permissions'])) {
                return true;
            }
            // בדוק אם התפקיד נמצא ברשימת ההרשאות
            return in_array($role, $field['permissions']);
        }
    }
    
    return false;
}
?>