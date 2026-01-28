<?php
/**
 * Clear all notifications and reset push subscriptions
 */
require_once __DIR__ . '/config.php';

$pdo = getDBConnection();

$results = [];

// 1. Mark all push subscriptions as inactive (force re-registration)
$stmt = $pdo->exec("UPDATE push_subscriptions SET is_active = 0");
$results['push_subscriptions_deactivated'] = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();

// 2. Clear scheduled notifications
$stmt = $pdo->exec("DELETE FROM scheduled_notifications");
$results['scheduled_notifications_deleted'] = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();

// 3. Clear notification deliveries
$stmt = $pdo->exec("DELETE FROM notification_deliveries WHERE 1=1");
$results['notification_deliveries_deleted'] = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();

// 4. Clear push notifications (polling fallback)
$stmt = $pdo->exec("DELETE FROM push_notifications WHERE 1=1");
$results['push_notifications_deleted'] = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();

// 5. Clear notification approvals
$stmt = $pdo->exec("DELETE FROM notification_approvals WHERE 1=1");
$results['notification_approvals_deleted'] = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();

echo json_encode([
    'success' => true,
    'message' => 'All notifications cleared and subscriptions reset',
    'results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
