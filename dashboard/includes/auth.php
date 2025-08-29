<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserDashboardType($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT dashboard_type 
            FROM user_permissions 
            WHERE user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['dashboard_type'] : 'default';
    } catch (Exception $e) {
        error_log("Error getting dashboard type: " . $e->getMessage());
        return 'default';
    }
}

function hasPermission($permission) {
    if (!isset($_SESSION['dashboard_type'])) {
        return false;
    }
    
    $dashboardType = $_SESSION['dashboard_type'];
    $permissions = DASHBOARD_TYPES[$dashboardType]['permissions'] ?? [];
    
    return in_array($permission, $permissions);
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        die('אין לך הרשאה לצפות בדף זה');
    }
}
?>