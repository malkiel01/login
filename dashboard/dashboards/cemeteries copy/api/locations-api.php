<?php
// dashboards/cemeteries/api/locations-api.php
// API לניהול מדינות וערים

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

try {
    $pdo = getDBConnection();
    
    $action = $_GET['action'] ?? '';
    
    switch($action) {
        case 'getCountries':
            // קבל את כל המדינות הפעילות
            $stmt = $pdo->query("
                SELECT id, unicId, countryNameHe, countryNameEn 
                FROM countries 
                WHERE isActive = 1 
                ORDER BY countryNameHe
            ");
            $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $countries
            ]);
            break;
            
        case 'getCities':
            $countryId = $_GET['countryId'] ?? '';
            
            if (!$countryId) {
                throw new Exception('Country ID is required');
            }
            
            // קבל את כל הערים של המדינה
            $stmt = $pdo->prepare("
                SELECT id, unicId, cityNameHe, cityNameEn 
                FROM cities 
                WHERE countryId = :countryId 
                AND isActive = 1 
                ORDER BY cityNameHe
            ");
            $stmt->execute(['countryId' => $countryId]);
            $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $cities
            ]);
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            // חיפוש ערים
            $stmt = $pdo->prepare("
                SELECT 
                    c.unicId,
                    c.cityNameHe,
                    c.cityNameEn,
                    co.countryNameHe,
                    CONCAT(c.cityNameHe, ', ', co.countryNameHe) as fullName
                FROM cities c
                JOIN countries co ON c.countryId = co.unicId
                WHERE c.isActive = 1 
                AND (
                    c.cityNameHe LIKE :query 
                    OR c.cityNameEn LIKE :query
                )
                LIMIT 10
            ");
            $stmt->execute(['query' => "%$query%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>