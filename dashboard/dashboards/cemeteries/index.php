<?php
// cemetery_dashboard/index.php
require_once 'dashboards/cemeteries/config.php';
// require_once 'dashboards/cemeteries/includes/functions.php';

// בדיקת הרשאות
if (!checkPermission('view', 'cemetery')) {
    die('אין לך הרשאה לצפות בעמוד זה');
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo DASHBOARD_NAME; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/dashboard.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/tables.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/forms.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Header -->
        <?php include 'dashboards/cemeteries/includes/header.php'; ?>
        
        <div class="dashboard-container">
            <!-- Sidebar -->
            <?php include 'dashboards/cemeteries/includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="main-content">
                <!-- Action Bar -->
                <div class="action-bar">
                    <div class="breadcrumb" id="breadcrumb">
                        <span class="breadcrumb-item">ראשי</span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn btn-secondary" onclick="refreshData()">
                            <svg class="icon"><use xlink:href="#icon-refresh"></use></svg>
                            רענון
                        </button>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <svg class="icon"><use xlink:href="#icon-plus"></use></svg>
                            הוסף חדש
                        </button>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid" id="statsGrid">
                    <!-- Will be populated by JS -->
                </div>
                
                <!-- Data Table -->
                <div class="table-container">
                    <table class="data-table" id="dataTable">
                        <thead>
                            <tr id="tableHeaders">
                                <!-- Headers will be populated by JS -->
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Data will be populated by JS -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination" id="pagination">
                    <!-- Will be populated by JS -->
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modals -->
    <!-- < ?php include 'dashboards/cemeteries/includes/modals.php'; ?> -->
    
    <!-- SVG Icons -->
    <svg style="display: none;">
        <symbol id="icon-refresh" viewBox="0 0 24 24">
            <path d="M4 12a8 8 0 0 1 8-8v2a6 6 0 0 0 0 12v2a8 8 0 0 1-8-8z"/>
            <path d="M12 4V2l3 3-3 3v-2a6 6 0 0 0 0 12v2l3-3-3-3v2a8 8 0 0 1 0-16z"/>
        </symbol>
        <symbol id="icon-plus" viewBox="0 0 24 24">
            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        <symbol id="icon-edit" viewBox="0 0 24 24">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/>
        </symbol>
        <symbol id="icon-delete" viewBox="0 0 24 24">
            <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
    </svg>
    
    <!-- JavaScript Files -->
    <script src="dashboards/cemeteries/js/main.js"></script>
    <script src="dashboards/cemeteries/js/hierarchy.js"></script>
    <script src="dashboards/cemeteries/js/customers.js"></script>
    <script src="dashboards/cemeteries/js/purchases.js"></script>
    <script src="dashboards/cemeteries/js/burials.js"></script>
    
    <script>
        // Initialize dashboard on load
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
        });
    </script>
</body>
</html>