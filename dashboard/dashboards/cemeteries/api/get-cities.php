<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config.php';

// try {
//     $countryId = $_GET['countryId'] ?? null;
//     $conn = getDBConnection();
    
//     $sql = "SELECT unicId as value, cityNameHe as text 
//             FROM cities WHERE isActive = 1";
    
//     if ($countryId) {
//         $sql .= " AND countryId = :countryId";
//     }
    
//     $sql .= " ORDER BY cityNameHe";
    
//     $stmt = $conn->prepare($sql);
//     if ($countryId) {
//         $stmt->bindParam(':countryId', $countryId);
//     }
//     $stmt->execute();
    
//     $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
//     echo json_encode([
//         'success' => true,
//         'cities' => $cities
//     ]);
    
// } catch (Exception $e) {
//     echo json_encode([
//         'success' => false,
//         'error' => $e->getMessage()
//     ]);
// }

try {
    $countryId = $_GET['countryId'] ?? null;
    $conn = getDBConnection();
    
    $sql = "SELECT unicId, cityNameHe 
            FROM cities WHERE isActive = 1";
    
    if ($countryId) {
        $sql .= " AND countryId = :countryId";
    }
    
    $sql .= " ORDER BY cityNameHe";
    
    $stmt = $conn->prepare($sql);
    if ($countryId) {
        $stmt->bindParam(':countryId', $countryId);
    }
    $stmt->execute();
    
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ← השינוי הוא כאן! פשוט החזר את המערך ישירות
    echo json_encode($cities, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
