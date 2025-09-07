<?php
// cemetery_dashboard/index.php
require_once 'dashboards/cemeteries/config.php';
require_once 'dashboards/cemeteries/includes/functions.php';

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
    
    <!-- CSS Files - כל הקבצים כולל החדשים -->
    <link rel="stylesheet" href="dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/dashboard.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/sidebar.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/header.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/tables.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/forms.css">
    <link rel="stylesheet" href="dashboards/cemeteries/css/cards.css">
</head>
<body>
    <!-- SVG Icons - חייב להיות בתחילת ה-body -->
    <svg style="display: none;">
        <!-- Refresh Icon -->
        <symbol id="icon-refresh" viewBox="0 0 24 24">
            <path d="M4 12a8 8 0 0 1 14.93-4H15.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h5a.5.5 0 0 0 .5-.5v-5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v2.22A10 10 0 1 0 22 12h-2a8 8 0 1 1-16 0z" fill="currentColor"/>
        </symbol>
        
        <!-- Hamburger Menu -->
        <symbol id="icon-menu" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
        </symbol>
        
        <!-- Close -->
        <symbol id="icon-close" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/>
        </symbol>
        
        <!-- Plus -->
        <symbol id="icon-plus" viewBox="0 0 24 24">
            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        
        <!-- Edit -->
        <symbol id="icon-edit" viewBox="0 0 24 24">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/>
        </symbol>
        
        <!-- Delete -->
        <symbol id="icon-delete" viewBox="0 0 24 24">
            <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        
        <!-- Download -->
        <symbol id="icon-download" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5l5 5l5-5m-5 5V3"/>
        </symbol>
        
        <!-- Fullscreen -->
        <symbol id="icon-fullscreen" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
        </symbol>
        
        <!-- Expand/Collapse -->
        <symbol id="icon-expand" viewBox="0 0 24 24">
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        
        <!-- Search -->
        <symbol id="icon-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" fill="none"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
    </svg>

    <div class="dashboard-wrapper">
        <!-- Header -->
        <?php include 'dashboards/cemeteries/includes/header.php'; ?>
        
        <div class="dashboard-container">
            <!-- Sidebar -->
            <?php include 'dashboards/cemeteries/includes/sidebar.php'; ?>
            
            <!-- Overlay for mobile sidebar -->
            <div id="sidebarOverlay" class="sidebar-overlay"></div>
            
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
                            הוספה
                        </button>
                    </div>
                </div>
                
                <!-- Table Container -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="data-table" id="mainTable">
                            <thead>
                                <tr id="tableHeaders">
                                    <th>טוען...</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="6" class="text-center">טוען נתונים...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Entity cards for mobile will be generated here -->
                    <div class="entity-cards" id="entityCards"></div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modals -->
    <?php include 'dashboards/cemeteries/includes/modals.php'; ?>
    
    <!-- JavaScript Files - כולל הקובץ החדש לרספונסיביות -->
    <script src="dashboards/cemeteries/js/main.js"></script>
    <script src="dashboards/cemeteries/js/cards.js"></script>
    <!-- <script src="dashboards/cemeteries/js/hierarchy-cards.js"></script> -->
    <script src="dashboards/cemeteries/js/responsive.js"></script>
    <script src="dashboards/cemeteries/js/main-cemeteries.js"></script>
    <script src="dashboards/cemeteries/js/main-blocks.js"></script>
    <script src="dashboards/cemeteries/js/main-plots.js"></script>
    <script src="dashboards/cemeteries/js/main-area-graves.js"></script>
    <script src="dashboards/cemeteries/js/main-graves.js"></script>
    <script src="dashboards/cemeteries/js/hierarchy.js"></script>
    <script src="dashboards/cemeteries/js/customers.js"></script>
    <script src="dashboards/cemeteries/js/purchases.js"></script>
    <script src="dashboards/cemeteries/js/burials.js"></script>
    
    <script>
        // Initialize dashboard on load
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
            // Initialize responsive features
            if (typeof initializeEntityItems === 'function') {
                initializeEntityItems();
            }
            if (typeof handleTableResponsive === 'function') {
                handleTableResponsive();
            }
        });
    </script>
</body>
</html>