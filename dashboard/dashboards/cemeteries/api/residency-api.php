<?php
// dashboard/dashboards/cemeteries/api/residency-api.php
// API לניהול הגדרות תושבות

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';

// פעולה מבוקשת
$action = $_GET['action'] ?? '';

// טיפול בפעולות שונות
switch ($action) {
    case 'list':
        requireViewPermission('residency');
        listResidencies();
        break;

    case 'get':
        requireViewPermission('residency');
        getResidency($_GET['id'] ?? '');
        break;

    case 'save':
        requireEditPermission('residency');
        saveResidency();
        break;

    case 'delete':
        requireDeletePermission('residency');
        deleteResidency($_GET['id'] ?? '');
        break;

    case 'search':
        requireViewPermission('residency');
        searchResidencies($_GET['query'] ?? '');
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// רשימת הגדרות תושבות
function listResidencies() {
    try {
        $pdo = getDBConnection();
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        // שליפת הרשומות עם JOIN לטבלאות מדינות וערים
        $sql = "SELECT r.*, 
                c.countryNameHe, 
                ct.cityNameHe
                FROM residency_settings r
                LEFT JOIN countries c ON r.countryId = c.unicId
                LEFT JOIN cities ct ON r.cityId = ct.unicId
                WHERE r.isActive = 1
                ORDER BY r.createDate DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $residencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ספירת סך הרשומות
        $countStmt = $pdo->query("SELECT COUNT(*) FROM residency_settings WHERE isActive = 1");
        $total = $countStmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => $residencies,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// קבלת הגדרת תושבות ספציפית
function getResidency($id) {
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT r.*, 
                c.countryNameHe, 
                ct.cityNameHe
                FROM residency_settings r
                LEFT JOIN countries c ON r.countryId = c.unicId
                LEFT JOIN cities ct ON r.cityId = ct.unicId
                WHERE r.unicId = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $residency = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($residency) {
            echo json_encode(['success' => true, 'data' => $residency]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Residency not found']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// שמירת הגדרת תושבות
function saveResidency() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // בדיקת שדות חובה
    if (empty($data['residencyName']) || empty($data['residencyType'])) {
        echo json_encode(['success' => false, 'error' => 'חסרים שדות חובה']);
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // בדיקה אם זו עריכה או הוספה
        if (!empty($data['unicId'])) {
            // עריכה
            $sql = "UPDATE residency_settings SET 
                    residencyName = :residencyName,
                    countryId = :countryId,
                    cityId = :cityId,
                    residencyType = :residencyType,
                    description = :description,
                    countryNameHe = :countryNameHe,
                    cityNameHe = :cityNameHe,
                    updateDate = NOW()
                    WHERE unicId = :unicId";
            
            $stmt = $pdo->prepare($sql);
            $params = [
                'unicId' => $data['unicId'],
                'residencyName' => $data['residencyName'],
                'countryId' => $data['countryId'] ?? null,
                'cityId' => $data['cityId'] ?? null,
                'residencyType' => (int)$data['residencyType'],
                'description' => $data['description'] ?? null,
                'countryNameHe' => $data['countryNameHe'] ?? null,
                'cityNameHe' => $data['cityNameHe'] ?? null
            ];

            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'הגדרת התושבות עודכנה בהצלחה']);

        } else {
            // הוספה חדשה
            $unicId = 'RES' . uniqid();

            $sql = "INSERT INTO residency_settings
                    (unicId, residencyName, countryId, cityId, residencyType, description,
                     countryNameHe, cityNameHe, createDate, updateDate, isActive)
                    VALUES
                    (:unicId, :residencyName, :countryId, :cityId, :residencyType, :description,
                     :countryNameHe, :cityNameHe, NOW(), NOW(), 1)";

            $stmt = $pdo->prepare($sql);
            $params = [
                'unicId' => $unicId,
                'residencyName' => $data['residencyName'],
                'countryId' => $data['countryId'] ?? null,
                'cityId' => $data['cityId'] ?? null,
                'residencyType' => (int)$data['residencyType'],
                'description' => $data['description'] ?? null,
                'countryNameHe' => $data['countryNameHe'] ?? null,
                'cityNameHe' => $data['cityNameHe'] ?? null
            ];
            
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true, 
                'message' => 'הגדרת התושבות נוספה בהצלחה',
                'id' => $unicId
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// מחיקת הגדרת תושבות
function deleteResidency($id) {
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // סימון כלא פעיל במקום מחיקה פיזית
        $sql = "UPDATE residency_settings SET 
                isActive = 0, 
                inactiveDate = NOW() 
                WHERE unicId = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        echo json_encode(['success' => true, 'message' => 'הגדרת התושבות נמחקה בהצלחה']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// חיפוש הגדרות תושבות
function searchResidencies($query) {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT r.*, 
                c.countryNameHe, 
                ct.cityNameHe
                FROM residency_settings r
                LEFT JOIN countries c ON r.countryId = c.unicId
                LEFT JOIN cities ct ON r.cityId = ct.unicId
                WHERE r.isActive = 1 
                AND (r.residencyName LIKE :query 
                     OR c.countryNameHe LIKE :query 
                     OR ct.cityNameHe LIKE :query)
                ORDER BY r.residencyName ASC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $searchParam = '%' . $query . '%';
        $stmt->execute(['query' => $searchParam]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $results]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>