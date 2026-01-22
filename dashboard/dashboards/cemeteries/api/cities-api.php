<?php
 // ========================================
 // קובץ 2: dashboard/dashboards/cemeteries/api/cities-api.php
 // API לניהול ערים
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
             
         case 'create':
             $data = json_decode(file_get_contents('php://input'), true);
             
             // ולידציה
             if (empty($data['cityNameHe']) || empty($data['cityNameEn'])) {
                 throw new Exception('שם העיר בעברית ובאנגלית הם שדות חובה');
             }
             
             if (empty($data['countryId'])) {
                 throw new Exception('יש לבחור מדינה');
             }
             
             // בדיקת כפילויות באותה מדינה
             $stmt = $pdo->prepare("
                 SELECT COUNT(*) FROM cities 
                 WHERE countryId = :countryId
                 AND (cityNameHe = :nameHe OR cityNameEn = :nameEn) 
                 AND isActive = 1
             ");
             $stmt->execute([
                 'countryId' => $data['countryId'],
                 'nameHe' => $data['cityNameHe'],
                 'nameEn' => $data['cityNameEn']
             ]);
             
             if ($stmt->fetchColumn() > 0) {
                 throw new Exception('עיר עם שם זה כבר קיימת במדינה זו');
             }
             
             // יצירת unicId
             $unicId = 'CITY_' . uniqid();
             
             // הוספת העיר
             $stmt = $pdo->prepare("
                 INSERT INTO cities (
                     unicId, countryId, cityNameHe, cityNameEn, 
                     countryNameHe, createDate, updateDate, isActive
                 ) VALUES (
                     :unicId, :countryId, :cityNameHe, :cityNameEn,
                     :countryNameHe, NOW(), NOW(), 1
                 )
             ");
             
             $stmt->execute([
                 'unicId' => $unicId,
                 'countryId' => $data['countryId'],
                 'cityNameHe' => $data['cityNameHe'],
                 'cityNameEn' => $data['cityNameEn'],
                 'countryNameHe' => $data['countryNameHe'] ?? ''
             ]);
             
             echo json_encode([
                 'success' => true,
                 'message' => 'העיר נוספה בהצלחה',
                 'id' => $unicId
             ]);
             break;

         case 'update':
             if (!$id) {
                 throw new Exception('City ID is required');
             }
             
             $data = json_decode(file_get_contents('php://input'), true);
             
             // ולידציה
             if (empty($data['cityNameHe']) || empty($data['cityNameEn'])) {
                 throw new Exception('שם העיר בעברית ובאנגלית הם שדות חובה');
             }
             
             if (empty($data['countryId'])) {
                 throw new Exception('יש לבחור מדינה');
             }
             
             // בדיקת כפילויות (לא כולל את העיר הנוכחית)
             $stmt = $pdo->prepare("
                 SELECT COUNT(*) FROM cities 
                 WHERE countryId = :countryId
                 AND (cityNameHe = :nameHe OR cityNameEn = :nameEn) 
                 AND unicId != :id
                 AND isActive = 1
             ");
             $stmt->execute([
                 'countryId' => $data['countryId'],
                 'nameHe' => $data['cityNameHe'],
                 'nameEn' => $data['cityNameEn'],
                 'id' => $id
             ]);
             
             if ($stmt->fetchColumn() > 0) {
                 throw new Exception('עיר עם שם זה כבר קיימת במדינה זו');
             }
             
             // עדכון העיר
             $stmt = $pdo->prepare("
                 UPDATE cities SET 
                     countryId = :countryId,
                     cityNameHe = :cityNameHe,
                     cityNameEn = :cityNameEn,
                     countryNameHe = :countryNameHe,
                     updateDate = NOW()
                 WHERE unicId = :id
             ");
             
             $stmt->execute([
                 'countryId' => $data['countryId'],
                 'cityNameHe' => $data['cityNameHe'],
                 'cityNameEn' => $data['cityNameEn'],
                 'countryNameHe' => $data['countryNameHe'] ?? '',
                 'id' => $id
             ]);
             
             echo json_encode([
                 'success' => true,
                 'message' => 'העיר עודכנה בהצלחה'
             ]);
             break;

         case 'delete':
             if (!$id) {
                 throw new Exception('City ID is required');
             }
             
             // בדוק אם העיר משמשת במקומות אחרים (לדוגמה בלקוחות או הגדרות תושבות)
             $checkUsage = false;
             
             // בדיקה בלקוחות
             $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE cityId = :cityId AND isActive = 1");
             $stmt->execute(['cityId' => $id]);
             if ($stmt->fetchColumn() > 0) {
                 $checkUsage = true;
             }
             
             // בדיקה בהגדרות תושבות
             $stmt = $pdo->prepare("SELECT COUNT(*) FROM residency_settings WHERE cityId = :cityId AND isActive = 1");
             $stmt->execute(['cityId' => $id]);
             if ($stmt->fetchColumn() > 0) {
                 $checkUsage = true;
             }
             
             if ($checkUsage) {
                 throw new Exception('לא ניתן למחוק עיר שנמצאת בשימוש. יש להסיר קודם את כל הקישורים לעיר זו.');
             }
             
             // מחיקה רכה
             $stmt = $pdo->prepare("
                 UPDATE cities SET 
                     isActive = 0,
                     inactiveDate = NOW()
                 WHERE unicId = :id
             ");
             $stmt->execute(['id' => $id]);
             
             echo json_encode([
                 'success' => true,
                 'message' => 'העיר נמחקה בהצלחה'
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