<?php
/**
 * Entity Selector for Map v2
 * בורר ישויות לעורך המפות
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// חיבור לבסיס נתונים - כמו בשאר ה-API
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

// קבלת סוג הישות
$entityType = $_GET['entityType'] ?? '';

$entities = [];

try {
    switch ($entityType) {
        case 'cemetery':
            $stmt = $pdo->query("SELECT unicId, cemeteryNameHe as name FROM cemeteries WHERE isActive = 1 ORDER BY cemeteryNameHe");
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'block':
            $stmt = $pdo->query("SELECT b.unicId, CONCAT(b.blockNameHe, ' - ', COALESCE(c.cemeteryNameHe, '')) as name
                FROM blocks b
                LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                WHERE b.isActive = 1
                ORDER BY c.cemeteryNameHe, b.blockNameHe");
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'plot':
            $stmt = $pdo->query("SELECT p.unicId, CONCAT(p.plotNameHe, ' - ', COALESCE(b.blockNameHe, ''), ' - ', COALESCE(c.cemeteryNameHe, '')) as name
                FROM plots p
                LEFT JOIN blocks b ON p.blockId = b.unicId
                LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                WHERE p.isActive = 1
                ORDER BY c.cemeteryNameHe, b.blockNameHe, p.plotNameHe");
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'areaGrave':
            $stmt = $pdo->query("SELECT a.unicId, CONCAT(a.areaGraveNameHe, ' - ', COALESCE(p.plotNameHe, ''), ' - ', COALESCE(b.blockNameHe, '')) as name
                FROM areaGraves a
                LEFT JOIN `rows` r ON a.lineId = r.unicId
                LEFT JOIN plots p ON r.plotId = p.unicId
                LEFT JOIN blocks b ON p.blockId = b.unicId
                WHERE a.isActive = 1
                ORDER BY b.blockNameHe, p.plotNameHe, a.areaGraveNameHe
                LIMIT 200");
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid entity type', 'entities' => []]);
            exit;
    }

    echo json_encode(['success' => true, 'entities' => $entities]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'entities' => []]);
}
