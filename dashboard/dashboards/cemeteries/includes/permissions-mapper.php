<?php
// dashboard/dashboards/cemeteries/includes/permissions-mapper.php
// מיפוי בין dashboard_type לבין role בהיררכיית בתי עלמין
// ⚠️ הפונקציות עטופות ב-function_exists כי חלקן כבר מוגדרות ב-middleware.php

/**
 * ממפה את סוג הדשבורד לתפקיד במערכת בתי עלמין
 */
if (!function_exists('mapDashboardTypeToRole')) {
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
}

/**
 * בדיקה אם למשתמש יש הרשאת מנהל בית עלמין
 */
if (!function_exists('isCemeteryManager')) {
    function isCemeteryManager() {
        $dashboardType = $_SESSION['dashboard_type'] ?? 'default';
        return in_array($dashboardType, ['cemetery_manager', 'admin']);
    }
}

/**
 * קבלת תפקיד המשתמש הנוכחי
 * ⚠️ פונקציה זו כבר מוגדרת ב-middleware.php - לא מגדירים מחדש
 */
// getCurrentUserRole is already defined in middleware.php

/**
 * בדיקת הרשאה ספציפית
 * ⚠️ פונקציה זו כבר מוגדרת ב-middleware.php - לא מגדירים מחדש
 */
// hasPermission is already defined in middleware.php

/**
 * בדיקה אם למשתמש יש גישה לשדה מסוים
 */
if (!function_exists('canAccessField')) {
    function canAccessField($fieldName, $type) {
        $role = function_exists('getCurrentUserRole') ? getCurrentUserRole() : 'viewer';

        // טען את הקונפיג
        $configFile = __DIR__ . '/../config/cemetery-hierarchy-config.php';
        if (!file_exists($configFile)) {
            return false;
        }
        $config = require $configFile;
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
}
?>