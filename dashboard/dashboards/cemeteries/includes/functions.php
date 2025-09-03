<?php
// dashboard/dashboards/cemeteries/includes/functions.php
// פונקציות עזר לדשבורד בתי עלמין

/**
 * בדיקת הרשאה לפעולה
 */
function checkPermission($action, $module = 'cemetery') {
    // בדיקה בסיסית - להחליף בבדיקת הרשאות אמיתית
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // בינתיים מאשרים הכל
    return true;
}

// /**
//  * רישום פעילות בלוג
//  */
// function logActivity($action, $module, $itemId, $details = []) {
//     global $pdo;
    
//     try {
//         // בדיקה אם טבלת לוגים קיימת
//         $stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
//         if ($stmt->rowCount() == 0) {
//             // אם הטבלה לא קיימת, רק רשום ב-error log
//             error_log("[$module] $action on item #$itemId - " . json_encode($details));
//             return;
//         }
        
//         // הכנסה לטבלת לוגים
//         $stmt = $pdo->prepare("
//             INSERT INTO activity_logs (user_id, module, action, item_id, details, ip_address, created_at)
//             VALUES (:user_id, :module, :action, :item_id, :details, :ip, NOW())
//         ");
        
//         $stmt->execute([
//             'user_id' => $_SESSION['user_id'] ?? 0,
//             'module' => $module,
//             'action' => $action,
//             'item_id' => $itemId,
//             'details' => json_encode($details),
//             'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
//         ]);
//     } catch (Exception $e) {
//         error_log("Failed to log activity: " . $e->getMessage());
//     }
// }

// /**
//  * קבלת חיבור לבסיס נתונים
//  */
// function getDBConnection() {
//     global $pdo;
    
//     if ($pdo instanceof PDO) {
//         return $pdo;
//     }
    
//     // אם אין חיבור גלובלי, צור חדש
//     try {
//         // השתמש בהגדרות מהקונפיג הראשי
//         $host = defined('DB_HOST') ? DB_HOST : 'localhost';
//         $dbname = defined('DB_NAME') ? DB_NAME : '';
//         $username = defined('DB_USER') ? DB_USER : '';
//         $password = defined('DB_PASS') ? DB_PASS : '';
        
//         $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
//         $options = [
//             PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//             PDO::ATTR_EMULATE_PREPARES => false
//         ];
        
//         $pdo = new PDO($dsn, $username, $password, $options);
//         return $pdo;
        
//     } catch (PDOException $e) {
//         die("Connection failed: " . $e->getMessage());
//     }
// }

// /**
//  * ניקוי קלט מהמשתמש
//  */
// function cleanInput($data) {
//     if (is_array($data)) {
//         return array_map('cleanInput', $data);
//     }
    
//     $data = trim($data);
//     $data = stripslashes($data);
//     $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
//     return $data;
// }

// /**
//  * בדיקת תקינות אימייל
//  */
// function validateEmail($email) {
//     return filter_var($email, FILTER_VALIDATE_EMAIL);
// }

// /**
//  * בדיקת תקינות טלפון ישראלי
//  */
// function validateIsraeliPhone($phone) {
//     // הסרת תווים שאינם ספרות
//     $phone = preg_replace('/[^0-9]/', '', $phone);
    
//     // בדיקת אורך ותבנית
//     if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
//         return true;
//     }
//     if (strlen($phone) == 9 && in_array(substr($phone, 0, 1), ['5', '7', '8'])) {
//         return true;
//     }
    
//     return false;
// }

// /**
//  * בדיקת תקינות ת.ז. ישראלית
//  */
// function validateIsraeliID($id) {
//     $id = trim($id);
    
//     // הסרת תווים שאינם ספרות
//     $id = preg_replace('/[^0-9]/', '', $id);
    
//     // השלמה ל-9 ספרות עם אפסים מובילים
//     $id = str_pad($id, 9, '0', STR_PAD_LEFT);
    
