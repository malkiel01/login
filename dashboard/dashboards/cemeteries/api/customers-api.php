<?php
    /*
    * File: api/customers-api.php
    * Version: 2.5.0
    * Updated: 2025-10-24
    * Author: Malkiel
    * Change Summary:
    * - v2.5.0: הוספת Version Header לעקביות עם מערכת
    * - תמיכה בחישוב תושבות דינמי
    * - מנגנון חיפוש מתקדם ב-6 שדות
    * - API מלא עם pagination ו-stats
    * - פונקציות calculateResidency ו-mapResidencyTypeToValue
    */

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type');

    // חיבור לבסיס נתונים
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

    try {
        $pdo = getDBConnection();
    } catch(PDOException $e) {
        die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
    }

    // קבלת הפעולה
    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? null;


    /**
     * חישוב סוג תושבות לקוח על בסיס הגדרות התושבות
     * @param PDO $pdo
     * @param int $typeId - סוג זיהוי הלקוח (1=ת.ז, 2=דרכון, 3=אלמוני, 4=תינוק)
     * @param string|null $countryId - יוניק של המדינה
     * @param string|null $cityId - יוניק של העיר
     * @return int סוג תושבות (1=תושב העיר, 2=תושב הארץ, 3=תושב חו"ל)
     */
    function calculateResidency($pdo, $typeId, $countryId = null, $cityId = null) {
        // אם סוג הזיהוי הוא דרכון (2) - תמיד תושב חו"ל
        if ($typeId == 2) {
            return 3;
        }
        
        // אם אין מדינה - תושב חו"ל
        if (empty($countryId)) {
            return 3;
        }
        
        // בדיקה אם המדינה קיימת בהגדרות התושבות
        $stmt = $pdo->prepare("
            SELECT residencyType 
            FROM residency_settings 
            WHERE countryId = :countryId 
            AND cityId IS NULL
            AND isActive = 1
            LIMIT 1
        ");
        $stmt->execute(['countryId' => $countryId]);
        $countryResidency = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // אם המדינה לא קיימת בהגדרות - תושב חו"ל
        if (!$countryResidency) {
            return 3;
        }
        
        // אם יש עיר, בדיקה אם העיר קיימת בהגדרות
        if (!empty($cityId)) {
            $stmt = $pdo->prepare("
                SELECT residencyType 
                FROM residency_settings 
                WHERE countryId = :countryId 
                AND cityId = :cityId
                AND isActive = 1
                LIMIT 1
            ");
            $stmt->execute([
                'countryId' => $countryId,
                'cityId' => $cityId
            ]);
            $cityResidency = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // אם העיר קיימת בהגדרות - החזר את סוג התושבות שלה
            if ($cityResidency) {
                return mapResidencyTypeToValue($cityResidency['residencyType']);
            }
        }
        
        // אם העיר לא קיימת או לא הוגדרה - החזר את סוג התושבות של המדינה
        return mapResidencyTypeToValue($countryResidency['residencyType']);
    }

    /**
     * המרת סוג תושבות מטקסט למספר
     * @param string $residencyType
     * @return int
     */
    function mapResidencyTypeToValue($residencyType) {
        switch($residencyType) {
            case 'jerusalem_area':
                return 1; // תושב העיר (ירושלים והסביבה)
            case 'israel':
                return 2; // תושב הארץ
            case 'abroad':
            default:
                return 3; // תושב חו"ל
        }
    }

    try {
        switch ($action) {
            // רשימת כל הלקוחות
            case 'list':
                $search = $_GET['search'] ?? '';
                $status = $_GET['status'] ?? '';
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 999999;
                $offset = ($page - 1) * $limit;
                
                // 🆕 פילטרים מתקדמים
                $filters = [];
                
                // אוסף את כל הפילטרים מה-URL
                foreach ($_GET as $key => $value) {
                    if (strpos($key, 'filter_') === 0 && !empty($value)) {
                        // פורמט: filter_0_field, filter_0_value, filter_0_type
                        preg_match('/filter_(\d+)_(\w+)/', $key, $matches);
                        if ($matches) {
                            $index = $matches[1];
                            $type = $matches[2];
                            
                            if (!isset($filters[$index])) {
                                $filters[$index] = [];
                            }
                            
                            $filters[$index][$type] = $value;
                        }
                    }
                }
                
                // בניית השאילתה הראשית
                $sql = "
                    SELECT 
                        c.*,
                        co.countryNameHe as country_name,
                        ci.cityNameHe as city_name
                    FROM customers c
                    LEFT JOIN countries co ON c.countryId = co.unicId
                    LEFT JOIN cities ci ON c.cityId = ci.unicId
                    WHERE c.isActive = 1
                ";
                $params = [];
                
                // חיפוש כללי (search bar)
                if ($search) {
                    $sql .= " AND (
                        c.firstName LIKE :search1 OR 
                        c.lastName LIKE :search2 OR 
                        c.numId LIKE :search3 OR 
                        c.phone LIKE :search4 OR 
                        c.phoneMobile LIKE :search5 OR
                        c.fullNameHe LIKE :search6
                    )";
                    $searchTerm = "%$search%";
                    $params['search1'] = $searchTerm;
                    $params['search2'] = $searchTerm;
                    $params['search3'] = $searchTerm;
                    $params['search4'] = $searchTerm;
                    $params['search5'] = $searchTerm;
                    $params['search6'] = $searchTerm;
                }
                
                // 🆕 פילטרים מתקדמים
                foreach ($filters as $index => $filter) {
                    if (!isset($filter['field']) || !isset($filter['value'])) {
                        continue;
                    }
                    
                    $field = $filter['field'];
                    $value = $filter['value'];
                    $matchType = $filter['type'] ?? 'fuzzy';
                    
                    $paramName = "filter_{$index}";
                    
                    switch ($matchType) {
                        case 'exact':
                            $sql .= " AND c.$field = :$paramName";
                            $params[$paramName] = $value;
                            break;
                            
                        case 'fuzzy':
                            $sql .= " AND c.$field LIKE :$paramName";
                            $params[$paramName] = "%$value%";
                            break;
                            
                        case 'startsWith':
                            $sql .= " AND c.$field LIKE :$paramName";
                            $params[$paramName] = "$value%";
                            break;
                            
                        case 'endsWith':
                            $sql .= " AND c.$field LIKE :$paramName";
                            $params[$paramName] = "%$value";
                            break;
                            
                        case 'greaterThan':
                            $sql .= " AND c.$field > :$paramName";
                            $params[$paramName] = $value;
                            break;
                            
                        case 'lessThan':
                            $sql .= " AND c.$field < :$paramName";
                            $params[$paramName] = $value;
                            break;
                            
                        case 'between':
                            if (isset($filter['valueEnd'])) {
                                $sql .= " AND c.$field BETWEEN :$paramName AND :{$paramName}_end";
                                $params[$paramName] = $value;
                                $params["{$paramName}_end"] = $filter['valueEnd'];
                            }
                            break;
                            
                        case 'before':
                            $sql .= " AND c.$field < :$paramName";
                            $params[$paramName] = $value;
                            break;
                            
                        case 'after':
                            $sql .= " AND c.$field > :$paramName";
                            $params[$paramName] = $value;
                            break;
                            
                        case 'today':
                            $sql .= " AND DATE(c.$field) = CURDATE()";
                            break;
                            
                        case 'thisWeek':
                            $sql .= " AND YEARWEEK(c.$field, 1) = YEARWEEK(CURDATE(), 1)";
                            break;
                            
                        case 'thisMonth':
                            $sql .= " AND YEAR(c.$field) = YEAR(CURDATE()) AND MONTH(c.$field) = MONTH(CURDATE())";
                            break;
                            
                        case 'thisYear':
                            $sql .= " AND YEAR(c.$field) = YEAR(CURDATE())";
                            break;
                    }
                }
                
                // סינון לפי סטטוס
                if ($status !== '') {
                    $sql .= " AND c.statusCustomer = :status";
                    $params['status'] = $status;
                }
                
                // ✅ ספירת תוצאות מסוננות
                $countSql = "
                    SELECT COUNT(*) 
                    FROM customers c 
                    WHERE c.isActive = 1
                ";
                
                $countParams = [];
                
                // העתק את אותם תנאי חיפוש לספירה
                if ($search) {
                    $countSql .= " AND (
                        c.firstName LIKE :search1 OR 
                        c.lastName LIKE :search2 OR 
                        c.numId LIKE :search3 OR 
                        c.phone LIKE :search4 OR 
                        c.phoneMobile LIKE :search5 OR
                        c.fullNameHe LIKE :search6
                    )";
                    $countParams['search1'] = $searchTerm;
                    $countParams['search2'] = $searchTerm;
                    $countParams['search3'] = $searchTerm;
                    $countParams['search4'] = $searchTerm;
                    $countParams['search5'] = $searchTerm;
                    $countParams['search6'] = $searchTerm;
                }
                
                // 🆕 העתק פילטרים לספירה
                foreach ($filters as $index => $filter) {
                    if (!isset($filter['field']) || !isset($filter['value'])) {
                        continue;
                    }
                    
                    $field = $filter['field'];
                    $value = $filter['value'];
                    $matchType = $filter['type'] ?? 'fuzzy';
                    
                    $paramName = "filter_{$index}";
                    
                    switch ($matchType) {
                        case 'exact':
                            $countSql .= " AND c.$field = :$paramName";
                            $countParams[$paramName] = $value;
                            break;
                            
                        case 'fuzzy':
                            $countSql .= " AND c.$field LIKE :$paramName";
                            $countParams[$paramName] = "%$value%";
                            break;
                            
                        case 'startsWith':
                            $countSql .= " AND c.$field LIKE :$paramName";
                            $countParams[$paramName] = "$value%";
                            break;
                            
                        case 'endsWith':
                            $countSql .= " AND c.$field LIKE :$paramName";
                            $countParams[$paramName] = "%$value";
                            break;
                            
                        case 'greaterThan':
                            $countSql .= " AND c.$field > :$paramName";
                            $countParams[$paramName] = $value;
                            break;
                            
                        case 'lessThan':
                            $countSql .= " AND c.$field < :$paramName";
                            $countParams[$paramName] = $value;
                            break;
                            
                        case 'between':
                            if (isset($filter['valueEnd'])) {
                                $countSql .= " AND c.$field BETWEEN :$paramName AND :{$paramName}_end";
                                $countParams[$paramName] = $value;
                                $countParams["{$paramName}_end"] = $filter['valueEnd'];
                            }
                            break;
                            
                        case 'before':
                            $countSql .= " AND c.$field < :$paramName";
                            $countParams[$paramName] = $value;
                            break;
                            
                        case 'after':
                            $countSql .= " AND c.$field > :$paramName";
                            $countParams[$paramName] = $value;
                            break;
                            
                        case 'today':
                            $countSql .= " AND DATE(c.$field) = CURDATE()";
                            break;
                            
                        case 'thisWeek':
                            $countSql .= " AND YEARWEEK(c.$field, 1) = YEARWEEK(CURDATE(), 1)";
                            break;
                            
                        case 'thisMonth':
                            $countSql .= " AND YEAR(c.$field) = YEAR(CURDATE()) AND MONTH(c.$field) = MONTH(CURDATE())";
                            break;
                            
                        case 'thisYear':
                            $countSql .= " AND YEAR(c.$field) = YEAR(CURDATE())";
                            break;
                    }
                }
                
                if ($status !== '') {
                    $countSql .= " AND c.statusCustomer = :status";
                    $countParams['status'] = $status;
                }
                
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($countParams);
                $total = $countStmt->fetchColumn();
                
                // ✅ ספירת כל הלקוחות (ללא סינון)
                $totalAllSql = "SELECT COUNT(*) FROM customers WHERE isActive = 1";
                $totalAll = $pdo->query($totalAllSql)->fetchColumn();
                
                // הוספת מיון ועימוד
                $sql .= " ORDER BY c.createDate DESC LIMIT :limit OFFSET :offset";
                
                $stmt = $pdo->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue(":$key", $value);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $customers,
                    'pagination' => [
                        'total' => $total,
                        'totalAll' => $totalAll,
                        'page' => $page,
                        'limit' => $limit,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
                break;
            // קבלת לקוח בודד
            case 'get':
                if (!$id) {
                    throw new Exception('Customer ID is required');
                }
                
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE unicId = :id AND isActive = 1");
                $stmt->execute(['id' => $id]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$customer) {
                    throw new Exception('Customer not found');
                }
                
                // הוספת נתונים קשורים (רכישות, קבורות)
                $stmt = $pdo->prepare("
                    SELECT p.*, g.graveNameHe, g.graveLocation 
                    FROM purchases p
                    LEFT JOIN graves g ON p.graveId = g.unicId
                    WHERE p.clientId = :customer_id AND p.isActive = 1
                    ORDER BY p.dateOpening DESC
                ");

                $stmt->execute(['customer_id' => $customer['unicId']]); // שים לב - משתמשים ב-unicId של הלקוח
                $customer['purchases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $customer]);
                break;
                
            // הוספת לקוח חדש
            case 'create':
                $data = json_decode(file_get_contents('php://input'), true);
                
                // ולידציה
                if (empty($data['firstName']) || empty($data['lastName'])) {
                    throw new Exception('שם פרטי ושם משפחה הם שדות חובה');
                }
                
                // בדיקת כפל תעודת זהות
                if (!empty($data['numId'])) {
                    $stmt = $pdo->prepare("SELECT unicId FROM customers WHERE numId = :numId AND isActive = 1");
                    $stmt->execute(['numId' => $data['numId']]);
                    if ($stmt->fetch()) {
                        throw new Exception('לקוח עם תעודת זהות זו כבר קיים במערכת');
                    }
                }

                // חישוב תושבות אוטומטי - רק בהוספת לקוח חדש
                $data['resident'] = calculateResidency(
                    $pdo, 
                    $data['typeId'] ?? 3,
                    $data['countryId'] ?? null, 
                    $data['cityId'] ?? null
                );
                
                // חישוב גיל אם יש תאריך לידה
                if (!empty($data['dateBirth'])) {
                    $birthDate = new DateTime($data['dateBirth']);
                    $today = new DateTime();
                    $data['age'] = $birthDate->diff($today)->y;
                }
                
                // יצירת unicId
                $data['unicId'] = uniqid('customer_', true);
                
                // הוספת תאריכים
                $data['createDate'] = date('Y-m-d H:i:s');
                $data['updateDate'] = date('Y-m-d H:i:s');
                
                // בניית השאילתה
                $fields = [
                    'unicId', 'typeId', 'numId', 'firstName', 'lastName', 'oldName', 'nom',
                    'gender', 'nameFather', 'nameMother', 'maritalStatus', 'dateBirth',
                    'countryBirth', 'countryBirthId', 'age', 'resident', 'countryId', 'cityId', 
                    'address', 'phone', 'phoneMobile', 'statusCustomer', 'spouse', 'comment',
                    'association', 'dateBirthHe', 'tourist', 'createDate', 'updateDate'
                ];
                
                $insertFields = [];
                $insertValues = [];
                $params = [];
                
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $insertFields[] = $field;
                        $insertValues[] = ":$field";
                        $params[$field] = $data[$field];
                    }
                }
                
                $sql = "INSERT INTO customers (" . implode(', ', $insertFields) . ") 
                        VALUES (" . implode(', ', $insertValues) . ")";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $clientId = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'הלקוח נוסף בהצלחה',
                    'id' => $clientId
                ]);
                break;
                
            // עדכון לקוח
            case 'update':
                if (!$id) {
                    throw new Exception('Customer ID is required');
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                
                // ולידציה
                if (empty($data['firstName']) || empty($data['lastName'])) {
                    throw new Exception('שם פרטי ושם משפחה הם שדות חובה');
                }
                
                // בדיקת כפל תעודת זהות - רק אם השתנה
                if (!empty($data['numId'])) {
                    // קודם בדוק מה היה המספר הקודם
                    $checkStmt = $pdo->prepare("SELECT numId FROM customers WHERE unicId = :id");
                    $checkStmt->execute(['id' => $id]);
                    $currentNumId = $checkStmt->fetchColumn();
                    
                    // בדוק כפילות רק אם המספר השתנה
                    if ($currentNumId != $data['numId']) {
                        $stmt = $pdo->prepare("SELECT unicId FROM customers WHERE numId = :numId AND isActive = 1");
                        $stmt->execute(['numId' => $data['numId']]);
                        if ($stmt->fetch()) {
                            throw new Exception('לקוח עם תעודת זהות זו כבר קיים במערכת');
                        }
                    }
                }

                // חישוב גיל אם יש תאריך לידה
                if (!empty($data['dateBirth'])) {
                    $birthDate = new DateTime($data['dateBirth']);
                    $today = new DateTime();
                    $data['age'] = $birthDate->diff($today)->y;
                }
                
                // עדכון תאריך
                $data['updateDate'] = date('Y-m-d H:i:s');
                
                // בניית השאילתה
                $fields = [
                    'typeId', 'numId', 'firstName', 'lastName', 'oldName', 'nom',
                    'gender', 'nameFather', 'nameMother', 'maritalStatus', 'dateBirth',
                    'countryBirth', 'countryBirthId', 'age', 'resident', 'countryId', 'cityId',
                    'address', 'phone', 'phoneMobile', 'statusCustomer', 'spouse', 'comment',
                    'association', 'dateBirthHe', 'tourist', 'updateDate'
                ];
                
                $updateFields = [];
                $params = ['id' => $id];
                
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }
                
                if (empty($updateFields)) {
                    throw new Exception('No fields to update');
                }
                
                $sql = "UPDATE customers SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'הלקוח עודכן בהצלחה'
                ]);
                break;
                
            // מחיקת לקוח (מחיקה רכה)
            case 'delete':
                if (!$id) {
                    throw new Exception('Customer ID is required');
                }
                
                // בדיקה אם יש רכישות או קבורות קשורות
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchases WHERE clientId = :id AND isActive = 1");
                $stmt->execute(['id' => $id]);
                $purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($purchases > 0) {
                    throw new Exception('לא ניתן למחוק לקוח עם רכישות פעילות');
                }
                
                // מחיקה רכה
                $stmt = $pdo->prepare("UPDATE customers SET isActive = 0, inactiveDate = :date WHERE id = :id");
                $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'הלקוח נמחק בהצלחה'
                ]);
                break;
                
            // סטטיסטיקות לקוחות
            case 'stats':
                $stats = [];
                
                // סה"כ לקוחות לפי סטטוס
                $stmt = $pdo->query("
                    SELECT statusCustomer, COUNT(*) as count 
                    FROM customers 
                    WHERE isActive = 1 
                    GROUP BY statusCustomer
                ");
                $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // סה"כ לקוחות לפי סוג
                $stmt = $pdo->query("
                    SELECT typeId, COUNT(*) as count 
                    FROM customers 
                    WHERE isActive = 1 
                    GROUP BY typeId
                ");
                $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // לקוחות חדשים החודש
                $stmt = $pdo->query("
                    SELECT COUNT(*) as count 
                    FROM customers 
                    WHERE isActive = 1 
                    AND createDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                ");
                $stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                echo json_encode(['success' => true, 'data' => $stats]);
                break;
                
            // חיפוש מהיר (לאוטוקומפליט)
            case 'search':
                $query = $_GET['q'] ?? '';
                if (strlen($query) < 2) {
                    echo json_encode(['success' => true, 'data' => []]);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    SELECT id, firstName, lastName, numId, phone, phoneMobile
                    FROM customers 
                    WHERE isActive = 1 
                    AND (
                        firstName LIKE :query OR 
                        lastName LIKE :query OR 
                        numId LIKE :query OR
                        fullNameHe LIKE :query
                    )
                    LIMIT 10
                ");
                $stmt->execute(['query' => "%$query%"]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $results]);
                break;
            // חישוב תושבות בזמן אמת עבור הטופס   
            case 'calculate_residency':
                // חישוב תושבות בזמן אמת עבור הטופס
                $data = json_decode(file_get_contents('php://input'), true);
                
                $typeId = $data['typeId'] ?? 3;
                $countryId = $data['countryId'] ?? null;
                $cityId = $data['cityId'] ?? null;
                
                $residency = calculateResidency($pdo, $typeId, $countryId, $cityId);
                
                echo json_encode([
                    'success' => true,
                    'residency' => $residency,
                    'residency_text' => [
                        1 => 'ירושלים והסביבה',
                        2 => 'תושב חוץ',
                        3 => 'תושב חו״ל'
                    ][$residency]
                ]);
                break;
                
            // רשימת לקוחות פנויים בלבד (ללא רכישות/קבורות)
            case 'available2':
                // ✅ קבל את הלקוח הנוכחי אם קיים
                $currentClientId = $_GET['currentClientId'] ?? null;
                
                if ($currentClientId) {
                    // ✅ במצב עריכה - כלול גם את הלקוח הנוכחי
                    $sql = "
                        SELECT 
                            unicId, 
                            firstName, 
                            lastName, 
                            phone, 
                            phoneMobile, 
                            resident,
                            CASE WHEN unicId = :currentClient THEN 1 ELSE 0 END as is_current
                        FROM customers 
                        WHERE (statusCustomer = 1 OR unicId = :currentClient2)
                        AND isActive = 1 
                        ORDER BY is_current DESC, lastName, firstName
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'currentClient' => $currentClientId,
                        'currentClient2' => $currentClientId
                    ]);
                } else {
                    // ✅ במצב הוספה - רק לקוחות פנויים
                    $sql = "
                        SELECT 
                            unicId, 
                            firstName, 
                            lastName, 
                            phone, 
                            phoneMobile, 
                            resident
                        FROM customers 
                        WHERE statusCustomer = 1 
                        AND isActive = 1 
                        ORDER BY lastName, firstName
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                }
                
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $customers]);
                break;

            case 'available':
                $currentClientId = $_GET['currentClientId'] ?? null;
                $formType = $_GET['type'] ?? 'purchase';
                
                if ($currentClientId) {
                    // ✅ במצב עריכה
                    if ($formType === 'burial') {
                        // קבורה: לקוחות שאין להם קבורה + הלקוח הנוכחי
                        $sql = "
                            SELECT 
                                unicId, 
                                firstName, 
                                lastName, 
                                phone, 
                                phoneMobile, 
                                resident,
                                CASE WHEN unicId = :currentClient THEN 1 ELSE 0 END as is_current
                            FROM customers 
                            WHERE (
                                NOT EXISTS (
                                    SELECT 1 FROM burials b 
                                    WHERE b.clientId = customers.unicId 
                                    AND b.isActive = 1
                                )
                                OR unicId = :currentClient2
                            )
                            AND isActive = 1 
                            ORDER BY is_current DESC, lastName, firstName
                        ";
                    } else {
                        // רכישה: לקוחות שאין להם רכישה ואין להם קבורה + הלקוח הנוכחי
                        $sql = "
                            SELECT 
                                unicId, 
                                firstName, 
                                lastName, 
                                phone, 
                                phoneMobile, 
                                resident,
                                CASE WHEN unicId = :currentClient THEN 1 ELSE 0 END as is_current
                            FROM customers 
                            WHERE (
                                (
                                    NOT EXISTS (
                                        SELECT 1 FROM purchases p 
                                        WHERE p.clientId = customers.unicId 
                                        AND p.isActive = 1
                                    )
                                    AND NOT EXISTS (
                                        SELECT 1 FROM burials b 
                                        WHERE b.clientId = customers.unicId 
                                        AND b.isActive = 1
                                    )
                                )
                                OR unicId = :currentClient2
                            )
                            AND isActive = 1 
                            ORDER BY is_current DESC, lastName, firstName
                        ";
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'currentClient' => $currentClientId,
                        'currentClient2' => $currentClientId
                    ]);
                    
                } else {
                    // ✅ במצב הוספה
                    if ($formType === 'burial') {
                        // קבורה: לקוחות שאין להם קבורה
                        $sql = "
                            SELECT 
                                unicId, 
                                firstName, 
                                lastName, 
                                phone, 
                                phoneMobile, 
                                resident
                            FROM customers 
                            WHERE NOT EXISTS (
                                SELECT 1 FROM burials b 
                                WHERE b.clientId = customers.unicId 
                                AND b.isActive = 1
                            )
                            AND isActive = 1 
                            ORDER BY lastName, firstName
                        ";
                    } else {
                        // רכישה: לקוחות שאין להם רכישה ואין להם קבורה
                        $sql = "
                            SELECT 
                                unicId, 
                                firstName, 
                                lastName, 
                                phone, 
                                phoneMobile, 
                                resident
                            FROM customers 
                            WHERE NOT EXISTS (
                                SELECT 1 FROM purchases p 
                                WHERE p.clientId = customers.unicId 
                                AND p.isActive = 1
                            )
                            AND NOT EXISTS (
                                SELECT 1 FROM burials b 
                                WHERE b.clientId = customers.unicId 
                                AND b.isActive = 1
                            )
                            AND isActive = 1 
                            ORDER BY lastName, firstName
                        ";
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                }
                
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $customers]);
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