<?php
 /**
  * User Dashboard Module
  * מודול דשבורד למשתמשים רגילים
  */

 class UserDashboard {
     private $data;
     private $db;
     
     /**
      * Constructor
      */
     public function __construct($data) {
         $this->data = $data;
         $this->db = DatabaseManager::getInstance();
     }
     
     /**
      * הצגת הדשבורד
      */
     public function display() {
         // טעינת נתונים
         $profile = $this->getUserProfile();
         $stats = $this->getUserStats();
         $recentActivity = $this->getUserActivity();
         $notifications = $this->getUserNotifications();
         
         // טעינת template
         $this->render('user-dashboard', [
             'user' => $this->data['user'],
             'profile' => $profile,
             'stats' => $stats,
             'activity' => $recentActivity,
             'notifications' => $notifications
         ]);
     }
     
     /**
      * קבלת פרופיל משתמש
      */
     private function getUserProfile() {
         return $this->db->getUserById($this->data['user']['id']);
     }
     
     /**
      * קבלת סטטיסטיקות משתמש
      */
     private function getUserStats() {
         $userId = $this->data['user']['id'];
         
         return [
             'total_logins' => $this->getTotalLogins($userId),
             'last_login' => $this->data['user']['last_login'],
             'account_age' => $this->getAccountAge($userId),
             'activity_score' => $this->getActivityScore($userId)
         ];
     }
     
     /**
      * קבלת פעילות משתמש
      */
     private function getUserActivity() {
         return $this->db->getActivityLog(20, $this->data['user']['id']);
     }
     
     /**
      * קבלת התראות
      */
     private function getUserNotifications() {
         // כאן יש לממש מערכת התראות
         return [
             ['type' => 'info', 'message' => 'ברוך הבא חזרה!', 'time' => 'עכשיו'],
             ['type' => 'success', 'message' => 'הפרופיל שלך עודכן בהצלחה', 'time' => 'אתמול']
         ];
     }
     
     /**
      * חישוב סה"כ התחברויות
      */
     private function getTotalLogins($userId) {
         $sql = "SELECT COUNT(*) as count FROM activity_logs 
                 WHERE user_id = ? AND action = 'login'";
         $result = $this->db->query($sql, [$userId])->fetch();
         return $result['count'];
     }
     
     /**
      * חישוב גיל החשבון
      */
     private function getAccountAge($userId) {
         $user = $this->db->getUserById($userId);
         $created = new DateTime($user['created_at']);
         $now = new DateTime();
         $diff = $now->diff($created);
         
         if ($diff->y > 0) {
             return $diff->y . ' שנים';
         } elseif ($diff->m > 0) {
             return $diff->m . ' חודשים';
         } else {
             return $diff->d . ' ימים';
         }
     }
     
     /**
      * חישוב ציון פעילות
      */
     private function getActivityScore($userId) {
         $sql = "SELECT COUNT(*) as count FROM activity_logs 
                 WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
         $result = $this->db->query($sql, [$userId])->fetch();
         
         $score = min(100, $result['count'] * 2);
         return $score;
     }
     
     /**
      * רינדור template
      */
     private function render($template, $data) {
         extract($data);
         
         $pageTitle = 'הדשבורד שלי - ' . SITE_NAME;
         $dependencies = DependencyLoader::renderHTML('user');
         
         include TEMPLATES_PATH . '/layouts/header.php';
         include TEMPLATES_PATH . '/user/' . $template . '.php';
         include TEMPLATES_PATH . '/layouts/footer.php';
     }
 }
?>