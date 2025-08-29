<?php
 /**
  * Permission Manager
  * מנהל הרשאות מרכזי
  */

 class PermissionManager {
     private $permissions = [];
     private $roles = [];
     private $userPermissions = [];
     
     /**
      * Constructor
      */
     public function __construct($config = []) {
         $this->permissions = $config;
         $this->loadRoles();
     }
     
     /**
      * טעינת תפקידים
      */
     private function loadRoles() {
         $rolesFile = __DIR__ . '/../config/roles.json';
         if (file_exists($rolesFile)) {
             $this->roles = json_decode(file_get_contents($rolesFile), true);
         }
     }
     
     /**
      * קבלת הרשאות לפי תפקיד
      */
     public function getPermissionsForRole($role) {
         if (!isset($this->roles[$role])) {
             return $this->getDefaultPermissions();
         }
         
         return $this->roles[$role]['permissions'] ?? [];
     }
     
     /**
      * בדיקת הרשאה
      */
     public function hasPermission($permission, $role = null) {
         if ($role === null && isset($_SESSION['user_role'])) {
             $role = $_SESSION['user_role'];
         }
         
         $permissions = $this->getPermissionsForRole($role);
         return in_array($permission, $permissions);
     }
     
     /**
      * בדיקת הרשאות מרובות
      */
     public function hasAllPermissions($permissions, $role = null) {
         foreach ($permissions as $permission) {
             if (!$this->hasPermission($permission, $role)) {
                 return false;
             }
         }
         return true;
     }
     
     /**
      * בדיקת הרשאה אחת לפחות
      */
     public function hasAnyPermission($permissions, $role = null) {
         foreach ($permissions as $permission) {
             if ($this->hasPermission($permission, $role)) {
                 return true;
             }
         }
         return false;
     }
     
     /**
      * קבלת הרשאות ברירת מחדל
      */
     private function getDefaultPermissions() {
         return [
             'view_dashboard',
             'view_profile',
             'edit_own_profile'
         ];
     }
     
     /**
      * הוספת הרשאה דינמית
      */
     public function grantPermission($userId, $permission) {
         if (!isset($this->userPermissions[$userId])) {
             $this->userPermissions[$userId] = [];
         }
         
         if (!in_array($permission, $this->userPermissions[$userId])) {
             $this->userPermissions[$userId][] = $permission;
             
             // שמירה במסד נתונים
             $db = DatabaseManager::getInstance();
             $db->grantUserPermission($userId, $permission);
         }
     }
     
     /**
      * הסרת הרשאה
      */
     public function revokePermission($userId, $permission) {
         if (isset($this->userPermissions[$userId])) {
             $key = array_search($permission, $this->userPermissions[$userId]);
             if ($key !== false) {
                 unset($this->userPermissions[$userId][$key]);
                 
                 // הסרה ממסד נתונים
                 $db = DatabaseManager::getInstance();
                 $db->revokeUserPermission($userId, $permission);
             }
         }
     }
     
     /**
      * בדיקת תפקיד
      */
     public function hasRole($role, $userId = null) {
         if ($userId === null && isset($_SESSION['user_id'])) {
             $userId = $_SESSION['user_id'];
         }
         
         $db = DatabaseManager::getInstance();
         $user = $db->getUserById($userId);
         
         return $user['role'] === $role;
     }
     
     /**
      * החלפת תפקיד
      */
     public function changeRole($userId, $newRole) {
         if (!isset($this->roles[$newRole])) {
             throw new Exception("Role '$newRole' does not exist");
         }
         
         $db = DatabaseManager::getInstance();
         $db->updateUserRole($userId, $newRole);
         
         // עדכון session אם זה המשתמש הנוכחי
         if ($userId == $_SESSION['user_id']) {
             $_SESSION['user_role'] = $newRole;
             $_SESSION['permissions'] = $this->getPermissionsForRole($newRole);
         }
     }
 }
?>