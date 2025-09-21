<?php
 // ========================================
 // קובץ 2: dashboard/dashboards/cemeteries/api/cities-api.php
 // API לניהול ערים
 // ========================================

 header('Content-Type: application/json; charset=utf-8');
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
 header('Access-Control-Allow-Headers: Content-Type');

 // חיבור לבסיס נתונים
 require_once dirname(__DIR__) . '/config.php';

 try {
     $pdo = getDBConnection();
 } catch(PDOException $e) {
     die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
 }

 // קבלת הפעולה
 $action = $_GET['action'] ?? '';
 $id = $_GET['id'] ?? null;
 $countryId = $_GET['countryId'] ?? null;

 try {
     switch ($action) {
         // רשימת כל הערים
         case 'list':
             $search = $_GET['search'] ?? '';
             $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
             $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
             $offset = ($page - 1) * $limit;
             
             // בניית השאילתה עם JOIN למדינות
             $sql = "
                 SELECT 
                     c.*,
                     co.countryNameHe as country_name
                 FROM cities c
                 LEFT JOIN countries co ON c.countryId = co.unicId
                 WHERE c.isActive = 1
             ";
             $params = [];
             
             // סינון לפי מדינה
             if ($countryId) {
                 $sql .= " AND c.countryId = :countryId";
                 $params['countryId'] = $countryId;
             }
             
             // חיפוש
             if ($search) {
                 $sql .= " AND (
                     c.cityNameHe LIKE :search OR 
                     c.cityNameEn LIKE :search
                 )";
                 $params['search'] = "%$search%";
             }
             
             // ספירת סה"כ תוצאות
             $countSql = "SELECT COUNT(*) as total FROM cities c WHERE c.isActive = 1";
             if ($countryId) {
                 $countSql .= " AND c.countryId = :countryId";
             }
             if ($search) {
                 $countSql .= " AND (c.cityNameHe LIKE :search OR c.cityNameEn LIKE :search)";
             }
             
             $countStmt = $pdo->prepare($countSql);
             $countStmt->execute($params);
             $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
             
             // הוספת מיון ועימוד
             $sql .= " ORDER BY c.cityNameHe ASC LIMIT :limit OFFSET :offset";
             
             $stmt = $pdo->prepare($sql);
             foreach ($params as $key => $value) {
                 $stmt->bindValue($key, $value);
             }
             $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
             $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
             $stmt->execute();
             
             $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode([
                 'success' => true,
                 'data' => $cities,
                 'pagination' => [
                     'total' => $total,
                     'page' => $page,
                     'limit' => $limit,
                     'pages' => ceil($total / $limit)
                 ]
             ]);
             break;
             
         // קבלת עיר בודדת
         case 'get':
             if (!$id) {
                 throw new Exception('City ID is required');
             }
             
             $stmt = $pdo->prepare("
                 SELECT c.*, co.countryNameHe as country_name
                 FROM cities c
                 LEFT JOIN countries co ON c.countryId = co.unicId
                 WHERE c.unicId = :id AND c.isActive = 1
             ");
             $stmt->execute(['id' => $id]);
             $city = $stmt->fetch(PDO::FETCH_ASSOC);
             
             if (!$city) {
                 throw new Exception('City not found');
             }
             
             echo json_encode(['success' => true, 'data' => $city]);
             break;
             
         // חיפוש מהיר (לאוטוקומפליט)
         case 'search':
             $query = $_GET['q'] ?? '';
             if (strlen($query) < 2) {
                 echo json_encode(['success' => true, 'data' => []]);
                 break;
             }
             
             $sql = "
                 SELECT c.unicId, c.cityNameHe, c.cityNameEn, co.countryNameHe
                 FROM cities c
                 LEFT JOIN countries co ON c.countryId = co.unicId
                 WHERE c.isActive = 1 
                 AND (
                     c.cityNameHe LIKE :query OR 
                     c.cityNameEn LIKE :query
                 )
             ";
             
             $params = ['query' => "%$query%"];
             
             // אם יש countryId, סנן לפי מדינה
             if ($countryId) {
                 $sql .= " AND c.countryId = :countryId";
                 $params['countryId'] = $countryId;
             }
             
             $sql .= " ORDER BY c.cityNameHe ASC LIMIT 20";
             
             $stmt = $pdo->prepare($sql);
             $stmt->execute($params);
             $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode(['success' => true, 'data' => $results]);
             break;
             
         // רשימה פשוטה לסלקט
         case 'select':
             $sql = "
                 SELECT unicId, cityNameHe 
                 FROM cities 
                 WHERE isActive = 1
             ";
             
             $params = [];
             
             // אם יש countryId, סנן לפי מדינה
             if ($countryId) {
                 $sql .= " AND countryId = :countryId";
                 $params['countryId'] = $countryId;
             }
             
             $sql .= " ORDER BY cityNameHe ASC";
             
             $stmt = $pdo->prepare($sql);
             $stmt->execute($params);
             $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode(['success' => true, 'data' => $cities]);
             break;
             
         // ערים לפי מדינה (תאימות אחורה)
         case 'by_country':
             if (!$countryId) {
                 // אם אין מדינה, החזר את כל הערים
                 $stmt = $pdo->query("
                     SELECT unicId, cityNameHe 
                     FROM cities 
                     WHERE isActive = 1 
                     ORDER BY cityNameHe ASC
                 ");
             } else {
                 $stmt = $pdo->prepare("
                     SELECT unicId, cityNameHe 
                     FROM cities 
                     WHERE countryId = :countryId AND isActive = 1 
                     ORDER BY cityNameHe ASC
                 ");
                 $stmt->execute(['countryId' => $countryId]);
             }
             
             $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode(['success' => true, 'data' => $cities]);
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