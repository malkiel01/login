<?php
/**
 * Push Notification Debug Logger
 * Access: /push/push-log.php
 */

define('PUSH_LOG_FILE', __DIR__ . '/push-debug.log');

/**
 * Log a push notification event
 */
function pushLog(string $type, string $message, $data = null): void {
    $logEntry = date('Y-m-d H:i:s') . " [$type] " . $message;
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $logEntry .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            $logEntry .= " | " . $data;
        }
    }
    $logEntry .= "\n";
    @file_put_contents(PUSH_LOG_FILE, $logEntry, FILE_APPEND);
}

// Only continue if accessed directly
if (basename($_SERVER['SCRIPT_FILENAME']) !== 'push-log.php') {
    return;
}

require_once __DIR__ . '/../config.php';

// Handle log entry from client
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? 'No message';
    $data = $input['data'] ?? null;

    pushLog('CLIENT', $message, $data);

    echo json_encode(['success' => true]);
    exit;
}

// Handle clear log
if (isset($_GET['clear'])) {
    @unlink(PUSH_LOG_FILE);
    header('Location: /push/push-log.php');
    exit;
}

// Display log
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Push Debug Log</title>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
echo "<style>body{font-family:monospace;padding:10px;background:#1e1e1e;color:#0f0;font-size:12px;}";
echo "pre{white-space:pre-wrap;word-wrap:break-word;}";
echo "a{color:#0ff;}</style></head><body>";

echo "<h2>Push Debug Log</h2>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p><a href='?clear=1'>Clear Log</a> | <a href='javascript:location.reload()'>Refresh</a></p>";
echo "<hr>";

if (file_exists(PUSH_LOG_FILE)) {
    $content = file_get_contents(PUSH_LOG_FILE);
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
} else {
    echo "<p>No log entries yet.</p>";
}

echo "</body></html>";
