<?php
/**
 * Debug Logger - כותב לוגים לקובץ
 */

header('Content-Type: application/json');

// קבל את ה-JSON מהבקשה
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No data']);
    exit;
}

// הוסף timestamp ו-IP
$data['server_time'] = date('Y-m-d H:i:s');
$data['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// כתוב לקובץ לוג
$logFile = __DIR__ . '/../logs/debug.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logLine = date('Y-m-d H:i:s') . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

// גם לוג לשרת
error_log('[DEBUG-LOG] ' . json_encode($data, JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true]);
