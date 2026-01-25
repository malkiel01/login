<?php
/**
 * Company Settings API
 * v1.0.0 - 2026-01-25
 *
 * Endpoints:
 * GET  - Load all company settings
 * POST - Save company settings
 */

// Load main config
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/middleware.php';

// Load manager class
require_once __DIR__ . '/CompanySettingsManager.php';

// Set headers
header('Content-Type: application/json; charset=utf-8');

// CORS
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
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check authentication (admin only)
requireApiDashboard(['admin', 'cemetery_manager']);

// Initialize manager
$conn = getDBConnection();
$manager = CompanySettingsManager::getInstance($conn);

// Handle request
try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGet($manager);
            break;

        case 'POST':
            handlePost($manager);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Company Settings API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET request - load all settings
 */
function handleGet($manager) {
    $settings = $manager->getAll();

    echo json_encode([
        'success' => true,
        'data' => $settings
    ]);
}

/**
 * Handle POST request - save settings
 */
function handlePost($manager) {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    // Validate and sanitize settings
    $allowedKeys = [
        'company_name',
        'company_logo',
        'phone_primary',
        'phone_secondary',
        'address',
        'social_security_code',
        'email'
    ];

    $settings = [];
    foreach ($allowedKeys as $key) {
        if (isset($input[$key])) {
            $settings[$key] = trim($input[$key]);
        }
    }

    if (empty($settings)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid settings provided']);
        return;
    }

    // Save settings
    $success = $manager->setMultiple($settings);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'הגדרות נשמרו בהצלחה'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save settings']);
    }
}
