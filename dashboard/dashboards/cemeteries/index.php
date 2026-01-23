<?php

// cemetery_dashboard/index.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/functions.php';

// בדיקת הרשאות - רק cemetery_manager או admin יכולים לגשת
requireDashboard(['cemetery_manager', 'admin']);
?>
<?php
// טען את הקונפיג תשלומים
$paymentTypesConfig = require $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/payment-types-config.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">
    
    <!-- CSS Files - כל הקבצים כולל החדשים -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/sidebar.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/header.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/cards.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/breadcrumb.css">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/smart-select.css">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/search.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/universal-search.css?v=20260122ci">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/table-manager.css">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/reports/graves-inventory-report.css">

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
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/header.php'; ?>
        
        <div class="dashboard-container">
            <!-- Sidebar -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/sidebar.php'; ?>
            
            <!-- Overlay for mobile sidebar -->
            <div id="sidebarOverlay" class="sidebar-overlay"></div>
            
            <!-- Main Content -->
            <main class="main-content">
                <!-- Breadcrumb -->
                <div class="breadcrumb" id="breadcrumb">
                    <span class="breadcrumb-item">ראשי</span>
                </div>

                <!-- Entity Title + Actions -->
                <div class="entity-title-container" id="entityTitleContainer">
                    <div class="entity-title-text">
                        <h1 class="entity-title" id="entityTitle"></h1>
                        <span class="entity-subtitle" id="entitySubtitle"></span>
                    </div>
                    <div class="action-buttons">
                        <!-- כפתור הצג חיפוש - מופיע רק כשהחיפוש מכווץ -->
                        <button class="btn-show-search" onclick="UniversalSearch.expandSearchSection(window.currentType || 'cemetery')" title="הצג חיפוש">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <span>הצג חיפוש</span>
                        </button>
                        <button class="btn-search-toggle" onclick="UniversalSearch.toggleOrFocusSearch(window.currentType || 'cemetery')" title="חיפוש">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <span>חיפוש</span>
                        </button>
                        <button class="btn btn-primary btn-add" onclick="tableRenderer.openAddModal()">
                            <svg class="icon"><use xlink:href="#icon-plus"></use></svg>
                            <span>הוספה</span>
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

    <!-- JavaScript Files - סדר קריטי! -->

    <!-- 1️⃣ בסיס -->
    <script src="/dashboard/dashboards/cemeteries/js/universal-search.js?v=20260122g"></script>
    <script src="/dashboard/dashboards/cemeteries/js/breadcrumb.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/main.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/sidebar-counts.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/clearDashboard.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/table-manager.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/universal-search-init.js"></script>

    <!-- 2️⃣ מערכת פופאפים -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-manager.js"></script>

    <!-- 3️⃣ מערכת טפסים -->
    <script src="/dashboard/dashboards/cemeteries/forms/FormValidations.js"></script>
    <script src="/dashboard/dashboards/cemeteries/forms/payment-display-manager.js"></script>
    <!-- FormHandler removed - migrated to PopupManager (2026-01-23) -->

    <!-- 4️⃣ כרטיסים -->
    <script src="/dashboard/dashboards/cemeteries/js/hierarchy-cards.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/cards.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/responsive.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/unified-table-renderer.js"></script>

    <!-- 5️⃣ OperationManager ו-Utilities (קריטי - לפני המערכת החדשה!) -->
    <script src="/dashboard/dashboards/cemeteries/js/operation-manager.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-common-utils.js"></script>

    <!-- 6️⃣ 🆕 המערכת החדשה - Entity Framework (אחרי הכל!) -->
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-config.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-state-manager.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-loader.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-renderer.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-manager.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-initializer.js"></script>

    <!-- 7️⃣ הקבצים הישנים (יישארו כ-fallback) -->
    <script src="/dashboard/dashboards/cemeteries/js/cemeteries-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/blocks-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/plots-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/area-graves-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/graves-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/customers-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/purchases-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/burials-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/payments-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/residency-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/countries-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/cities-management.js"></script>

    <script src="/dashboard/dashboards/cemeteries/js/live-search.js"></script>

    <script src="/dashboard/dashboards/cemeteries/js/reports/graves-inventory-report.js"></script>

    <!-- 🗺️ מפה -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // הגדרת worker של PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <!-- Map v2 Launcher -->
    <script src="/dashboard/dashboards/cemeteries/js/map-launcher.js"></script>

    <!-- 7️⃣ אתחול -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
            if (typeof initializeEntityItems === 'function') {
                initializeEntityItems();
            }
            if (typeof handleTableResponsive === 'function') {
                handleTableResponsive();
            }
        });
    </script>

    <script>
        window.PAYMENT_TYPES_CONFIG = <?php echo json_encode($paymentTypesConfig['payment_types']); ?>;
    </script>
    <script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>
</body>
</html>
