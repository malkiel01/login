<?php
class DashboardRouter {
    private $dashboardType;
    private $allowedTypes = ['admin', 'manager', 'employee', 'client', 'default'];
    
    public function __construct($type) {
        $this->dashboardType = in_array($type, $this->allowedTypes) ? $type : 'default';
    }
    
    public function route() {
        $dashboardFile = __DIR__ . '/dashboards/' . $this->dashboardType . '.php';
        
        if (file_exists($dashboardFile)) {
            require_once $dashboardFile;
        } else {
            require_once __DIR__ . '/dashboards/default.php';
        }
    }
}
?>