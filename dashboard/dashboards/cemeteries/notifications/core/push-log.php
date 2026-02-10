<?php
/**
 * Push Notification Debug Logger
 *
 * This file provides simple file-based debug logging for push notifications.
 * It's complementary to NotificationLogger which logs to the database.
 *
 * PURPOSE:
 * - Quick debugging during development
 * - Viewing logs without database access
 * - Lightweight logging for high-frequency events
 * - Client-side logging endpoint (via POST)
 *
 * DIFFERENCE FROM NotificationLogger:
 * - push-log.php: File-based, human-readable, for debugging
 * - NotificationLogger: Database-based, structured, for analytics/audit
 *
 * FEATURES:
 * - pushLog(): Core logging function
 * - parseUserAgent(): Device/browser detection (uses UserAgentParser)
 * - logUserLogin(): Log user logins
 * - logPushSubscription(): Log subscription events
 * - logPushSendResult(): Log delivery results
 * - Web UI for viewing/clearing logs
 * - API endpoint for client-side logging
 *
 * LOG FILE LOCATION:
 * /push/push-debug.log
 *
 * LOG FORMAT:
 * YYYY-MM-DD HH:MM:SS [TYPE] Message | {"json":"data"}
 *
 * USAGE (from PHP):
 * ```php
 * require_once 'push-log.php';
 * pushLog('SEND', 'Sending notification', ['userId' => 123]);
 * ```
 *
 * USAGE (from JavaScript):
 * ```javascript
 * fetch('/push/push-log.php', {
 *     method: 'POST',
 *     body: JSON.stringify({ message: 'Event', data: {...} })
 * });
 * ```
 *
 * WEB UI:
 * Access /push/push-log.php directly to view logs in browser
 * Use ?clear=1 to clear the log file
 *
 * @package     Notifications
 * @subpackage  Core
 * @version     1.1.0 - Now uses UserAgentParser utility
 * @since       1.0.0
 * @see         NotificationLogger For database-based logging
 * @see         UserAgentParser For User-Agent parsing
 */

// Load shared User-Agent parser utility
require_once __DIR__ . '/UserAgentParser.php';

/**
 * Log file path - stored in /push/ folder
 * @var string
 */
define('PUSH_LOG_FILE', __DIR__ . '/../../../../../push/push-debug.log');

/**
 * Log a push notification event to file
 *
 * Core logging function that writes timestamped entries to the log file.
 * Data is JSON-encoded if array/object.
 *
 * @param string     $type    Event type (e.g., 'SEND', 'ERROR', 'LOGIN')
 * @param string     $message Human-readable message
 * @param mixed|null $data    Optional data to log (array, object, or scalar)
 *
 * @return void
 *
 * @example
 * ```php
 * pushLog('SEND', 'Notification sent', ['userId' => 123, 'success' => true]);
 * // Output: 2026-01-30 14:23:45 [SEND] Notification sent | {"userId":123,"success":true}
 * ```
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

/**
 * Parse User-Agent string to extract device information
 *
 * Wrapper function that uses the shared UserAgentParser utility.
 * Adds ua_short for backward compatibility with existing code.
 *
 * @param string|null $ua User-Agent string (defaults to $_SERVER['HTTP_USER_AGENT'])
 *
 * @return array Device info with keys:
 *               - device: 'iPhone'|'iPad'|'Android Phone'|'Android Tablet'|'Desktop'|'Unknown'
 *               - os: 'iOS'|'Android'|'Windows'|'macOS'|'Linux'|'Unknown'
 *               - browser: 'Chrome'|'Safari'|'Firefox'|'Edge'|'Unknown'
 *               - ua_short: First 100 chars of UA string
 *
 * @see UserAgentParser::parse() For the underlying implementation
 */
function parseUserAgent(?string $ua = null): array {
    $result = UserAgentParser::parse($ua);
    $result['ua_short'] = UserAgentParser::getShort($ua);
    return $result;
}

/**
 * Log user login event with device information
 *
 * Records when a user logs in, including their device and location.
 *
 * @param int    $userId   The user ID that logged in
 * @param string $username The username for display
 *
 * @return void
 */
function logUserLogin(int $userId, string $username): void {
    $device = parseUserAgent();
    pushLog('LOGIN', "User logged in", [
        'userId' => $userId,
        'username' => $username,
        'device' => $device['device'],
        'os' => $device['os'],
        'browser' => $device['browser'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);
}

/**
 * Log push subscription event (subscribe/unsubscribe)
 *
 * Records when a user registers or removes a push subscription.
 * Truncates endpoint URL to 60 chars for readability.
 *
 * @param int    $userId   The user ID
 * @param string $endpoint The push service endpoint URL
 * @param string $action   Action type: 'subscribe' or 'unsubscribe'
 *
 * @return void
 */
function logPushSubscription(int $userId, string $endpoint, string $action = 'subscribe'): void {
    $device = parseUserAgent();
    $endpointShort = substr($endpoint, 0, 60) . '...';
    pushLog('SUBSCRIPTION', "$action", [
        'userId' => $userId,
        'endpoint' => $endpointShort,
        'device' => $device['device'],
        'os' => $device['os'],
        'browser' => $device['browser']
    ]);
}

/**
 * Log push notification send result with device information
 *
 * Records the outcome of a push notification send attempt.
 * Shows ✅ for success and ❌ for failure with device details.
 *
 * @param int         $userId         Target user ID
 * @param int         $subscriptionId The subscription ID that was used
 * @param string      $endpoint       Push service endpoint URL
 * @param bool        $success        Whether the send succeeded
 * @param string|null $error          Error message if failed
 * @param string|null $userAgent      User-Agent of the target device
 *
 * @return void
 */
function logPushSendResult(int $userId, int $subscriptionId, string $endpoint, bool $success, ?string $error = null, ?string $userAgent = null): void {
    $device = parseUserAgent($userAgent);
    $endpointShort = substr($endpoint, 0, 50) . '...';

    $status = $success ? '✅ DELIVERED' : '❌ FAILED';

    pushLog('PUSH', "$status to user $userId", [
        'subscriptionId' => $subscriptionId,
        'endpoint' => $endpointShort,
        'device' => $device['device'],
        'os' => $device['os'],
        'browser' => $device['browser'],
        'error' => $error
    ]);
}

// =============================================================================
// WEB UI AND API SECTION
// =============================================================================
// This section only runs when the file is accessed directly (not included).
// Provides: 1) POST API for client-side logging  2) Web UI for viewing logs
// =============================================================================

// Only continue if accessed directly (not included by another file)
if (basename($_SERVER['SCRIPT_FILENAME']) !== 'push-log.php') {
    return;
}

require_once __DIR__ . '/../config.php';

// -----------------------------------------------------------------------------
// POST API: Accept log entries from JavaScript client
// Endpoint: POST /push/push-log.php
// Body: { "message": "...", "data": {...} }
// -----------------------------------------------------------------------------
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

// -----------------------------------------------------------------------------
// CLEAR LOG: Delete log file when ?clear=1 is passed
// -----------------------------------------------------------------------------
if (isset($_GET['clear'])) {
    @unlink(PUSH_LOG_FILE);
    header('Location: /push/push-log.php');
    exit;
}

// -----------------------------------------------------------------------------
// WEB UI: Display log file contents in browser with dark theme
// -----------------------------------------------------------------------------
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
