<?php
// cemetery_dashboard/config.php
// קונפיג מקומי לדשבורד שמשתמש בקונפיג הראשי

// טוען את הקונפיג הראשי של הפרויקט
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// הגדרות ספציפיות לדשבורד בתי העלמין
define('DASHBOARD_NAME', 'ניהול בתי עלמין');
define('DASHBOARD_VERSION', '1.0.0');
define('DASHBOARD_PATH', dirname(__FILE__));
define('CEMETERY_DASHBOARD_URL', '/dashboards/cemeteries/');

// הגדרות סטטוסים לבתי עלמין
if (!defined('GRAVE_STATUS')) {
    define('GRAVE_STATUS', [
        1 => ['name' => 'פנוי', 'color' => '#10b981', 'bg' => '#d1fae5'],
        2 => ['name' => 'נרכש', 'color' => '#f97316', 'bg' => '#fed7aa'],
        3 => ['name' => 'תפוס', 'color' => '#dc2626', 'bg' => '#fecaca'],
        4 => ['name' => 'שמור', 'color' => '#6366f1', 'bg' => '#ddd6fe']
    ]);
}

if (!defined('PLOT_TYPES')) {
    define('PLOT_TYPES', [
        1 => ['name' => 'פטור', 'icon' => '🟢'],
        2 => ['name' => 'חריג', 'icon' => '🟡'],
        3 => ['name' => 'סגור', 'icon' => '🔴']
    ]);
}

if (!defined('CUSTOMER_STATUS')) {
    define('CUSTOMER_STATUS', [
        1 => ['name' => 'פעיל', 'color' => '#10b981'],
        2 => ['name' => 'רכש', 'color' => '#3b82f6'],
        3 => ['name' => 'נפטר', 'color' => '#6b7280']
    ]);
}

if (!defined('PURCHASE_STATUS')) {
    define('PURCHASE_STATUS', [
        1 => ['name' => 'טיוטה', 'color' => '#6b7280'],
        2 => ['name' => 'אושר', 'color' => '#3b82f6'],
        3 => ['name' => 'שולם', 'color' => '#10b981'],
        4 => ['name' => 'בוטל', 'color' => '#dc2626']
    ]);
}

// פונקציות עזר ספציפיות לדשבורד
function getHierarchyLevel($type) {
    $levels = [
        'cemetery' => 'בית עלמין',
        'block' => 'גוש',
        'plot' => 'חלקה',
        'row' => 'שורה',
        'area_grave' => 'אחוזת קבר',
        'grave' => 'קבר'
    ];
    return $levels[$type] ?? $type;
}

function formatGraveLocation($cemetery, $block, $plot, $row, $area, $grave) {
    $parts = array_filter([$cemetery, $block, $plot, $row, $area, $grave]);
    return implode(' ← ', $parts);
}

// function calculateAge($birthDate, $deathDate = null) {
//     if (!$birthDate) return null;
    
//     $from = new DateTime($birthDate);
//     $to = $deathDate ? new DateTime($deathDate) : new DateTime();
    
//     return $from->diff($to)->y;
// }

// בדיקת הרשאות למשתמש (אם יש מערכת משתמשים)
function checkPermission($action, $module = 'cemetery') {
    // TODO: להוסיף בדיקת הרשאות אמיתית
    return true;
}

// לוג פעולות
function logActivity($action, $module, $itemId, $details = []) {
    // TODO: להוסיף מערכת לוגים
    error_log("[$module] $action on item #$itemId");
}
?>