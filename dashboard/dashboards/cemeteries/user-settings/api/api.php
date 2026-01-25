<?php
/*
 * File: user-settings/api/api.php
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: API endpoint להגדרות משתמש
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';
require_once __DIR__ . '/UserSettingsManager.php';

// בדיקת התחברות
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $conn = getDBConnection();
    $userId = getCurrentUserId();

    // קריאת JSON מה-body קודם (לפני בדיקת action)
    $input = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        if ($rawInput) {
            $input = json_decode($rawInput, true);
        }
    }

    // קבלת סוג המכשיר (desktop/mobile)
    $deviceType = $input['deviceType'] ?? $_GET['deviceType'] ?? 'desktop';

    $settings = new UserSettingsManager($conn, $userId, $deviceType);

    // עכשיו בודקים action - כולל מתוך ה-JSON
    if ($input && isset($input['action'])) {
        $action = $input['action'];
    } else {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'get';
    }

    $response = ['success' => false];

    switch ($action) {
        case 'get':
            $key = $_GET['key'] ?? null;
            $category = $_GET['category'] ?? null;

            if ($key) {
                // קבלת הגדרה בודדת
                $value = $settings->get($key);
                $response = [
                    'success' => true,
                    'key' => $key,
                    'value' => $value,
                    'deviceType' => $deviceType
                ];
            } else {
                // קבלת כל ההגדרות
                $allSettings = $settings->getAllWithDefaults($category);
                $response = [
                    'success' => true,
                    'settings' => $allSettings,
                    'category' => $category,
                    'deviceType' => $deviceType
                ];
            }
            break;

        case 'set':
            // $input כבר נקרא למעלה
            if (!$input) {
                throw new Exception('No input data received');
            }

            if (isset($input['settings']) && is_array($input['settings'])) {
                // שמירת מספר הגדרות
                $success = $settings->setMultiple($input['settings']);
            } elseif (isset($input['key'])) {
                // שמירת הגדרה בודדת
                $success = $settings->set(
                    $input['key'],
                    $input['value'],
                    $input['type'] ?? null,
                    $input['category'] ?? null
                );
            } else {
                throw new Exception('Missing key or settings');
            }

            $response = ['success' => $success];
            break;

        case 'reset':
            // $input כבר נקרא למעלה (אם זה POST)
            $key = $input['key'] ?? null;
            $category = $input['category'] ?? null;

            if ($key) {
                $success = $settings->reset($key);
            } else {
                $success = $settings->resetAll($category);
            }

            $response = ['success' => $success];
            break;

        case 'categories':
            $categories = $settings->getCategories();
            $response = [
                'success' => true,
                'categories' => $categories
            ];
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
