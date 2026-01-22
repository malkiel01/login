<?php
// cemetery_dashboard/config.php
// קונפיג מקומי לדשבורד שמשתמש בקונפיג הראשי

// טוען את הקונפיג הראשי של הפרויקט
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// טוען את מערכת ההרשאות המרכזית
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/middleware.php';

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
        1 => ['name' => 'הפטור', 'icon' => '🟢'],
        2 => ['name' => 'חריגה', 'icon' => '🟡'],
        3 => ['name' => 'סגורה', 'icon' => '🔴']
    ]);
}

if (!defined('GRAVE_TYPES')) {
    define('GRAVE_TYPES', [
        1 => ['name' => 'שדה', 'icon' => '🌾'],
        2 => ['name' => 'רוויה', 'icon' => '🏘️'],
        3 => ['name' => 'סנהדרין', 'icon' => '⚖️']
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

// הגדרות תשלומים
if (!defined('PAYMENT_PLOT_TYPES')) {
    define('PAYMENT_PLOT_TYPES', [
        1 => ['name' => 'פטורה', 'color' => '#10b981', 'icon' => '🟢'],
        2 => ['name' => 'חריגה', 'color' => '#f97316', 'icon' => '🟡'],
        3 => ['name' => 'סגורה', 'color' => '#dc2626', 'icon' => '🔴']
    ]);
}

if (!defined('PAYMENT_GRAVE_TYPES')) {
    define('PAYMENT_GRAVE_TYPES', [
        1 => ['name' => 'שדה', 'icon' => '🌾'],
        2 => ['name' => 'רוויה', 'icon' => '🏘️'],
        3 => ['name' => 'סנהדרין', 'icon' => '⚖️']
    ]);
}

if (!defined('PAYMENT_RESIDENT_TYPES')) {
    define('PAYMENT_RESIDENT_TYPES', [
        1 => ['name' => 'ירושלים והסביבה', 'color' => '#10b981'],
        2 => ['name' => 'תושב חוץ', 'color' => '#f97316'],
        3 => ['name' => 'תושב חו״ל', 'color' => '#dc2626']
    ]);
}

if (!defined('PAYMENT_BUYER_STATUS')) {
    define('PAYMENT_BUYER_STATUS', [
        1 => ['name' => 'בחיים', 'color' => '#10b981'],
        2 => ['name' => 'לאחר פטירה', 'color' => '#6b7280'],
        3 => ['name' => 'בן/בת זוג נפטר', 'color' => '#3b82f6']
    ]);
}

if (!defined('PAYMENT_PRICE_DEFINITIONS')) {
    define('PAYMENT_PRICE_DEFINITIONS', [
        1 => ['name' => 'מחיר עלות הקבר', 'icon' => '💰'],
        2 => ['name' => 'שירותי לוויה', 'icon' => '🕯️'],
        3 => ['name' => 'שירותי קבורה', 'icon' => '⚰️'],
        4 => ['name' => 'אגרת מצבה', 'icon' => '🪦'],
        5 => ['name' => 'בדיקת עומק קבר', 'icon' => '📏'],
        6 => ['name' => 'פירוק מצבה', 'icon' => '🔨'],
        7 => ['name' => 'הובלה מנתבג', 'icon' => '✈️'],
        8 => ['name' => 'טהרה', 'icon' => '💧'],
        9 => ['name' => 'תכריכי פשתן', 'icon' => '🏳️'],
        10 => ['name' => 'החלפת שם', 'icon' => '📝']
    ]);
}

// פונקציות עזר ספציפיות לדשבורד
function getHierarchyLevel($type) {
    $levels = [
        'cemetery' => 'בית עלמין',
        'block' => 'גוש',
        'plot' => 'חלקה',
        'row' => 'שורה',
        'areaGrave' => 'אחוזת קבר',
        'grave' => 'קבר'
    ];
    return $levels[$type] ?? $type;
}

function formatGraveLocation($cemetery, $block, $plot, $row, $area, $grave) {
    $parts = array_filter([$cemetery, $block, $plot, $row, $area, $grave]);
    return implode(' ← ', $parts);
}

// פונקציית checkPermission מוגדרת כעת ב-/auth/middleware.php
// ומאפשרת בדיקת הרשאות אמיתית מול מסד הנתונים

// לוג פעולות
function logActivity($action, $module, $itemId, $details = []) {
    // TODO: להוסיף מערכת לוגים
    error_log("[$module] $action on item #$itemId");
}
?>