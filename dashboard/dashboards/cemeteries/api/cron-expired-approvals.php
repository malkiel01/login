<?php
/**
 * Cron Job: Process Expired Approval Requests
 * Marks pending approval requests as 'expired' when their expiration time has passed
 *
 * Setup cron (run every hour):
 * 0 * * * * /usr/bin/php /path/to/cron-expired-approvals.php >> /var/log/expired-approvals.log 2>&1
 *
 * Or with wget:
 * 0 * * * * wget -qO- https://yoursite.com/dashboard/dashboards/cemeteries/api/cron-expired-approvals.php?cron=1 > /dev/null 2>&1
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

// Load dependencies
require_once __DIR__ . '/../../../../config.php';
require_once __DIR__ . '/services/EntityApprovalService.php';

try {
    // Get database connection
    $pdo = getDBConnection();

    // Get service instance and process expired operations
    $service = EntityApprovalService::getInstance($pdo);
    $expiredCount = $service->processExpiredOperations();

    // Output results
    $output = date('Y-m-d H:i:s') . " - Expired approvals processed: {$expiredCount}\n";
    echo $output;

    // Log to error_log as well
    if ($expiredCount > 0) {
        error_log("[Cron-ExpiredApprovals] " . trim($output));
    }

} catch (Exception $e) {
    $error = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    echo $error;
    error_log("[Cron-ExpiredApprovals] " . trim($error));
    exit(1);
}
