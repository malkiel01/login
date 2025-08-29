<?php
 /**
  * User Functions
  * פונקציות למשתמשים רגילים
  */

 class UserFunctions {
     
     /**
      * עדכון פרופיל אישי
      */
     public static function updateProfile($userId, $data) {
         // וידוא שהמשתמש מעדכן רק את הפרופיל שלו
         if ($userId != $_SESSION['user_id']) {
             throw new Exception('Unauthorized');
         }
         
         $db = DatabaseManager::getInstance();
         $security = new SecurityManager();
         
         $updates = [];
         $params = [];
         
         // שדות מותרים לעדכון
         $allowedFields = ['name', 'email', 'phone', 'bio', 'avatar'];
         
         foreach ($allowedFields as $field) {
             if (isset($data[$field])) {
                 $updates[] = "$field = ?";
                 $params[] = $security->sanitizeInput($data[$field]);
             }
         }
         
         // עדכון סיסמה
         if (isset($data['password']) && !empty($data['password'])) {
             // בדיקת סיסמה ישנה
             if (empty($data['current_password'])) {
                 throw new Exception('Current password is required');
             }
             
             $user = $db->getUserById($userId);
             if (!$security->verifyPassword($data['current_password'], $user['password'])) {
                 throw new Exception('Current password is incorrect');
             }
             
             // ולידציה של סיסמה חדשה
             $passwordValidation = $security->validatePassword($data['password']);
             if ($passwordValidation !== true) {
                 throw new Exception(implode(', ', $passwordValidation));
             }
             
             $updates[] = 'password = ?';
             $params[] = $security->hashPassword($data['password']);
         }
         
         if (empty($updates)) {
             return false;
         }
         
         $updates[] = 'updated_at = NOW()';
         $params[] = $userId;
         
         $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
         $db->query($sql, $params);
         
         // רישום פעילות
         $db->logActivity($userId, 'update_profile', [
             'fields' => array_keys($data)
         ]);
         
         return true;
     }
     
     /**
      * העלאת תמונת פרופיל
      */
     public static function uploadAvatar($userId, $file) {
         if ($userId != $_SESSION['user_id']) {
             throw new Exception('Unauthorized');
         }
         
         // בדיקת קובץ
         if ($file['error'] !== UPLOAD_ERR_OK) {
             throw new Exception('Upload failed');
         }
         
         // בדיקת סוג קובץ
         $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
         if (!in_array($file['type'], $allowedTypes)) {
             throw new Exception('Invalid file type');
         }
         
         // בדיקת גודל
         if ($file['size'] > 5 * 1024 * 1024) { // 5MB
             throw new Exception('File too large');
         }
         
         // יצירת שם קובץ ייחודי
         $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
         $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
         $uploadPath = UPLOAD_PATH . '/avatars/' . $filename;
         
         // יצירת תיקייה אם לא קיימת
         if (!is_dir(dirname($uploadPath))) {
             mkdir(dirname($uploadPath), 0755, true);
         }
         
         // העלאת הקובץ
         if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
             throw new Exception('Failed to save file');
         }
         
         // עדכון במסד נתונים
         $db = DatabaseManager::getInstance();
         $sql = "UPDATE users SET avatar = ? WHERE id = ?";
         $db->query($sql, ['/uploads/avatars/' . $filename, $userId]);
         
         // מחיקת תמונה ישנה
         $user = $db->getUserById($userId);
         if (!empty($user['avatar']) && file_exists(DASHBOARD_PATH . $user['avatar'])) {
             unlink(DASHBOARD_PATH . $user['avatar']);
         }
         
         return '/uploads/avatars/' . $filename;
     }
     
     /**
      * קבלת היסטוריית התחברויות
      */
     public static function getLoginHistory($userId, $limit = 10) {
         if ($userId != $_SESSION['user_id']) {
             throw new Exception('Unauthorized');
         }
         
         $db = DatabaseManager::getInstance();
         
         $sql = "SELECT created_at, ip_address, user_agent, details 
                 FROM activity_logs 
                 WHERE user_id = ? AND action = 'login' 
                 ORDER BY created_at DESC 
                 LIMIT ?";
         
         $stmt = $db->query($sql, [$userId, $limit]);
         return $stmt->fetchAll();
     }
     
     /**
      * ייצוא נתונים אישיים (GDPR)
      */
     public static function exportPersonalData($userId) {
         if ($userId != $_SESSION['user_id']) {
             throw new Exception('Unauthorized');
         }
         
         $db = DatabaseManager::getInstance();
         
         // איסוף כל הנתונים
         $data = [
             'profile' => $db->getUserById($userId),
             'activity' => $db->getActivityLog(null, $userId),
             'login_history' => self::getLoginHistory($userId, 100)
         ];
         
         // הסרת נתונים רגישים
         unset($data['profile']['password']);
         
         // רישום פעילות
         $db->logActivity($userId, 'export_personal_data');
         
         // החזרת JSON
         header('Content-Type: application/json');
         header('Content-Disposition: attachment; filename="my_data_' . date('Y-m-d') . '.json"');
         
         echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
         exit;
     }
 }
?>