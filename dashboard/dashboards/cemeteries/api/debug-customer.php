<?php
/**
 * File: api/debug-customer.php
 * Description: Debug script to query customer by id (auto-increment) instead of unicId
 * Usage: ?id=19671
 */

header('Content-Type: application/json; charset=utf-8');

// Load config and DB connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

try {
    $pdo = getDBConnection();

    $id = $_GET['id'] ?? null;

    if (!$id) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing id parameter',
            'usage' => '?id=19671'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Query by id (auto-increment), not unicId
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        echo json_encode([
            'success' => true,
            'data' => $customer,
            'debug' => [
                'searched_by' => 'id (auto-increment)',
                'id' => $id,
                'unicId' => $customer['unicId'] ?? null,
                'maritalStatus' => $customer['maritalStatus'] ?? null,
                'maritalStatus_type' => gettype($customer['maritalStatus'] ?? null)
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Customer not found',
            'searched_by' => 'id (auto-increment)',
            'id' => $id
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
