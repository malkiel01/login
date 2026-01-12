<?php
/**
 * Entity Selector for Map v2
 * בורר ישויות לעורך המפות
 */

// Headers first!
header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = [
    'success' => false,
    'entities' => [],
    'debug' => []
];

try {
    // Load config
    $configPath = dirname(__DIR__) . '/config.php';
    $response['debug']['configPath'] = $configPath;
    $response['debug']['configExists'] = file_exists($configPath);

    if (!file_exists($configPath)) {
        throw new Exception('Config file not found: ' . $configPath);
    }

    require_once $configPath;

    // Check PDO
    if (!isset($pdo)) {
        throw new Exception('PDO not available after loading config');
    }

    $response['debug']['pdoConnected'] = true;

    // Get entity type
    $entityType = $_GET['entityType'] ?? '';
    $response['debug']['entityType'] = $entityType;

    if (empty($entityType)) {
        throw new Exception('No entity type provided');
    }

    $entities = [];

    switch ($entityType) {
        case 'cemetery':
            $sql = "SELECT unicId, cemeteryNameHe as name FROM cemeteries WHERE isActive = 1 ORDER BY cemeteryNameHe";
            $stmt = $pdo->query($sql);
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'block':
            $sql = "SELECT b.unicId, CONCAT(b.blockNameHe, ' - ', COALESCE(c.cemeteryNameHe, '')) as name
                FROM blocks b
                LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                WHERE b.isActive = 1
                ORDER BY c.cemeteryNameHe, b.blockNameHe";
            $stmt = $pdo->query($sql);
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'plot':
            $sql = "SELECT p.unicId, CONCAT(p.plotNameHe, ' - ', COALESCE(b.blockNameHe, ''), ' - ', COALESCE(c.cemeteryNameHe, '')) as name
                FROM plots p
                LEFT JOIN blocks b ON p.blockId = b.unicId
                LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                WHERE p.isActive = 1
                ORDER BY c.cemeteryNameHe, b.blockNameHe, p.plotNameHe";
            $stmt = $pdo->query($sql);
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'areaGrave':
            $sql = "SELECT a.unicId, CONCAT(a.areaGraveNameHe, ' - ', COALESCE(p.plotNameHe, ''), ' - ', COALESCE(b.blockNameHe, '')) as name
                FROM areaGraves a
                LEFT JOIN `rows` r ON a.lineId = r.unicId
                LEFT JOIN plots p ON r.plotId = p.unicId
                LEFT JOIN blocks b ON p.blockId = b.unicId
                WHERE a.isActive = 1
                ORDER BY b.blockNameHe, p.plotNameHe, a.areaGraveNameHe
                LIMIT 200";
            $stmt = $pdo->query($sql);
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            throw new Exception('Unknown entity type: ' . $entityType);
    }

    $response['success'] = true;
    $response['entities'] = $entities;
    $response['debug']['count'] = count($entities);

} catch (PDOException $e) {
    $response['success'] = false;
    $response['error'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
