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

                    <!-- Windows menu -->
                    <div class="dropdown">
                        <button class="btn-dropdown" id="btnWindowsMenu">
                            <svg viewBox="0 0 24 24" width="18" height="18">
                                <path fill="currentColor" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zm-9 9h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                            </svg>
                            <span>חלונות</span>
                            <svg class="arrow" viewBox="0 0 24 24" width="16" height="16">
                                <path fill="currentColor" d="M7 10l5 5 5-5z"/>
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="windowsMenu">
                            <button class="dropdown-item" id="btnTextStylePanel" data-panel="textStyle">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M9.93 13.5h4.14L12 7.98 9.93 13.5zM20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-4.05 16.5l-1.14-3H9.17l-1.12 3H5.96l5.11-13h1.86l5.11 13h-2.09z"/>
                                </svg>
                                <span>עיצוב כתב</span>
                                <svg class="check-icon" viewBox="0 0 24 24" width="14" height="14" style="margin-right: auto; display: none;">
                                    <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </button>
                            <button class="dropdown-item" id="btnElementStylePanel" data-panel="elementStyle">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                                </svg>
                                <span>עיצוב אלמנטים</span>
                                <svg class="check-icon" viewBox="0 0 24 24" width="14" height="14" style="margin-right: auto; display: none;">
                                    <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </button>
                            <button class="dropdown-item" id="btnLayersPanel" data-panel="layers">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/>
                                </svg>
                                <span>שכבות</span>
                                <svg class="check-icon" viewBox="0 0 24 24" width="14" height="14" style="margin-right: auto; display: none;">
                                    <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </button>
                            <div class="dropdown-divider"></div>
                            <button class="dropdown-item" id="btnChildrenPanel" data-panel="children">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                </svg>
                                <span>ילדים</span>
                                <svg class="check-icon" viewBox="0 0 24 24" width="14" height="14" style="margin-right: auto; display: none;">
                                    <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </button>
                            <button class="dropdown-item" id="btnAreaGravePanel" data-panel="areaGrave">
                                <svg viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10H7v-2h10v2z"/>
                                </svg>
                                <span>פרטי אחוזת קבר</span>
                                <svg class="check-icon" viewBox="0 0 24 24" width="14" height="14" style="margin-right: auto; display: none;">
                                    <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
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

    <!-- Dock Zones -->
    <div class="dock-zone dock-zone-left" id="dockZoneLeft">
        <div class="dock-panels" id="dockPanelsLeft"></div>
    </div>
    <div class="dock-zone dock-zone-right" id="dockZoneRight">
        <div class="dock-panels" id="dockPanelsRight"></div>
    </div>

    <!-- Floating Panels -->

    <!-- Text Style Panel -->
    <div class="floating-panel" id="textStylePanel" style="display: none;">
        <div class="floating-panel-header">
            <span class="floating-panel-title">עיצוב כתב</span>
            <button class="floating-panel-close" data-panel="textStyle">&times;</button>
        </div>
        <div class="floating-panel-body">
            <div class="panel-message" id="textPanelMessage">בחר טקסט לעריכה</div>
            <div class="panel-controls" id="textControls" style="display: none;">
                <div class="control-group">
                    <label>פונט</label>
                    <select id="fontFamily">
                        <option value="Arial, sans-serif">Arial</option>
                        <option value="'Times New Roman', serif">Times New Roman</option>
                        <option value="'Courier New', monospace">Courier New</option>
                        <option value="Georgia, serif">Georgia</option>
                        <option value="Verdana, sans-serif">Verdana</option>
                        <option value="'David Libre', serif">David</option>
                        <option value="'Heebo', sans-serif">Heebo</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>גודל</label>
                    <input type="number" id="fontSize" min="8" max="200" value="16">
                </div>
                <div class="control-group">
                    <label>צבע</label>
                    <input type="color" id="fontColor" value="#1e293b">
                </div>
                <div class="control-group">
                    <label>מרווח אותיות</label>
                    <input type="number" id="letterSpacing" min="-10" max="50" value="0" step="0.5">
                </div>
            </div>
        </div>
    </div>

    <!-- Element Style Panel -->
    <div class="floating-panel" id="elementStylePanel" style="display: none;">
        <div class="floating-panel-header">
            <span class="floating-panel-title">עיצוב אלמנטים</span>
            <button class="floating-panel-close" data-panel="elementStyle">&times;</button>
        </div>
        <div class="floating-panel-body">
            <div class="panel-message" id="elementPanelMessage">בחר אלמנט לעריכה</div>
            <div class="panel-controls" id="elementControls" style="display: none;">
                <div class="control-group">
                    <label>עובי קו</label>
                    <input type="number" id="strokeWidth" min="1" max="50" value="2">
                </div>
                <div class="control-group">
                    <label>צבע קו</label>
                    <input type="color" id="strokeColor" value="#3b82f6">
                </div>
                <div class="control-group">
                    <label>סוג קו</label>
                    <select id="strokeStyle">
                        <option value="solid">רציף</option>
                        <option value="dashed">מקווקו</option>
                        <option value="dotted">מנוקד</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Layers Panel -->
    <div class="floating-panel" id="layersPanel" style="display: none;">
        <div class="floating-panel-header">
            <span class="floating-panel-title">שכבות</span>
            <button class="floating-panel-close" data-panel="layers">&times;</button>
        </div>
        <div class="floating-panel-body">
            <div class="layers-list" id="layersList">
                <div class="panel-message">אין שכבות</div>
            </div>
        </div>
    </div>

    <!-- Children Panel -->
    <div class="floating-panel" id="childrenPanel" style="display: none;">
        <div class="floating-panel-header">
            <span class="floating-panel-title">ילדים</span>
            <button class="floating-panel-close" data-panel="children">&times;</button>
        </div>
        <div class="floating-panel-body">
            <!-- Toggle for showing descendants -->
            <div class="children-toggle-container" id="childrenToggleContainer" style="display: none;">
                <label class="children-toggle">
                    <input type="checkbox" id="showDescendantsToggle">
                    <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label">הצג צאצאים</span>
            </div>

            <!-- Message when no parent boundary -->
            <div class="panel-message" id="childrenNoParentBoundary">
                יש להגדיר גבול מפה לפני הוספת גבולות ילדים
            </div>

            <!-- Loading indicator -->
            <div class="panel-message" id="childrenLoading" style="display: none;">
                <span class="loading-spinner"></span> טוען...
            </div>

            <!-- Children list -->
            <div id="childrenListContainer" style="display: none;">
                <div class="children-list" id="childrenList"></div>
            </div>

            <!-- No children message -->
            <div class="panel-message" id="childrenEmpty" style="display: none;">
                אין ילדים להצגה
            </div>
        </div>
    </div>

    <!-- AreaGrave Details Panel -->
    <div class="floating-panel floating-panel-wide" id="areaGravePanel" style="display: none;">
        <div class="floating-panel-header">
            <span class="floating-panel-title">פרטי אחוזת קבר</span>
            <button class="floating-panel-close" data-panel="areaGrave">&times;</button>
        </div>
        <div class="floating-panel-body">
            <!-- No selection message -->
            <div class="panel-message" id="areaGraveNoSelection">
                לחץ פעמיים על מלבן אחוזת קבר לצפייה בפרטים
            </div>

            <!-- AreaGrave details content -->
            <div id="areaGraveContent" style="display: none;">
                <!-- Header info -->
                <div class="areagrave-header">
                    <div class="areagrave-title">
                        <span class="areagrave-name" id="areaGraveName">-</span>
                        <span class="areagrave-row" id="areaGraveRow">-</span>
                    </div>
                </div>

                <!-- Position controls -->
                <div class="areagrave-position-section">
                    <h4 class="section-title">מיקום וגודל</h4>
                    <div class="position-controls">
                        <div class="position-field">
                            <label>X</label>
                            <input type="number" id="areaGraveX" step="1">
                        </div>
                        <div class="position-field">
                            <label>Y</label>
                            <input type="number" id="areaGraveY" step="1">
                        </div>
                        <div class="position-field">
                            <label>רוחב</label>
                            <input type="number" id="areaGraveWidth" min="1" step="1">
                        </div>
                        <div class="position-field">
                            <label>גובה</label>
                            <input type="number" id="areaGraveHeight" min="1" step="1">
                        </div>
                        <div class="position-field">
                            <label>זווית</label>
                            <input type="number" id="areaGraveAngle" min="0" max="360" step="1">
                        </div>
                    </div>
                </div>

                <!-- Graves list -->
                <div class="areagrave-graves-section">
                    <h4 class="section-title">רשימת קברים</h4>
                    <div class="graves-list" id="areaGraveGravesList">
                        <!-- Populated dynamically -->
                    </div>
                </div>
            </div>
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
