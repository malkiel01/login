#!/usr/bin/php
<?php
/**
 * Cron Job: Process Scheduled Notifications
 * Sends due scheduled notifications via Web Push
 *
 * Setup cron (run every minute):
 * * * * * * /usr/bin/php /path/to/cron-scheduled-notifications.php >> /var/log/push-notifications.log 2>&1
 *
 * Or with wget:
 * * * * * * wget -qO- https://yoursite.com/push/cron-scheduled-notifications.php?cron=1 > /dev/null 2>&1
 *
 * @version 1.0.0
 */

// Security check for web access
if (php_sapi_name() !== 'cli') {
    // Check for cron secret key
    $cronKey = $_GET['cron'] ?? '';
    if ($cronKey !== '1' && $cronKey !== (getenv('CRON_SECRET') ?: '')) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}

require_once __DIR__ . '/send-push.php';

// Process scheduled notifications
$result = processScheduledNotifications();

// Output results
$output = date('Y-m-d H:i:s') . " - Processed: {$result['processed']}";
if (!empty($result['results'])) {
    foreach ($result['results'] as $id => $notifResult) {
        $sent = $notifResult['sent'] ?? 0;
        $failed = $notifResult['failed'] ?? 0;
        $output .= " | Notification #$id: sent=$sent, failed=$failed";
    }
}
$output .= "\n";

echo $output;

// Log to error_log as well
error_log("[Cron] " . trim($output));
