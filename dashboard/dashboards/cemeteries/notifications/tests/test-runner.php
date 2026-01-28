<?php
/**
 * Notifications Test Runner
 * @version 1.0.0
 *
 * Usage: php test-runner.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n";
echo "==========================================\n";
echo "  Notifications Module Tests\n";
echo "==========================================\n\n";

// Load test files
require_once __DIR__ . '/NotificationsApiTest.php';
require_once __DIR__ . '/NotificationsValidationTest.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Run API tests
echo "Running API Tests...\n";
echo "--------------------\n";
$apiTest = new NotificationsApiTest();
$apiResults = $apiTest->runAll();
$totalTests += $apiResults['total'];
$passedTests += $apiResults['passed'];
$failedTests += $apiResults['failed'];

echo "\n";

// Run Validation tests
echo "Running Validation Tests...\n";
echo "---------------------------\n";
$validationTest = new NotificationsValidationTest();
$validationResults = $validationTest->runAll();
$totalTests += $validationResults['total'];
$passedTests += $validationResults['passed'];
$failedTests += $validationResults['failed'];

// Summary
echo "\n";
echo "==========================================\n";
echo "  Test Summary\n";
echo "==========================================\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       $passedTests\n";
echo "Failed:       $failedTests\n";
echo "==========================================\n";

if ($failedTests > 0) {
    echo "\n❌ Some tests failed!\n";
    exit(1);
} else {
    echo "\n✅ All tests passed!\n";
    exit(0);
}
