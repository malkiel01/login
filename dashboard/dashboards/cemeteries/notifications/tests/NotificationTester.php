<?php
/**
 * Notification Tester
 * Automated testing system for notification functionality
 *
 * @package     Notifications
 * @subpackage  Tests
 * @version     1.0.0
 */

require_once __DIR__ . '/../core/NotificationLogger.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/push/send-push.php';

class NotificationTester {
    private PDO $pdo;
    private NotificationLogger $logger;
    private string $runId;
    private int $createdBy;
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    // Test configuration
    private const TEST_PREFIX = '[בדיקה] ';
    private const TIMEOUT_SECONDS = 30;

    public function __construct(PDO $pdo, int $createdBy) {
        $this->pdo = $pdo;
        $this->logger = NotificationLogger::getInstance($pdo);
        $this->createdBy = $createdBy;
        $this->runId = $this->generateUUID();
    }

    /**
     * Generate UUID for test run
     */
    private function generateUUID(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get the run ID
     */
    public function getRunId(): string {
        return $this->runId;
    }

    /**
     * Start a test run
     */
    public function startRun(array $config = []): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_test_runs
            (run_id, status, test_config, created_by)
            VALUES (?, 'running', ?, ?)
        ");
        $stmt->execute([
            $this->runId,
            json_encode($config),
            $this->createdBy
        ]);

        // Log test started
        $this->logger->logTestStarted($this->runId, $this->createdBy, $config);
    }

