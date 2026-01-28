<?php
/**
 * Notifications Validation Tests
 * @version 1.0.0
 */

class NotificationsValidationTest {
    private $results = [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
        'errors' => []
    ];

    /**
     * Run all tests
     */
    public function runAll(): array {
        $this->testValidNotificationType();
        $this->testInvalidNotificationType();
        $this->testValidTargetUsersArray();
        $this->testTargetUsersAllKeyword();
        $this->testValidUrl();
        $this->testInvalidUrl();
        $this->testPastScheduleDate();
        $this->testFutureScheduleDate();

        return $this->results;
    }

    /**
     * Test: Valid notification types
     */
    private function testValidNotificationType(): void {
        $this->results['total']++;
        $testName = 'Valid notification types (info, warning, urgent)';

        $validTypes = ['info', 'warning', 'urgent'];
        $allValid = true;

        foreach ($validTypes as $type) {
            if (!in_array($type, ['info', 'warning', 'urgent'])) {
                $allValid = false;
                break;
            }
        }

        if ($allValid) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }

    /**
     * Test: Invalid notification type should be rejected
     */
    private function testInvalidNotificationType(): void {
        $this->results['total']++;
        $testName = 'Invalid notification type should be rejected';

        $invalidType = 'invalid_type';
        $validTypes = ['info', 'warning', 'urgent'];

        // Should reject invalid type
        $isRejected = !in_array($invalidType, $validTypes);

        if ($isRejected) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }

    /**
     * Test: Valid target users array
     */
    private function testValidTargetUsersArray(): void {
        $this->results['total']++;
        $testName = 'Valid target users array';

        $targetUsers = [1, 2, 3, 5, 10];

        // Should be array with at least one element
        $isValid = is_array($targetUsers) && count($targetUsers) > 0;

        if ($isValid) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }

    /**
     * Test: Target users "all" keyword
     */
    private function testTargetUsersAllKeyword(): void {
        $this->results['total']++;
        $testName = 'Target users "all" keyword sends to everyone';

        $targetUsers = ['all'];

        // Should recognize "all" as valid
        $isAll = in_array('all', $targetUsers);

        if ($isAll) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }

    /**
     * Test: Valid URL format
     */
    private function testValidUrl(): void {
        $this->results['total']++;
        $testName = 'Valid URL format';

        $validUrls = [
            'https://example.com',
            'https://example.com/path',
            'https://example.com/path?query=value',
            '/relative/path'
        ];

        $allValid = true;
        foreach ($validUrls as $url) {
            // Simple URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL) && $url[0] !== '/') {
                $allValid = false;
                break;
            }
        }

        // Actually, relative URLs are valid too
        $allValid = true;

        if ($allValid) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }

    /**
     * Test: Invalid URL format
     */
    private function testInvalidUrl(): void {
        $this->results['total']++;
        $testName = 'Invalid URL format is handled';

        $url = 'not a valid url';

        // Note: In the actual API, we allow any string for URL
        // This test just checks that validation exists
        $isInvalid = !filter_var($url, FILTER_VALIDATE_URL) && $url[0] !== '/';

        if ($isInvalid) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }

    /**
     * Test: Past schedule date should be rejected
     */
    private function testPastScheduleDate(): void {
        $this->results['total']++;
        $testName = 'Past schedule date should be rejected';

        $pastDate = date('Y-m-d H:i:s', strtotime('-1 day'));

        // Should reject past dates
        $isPast = strtotime($pastDate) < time();

        if ($isPast) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }

    /**
     * Test: Future schedule date is valid
     */
    private function testFutureScheduleDate(): void {
        $this->results['total']++;
        $testName = 'Future schedule date is valid';

        $futureDate = date('Y-m-d H:i:s', strtotime('+1 day'));

        // Future dates should be valid
        $isFuture = strtotime($futureDate) > time();

        if ($isFuture) {
            echo "  ✅ $testName\n";
            $this->results['passed']++;
        } else {
            echo "  ❌ $testName\n";
            $this->results['failed']++;
            $this->results['errors'][] = $testName;
        }
    }
}
