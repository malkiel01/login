<?php
/**
 * Map Editor v2 - עורך מפות גרסה 2
 * מערכת חדשה ונקייה לניהול מפות
 */

require_once dirname(__DIR__) . '/config.php';

// קבלת פרמטרים
$entityType = $_GET['type'] ?? '';
$entityId = $_GET['id'] ?? '';
$entityName = $_GET['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>עורך מפות v2</title>

    <!-- Fabric.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

    <!-- PDF.js for PDF support -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <!-- Styles -->
    <link rel="stylesheet" href="css/map-editor.css">
</head>
<body>
    <div class="map-editor">
        <!-- Toolbar -->
        <div class="toolbar">
            <!-- Right side - Zoom controls -->
            <div class="toolbar-right">
                <button class="btn-tool" id="btnZoomOut" title="הקטן">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19 13H5v-2h14v2z"/>
                    </svg>
                </button>
                <span class="zoom-display" id="zoomDisplay">100%</span>
                <button class="btn-tool" id="btnZoomIn" title="הגדל">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </button>
                <button class="btn-tool" id="btnZoomFit" title="התאם לחלון">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M3 5v4h2V5h4V3H5c-1.1 0-2 .9-2 2zm2 10H3v4c0 1.1.9 2 2 2h4v-2H5v-4zm14 4h-4v2h4c1.1 0 2-.9 2-2v-4h-2v4zm0-16h-4v2h4v4h2V5c0-1.1-.9-2-2-2z"/>
                    </svg>
                </button>
            </div>

            <!-- Center - Entity info -->
            <div class="toolbar-center">
                <span class="entity-info" id="entityInfo">
                    <?php if ($entityName): ?>
                        <?php echo htmlspecialchars($entityName); ?>
                    <?php else: ?>
                        לא נבחרה ישות
                    <?php endif; ?>
                </span>
            </div>

            <!-- Left side - Edit mode toggle and menus -->
            <div class="toolbar-left">
                <!-- Edit mode toggle -->
                <button class="btn-toggle" id="btnEditMode">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                    <span>מצב עריכה</span>
                </button>

                <!-- Edit menus (hidden when not in edit mode) -->
                <div class="edit-menus" id="editMenus" style="display: none;">
                    <!-- Boundary menu -->
                    <div class="dropdown">
                        <button class="btn-dropdown" id="btnBoundaryMenu">
                            <svg viewBox="0 0 24 24" width="18" height="18">
                                <path fill="currentColor" d="M3 3h18v18H3V3zm2 2v14h14V5H5z"/>
                            </svg>
                            <span>גבול מפה</span>
                            <svg class="arrow" viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M7 10l5 5 5-5z"/>
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="boundaryMenu">
                            <button class="dropdown-item" id="btnAddBoundary">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                                הוספת גבול
                            </button>
                            <button class="dropdown-item" id="btnEditBoundary" disabled>
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/>
                                </svg>
                                עריכת גבול
                            </button>
                            <button class="dropdown-item" id="btnRemoveBoundary" disabled>
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                                הסרת גבול
                            </button>
                        </div>
                    </div>

                    <!-- Background menu -->
                    <div class="dropdown">
                        <button class="btn-dropdown" id="btnBackgroundMenu">
                            <svg viewBox="0 0 24 24" width="18" height="18">
                                <path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            </svg>
                            <span>רקע מפה</span>
                            <svg class="arrow" viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M7 10l5 5 5-5z"/>
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="backgroundMenu">
                            <button class="dropdown-item" id="btnAddBackground">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                                הוספת רקע
                            </button>
                            <button class="dropdown-item" id="btnEditBackground" disabled>
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/>
                                </svg>
                                עריכת רקע
                            </button>
                            <button class="dropdown-item" id="btnRemoveBackground" disabled>
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                </svg>
                                הסרת רקע
                            </button>
                        </div>
                    </div>

                    <!-- Save button -->
                    <button class="btn-save" id="btnSave">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                        </svg>
                        שמור
                    </button>
                </div>
            </div>
        </div>

        <!-- Canvas container -->
        <div class="canvas-container" id="canvasContainer">
            <canvas id="mapCanvas"></canvas>
        </div>

        <!-- Status bar -->
        <div class="status-bar" id="statusBar">
            <span id="statusText">מוכן</span>
        </div>
    </div>

    <!-- Hidden file input -->
    <input type="file" id="fileInput" accept="image/*,.pdf" style="display: none;">

    <!-- PDF Page selector modal -->
    <div class="modal" id="pdfModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>בחר עמוד מה-PDF</h3>
                <button class="btn-close" id="closePdfModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="pdf-pages" id="pdfPages"></div>
            </div>
        </div>
    </div>

    <!-- Config -->
    <script>
        const MAP_CONFIG = {
            entityType: '<?php echo addslashes($entityType); ?>',
            entityId: '<?php echo addslashes($entityId); ?>',
            entityName: '<?php echo addslashes($entityName); ?>',
            apiBase: '../api/'
        };
    </script>

    <!-- App scripts -->
    <script src="js/MapEditor.js" type="module"></script>
</body>
</html>
