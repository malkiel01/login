<?php
/**
 * Debug Logger - כותב לוגים לקובץ
 * v2 - עם טיפול בשגיאות מקיף
 */

// Prevent any output before headers
ob_start();

try {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    // Get input
    $input = file_get_contents('php://input');

    if (empty($input)) {
        throw new Exception('Empty input');
    }

    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }

    if (!$data) {
        throw new Exception('No data after decode');
    }

    // Add server info
    $data['server_time'] = date('Y-m-d H:i:s');
    $data['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Log file path
    $logFile = __DIR__ . '/../logs/debug.log';
    $logDir = dirname($logFile);

    // Ensure directory exists
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0777, true)) {
            throw new Exception('Failed to create log directory: ' . $logDir);
        }
    }

    // Check if directory is writable
    if (!is_writable($logDir)) {
        throw new Exception('Log directory not writable: ' . $logDir);
    }

    // Create log line
    $logLine = date('Y-m-d H:i:s') . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";

    // Write to file
    $result = file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

    if ($result === false) {
        throw new Exception('Failed to write to log file');
    }

    // Also log to PHP error log as backup
    error_log('[DEBUG-LOG] ' . json_encode($data, JSON_UNESCAPED_UNICODE));

    ob_end_clean();
    echo json_encode(['success' => true, 'bytes' => $result]);

} catch (Exception $e) {
    ob_end_clean();

    // Log error to PHP error log
    error_log('[DEBUG-LOG-ERROR] ' . $e->getMessage() . ' | Input: ' . substr($input ?? '', 0, 500));

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
