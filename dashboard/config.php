<?php
// Dashboard Configuration
define('DASHBOARD_VERSION', '1.0.0');
define('DASHBOARD_PATH', __DIR__);
define('DASHBOARD_URL', '/dashboard');

// Database Configuration (inherit from main config)
if (file_exists('../config.php')) {
    require_once '../config.php';
}

// Dashboard Types
define('DASHBOARD_TYPES', [
    'admin' => [
        'name' => 'דשבורד מנהל',
        'icon' => '👨‍💼',
        'color' => '#667eea',
        'permissions' => ['view_all', 'edit_all', 'delete_all', 'manage_users']
    ],
    'manager' => [
        'name' => 'דשבורד מנהל צוות',
        'icon' => '📈',
        'color' => '#11998e',
        'permissions' => ['view_team', 'edit_team', 'reports']
    ],
    'employee' => [
        'name' => 'דשבורד עובד',
        'icon' => '💼',
        'color' => '#FC466B',
        'permissions' => ['view_own', 'edit_own']
    ],
    'client' => [
        'name' => 'דשבורד לקוח',
        'icon' => '🏢',
        'color' => '#FDBB2D',
        'permissions' => ['view_projects', 'view_reports']
    ],
    'default' => [
        'name' => 'דשבורד בסיסי',
        'icon' => '🏠',
        'color' => '#e5e7eb',
        'permissions' => ['view_basic']
    ]
]);
?>