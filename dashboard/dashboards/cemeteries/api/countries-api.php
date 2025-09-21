<?php
 // ========================================
 // קובץ 1: dashboard/dashboards/cemeteries/api/countries-api.php
 // API לניהול מדינות
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

 try {
     switch ($action) {
         // רשימת כל המדינות
         case 'list':
             $search = $_GET['search'] ?? '';
             $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
             $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
             $offset = ($page - 1) * $limit;
             
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
             
             // הוספת מיון ועימוד
             $sql .= " ORDER BY countryNameHe ASC LIMIT :limit OFFSET :offset";
             
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