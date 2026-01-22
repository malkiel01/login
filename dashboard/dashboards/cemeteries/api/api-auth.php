<?php
/**
 * API Auth Helper - הגנה על API endpoints
 *
 * שימוש בכל קובץ API:
 * require_once __DIR__ . '/api-auth.php';
 *
 * זה יבדוק אוטומטית:
 * 1. שהמשתמש מחובר
 * 2. שיש לו הרשאות לדשבורד בתי עלמין
 *
 * @version 1.0.0
 */

// טען את הקונפיג (שמכיל את middleware)
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

// הגדר headers ל-API
header('Content-Type: application/json; charset=utf-8');

// אפשר CORS מאותו domain בלבד (אבטחה)
$allowedOrigins = [
    'https://mbe-plus.com',
    'https://www.mbe-plus.com',
    'http://localhost',
    'http://127.0.0.1'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // לפיתוח מקומי
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// טפל ב-OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// בדוק התחברות והרשאות
requireApiDashboard(['cemetery_manager', 'admin']);
