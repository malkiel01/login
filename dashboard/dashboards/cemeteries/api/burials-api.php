<?php
/*
 * File: dashboard/dashboards/cemeteries/api/burials-api.php
 * Version: 2.1.0
 * Updated: 2025-11-19
 * Author: Malkiel
 * Change Summary:
 * - v2.1.0: 🐛 תיקון קריטי - הסרת שדות לא קיימים מחיפוש
 *   ✅ הסרת b.customerFirstName, b.customerLastName, b.customerNumId
 *   ✅ שימוש רק בשדות מטבלת customers דרך JOIN (c.firstName, c.lastName, c.numId)
 *   ✅ תיקון stats - שימוש ב-fetchAll במקום fetch
 * - v2.0.0: יצירה מחדש מאפס - זהה 100% ל-purchases-api.php
 */

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';

// =====================================
// 1️⃣ קבלת נתוני POST/GET
// =====================================
$postData = json_decode(file_get_contents('php://input'), true);

// אם יש POST data - זה חיפוש מ-UniversalSearch
if ($postData && isset($postData['action'])) {
    $action = $postData['action'];
    $query = $postData['query'] ?? '';
    $filters = $postData['filters'] ?? [];
    $page = $postData['page'] ?? 1;
    $limit = $postData['limit'] ?? 200;
    $sort = $postData['orderBy'] ?? 'createDate';
    $order = strtoupper($postData['sortDirection'] ?? 'DESC');
    $status = '';  // סטטוס מגיע מפילטרים
    $customer_id = '';  // לקוח מגיע מפילטרים
} else {
    // אחרת - GET רגיל
    $action = $_GET['action'] ?? '';
    $query = $_GET['search'] ?? '';
    $filters = [];
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 200;
    $sort = $_GET['sort'] ?? 'createDate';
    $order = strtoupper($_GET['order'] ?? 'DESC');
    $status = $_GET['status'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
}

// ⭐ $id תמיד מגיע רק מ-GET (גם בעריכה וגם במחיקה)
$id = $_GET['id'] ?? null;

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

try {
    switch ($action) {
        case 'list':
            // חישוב offset
            $offset = ($page - 1) * $limit;

            // בניית השאילתא
            $sql = "
                SELECT 
                    b.*,                                    -- כל שדות burials
                    gv.cemeteryNameHe, gv.blockNameHe,     -- היררכיית בית עלמין
                    gv.plotNameHe, gv.lineNameHe,
                    gv.areaGraveNameHe, gv.graveNameHe,
                    gv.graveStatus, gv.comments AS graveComments,
                    cust1.fullNameHe AS clientFullNameHe,   -- פרטי נפטר
                    cust1.numId AS clientNumId,
                    cust1.nameFather AS clientNameFather,
                    cust1.nameMother AS clientNameMother,
                    cust2.fullNameHe AS contactFullNameHe   -- איש קשר
                FROM burials b
                LEFT JOIN graves_view gv ON b.graveId = gv.unicId
                LEFT JOIN customers cust1 ON b.clientId = cust1.unicId
                LEFT JOIN customers cust2 ON b.contactId = cust2.unicId
                WHERE b.isActive = 1
            ";

            $params = [];

            // ✅ חיפוש - שימוש באליאסים הנכונים
            if ($query) {
                $sql .= " AND (
                    b.id LIKE :query1 OR
                    b.serialBurialId LIKE :query2 OR
                    cust1.firstName LIKE :query3 OR
                    cust1.lastName LIKE :query4 OR
                    cust1.numId LIKE :query5 OR
                    gv.graveNameHe LIKE :query6
                )";
                $searchTerm = "%$query%";
                $params['query1'] = $searchTerm;
                $params['query2'] = $searchTerm;
                $params['query3'] = $searchTerm;
                $params['query4'] = $searchTerm;
                $params['query5'] = $searchTerm;
                $params['query6'] = $searchTerm;
            }
            
            // סינון לפי סטטוס
            if ($status) {
                $sql .= " AND b.burialStatus = :status";
                $params['status'] = $status;
            }
            
            // סינון לפי לקוח
            if ($customer_id) {
                $sql .= " AND b.clientId = :customer_id";
                $params['customer_id'] = $customer_id;
            }
            
            // ✅ ספירת סה"כ תוצאות
            $countSql = preg_replace('/SELECT\s+.*?\s+FROM/s', 'SELECT COUNT(*) FROM', $sql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // רשימת עמודות מותרות למיון
            $allowedSortColumns = ['createDate', 'dateBurial', 'dateDeath', 'burialStatus', 'id', 'serialBurialId'];
            if (!in_array($sort, $allowedSortColumns)) {
                $sort = 'createDate';
            }
            
            // בדיקת כיוון המיון
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY b.$sort $order LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $burials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // הוסף תאימות לאחור
            foreach ($burials as &$burial) {
                $burial['burial_date'] = $burial['dateBurial'];
                $burial['death_date'] = $burial['dateDeath'];
                $burial['burial_number'] = $burial['serialBurialId'];
                $burial['burial_status'] = $burial['burialStatus'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $burials,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        // קבלת קבורה בודדת
        case 'get':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    b.*,
                    c.firstName, c.lastName, c.numId, c.phone, c.phoneMobile,
                    g.graveNameHe, g.graveLocation, g.graveStatus,
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
                throw new Exception('Burial not found');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $burial
            ]);
            break;
        
        // יצירת קבורה חדשה
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // אימות שדות חובה
            $required = ['clientId', 'graveId', 'dateDeath', 'dateBurial', 'timeBurial'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("השדה $field הוא חובה");
                }
            }
            
            // בדוק אם הקבר תפוס
            $stmt = $pdo->prepare("SELECT graveStatus FROM graves WHERE unicId = :id");
            $stmt->execute(['id' => $data['graveId']]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            if ($grave['graveStatus'] == 3) {
                throw new Exception('הקבר תפוס - לא ניתן לבצע קבורה');
            }
            
            // יצירת unicId
            $unicId = uniqid('burial_', true);
            
            // הכנת נתונים
            $serialBurialId = $data['serialBurialId'] ?? 'B-' . date('Ymd') . '-' . rand(1000, 9999);
            $placeDeath = $data['placeDeath'] ?? '';
            $nationalInsurance = $data['nationalInsuranceBurial'] ?? 'לא';
            $deathAbroad = $data['deathAbroad'] ?? 'לא';
            $comment = $data['comment'] ?? '';
            $timeDeath = $data['timeDeath'] ?? null;
            
            // הכנסה למסד הנתונים
            $stmt = $pdo->prepare("
                INSERT INTO burials (
                    unicId, clientId, graveId, purchaseId, serialBurialId,
                    dateDeath, timeDeath, dateBurial, timeBurial,
                    placeDeath, nationalInsuranceBurial, deathAbroad,
                    savedGravesList, documentsList, historyList,
                    comment,
                    createDate, updateDate, isActive
                ) VALUES (
                    :unicId, :clientId, :graveId, :purchaseId, :serialBurialId,
                    :dateDeath, :timeDeath, :dateBurial, :timeBurial,
                    :placeDeath, :nationalInsurance, :deathAbroad,
                    '[]', '[]', '[]',
                    :comment,
                    NOW(), NOW(), 1
                )
            ");
            
            $stmt->execute([
                'unicId' => $unicId,
                'clientId' => $data['clientId'],
                'graveId' => $data['graveId'],
                'purchaseId' => $data['purchaseId'] ?? null,
                'serialBurialId' => $serialBurialId,
                'dateDeath' => $data['dateDeath'],
                'timeDeath' => $timeDeath,
                'dateBurial' => $data['dateBurial'],
                'timeBurial' => $data['timeBurial'],
                'placeDeath' => $placeDeath,
                'nationalInsurance' => $nationalInsurance,
                'deathAbroad' => $deathAbroad,
                'comment' => $comment
            ]);
            
            // עדכן סטטוס הקבר לתפוס (3)
            $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 3 WHERE unicId = :id");
            $stmt->execute(['id' => $data['graveId']]);
            
            // עדכן סטטוס הלקוח לנפטר (3)
            $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 3 WHERE unicId = :id");
            $stmt->execute(['id' => $data['clientId']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה נוצרה בהצלחה',
                'id' => $unicId
            ]);
            break;
        
        // עדכון קבורה
        case 'update':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // שליפת הקבורה הקיימת
            $stmt = $pdo->prepare("SELECT * FROM burials WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                throw new Exception('הקבורה לא נמצאה');
            }
            
            // הכנת שדות לעדכון
            $updates = [];
            $params = ['id' => $id];
            
            $allowedFields = [
                'dateDeath', 'timeDeath', 'dateBurial', 'timeBurial',
                'placeDeath', 'nationalInsuranceBurial', 'deathAbroad',
                'comment'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                throw new Exception('אין שדות לעדכון');
            }
            
            // הוסף תאריך עדכון
            $updates[] = "updateDate = NOW()";
            
            // ביצוע העדכון
            $sql = "UPDATE burials SET " . implode(', ', $updates) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה עודכנה בהצלחה'
            ]);
            break;
        
        case 'delete':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            // קבלת פרטי הקבורה
            $stmt = $pdo->prepare("SELECT graveId, clientId FROM burials WHERE id = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$burial) {
                throw new Exception('הקבורה לא נמצאה');
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare("UPDATE burials SET isActive = 0, inactiveDate = :date WHERE id = :id");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            // עדכון סטטוס הקבר - בדוק אם יש רכישה פעילה
            if ($burial['graveId']) {
                // בדוק אם יש רכישה פעילה לקבר זה
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE graveId = :graveId AND isActive = 1");
                $stmt->execute(['graveId' => $burial['graveId']]);
                $hasPurchase = $stmt->fetchColumn() > 0;

                if ($hasPurchase) {
                    // יש רכישה - חזרה לנרכש (2)
                    $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 2 WHERE unicId = :id");
                    $stmt->execute(['id' => $burial['graveId']]);
                } else {
                    // אין רכישה - חזרה לפנוי (1)
                    $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = :id");
                    $stmt->execute(['id' => $burial['graveId']]);
                }
            }
            
            // בדוק אם ללקוח יש קבורות אחרות
            if ($burial['clientId']) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM burials 
                    WHERE clientId = :clientId AND id != :burialId AND isActive = 1
                ");
                $stmt->execute(['clientId' => $burial['clientId'], 'burialId' => $id]);
                
                // אם אין לו קבורות אחרות, החזר אותו לסטטוס רוכש (2)
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 2 WHERE unicId = :id");
                    $stmt->execute(['id' => $burial['clientId']]);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה נמחקה בהצלחה'
            ]);
            break;
            
        // סטטיסטיקות קבורות
        case 'stats':
            $stats = [];

            try {
                // סה"כ קבורות לפי סטטוס
                $stmt = $pdo->query("
                    SELECT burialStatus, COUNT(*) as count
                    FROM burials
                    WHERE isActive = 1
                    GROUP BY burialStatus
                ");
                $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $stats['by_status'] = [];
            }

            try {
                // קבורות החודש
                $stmt = $pdo->query("
                    SELECT COUNT(*) as count
                    FROM burials
                    WHERE isActive = 1
                    AND MONTH(dateBurial) = MONTH(CURRENT_DATE())
                    AND YEAR(dateBurial) = YEAR(CURRENT_DATE())
                ");
                $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $stats['this_month'] = ['count' => 0];
            }

            try {
                // קבורות השנה
                $stmt = $pdo->query("
                    SELECT COUNT(*) as count
                    FROM burials
                    WHERE isActive = 1
                    AND YEAR(dateBurial) = YEAR(CURRENT_DATE())
                ");
                $stats['this_year'] = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $stats['this_year'] = ['count' => 0];
            }

            try {
                // קבורות לפי סוגים
                $stmt = $pdo->query("
                    SELECT
                        SUM(CASE WHEN nationalInsuranceBurial = 'כן' THEN 1 ELSE 0 END) as national_insurance,
                        SUM(CASE WHEN deathAbroad = 'כן' THEN 1 ELSE 0 END) as abroad
                    FROM burials
                    WHERE isActive = 1
                ");
                $stats['by_type'] = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $stats['by_type'] = ['national_insurance' => 0, 'abroad' => 0];
            }

            try {
                // סה"כ קבורות פעילות
                $stmt = $pdo->query("
                    SELECT COUNT(*) as total_burials
                    FROM burials
                    WHERE isActive = 1
                ");
                $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $stats['totals'] = ['total_burials' => 0];
            }

            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // חיפוש מהיר לאוטוקומפליט - ✅ תוקן
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    b.id, b.serialBurialId, b.dateBurial, b.dateDeath,
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    g.graveNameHe as grave_name
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                LEFT JOIN graves g ON b.graveId = g.unicId
                WHERE b.isActive = 1 
                AND (
                    b.serialBurialId LIKE :query1 OR 
                    c.firstName LIKE :query2 OR 
                    c.lastName LIKE :query3 OR
                    c.numId LIKE :query4 OR
                    g.graveNameHe LIKE :query5
                )
                LIMIT 10
            ");
            $searchTerm = "%$query%";
            $stmt->execute([
                'query1' => $searchTerm,
                'query2' => $searchTerm,
                'query3' => $searchTerm,
                'query4' => $searchTerm,
                'query5' => $searchTerm
            ]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $results]);
            break;
            
        // קבלת קבורה לפי קבר
        case 'getByGrave':
            $graveId = $_GET['graveId'] ?? null;
            if (!$graveId) {
                throw new Exception('Grave ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*, 
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    g.graveNameHe as grave_name
                FROM burials b
                INNER JOIN customers c ON b.clientId = c.unicId
                INNER JOIN graves g ON b.graveId = g.unicId
                WHERE b.graveId = :graveId 
                AND b.isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['graveId' => $graveId]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $burial
            ]);
            break;

        // קבלת קבורה לפי לקוח
        case 'getByCustomer':
            $customerId = $_GET['customerId'] ?? null;
            if (!$customerId) {
                throw new Exception('Customer ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*, 
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    g.graveNameHe as grave_name
                FROM burials b
                INNER JOIN customers c ON b.clientId = c.unicId
                INNER JOIN graves g ON b.graveId = g.unicId
                WHERE b.clientId = :customerId 
                AND b.isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['customerId' => $customerId]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $burial
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