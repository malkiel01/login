<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config.php';

try {
    $cityId = $_GET['cityId'] ?? null;
    $residency = 3; // Default: חו"ל
    
    if ($cityId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT c.cityNameHe, cn.countryNameHe 
            FROM cities c
            JOIN countries cn ON c.countryId = cn.unicId
            WHERE c.unicId = :cityId
        ");
        $stmt->execute(['cityId' => $cityId]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($city && $city['countryNameHe'] == 'ישראל') {
            $jerusalemArea = ['ירושלים', 'בית שמש', 'מעלה 
אדומים'];
            if (in_array($city['cityNameHe'], $jerusalemArea)) {
                $residency = 1; // ירושלים והסביבה
            } else {
                $residency = 2; // תושב חוץ
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'residency' => $residency
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
