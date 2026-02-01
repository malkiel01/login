<?php
 // ========================================
 // קובץ 1: dashboard/dashboards/cemeteries/api/countries-api.php
 // API לניהול מדינות
 // ========================================

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';

 try {
     $pdo = getDBConnection();
 } catch(PDOException $e) {
     die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
 }

 // קבלת הפעולה
 $action = $_GET['action'] ?? '';
 $id = $_GET['id'] ?? null;

 try {
     switch ($action) {
         // רשימת כל המדינות
         case 'list':
             requireViewPermission('countries');
             $search = $_GET['search'] ?? '';
             $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
             $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 300;
             $offset = ($page - 1) * $limit;

             // ⭐ פרמטרי מיון
             $allowedSortColumns = ['countryNameHe', 'countryNameEn', 'countryCode', 'createDate'];
             $orderBy = $_GET['orderBy'] ?? 'countryNameHe';
             $sortDirection = strtoupper($_GET['sortDirection'] ?? 'ASC');
             if (!in_array($orderBy, $allowedSortColumns)) $orderBy = 'countryNameHe';
             if (!in_array($sortDirection, ['ASC', 'DESC'])) $sortDirection = 'ASC';

             // בניית השאילתה
             $sql = "SELECT * FROM countries WHERE isActive = 1";
             $params = [];
             
             // חיפוש
             if ($search) {
                 $sql .= " AND (
                     countryNameHe LIKE :search OR 
                     countryNameEn LIKE :search
                 )";
                 $params['search'] = "%$search%";
             }
             
             // ספירת סה"כ תוצאות
             $countSql = "SELECT COUNT(*) as total FROM countries WHERE isActive = 1";
             if ($search) {
                 $countSql .= " AND (countryNameHe LIKE :search OR countryNameEn LIKE :search)";
             }
             $countStmt = $pdo->prepare($countSql);
             $countStmt->execute($params);
             $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
             
             // ⭐ הוספת מיון ועימוד
             $sql .= " ORDER BY {$orderBy} {$sortDirection} LIMIT :limit OFFSET :offset";
             
             $stmt = $pdo->prepare($sql);
             foreach ($params as $key => $value) {
                 $stmt->bindValue($key, $value);
             }
             $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
             $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
             $stmt->execute();
             
             $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode([
                 'success' => true,
                 'data' => $countries,
                 'pagination' => [
                     'total' => $total,
                     'page' => $page,
                     'limit' => $limit,
                     'pages' => ceil($total / $limit)
                 ]
             ]);
             break;
             
         // קבלת מדינה בודדת
         case 'get':
             requireViewPermission('countries');
             if (!$id) {
                 throw new Exception('Country ID is required');
             }
             
             $stmt = $pdo->prepare("SELECT * FROM countries WHERE unicId = :id AND isActive = 1");
             $stmt->execute(['id' => $id]);
             $country = $stmt->fetch(PDO::FETCH_ASSOC);
             
             if (!$country) {
                 throw new Exception('Country not found');
             }
             
             // הוספת מספר הערים במדינה
             $stmt = $pdo->prepare("SELECT COUNT(*) as cities_count FROM cities WHERE countryId = :countryId AND isActive = 1");
             $stmt->execute(['countryId' => $country['unicId']]);
             $country['cities_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['cities_count'];
             
             echo json_encode(['success' => true, 'data' => $country]);
             break;
             
         // חיפוש מהיר (לאוטוקומפליט)
         case 'search':
             $query = $_GET['q'] ?? '';
             if (strlen($query) < 2) {
                 echo json_encode(['success' => true, 'data' => []]);
                 break;
             }
             
             $stmt = $pdo->prepare("
                 SELECT unicId, countryNameHe, countryNameEn
                 FROM countries 
                 WHERE isActive = 1 
                 AND (
                     countryNameHe LIKE :query OR 
                     countryNameEn LIKE :query
                 )
                 ORDER BY countryNameHe ASC
                 LIMIT 20
             ");
             $stmt->execute(['query' => "%$query%"]);
             $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode(['success' => true, 'data' => $results]);
             break;
             
         // רשימה פשוטה לסלקט
         case 'select':
             $stmt = $pdo->query("
                 SELECT unicId, countryNameHe 
                 FROM countries 
                 WHERE isActive = 1 
                 ORDER BY countryNameHe ASC
             ");
             $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode(['success' => true, 'data' => $countries]);
             break;
          case 'create':
             requireCreatePermission('countries');
             $data = json_decode(file_get_contents('php://input'), true);

             // ולידציה
             if (empty($data['countryNameHe']) || empty($data['countryNameEn'])) {
                 throw new Exception('שם המדינה בעברית ובאנגלית הם שדות חובה');
             }
             
             // בדיקת כפילויות
             $stmt = $pdo->prepare("
                 SELECT COUNT(*) FROM countries 
                 WHERE (countryNameHe = :nameHe OR countryNameEn = :nameEn) 
                 AND isActive = 1
             ");
             $stmt->execute([
                 'nameHe' => $data['countryNameHe'],
                 'nameEn' => $data['countryNameEn']
             ]);
             
             if ($stmt->fetchColumn() > 0) {
                 throw new Exception('מדינה עם שם זה כבר קיימת במערכת');
             }
             
             // יצירת unicId
             $unicId = 'COUNTRY_' . uniqid();
             
             // הוספת המדינה
             $stmt = $pdo->prepare("
                 INSERT INTO countries (
                     unicId, countryNameHe, countryNameEn, 
                     createDate, updateDate, isActive
                 ) VALUES (
                     :unicId, :countryNameHe, :countryNameEn,
                     NOW(), NOW(), 1
                 )
             ");
             
             $stmt->execute([
                 'unicId' => $unicId,
                 'countryNameHe' => $data['countryNameHe'],
                 'countryNameEn' => $data['countryNameEn']
             ]);
             
             echo json_encode([
                 'success' => true,
                 'message' => 'המדינה נוספה בהצלחה',
                 'id' => $unicId
             ]);
             break;

         case 'update':
             requireEditPermission('countries');
             if (!$id) {
                 throw new Exception('Country ID is required');
             }

             $data = json_decode(file_get_contents('php://input'), true);

             // ולידציה
             if (empty($data['countryNameHe']) || empty($data['countryNameEn'])) {
                 throw new Exception('שם המדינה בעברית ובאנגלית הם שדות חובה');
             }
             
             // בדיקת כפילויות (לא כולל את המדינה הנוכחית)
             $stmt = $pdo->prepare("
                 SELECT COUNT(*) FROM countries 
                 WHERE (countryNameHe = :nameHe OR countryNameEn = :nameEn) 
                 AND unicId != :id
                 AND isActive = 1
             ");
             $stmt->execute([
                 'nameHe' => $data['countryNameHe'],
                 'nameEn' => $data['countryNameEn'],
                 'id' => $id
             ]);
             
             if ($stmt->fetchColumn() > 0) {
                 throw new Exception('מדינה עם שם זה כבר קיימת במערכת');
             }
             
             // עדכון המדינה
             $stmt = $pdo->prepare("
                 UPDATE countries SET 
                     countryNameHe = :countryNameHe,
                     countryNameEn = :countryNameEn,
                     updateDate = NOW()
                 WHERE unicId = :id
             ");
             
             $stmt->execute([
                 'countryNameHe' => $data['countryNameHe'],
                 'countryNameEn' => $data['countryNameEn'],
                 'id' => $id
             ]);
             
             echo json_encode([
                 'success' => true,
                 'message' => 'המדינה עודכנה בהצלחה'
             ]);
             break;

         case 'delete':
             requireDeletePermission('countries');
             if (!$id) {
                 throw new Exception('Country ID is required');
             }

             // בדיקה אם יש ערים במדינה
             $stmt = $pdo->prepare("
                 SELECT COUNT(*) FROM cities 
                 WHERE countryId = :countryId 
                 AND isActive = 1
             ");
             $stmt->execute(['countryId' => $id]);
             
             if ($stmt->fetchColumn() > 0) {
                 throw new Exception('לא ניתן למחוק מדינה שיש בה ערים. יש למחוק קודם את הערים.');
             }
             
             // מחיקה רכה
             $stmt = $pdo->prepare("
                 UPDATE countries SET 
                     isActive = 0,
                     inactiveDate = NOW()
                 WHERE unicId = :id
             ");
             $stmt->execute(['id' => $id]);
             
             echo json_encode([
                 'success' => true,
                 'message' => 'המדינה נמחקה בהצלחה'
             ]);
             break;

         case 'save':
             // נתב ל-create או update בהתאם לנוכחות unicId
             $data = json_decode(file_get_contents('php://input'), true);
             
             if (!empty($data['unicId'])) {
                 $_GET['id'] = $data['unicId'];
                 $_GET['action'] = 'update';
                 include __FILE__;
             } else {
                 $_GET['action'] = 'create';
                 include __FILE__;
             }
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