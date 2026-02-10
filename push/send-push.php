<?php
/**
 * Push Notification Sender - Bridge File
 *
 * This file provides backward compatibility for code that requires send-push.php
 * All functionality is now in NotificationService.php
 *
 * Available functions (provided by NotificationService.php):
 * - sendPushToUser(int $userId, string $title, string $body, ?string $url, ?string $icon, array $options): array
 * - sendPushToUsers(array $userIds, string $title, string $body, ?string $url, array $options): array
 * - sendPushToAll(string $title, string $body, ?string $url, array $options): array
 * - processScheduledNotifications(): array
 *
 * @version 2.0.0 - Simplified to bridge file
 * @see NotificationService.php for the actual implementation
 */

// Load the unified notification service (provides all functions via backward compatibility)
require_once __DIR__ . '/NotificationService.php';

// CLI handler - process scheduled notifications when called directly
if (php_sapi_name() === 'cli' || (isset($_GET['cron']) && $_GET['cron'] === 'process')) {
    $result = processScheduledNotifications();
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
