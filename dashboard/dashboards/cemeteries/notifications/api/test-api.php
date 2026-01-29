<?php
/**
 * Notification Test API
 * Endpoints for running and monitoring automated tests
 *
 * Endpoints:
 * POST action=start_all         - Run all tests
 * POST action=start_test        - Run specific test
 * GET  ?action=status&run_id=X  - Get test run status
 * GET  ?action=history          - Get test history
 * GET  ?action=available_tests  - Get list of available tests
 * POST action=cancel            - Cancel running test (future)
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/api/api-auth.php';
require_once __DIR__ . '/NotificationTester.php';

header('Content-Type: application/json');

// Must be admin or have test permission
if (!isAdmin() && !hasModulePermission('notifications', 'edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'אין הרשאה להריץ בדיקות']);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

// Handle JSON body for POST
$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    switch ($action) {
        case 'start_all':
            handleStartAll($pdo, $userId);
            break;

        case 'start_test':
            handleStartTest($pdo, $userId, $input);
            break;

        case 'status':
            handleGetStatus($pdo);
            break;

        case 'history':
            handleGetHistory($pdo);
            break;

        case 'available_tests':
            handleGetAvailableTests();
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
 * Run all tests
 */
function handleStartAll(PDO $pdo, int $userId): void {
    $tester = new NotificationTester($pdo, $userId);
    $results = $tester->runAllTests();

    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

/**
 * Run specific test
 */
function handleStartTest(PDO $pdo, int $userId, array $input): void {
    $testId = $input['test_id'] ?? '';

    if (empty($testId)) {
        throw new Exception('נא לבחור בדיקה');
    }

    $availableTests = array_column(NotificationTester::getAvailableTests(), 'id');
    if (!in_array($testId, $availableTests)) {
        throw new Exception('בדיקה לא קיימת');
    }

    $tester = new NotificationTester($pdo, $userId);
    $results = $tester->runTest($testId);

    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

/**
 * Get test run status
 */
function handleGetStatus(PDO $pdo): void {
    $runId = $_GET['run_id'] ?? '';

    if (empty($runId)) {
        throw new Exception('מזהה ריצה חסר');
    }

    $status = NotificationTester::getRunStatus($pdo, $runId);

    if (!$status) {
        throw new Exception('ריצת בדיקה לא נמצאה');
    }

    echo json_encode([
        'success' => true,
        'data' => $status
    ]);
}

/**
 * Get test history
 */
function handleGetHistory(PDO $pdo): void {
    $limit = (int)($_GET['limit'] ?? 20);
    $limit = min(100, max(1, $limit));

    $history = NotificationTester::getHistory($pdo, $limit);

    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
}

/**
 * Get available tests
 */
function handleGetAvailableTests(): void {
    $tests = NotificationTester::getAvailableTests();

    echo json_encode([
        'success' => true,
        'data' => $tests
    ]);
}
