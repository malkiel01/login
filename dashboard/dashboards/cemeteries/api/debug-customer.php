<?php
/**
 * File: api/debug-customer.php
 * Description: Debug script to compare customers by id (auto-increment)
 * Usage: ?ids=3840,19671 (compare mode)
 *        ?id=19671 (single mode)
 */

header('Content-Type: application/json; charset=utf-8');

// Load config and DB connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

try {
    $pdo = getDBConnection();

    // Support multiple IDs for comparison
    $ids = $_GET['ids'] ?? $_GET['id'] ?? null;

    if (!$ids) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing id/ids parameter',
            'usage' => [
                'single' => '?id=19671',
                'compare' => '?ids=3840,19671'
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Parse IDs (comma-separated)
    $idArray = array_map('trim', explode(',', $ids));

    $customers = [];
    $comparison = [];

    foreach ($idArray as $id) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            $customers[$id] = $customer;

            // Build comparison data
            $comparison[$id] = [
                'id' => $customer['id'],
                'unicId' => $customer['unicId'],
                'firstName' => $customer['firstName'],
                'lastName' => $customer['lastName'],
                'maritalStatus' => $customer['maritalStatus'],
                'maritalStatus_type' => gettype($customer['maritalStatus']),
                'maritalStatus_value' => var_export($customer['maritalStatus'], true),
                'spouse' => $customer['spouse'],
                'isActive' => $customer['isActive'],
                'createDate' => $customer['createDate'],
                'filter_check' => [
                    '!status' => !$customer['maritalStatus'] ? 'true' : 'false',
                    'status == 1' => ($customer['maritalStatus'] == 1) ? 'true' : 'false',
                    'would_pass_filter' => (!$customer['maritalStatus'] || $customer['maritalStatus'] == 1) ? 'YES' : 'NO'
                ]
            ];
        } else {
            $customers[$id] = null;
            $comparison[$id] = ['error' => 'Customer not found'];
        }
    }

    // If comparing multiple, highlight differences
    $differences = [];
    if (count($idArray) > 1 && count($customers) > 1) {
        $keys = ['maritalStatus', 'spouse', 'isActive', 'statusCustomer'];
        foreach ($keys as $key) {
            $values = [];
            foreach ($idArray as $id) {
                if (isset($customers[$id])) {
                    $values[$id] = $customers[$id][$key] ?? null;
                }
            }
            if (count(array_unique($values)) > 1) {
                $differences[$key] = $values;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'comparison' => $comparison,
        'differences' => $differences,
        'full_data' => $customers
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
