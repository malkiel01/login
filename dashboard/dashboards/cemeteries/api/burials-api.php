<?php
// dashboard/dashboards/cemeteries/api/burials-api.php
// API לניהול קבורות

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

try {
    switch ($action) {
        case 'get':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*,
                       CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                       c.numId as customer_id_number,
                       g.graveNameHe as grave_name,
                       g.graveStatus,
                       p.serialPurchaseId as purchase_number
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                LEFT JOIN graves g ON b.graveId = g.unicId
                LEFT JOIN purchases p ON b.purchaseId = p.unicId
                WHERE b.unicId = :id AND b.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$burial) {
                throw new Exception('קבורה לא נמצאה');
            }
            
            echo json_encode(['success' => true, 'data' => $burial]);
            break;
            
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (empty($data['clientId'])) {
                throw new Exception('לקוח הוא שדה חובה');
            }
            
            if (empty($data['graveId'])) {
                throw new Exception('קבר הוא שדה חובה');
            }
            
            // בדוק שהקבר זמין לקבורה
            $stmt = $pdo->prepare("SELECT graveStatus FROM graves WHERE unicId = :id");
            $stmt->execute(['id' => $data['graveId']]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            // קבר חייב להיות פנוי (1) או נרכש (2)
            if (!in_array($grave['graveStatus'], [1, 2])) {
                throw new Exception('הקבר אינו זמין לקבורה');
            }
            
            // יצירת unicId
            if (!isset($data['unicId'])) {
                $data['unicId'] = uniqid('burial_', true);
            }
            
            // יצירת מספר סידורי לקבורה
            if (!isset($data['serialBurialId'])) {
                $year = date('Y');
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(serialBurialId, 6) AS UNSIGNED)) as max_serial 
                                     FROM burials 
                                     WHERE serialBurialId LIKE '$year-%'");
                $maxSerial = $stmt->fetch(PDO::FETCH_ASSOC)['max_serial'] ?? 0;
                $data['serialBurialId'] = $year . '-' . str_pad($maxSerial + 1, 5, '0', STR_PAD_LEFT);
            }
            
            // הוספת תאריכים
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            // שדות מותרים
            $allowedFields = [
                'unicId', 'clientId', 'graveId', 'purchaseId', 'serialBurialId',
                'dateDeath', 'timeDeath', 'dateBurial', 'timeBurial', 'placeDeath',
                'nationalInsuranceBurial', 'deathAbroad', 'dateOpening_tld', 'reportingBL',
                'kinship', 'buriaLicense', 'comment', 'createDate', 'updateDate', 'isActive'
            ];
            
            $insertFields = [];
            $insertValues = [];
            $params = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = ":$field";
                    $params[$field] = $data[$field];
                }
            }
            
            $sql = "INSERT INTO burials (" . implode(', ', $insertFields) . ") 
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // עדכון סטטוס הקבר לקבור (3)
            $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 3 WHERE unicId = :id");
            $stmt->execute(['id' => $data['graveId']]);
            
            // עדכון סטטוס הלקוח לנפטר (3)
            $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 3 WHERE unicId = :id");
            $stmt->execute(['id' => $data['clientId']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה נוספה בהצלחה',
                'unicId' => $data['unicId']
            ]);
            break;
            
        case 'update':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            $allowedFields = [
                'dateDeath', 'timeDeath', 'dateBurial', 'timeBurial', 'placeDeath',
                'nationalInsuranceBurial', 'deathAbroad', 'dateOpening_tld', 'reportingBL',
                'kinship', 'buriaLicense', 'comment', 'updateDate'
            ];
            
            $updateFields = [];
            $params = ['id' => $id];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('אין נתונים לעדכון');
            }
            
            $sql = "UPDATE burials SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה עודכנה בהצלחה'
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