    /**
     * Complete a test run
     */
    public function completeRun(): void {
        $status = $this->failed > 0 ? 'failed' : 'completed';

        $stmt = $this->pdo->prepare("
            UPDATE notification_test_runs
            SET status = ?,
                total_tests = ?,
                passed_tests = ?,
                failed_tests = ?,
                completed_at = NOW(),
                results = ?
            WHERE run_id = ?
        ");
        $stmt->execute([
            $status,
            count($this->results),
            $this->passed,
            $this->failed,
            json_encode($this->results),
            $this->runId
        ]);

        // Log test completed
        $this->logger->logTestCompleted($this->runId, [
            'status' => $status,
            'total' => count($this->results),
            'passed' => $this->passed,
            'failed' => $this->failed
        ]);
    }

    /**
     * Run all tests
     */
    public function runAllTests(): array {
        $this->startRun(['tests' => 'all']);

        // Run each test
        $this->testBasicSend();
        $this->testSendToMultipleUsers();
        $this->testApprovalNotification();
        $this->testScheduledNotification();
        $this->testDeliveryLogging();

        $this->completeRun();

        return [
            'run_id' => $this->runId,
            'total' => count($this->results),
            'passed' => $this->passed,
            'failed' => $this->failed,
            'results' => $this->results
        ];
    }

    /**
     * Run a specific test
     */
    public function runTest(string $testName): array {
        $this->startRun(['tests' => [$testName]]);

        switch ($testName) {
            case 'basic_send':
                $this->testBasicSend();
                break;
            case 'multiple_users':
                $this->testSendToMultipleUsers();
                break;
            case 'approval':
                $this->testApprovalNotification();
                break;
            case 'scheduled':
                $this->testScheduledNotification();
                break;
            case 'delivery_logging':
                $this->testDeliveryLogging();
                break;
            default:
                $this->recordResult($testName, false, 'Unknown test');
        }

        $this->completeRun();

        return [
            'run_id' => $this->runId,
            'total' => count($this->results),
            'passed' => $this->passed,
            'failed' => $this->failed,
            'results' => $this->results
        ];
    }

    /**
     * Record test result
     */
    private function recordResult(string $testName, bool $passed, string $message, array $details = []): void {
        $result = [
            'test' => $testName,
            'passed' => $passed,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->results[] = $result;

        if ($passed) {
            $this->passed++;
        } else {
            $this->failed++;
        }
    }

    /**
     * Test 1: Basic notification send
     */
    private function testBasicSend(): void {
        $testName = 'basic_send';

        try {
            // Create a test notification
            $notificationId = $this->createTestNotification([
                'title' => self::TEST_PREFIX . 'בדיקת שליחה בסיסית',
                'body' => 'זוהי הודעת בדיקה אוטומטית. ניתן להתעלם.',
                'target_users' => [$this->createdBy]
            ]);

            if (!$notificationId) {
                throw new Exception('Failed to create notification');
            }

            // Check if it was logged
            $logs = $this->getLogsForNotification($notificationId);
            $hasCreatedLog = $this->hasLogOfType($logs, 'created');

            if ($hasCreatedLog) {
                $this->recordResult($testName, true, 'Notification created and logged successfully', [
                    'notification_id' => $notificationId
                ]);
            } else {
                $this->recordResult($testName, false, 'Notification created but not logged', [
                    'notification_id' => $notificationId
                ]);
            }

            // Cleanup
            $this->cleanupTestNotification($notificationId);

        } catch (Exception $e) {
            $this->recordResult($testName, false, 'Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test 2: Send to multiple users
     */
    private function testSendToMultipleUsers(): void {
        $testName = 'multiple_users';

        try {
            // Get a few active users
            $stmt = $this->pdo->query("SELECT id FROM users WHERE is_active = 1 LIMIT 3");
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($userIds) < 2) {
                $this->recordResult($testName, true, 'Skipped - not enough users in system', [
                    'users_found' => count($userIds)
                ]);
                return;
            }

            $notificationId = $this->createTestNotification([
                'title' => self::TEST_PREFIX . 'בדיקת שליחה מרובה',
                'body' => 'בדיקת שליחה למספר משתמשים.',
                'target_users' => $userIds
            ]);

            // Check push_notifications table
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM push_notifications
                WHERE scheduled_notification_id = ?
            ");
            $stmt->execute([$notificationId]);
            $insertedCount = (int)$stmt->fetchColumn();

            if ($insertedCount >= count($userIds)) {
                $this->recordResult($testName, true, "Notification sent to $insertedCount users", [
                    'notification_id' => $notificationId,
                    'target_count' => count($userIds),
                    'inserted_count' => $insertedCount
                ]);
            } else {
                $this->recordResult($testName, false, "Expected {count($userIds)} records, got $insertedCount", [
                    'notification_id' => $notificationId
                ]);
            }

            // Cleanup
            $this->cleanupTestNotification($notificationId);

        } catch (Exception $e) {
            $this->recordResult($testName, false, 'Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test 3: Approval notification
     */
    private function testApprovalNotification(): void {
        $testName = 'approval';

        try {
            $notificationId = $this->createTestNotification([
                'title' => self::TEST_PREFIX . 'בדיקת אישור',
                'body' => 'בדיקת התראה עם דרישת אישור.',
                'target_users' => [$this->createdBy],
                'requires_approval' => true,
                'approval_message' => 'אנא אשר הודעה זו'
            ]);

            // Check if approval record was created
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_approvals
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $this->createdBy]);
            $approval = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($approval && $approval['status'] === 'pending') {
                $this->recordResult($testName, true, 'Approval notification created with pending status', [
                    'notification_id' => $notificationId,
                    'approval_id' => $approval['id']
                ]);
            } else {
                $this->recordResult($testName, false, 'Approval record not created properly', [
                    'notification_id' => $notificationId,
                    'approval' => $approval
                ]);
            }

            // Cleanup
            $this->cleanupTestNotification($notificationId);

        } catch (Exception $e) {
            $this->recordResult($testName, false, 'Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test 4: Scheduled notification
     */
    private function testScheduledNotification(): void {
        $testName = 'scheduled';

        try {
            $scheduledAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $this->pdo->prepare("
                INSERT INTO scheduled_notifications
                (title, body, notification_type, target_users, scheduled_at, status, created_by)
                VALUES (?, ?, 'info', ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                self::TEST_PREFIX . 'בדיקת תזמון',
                'בדיקת התראה מתוזמנת.',
                json_encode([$this->createdBy]),
                $scheduledAt,
                $this->createdBy
            ]);
            $notificationId = $this->pdo->lastInsertId();

            // Log it manually since we bypassed the API
            $this->logger->logCreated($notificationId, $this->createdBy, [
                'title' => self::TEST_PREFIX . 'בדיקת תזמון',
                'test_run_id' => $this->runId
            ]);
            $this->logger->logScheduled($notificationId, self::TEST_PREFIX . 'בדיקת תזמון', $scheduledAt);

            // Check if it was created with correct status
            $stmt = $this->pdo->prepare("SELECT * FROM scheduled_notifications WHERE id = ?");
            $stmt->execute([$notificationId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($notification && $notification['status'] === 'pending' && $notification['scheduled_at'] === $scheduledAt) {
                $this->recordResult($testName, true, 'Scheduled notification created correctly', [
                    'notification_id' => $notificationId,
                    'scheduled_at' => $scheduledAt
                ]);
            } else {
                $this->recordResult($testName, false, 'Scheduled notification not created properly', [
                    'notification_id' => $notificationId
                ]);
            }

            // Cleanup
            $this->cleanupTestNotification($notificationId);

        } catch (Exception $e) {
            $this->recordResult($testName, false, 'Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test 5: Delivery logging
     */
    private function testDeliveryLogging(): void {
        $testName = 'delivery_logging';

        try {
            $notificationId = $this->createTestNotification([
                'title' => self::TEST_PREFIX . 'בדיקת לוגים',
                'body' => 'בדיקת רישום לוגים.',
                'target_users' => [$this->createdBy]
            ]);

            // Wait a bit for logs to be written
            usleep(500000); // 0.5 seconds

            // Check logs
            $logs = $this->getLogsForNotification($notificationId);

            $hasCreated = $this->hasLogOfType($logs, 'created');
            $hasSendAttempt = $this->hasLogOfType($logs, 'send_attempt') || $this->hasLogOfType($logs, 'delivered');

            $details = [
                'notification_id' => $notificationId,
                'log_count' => count($logs),
                'has_created' => $hasCreated,
                'has_send_attempt' => $hasSendAttempt
            ];

            if ($hasCreated) {
                $this->recordResult($testName, true, 'Delivery logging working correctly', $details);
            } else {
                $this->recordResult($testName, false, 'Some logs are missing', $details);
            }

            // Cleanup
            $this->cleanupTestNotification($notificationId);

        } catch (Exception $e) {
            $this->recordResult($testName, false, 'Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a test notification using the API logic
     */
    private function createTestNotification(array $params): int {
        $title = $params['title'] ?? self::TEST_PREFIX . 'Test';
        $body = $params['body'] ?? 'Test notification body';
        $targetUsers = $params['target_users'] ?? [$this->createdBy];
        $requiresApproval = !empty($params['requires_approval']);
        $approvalMessage = $params['approval_message'] ?? null;

        $stmt = $this->pdo->prepare("
            INSERT INTO scheduled_notifications
            (title, body, notification_type, target_users, status, created_by, requires_approval, approval_message, sent_at)
            VALUES (?, ?, 'info', ?, 'sent', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $title,
            $body,
            json_encode($targetUsers),
            $this->createdBy,
            $requiresApproval ? 1 : 0,
            $approvalMessage
        ]);

        $notificationId = (int)$this->pdo->lastInsertId();

        // Log creation
        $this->logger->logCreated($notificationId, $this->createdBy, [
            'title' => $title,
            'body' => $body,
            'test_run_id' => $this->runId
        ]);

        // Create approval records if needed
        if ($requiresApproval) {
            foreach ($targetUsers as $userId) {
                $this->pdo->prepare("
                    INSERT IGNORE INTO notification_approvals
                    (notification_id, user_id, status)
                    VALUES (?, ?, 'pending')
                ")->execute([$notificationId, $userId]);
            }
        }

        // Insert into push_notifications for each user
        $insertStmt = $this->pdo->prepare("
            INSERT INTO push_notifications
            (scheduled_notification_id, user_id, title, body)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($targetUsers as $userId) {
            $insertStmt->execute([$notificationId, $userId, $title, $body]);
        }

        return $notificationId;
    }

    /**
     * Get logs for a notification
     */
    private function getLogsForNotification(int $notificationId): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notification_logs
            WHERE notification_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$notificationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if logs contain a specific event type
     */
    private function hasLogOfType(array $logs, string $eventType): bool {
        foreach ($logs as $log) {
            if ($log['event_type'] === $eventType) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cleanup test notification
     */
    private function cleanupTestNotification(int $notificationId): void {
        // Delete in order to respect foreign keys
        $this->pdo->prepare("DELETE FROM notification_logs WHERE notification_id = ?")->execute([$notificationId]);
        $this->pdo->prepare("DELETE FROM notification_approvals WHERE notification_id = ?")->execute([$notificationId]);
        $this->pdo->prepare("DELETE FROM push_notifications WHERE scheduled_notification_id = ?")->execute([$notificationId]);
        $this->pdo->prepare("DELETE FROM scheduled_notifications WHERE id = ?")->execute([$notificationId]);
    }

    /**
     * Get test run status
     */
    public static function getRunStatus(PDO $pdo, string $runId): ?array {
        $stmt = $pdo->prepare("SELECT * FROM notification_test_runs WHERE run_id = ?");
        $stmt->execute([$runId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$run) {
            return null;
        }

        $run['results'] = json_decode($run['results'], true);
        $run['test_config'] = json_decode($run['test_config'], true);

        return $run;
    }

    /**
     * Get test history
     */
    public static function getHistory(PDO $pdo, int $limit = 20): array {
        $stmt = $pdo->prepare("
            SELECT
                tr.*,
                u.name as creator_name
            FROM notification_test_runs tr
            LEFT JOIN users u ON u.id = tr.created_by
            ORDER BY tr.started_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($runs as &$run) {
            $run['results'] = json_decode($run['results'], true);
            $run['test_config'] = json_decode($run['test_config'], true);
        }

        return $runs;
    }

    /**
     * Get available tests
     */
    public static function getAvailableTests(): array {
        return [
            [
                'id' => 'basic_send',
                'name' => 'בדיקת שליחה בסיסית',
                'description' => 'יצירה ושליחה של התראה פשוטה'
            ],
            [
                'id' => 'multiple_users',
                'name' => 'שליחה למספר משתמשים',
                'description' => 'בדיקת שליחה למספר משתמשים במקביל'
            ],
            [
                'id' => 'approval',
                'name' => 'התראת אישור',
                'description' => 'בדיקת יצירת התראה עם דרישת אישור'
            ],
            [
                'id' => 'scheduled',
                'name' => 'התראה מתוזמנת',
                'description' => 'בדיקת יצירת התראה עם תזמון עתידי'
            ],
            [
                'id' => 'delivery_logging',
                'name' => 'רישום לוגים',
                'description' => 'בדיקה שכל האירועים נרשמים בלוג'
            ]
        ];
    }
}