//     // אלגוריתם בדיקת ת.ז.
//     $sum = 0;
//     for ($i = 0; $i < 9; $i++) {
//         $num = intval($id[$i]);
//         $num *= (($i % 2) + 1);
//         if ($num > 9) {
//             $num = intval($num / 10) + ($num % 10);
//         }
//         $sum += $num;
//     }
    
//     return ($sum % 10 == 0);
// }

// /**
//  * פורמט תאריך לעברית
//  */
// function formatHebrewDate($date, $format = 'd/m/Y') {
//     if (!$date) return '-';
    
//     try {
//         $datetime = new DateTime($date);
//         return $datetime->format($format);
//     } catch (Exception $e) {
//         return '-';
//     }
// }

// /**
//  * פורמט תאריך ושעה לעברית
//  */
// function formatHebrewDateTime($datetime, $format = 'd/m/Y H:i') {
//     if (!$datetime) return '-';
    
//     try {
//         $dt = new DateTime($datetime);
//         return $dt->format($format);
//     } catch (Exception $e) {
//         return '-';
//     }
// }

// /**
//  * חישוב גיל
//  */
// function calculateAge($birthDate, $deathDate = null) {
//     if (!$birthDate) return null;
    
//     try {
//         $from = new DateTime($birthDate);
//         $to = $deathDate ? new DateTime($deathDate) : new DateTime();
        
//         return $from->diff($to)->y;
//     } catch (Exception $e) {
//         return null;
//     }
// }

// /**
//  * יצירת מזהה ייחודי
//  */
// function generateUniqueId($prefix = '') {
//     return uniqid($prefix, true);
// }

// /**
//  * המרת מספר לפורמט כסף
//  */
// function formatMoney($amount, $currency = '₪') {
//     return $currency . ' ' . number_format($amount, 2, '.', ',');
// }

// /**
//  * קבלת שם סטטוס קבר
//  */
// function getGraveStatusName($status) {
//     if (defined('GRAVE_STATUS') && isset(GRAVE_STATUS[$status])) {
//         return GRAVE_STATUS[$status]['name'];
//     }
//     return 'לא ידוע';
// }

// /**
//  * קבלת צבע סטטוס קבר
//  */
// function getGraveStatusColor($status) {
//     if (defined('GRAVE_STATUS') && isset(GRAVE_STATUS[$status])) {
//         return GRAVE_STATUS[$status]['color'];
//     }
//     return '#6b7280';
// }

// /**
//  * קבלת שם סוג חלקה
//  */
// function getPlotTypeName($type) {
//     if (defined('PLOT_TYPES') && isset(PLOT_TYPES[$type])) {
//         return PLOT_TYPES[$type]['name'];
//     }
//     return 'לא ידוע';
// }

// /**
//  * בניית מיקום קבר מלא
//  */
// function buildGraveFullLocation($graveId) {
//     $pdo = getDBConnection();
    
//     try {
//         $stmt = $pdo->prepare("
//             SELECT 
//                 c.name as cemetery,
//                 b.name as block,
//                 p.name as plot,
//                 r.name as row,
//                 ag.name as area_grave,
//                 g.grave_number
//             FROM graves g
//             LEFT JOIN area_graves ag ON g.area_grave_id = ag.id
//             LEFT JOIN rows r ON ag.row_id = r.id
//             LEFT JOIN plots p ON r.plot_id = p.id
//             LEFT JOIN blocks b ON p.block_id = b.id
//             LEFT JOIN cemeteries c ON b.cemetery_id = c.id
//             WHERE g.id = :id
//         ");
        
//         $stmt->execute(['id' => $graveId]);
//         $data = $stmt->fetch();
        
//         if ($data) {
//             $parts = array_filter([
//                 $data['cemetery'],
//                 $data['block'],
//                 $data['plot'],
//                 $data['row'],
//                 $data['area_grave'],
//                 $data['grave_number']
//             ]);
//             return implode(' ← ', $parts);
//         }
//     } catch (Exception $e) {
//         error_log("Error building grave location: " . $e->getMessage());
//     }
    
