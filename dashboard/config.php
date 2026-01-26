<?php
// Dashboard Configuration
define('DASHBOARD_VERSION', '1.0.0');
define('DASHBOARD_PATH', __DIR__);
define('DASHBOARD_URL', '/dashboard');

// Database Configuration (inherit from main config)
if (file_exists('../config.php')) {
    require_once '../config.php';
}

// Dashboard Types
define('DASHBOARD_TYPES', [
    // מנהל מערכת - למפתח בלבד, לא ניתן לבחירה
    'admin' => [
        'name' => 'מנהל מערכת',
        'icon' => '👨‍💼',
        'color' => '#667eea',
        'system_only' => true,  // לא ניתן לבחירה בטופס
        'has_profiles' => false,
        'permissions' => ['*']  // גישה מלאה לכל
    ],

    // לקוח - מובנה, ללא פרופילים
    'client' => [
        'name' => 'דשבורד לקוח',
        'icon' => '🏢',
        'color' => '#FDBB2D',
        'system_only' => false,
        'has_profiles' => false,  // ללא בחירת פרופיל
        'redirect' => '/dashboard/dashboards/client.php',
        'permissions' => ['view_projects', 'view_reports']
    ],

    // בסיסי - מובנה, ללא פרופילים
    'default' => [
        'name' => 'דשבורד בסיסי',
        'icon' => '🏠',
        'color' => '#e5e7eb',
        'system_only' => false,
        'has_profiles' => false,  // ללא בחירת פרופיל
        'redirect' => '/dashboard/dashboards/default.php',
        'permissions' => ['view_basic']
    ],

    // בית עלמין - עם פרופילים והרשאות גרנולריות
    'cemetery_manager' => [
        'name' => 'דשבורד בתי עלמין',
        'icon' => '🪦',
        'color' => '#8B4513',
        'system_only' => false,
        'has_profiles' => true,   // ניתן לבחור/ליצור פרופיל
        'redirect' => '/dashboard/dashboards/cemeteries/',
        'permissions' => []       // ההרשאות מגיעות מהפרופיל
    ]
]);

/**
 * קבלת סוגי דשבורדים הניתנים לבחירה (ללא admin)
 */
function getSelectableDashboardTypes(): array {
    $types = [];
    foreach (DASHBOARD_TYPES as $key => $type) {
        if (empty($type['system_only'])) {
            $types[$key] = $type;
        }
    }
    return $types;
}

/**
 * בדיקה האם לסוג דשבורד יש פרופילים
 */
function dashboardHasProfiles(string $dashboardType): bool {
    return DASHBOARD_TYPES[$dashboardType]['has_profiles'] ?? false;
}
?>
