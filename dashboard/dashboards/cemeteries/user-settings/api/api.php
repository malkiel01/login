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
    $settings = new UserSettingsManager($conn, $userId);

    $action = $_GET['action'] ?? $_POST['action'] ?? 'get';
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
                    'value' => $value
                ];
            } else {
                // קבלת כל ההגדרות
                $allSettings = $settings->getAllWithDefaults($category);
                $response = [
                    'success' => true,
                    'settings' => $allSettings,
                    'category' => $category
                ];
            }
            break;

        case 'set':
            $input = json_decode(file_get_contents('php://input'), true);

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
            $input = json_decode(file_get_contents('php://input'), true);
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