//     return 'מיקום לא ידוע';
// }

// /**
//  * בדיקה אם קבר פנוי
//  */
// function isGraveAvailable($graveId) {
//     $pdo = getDBConnection();
    
//     try {
//         $stmt = $pdo->prepare("
//             SELECT grave_status 
//             FROM graves 
//             WHERE id = :id AND is_active = 1
//         ");
//         $stmt->execute(['id' => $graveId]);
//         $grave = $stmt->fetch();
        
//         return $grave && $grave['grave_status'] == 1;
//     } catch (Exception $e) {
//         return false;
//     }
// }

// /**
//  * בדיקה אם קבר תפוס
//  */
// function isGraveOccupied($graveId) {
//     $pdo = getDBConnection();
    
//     try {
//         // בדוק אם יש קבורה פעילה
//         $stmt = $pdo->prepare("
//             SELECT COUNT(*) 
//             FROM burials 
//             WHERE grave_id = :id AND is_active = 1
//         ");
//         $stmt->execute(['id' => $graveId]);
        
//         return $stmt->fetchColumn() > 0;
//     } catch (Exception $e) {
//         return false;
//     }
// }

// /**
//  * קבלת סטטיסטיקות כלליות
//  */
// function getDashboardStats() {
//     $pdo = getDBConnection();
//     $stats = [];
    
//     try {
//         // ספירת קברים לפי סטטוס
//         $stmt = $pdo->query("
//             SELECT 
//                 COUNT(*) as total,
//                 SUM(CASE WHEN grave_status = 1 THEN 1 ELSE 0 END) as available,
//                 SUM(CASE WHEN grave_status = 2 THEN 1 ELSE 0 END) as reserved,
//                 SUM(CASE WHEN grave_status = 3 THEN 1 ELSE 0 END) as occupied,
//                 SUM(CASE WHEN grave_status = 4 THEN 1 ELSE 0 END) as saved
//             FROM graves 
//             WHERE is_active = 1
//         ");
//         $stats['graves'] = $stmt->fetch();
        
//         // ספירת לקוחות
//         $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers WHERE is_active = 1");
//         $stats['customers'] = $stmt->fetchColumn();
        
//         // ספירת רכישות
//         $stmt = $pdo->query("SELECT COUNT(*) as total FROM purchases WHERE is_active = 1");
//         $stats['purchases'] = $stmt->fetchColumn();
        
//         // ספירת קבורות
//         $stmt = $pdo->query("SELECT COUNT(*) as total FROM burials WHERE is_active = 1");
//         $stats['burials'] = $stmt->fetchColumn();
        
//     } catch (Exception $e) {
//         error_log("Error getting dashboard stats: " . $e->getMessage());
//     }
    
//     return $stats;
// }

// /**
//  * יצירת אפשרויות לרשימה נפתחת
//  */
// function generateSelectOptions($items, $selectedId = null, $valueField = 'id', $textField = 'name') {
//     $options = '';
    
//     foreach ($items as $item) {
//         $value = is_array($item) ? $item[$valueField] : $item->$valueField;
//         $text = is_array($item) ? $item[$textField] : $item->$textField;
//         $selected = ($value == $selectedId) ? 'selected' : '';
        
//         $options .= sprintf(
//             '<option value="%s" %s>%s</option>',
//             htmlspecialchars($value),
//             $selected,
//             htmlspecialchars($text)
//         );
//     }
    
//     return $options;
// }

// /**
//  * שליחת התראה למשתמש
//  */
// function sendNotification($userId, $title, $message, $type = 'info') {
//     // TODO: implement notification system
//     error_log("Notification to user $userId: $title - $message");
// }

// /**
//  * בדיקת תקינות תאריך
//  */
// function isValidDate($date, $format = 'Y-m-d') {
//     $d = DateTime::createFromFormat($format, $date);
//     return $d && $d->format($format) === $date;
// }
?>