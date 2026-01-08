/**
 * Map Launcher - מנהל פתיחת המפה
 * Version: 3.11.0 - Refactoring Steps 1-13: StateManager + EntitySelector + LauncherModal + Toolbar + ZoomControls + CanvasManager + PolygonDrawer + BoundaryEditor + BackgroundEditor + HistoryManager + EditModeToggle + ContextMenu + MapPopup
 * Features: Edit mode, Background image, Polygon drawing, Undo/Redo, Context menu, Popup management
 */

// טעינת StateManager
(async function initStateManager() {
    try {
        const { StateManager } = await import('../map/core/StateManager.js');
        window.mapState = new StateManager();
    } catch (error) {
        console.error('❌ Failed to load StateManager:', error);
        // Fallback: create simple state object
        window.mapState = {
            zoom: 1,
            getZoom: function() { return this.zoom; },
            setZoom: function(z) { this.zoom = z; }
        };
    }
})();

// טעינת EntitySelector
(async function initEntitySelector() {
    try {
        const { EntitySelector } = await import('../map/launcher/EntitySelector.js');
        window.entitySelector = new EntitySelector({ apiEndpoint: 'api/map-api.php' });
    } catch (error) {
        console.error('❌ Failed to load EntitySelector:', error);
    }
})();

// טעינת LauncherModal
(async function initLauncherModal() {
    try {
        const { LauncherModal } = await import('../map/launcher/LauncherModal.js');

        // Wait for EntitySelector to be ready
        while (!window.entitySelector) {
            await new Promise(resolve => setTimeout(resolve, 50));
        }

        window.launcherModal = new LauncherModal(window.entitySelector, {
            modalId: 'mapLauncherModal',
            title: 'פתיחת מפת בית עלמין'
        });

        // Connect launch callback to existing launchMap function
        window.launcherModal.onLaunch((entityType, entityId) => {
            // Update mapEntityType and mapEntitySelect values for launchMap compatibility
            document.getElementById('mapEntityType').value = entityType;
            document.getElementById('mapEntitySelect').value = entityId;
            launchMap();
        });

    } catch (error) {
        console.error('❌ Failed to load LauncherModal:', error);
    }
})();

// טעינת Toolbar
(async function initToolbar() {
    try {
        const { Toolbar } = await import('../map/ui/Toolbar.js');
        window.ToolbarClass = Toolbar;
    } catch (error) {
        console.error('❌ Failed to load Toolbar:', error);
    }
})();

// טעינת ZoomControls
(async function initZoomControls() {
    try {
        const { ZoomControls } = await import('../map/ui/ZoomControls.js');
        window.ZoomControlsClass = ZoomControls;
    } catch (error) {
        console.error('❌ Failed to load ZoomControls:', error);
    }
})();

// טעינת CanvasManager
(async function initCanvasManager() {
    try {
        const { CanvasManager } = await import('../map/core/CanvasManager.js');
        window.CanvasManagerClass = CanvasManager;
    } catch (error) {
        console.error('❌ Failed to load CanvasManager:', error);
    }
})();

// טעינת PolygonDrawer
(async function initPolygonDrawer() {
    try {
        const { PolygonDrawer } = await import('../map/editors/PolygonDrawer.js');
        window.PolygonDrawerClass = PolygonDrawer;
    } catch (error) {
        console.error('❌ Failed to load PolygonDrawer:', error);
    }
})();

// טעינת BoundaryEditor
(async function initBoundaryEditor() {
    try {
        const { BoundaryEditor } = await import('../map/editors/BoundaryEditor.js');
        window.BoundaryEditorClass = BoundaryEditor;
    } catch (error) {
        console.error('❌ Failed to load BoundaryEditor:', error);
    }
})();

// טעינת BackgroundEditor
(async function initBackgroundEditor() {
    try {
        const { BackgroundEditor } = await import('../map/editors/BackgroundEditor.js');
        window.BackgroundEditorClass = BackgroundEditor;
    } catch (error) {
        console.error('❌ Failed to load BackgroundEditor:', error);
    }
})();

// טעינת HistoryManager
(async function initHistoryManager() {
    try {
        const { HistoryManager } = await import('../map/core/HistoryManager.js');
        window.HistoryManagerClass = HistoryManager;
    } catch (error) {
        console.error('❌ Failed to load HistoryManager:', error);
    }
})();

// טעינת EditModeToggle
(async function initEditModeToggle() {
    try {
        const { EditModeToggle } = await import('../map/ui/EditModeToggle.js');
        window.EditModeToggleClass = EditModeToggle;
    } catch (error) {
        console.error('❌ Failed to load EditModeToggle:', error);
    }
})();

// טעינת ContextMenu
(async function initContextMenu() {
    try {
        const { ContextMenu } = await import('../map/ui/ContextMenu.js');
        window.ContextMenuClass = ContextMenu;
    } catch (error) {
        console.error('❌ Failed to load ContextMenu:', error);
    }
})();

// טעינת MapPopup
(async function initMapPopup() {
    try {
        const { MapPopup } = await import('../map/launcher/MapPopup.js');
        window.MapPopupClass = MapPopup;
    } catch (error) {
        console.error('❌ Failed to load MapPopup:', error);
    }
})();

// טעינת BoundaryEditPanel
(async function initBoundaryEditPanel() {
    try {
        const { BoundaryEditPanel } = await import('../map/ui/BoundaryEditPanel.js');
        window.BoundaryEditPanelClass = BoundaryEditPanel;
    } catch (error) {
        console.error('❌ Failed to load BoundaryEditPanel:', error);
    }
})();

// טעינת PolygonClipper
(async function initPolygonClipper() {
    try {
        const { PolygonClipper } = await import('../map/utils/PolygonClipper.js');
        window.PolygonClipperClass = PolygonClipper;
    } catch (error) {
        console.error('❌ Failed to load PolygonClipper:', error);
    }
})();

// משתנים גלובליים (מועברים בהדרגה ל-mapState)
let currentMapMode = 'view'; // ← Synced with mapState.mode
let isEditMode = false; // ← Synced with mapState.isEditMode
let currentZoom = 1; // ← Synced with mapState.zoom
let backgroundImage = null; // ← Synced with mapState.canvas.background.image
let currentEntityType = null; // ← Synced with mapState.entityType
let currentUnicId = null; // ← Synced with mapState.entityId
let drawingPolygon = false; // ← Synced with mapState.polygon.isDrawing
let polygonPoints = []; // ← Synced with mapState.polygon.points
let previewLine = null; // ← Synced with mapState.polygon.previewLine
let boundaryClipPath = null; // ← Synced with mapState.canvas.boundary.clipPath
let grayMask = null; // ← Synced with mapState.canvas.boundary.grayMask
let boundaryOutline = null; // ← Synced with mapState.canvas.boundary.outline
let isBoundaryEditMode = false; // ← Synced with mapState.canvas.boundary.isEditMode
let isBackgroundEditMode = false; // ← Synced with mapState.canvas.background.isEditMode
let currentPdfContext = null; // ← Synced with mapState.canvas.background.pdfContext
let currentPdfDoc = null; // ← Synced with mapState.canvas.background.pdfDoc

// גבול הורה (לישויות בנים)
let parentBoundaryPoints = null; // ← Synced with mapState.canvas.parent.points
let parentBoundaryOutline = null; // ← Synced with mapState.canvas.parent.outline
let lastValidBoundaryState = null; // ← Synced with mapState.canvas.boundary.lastValidState

// Undo/Redo
let canvasHistory = []; // ← Synced with mapState.history.states
let historyIndex = -1; // ← Synced with mapState.history.currentIndex
const MAX_HISTORY = 30; // מקסימום מצבים לשמירה

// GLOBAL WRAPPER FUNCTIONS (for backwards compatibility)
// These functions are called from sidebar.php and maintain the old API

/**
 * פתיחת מודל בחירת ישות - נקרא מה-sidebar
 */
function openMapLauncher() {
    if (window.launcherModal) {
        window.launcherModal.open();
    } else {
        console.error('❌ [LAUNCHER] LauncherModal not loaded yet');
    }
}

/**
 * סגירת מודל בחירת ישות
 */
function closeMapLauncher() {
    if (window.launcherModal) {
        window.launcherModal.close();
    }
}

async function launchMap() {
    const entityType = document.getElementById('mapEntityType').value;
    const unicId = document.getElementById('mapEntitySelect').value;

    if (!entityType) {
        alert('נא לבחור סוג ישות');
        document.getElementById('mapEntityType').focus();
        return;
    }

    if (!unicId) {
        alert('נא לבחור ישות מהרשימה');
        document.getElementById('mapEntitySelect').focus();
        return;
    }

    // בדיקת תקינות - האם הרשומה קיימת ופעילה
    const launchBtn = document.querySelector('.map-launcher-footer .btn-primary');
    const originalText = launchBtn ? launchBtn.textContent : '';

    const entityNames = {
        cemetery: 'בית עלמין',
        block: 'גוש',
        plot: 'חלקה',
        areaGrave: 'אחוזת קבר'
    };

    const parentNames = {
        block: 'בית העלמין',
        plot: 'הגוש'
    };

    try {
        if (launchBtn) {
            launchBtn.disabled = true;
            launchBtn.textContent = 'בודק...';
        }

        // בדיקה שהרשומה קיימת
        const response = await fetch(`api/cemetery-hierarchy.php?action=get&type=${entityType}&id=${unicId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'הרשומה לא נמצאה');
        }

        if (!result.data) {
            throw new Error('הרשומה לא נמצאה במערכת');
        }

        // לישויות בנים - בדיקה שלהורה יש גבול מוגדר
        if (entityType === 'block' || entityType === 'plot') {
            const parentResponse = await fetch(`api/cemetery-hierarchy.php?action=get_parent_map&type=${entityType}&id=${unicId}`);
            const parentResult = await parentResponse.json();

            if (!parentResult.success) {
                throw new Error(parentResult.error || 'שגיאה בטעינת נתוני ההורה');
            }

            if (parentResult.hasParent && !parentResult.parentHasBoundary) {
                alert(`לא ניתן לפתוח מפה ל${entityNames[entityType]}.\n\nיש להגדיר קודם גבול מפה ל${parentNames[entityType]}.`);
                return;
            }

            // שמור את נתוני ההורה לשימוש במפה
            if (parentResult.parentMapData) {
                window.parentMapData = parentResult.parentMapData;
            }
        } else {
            window.parentMapData = null;
        }

        // הרשומה קיימת ופעילה - פתח את המפה
        closeMapLauncher();
        openMapPopup(entityType, unicId);

    } catch (error) {
        alert(`שגיאה: לא נמצאה רשומת ${entityNames[entityType]} פעילה עם מזהה "${unicId}"\n\n${error.message}`);
        document.getElementById('mapUnicId').focus();
        document.getElementById('mapUnicId').select();
    } finally {
        if (launchBtn) {
            launchBtn.disabled = false;
            launchBtn.textContent = originalText;
        }
    }
}

/**
 * פתיחת פופאפ המפה
 * Uses MapPopup if available, otherwise falls back to old implementation
 */
function openMapPopup(entityType, unicId) {
    // Update StateManager
    if (window.mapState) {
        window.mapState.setEntity(entityType, unicId);
    }

    // Keep old variables in sync for backwards compatibility
    currentEntityType = entityType;
    currentUnicId = unicId;

    if (!window.MapPopupClass) return;

    if (!window.mapPopupInstance) {
        window.mapPopupInstance = new window.MapPopupClass({
            onMapInit: (entityType, unicId, entity) => {
                initializeMap(entityType, unicId, entity);
            },
            onClose: () => {
                cleanupMapState();
            }
        });
    }

    window.mapPopupInstance.open(entityType, unicId);
}

/**
 * טעינת נתוני המפה
 */
async function loadMapData(entityType, unicId) {
    try {
        const response = await fetch(`api/cemetery-hierarchy.php?action=get&type=${entityType}&id=${unicId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'לא נמצאה ישות');
        }

        const entity = result.data;
        const entityNames = {
            cemetery: 'בית עלמין',
            block: 'גוש',
            plot: 'חלקה',
            areaGrave: 'אחוזת קבר'
        };
        const entityName = entity.cemeteryNameHe || entity.blockNameHe || entity.plotNameHe || entity.areaGraveNameHe || unicId;
        document.getElementById('mapPopupTitle').textContent = `מפת ${entityNames[entityType]}: ${entityName}`;

        initializeMap(entityType, unicId, entity);

    } catch (error) {
        console.error('שגיאה בטעינת המפה:', error);
        document.getElementById('mapContainer').innerHTML = `
            <div class="map-loading">
                <p style="color: #dc2626;">שגיאה: ${error.message}</p>
                <button onclick="closeMapPopup()" style="margin-top: 12px; padding: 8px 16px; cursor: pointer;">סגור</button>
            </div>
        `;
    }
}

/**
 * אתחול המפה
 */
function initializeMap(entityType, unicId, entity) {
    // CSS is now injected by modules (Toolbar.js, ContextMenu.js)
    // No longer needed here - removed in Step 15 Batch #21

    const container = document.getElementById('mapContainer');

    container.innerHTML = `
        <!-- Toolbar - will be rendered by Toolbar.js -->
        <div id="mapToolbarContainer"></div>

        <!-- Canvas Area -->
        <div id="mapCanvas" class="map-canvas">
            <div class="edit-mode-indicator">מצב עריכה פעיל</div>
        </div>

        <!-- Hidden file inputs -->
        <input type="file" id="bgImageInput" class="hidden-file-input" accept="image/*,.pdf" onchange="handleBackgroundUpload(event)">
        <input type="file" id="addImageInput" class="hidden-file-input" accept="image/*,.pdf" onchange="handleAddImage(event)">

        <!-- Context Menu -->
        <div id="mapContextMenu" class="map-context-menu" style="display:none;">
            <div class="context-menu-content" id="contextMenuContent">
                <!-- ימולא דינמית -->
            </div>
        </div>

        <!-- PDF Page Selector Modal -->
        <div id="pdfPageSelectorModal" class="pdf-selector-overlay" style="display:none;">
            <div class="pdf-selector-modal">
                <div class="pdf-selector-header">
                    <h3>בחירת עמוד מ-PDF</h3>
                    <button type="button" class="pdf-selector-close" onclick="closePdfSelector()">&times;</button>
                </div>
                <div class="pdf-selector-info">
                    <span id="pdfFileName"></span>
                    <span id="pdfPageCount"></span>
                </div>
                <div class="pdf-selector-pages" id="pdfPagesContainer">
                    <!-- תמונות ממוזערות של העמודים -->
                </div>
                <div class="pdf-selector-footer">
                    <button type="button" class="btn-secondary" onclick="closePdfSelector()">ביטול</button>
                </div>
            </div>
        </div>
    `;

    // סגירת תפריט בלחיצה מחוץ
    document.addEventListener('click', hideContextMenu);

    // STEP 4/15: Initialize Toolbar
    // Create toolbar using Toolbar.js module
    if (window.ToolbarClass) {
        const toolbarContainer = document.getElementById('mapToolbarContainer');
        window.mapToolbar = new window.ToolbarClass(toolbarContainer, {
            onZoomIn: zoomMapIn,
            onZoomOut: zoomMapOut,
            onEditZoomLevel: editZoomLevel,
            onUploadBackground: uploadBackgroundImage,
            onToggleBackgroundEdit: toggleBackgroundEdit,
            onDeleteBackground: deleteBackground,
            onStartDrawPolygon: startDrawPolygon,
            onToggleBoundaryEdit: toggleBoundaryEdit,
            onDeleteBoundary: deleteBoundary,
            onUndo: undoCanvas,
            onRedo: redoCanvas,
            onSave: saveMapData
        });
    } else {
        console.warn('⚠️ Toolbar class not loaded yet');
    }

    createMapCanvas(entityType, unicId, entity);
}

/**
 * יצירת ה-Canvas
 * REFACTORED: משתמש ב-CanvasManager (Step 6/15)
 */
function createMapCanvas(entityType, unicId, entity) {
    const canvasContainer = document.getElementById('mapCanvas');

    // STEP 6/15: Use CanvasManager to create canvas
    if (window.CanvasManagerClass) {
        window.mapCanvasManager = new window.CanvasManagerClass(canvasContainer, {
            canvasId: 'fabricCanvas',
            backgroundColor: '#ffffff',
            selection: true,
            initialText: 'לחץ על "מצב עריכה" כדי להתחיל'
        });

        window.mapCanvas = window.mapCanvasManager.create();

        // Attach event handlers
        window.mapCanvasManager.attachEventHandlers({
            onMouseDown: handleCanvasClick,
            onMouseMove: handleCanvasMouseMove,
            onObjectModified: (e) => {
                saveCanvasState();
            },
            onContextMenu: handleCanvasRightClick,
            onZoomChange: (zoom) => {
                // Update zoom controls if available (Step 5/15)
                if (window.mapZoomControls) {
                    window.mapZoomControls.setZoom(zoom);
                } else {
                    currentZoom = zoom;
                    updateZoomDisplay();
                }
            }
        });
    } else {
        console.error('❌ CanvasManager not available - this should not happen!');
    }

    // STEP 5/15: Initialize ZoomControls
    if (window.ZoomControlsClass && window.mapCanvas) {
        window.mapZoomControls = new window.ZoomControlsClass(window.mapCanvas, {
            min: 0.3,
            max: 3,
            step: 0.1,
            onZoomChange: (zoom) => {
                currentZoom = zoom; // Keep in sync for backwards compatibility
                if (window.mapState) window.mapState.setZoom(zoom);
                updateZoomDisplay();
            }
        });
    }

    // STEP 7/15: Initialize PolygonDrawer
    if (window.PolygonDrawerClass && window.mapCanvas) {
        window.mapPolygonDrawer = new window.PolygonDrawerClass(window.mapCanvas, {
            color: '#3b82f6',
            strokeWidth: 2,
            pointRadius: 5,
            minPoints: 3,
            parentBoundary: parentBoundaryPoints, // גבול הורה לבדיקה
            onFinish: (points) => {
                // Create boundary with mask from the polygon points
                createBoundaryFromPoints(points);
            },
            onCancel: () => {
                drawingPolygon = false;
                document.getElementById('drawPolygonBtn')?.classList.remove('active');
                document.getElementById('mapCanvas').style.cursor = 'default';
            }
        });
    }

    // STEP 8/15: Initialize BoundaryEditor
    if (window.BoundaryEditorClass && window.mapCanvas) {
        window.mapBoundaryEditor = new window.BoundaryEditorClass(window.mapCanvas, {
            parentBoundary: parentBoundaryPoints,
            onUpdate: (newState) => {
                // Update lastValidBoundaryState
                lastValidBoundaryState = newState;
                if (window.mapState) {
                    window.mapState.canvas.boundary.lastValidState = newState;
                }
            },
            onDelete: () => {
                // Update global variables
                boundaryClipPath = null;
                grayMask = null;
                boundaryOutline = null;
                if (window.mapState) {
                    window.mapState.canvas.boundary.clipPath = null;
                    window.mapState.setGrayMask(null);
                    window.mapState.setBoundaryOutline(null);
                }
                // Hide buttons
                const editBtn = document.getElementById('editBoundaryBtn');
                const deleteBtn = document.getElementById('deleteBoundaryBtn');
                if (editBtn) {
                    editBtn.classList.add('hidden-btn');
                    editBtn.classList.remove('active');
                }
                if (deleteBtn) deleteBtn.classList.add('hidden-btn');
                // Hide edit panel
                if (window.mapBoundaryEditPanel) {
                    window.mapBoundaryEditPanel.hide();
                }
                saveCanvasState();
            }
        });
    }

    // Initialize BoundaryEditPanel
    if (window.BoundaryEditPanelClass && window.mapCanvas) {
        window.mapBoundaryEditPanel = new window.BoundaryEditPanelClass(window.mapCanvas, {
            onPointsChanged: (newPoints, newPolygon) => {
                // Update global reference
                boundaryOutline = newPolygon;
                if (window.mapState) {
                    window.mapState.setBoundaryOutline(newPolygon);
                }
                saveCanvasState();
            },
            onMaskChanged: (newMask) => {
                // Update global reference
                grayMask = newMask;
                if (window.mapState) {
                    window.mapState.setGrayMask(newMask);
                }
            },
            onClose: () => {
                // Optionally turn off edit mode when panel is closed
            }
        });
    }

    // STEP 9/15: Initialize BackgroundEditor
    if (window.BackgroundEditorClass && window.mapCanvas) {
        window.mapBackgroundEditor = new window.BackgroundEditorClass(window.mapCanvas, {
            onUpload: (img) => {
                // Update global variable
                backgroundImage = img;
                if (window.mapState) window.mapState.setBackgroundImage(img);

                // Show edit/delete buttons
                const editBgBtn = document.getElementById('editBackgroundBtn');
                const deleteBgBtn = document.getElementById('deleteBackgroundBtn');
                if (editBgBtn) {
                    editBgBtn.classList.remove('hidden-btn');
                    editBgBtn.classList.add('active');
                }
                if (deleteBgBtn) {
                    deleteBgBtn.classList.remove('hidden-btn');
                }

                // Update state
                isBackgroundEditMode = true;
                if (window.mapState) {
                    window.mapState.canvas.background.isEditMode = true;
                }

                // Ensure mask is locked
                if (grayMask) {
                    window.mapBackgroundEditor.ensureMaskLocked(grayMask);
                }

                // Reorder layers and save
                reorderLayers();
                saveCanvasState();
            },
            onDelete: () => {
                // Update global variable
                backgroundImage = null;
                if (window.mapState) window.mapState.setBackgroundImage(null);

                // Hide buttons
                const editBtn = document.getElementById('editBackgroundBtn');
                const deleteBtn = document.getElementById('deleteBackgroundBtn');
                if (editBtn) {
                    editBtn.classList.add('hidden-btn');
                    editBtn.classList.remove('active');
                }
                if (deleteBtn) deleteBtn.classList.add('hidden-btn');

                // Update state
                isBackgroundEditMode = false;
                if (window.mapState) {
                    window.mapState.canvas.background.isEditMode = false;
                }

                saveCanvasState();
            },
            onEditModeChange: (enabled) => {
                // Update global state
                isBackgroundEditMode = enabled;
                if (window.mapState) {
                    window.mapState.canvas.background.isEditMode = enabled;
                }

                // Update button UI
                const editBtn = document.getElementById('editBackgroundBtn');
                if (editBtn) {
                    if (enabled) {
                        editBtn.classList.add('active');
                    } else {
                        editBtn.classList.remove('active');
                    }
                }

                // Ensure mask is locked
                if (enabled && grayMask) {
                    window.mapBackgroundEditor.ensureMaskLocked(grayMask);
                }
            }
        });
    }

    // STEP 10/15: Initialize HistoryManager
    if (window.HistoryManagerClass && window.mapCanvas) {
        window.mapHistoryManager = new window.HistoryManagerClass(window.mapCanvas, {
            maxHistory: 30,
            stateManager: window.mapState,  // STEP 14: Auto-sync StateManager on undo/redo
            onChange: (state) => {
                // Update undo/redo buttons when history changes
                updateUndoRedoButtons();
            },
            onRestore: (restoredObjects) => {
                // Update global variables after restoration (for backward compatibility)
                backgroundImage = restoredObjects.backgroundImage;
                grayMask = restoredObjects.grayMask;
                boundaryOutline = restoredObjects.boundaryOutline;

                // Note: StateManager is now auto-synced in HistoryManager.restore()
                // No need for manual sync here anymore

                // סנכרן את BoundaryEditPanel עם האובייקטים החדשים
                if (window.mapBoundaryEditPanel && restoredObjects.boundaryOutline) {
                    window.mapBoundaryEditPanel.boundaryOutline = restoredObjects.boundaryOutline;
                    window.mapBoundaryEditPanel.grayMask = restoredObjects.grayMask;

                    // אם הפאנל פתוח, צריך לחבר מחדש את ה-listeners ולעדכן נקודות
                    if (window.mapBoundaryEditPanel.isVisible()) {
                        if (window.mapBoundaryEditPanel.isPointEditMode) {
                            // במצב עריכת נקודות - הצג נקודות חדשות
                            window.mapBoundaryEditPanel.showPointMarkers(true);
                        } else {
                            // במצב עריכת גבול - חבר listeners והצג נקודות קטנות
                            window.mapBoundaryEditPanel.unlockBoundaryForEdit();
                            window.mapBoundaryEditPanel.showPointMarkers(false);
                        }
                        window.mapBoundaryEditPanel.updatePointsCount();
                    }
                }

                // Lock system objects after restoration
                lockSystemObjects();

                // Update toolbar buttons
                updateToolbarButtons();
            }
        });
    }

    // STEP 11/15: Initialize EditModeToggle
    if (window.EditModeToggleClass) {
        window.mapEditModeToggle = new window.EditModeToggleClass({
            canvas: window.mapCanvas,
            onToggle: (enabled) => {
                // Sync global variable
                isEditMode = enabled;
                if (window.mapState) {
                    window.mapState.isEditMode = enabled;
                }
            },
            onEnter: () => {
                // Called when entering edit mode
            },
            onExit: () => {
                // Called when exiting edit mode
                // ביטול ציור פוליגון אם פעיל
                if (drawingPolygon) {
                    cancelPolygonDrawing();
                }
            }
        });

        // Initialize (connect to DOM)
        window.mapEditModeToggle.init();
    }

    // STEP 12/15: Initialize ContextMenu
    if (window.ContextMenuClass) {
        window.mapContextMenu = new window.ContextMenuClass({
            checkBoundary: hasBoundary,
            onAction: (action, data) => {
                // Handle all context menu actions
                handleContextMenuAction(action, data);
            }
        });

        // Initialize (connect to DOM)
        window.mapContextMenu.init();
    }

    // Load saved map data
    loadSavedMapData(entityType, unicId);
}

/**
 * טעינת נתוני מפה שמורים מהשרת
 */
async function loadSavedMapData(entityType, unicId) {
    try {
        // טען גבול הורה אם קיים (לישויות בנים)
        loadParentBoundary();

        const response = await fetch(`api/cemetery-hierarchy.php?action=get_map&type=${entityType}&id=${unicId}`);
        const result = await response.json();

        if (!result.success || !result.mapData || !result.mapData.canvasJSON) {
            return;
        }

        // טען את ה-canvas מה-JSON
        window.mapCanvas.loadFromJSON(result.mapData.canvasJSON, function() {
            const allObjects = window.mapCanvas.getObjects();

            // Fix textBaseline for all text objects ('alphabetical' -> 'alphabetic')
            let fixedTextObjects = 0;
            allObjects.forEach(obj => {
                if ((obj.type === 'text' || obj.type === 'i-text' || obj.type === 'textbox') && obj.textBaseline === 'alphabetical') {
                    obj.set('textBaseline', 'alphabetic');
                    fixedTextObjects++;
                }
            });
            if (fixedTextObjects > 0) {
                window.mapCanvas.renderAll();
            }

            // עדכן משתנים גלובליים לפי האובייקטים שנטענו
            backgroundImage = null;
            if (window.mapState) window.mapState.setBackgroundImage(null);
            grayMask = null;
            boundaryOutline = null;
            if (window.mapState) {
                window.mapState.setGrayMask(null);
                window.mapState.setBoundaryOutline(null);
            }

            window.mapCanvas.getObjects().forEach(obj => {
                if (obj.objectType === 'backgroundLayer') {
                    backgroundImage = obj;
                    if (window.mapState) window.mapState.setBackgroundImage(obj);
                } else if (obj.objectType === 'grayMask') {
                    grayMask = obj;
                    if (window.mapState) window.mapState.setGrayMask(obj);
                } else if (obj.objectType === 'boundaryOutline') {
                    boundaryOutline = obj;
                    if (window.mapState) window.mapState.setBoundaryOutline(obj);
                }
            });

            // Update BackgroundEditor
            if (window.mapBackgroundEditor && backgroundImage) {
                window.mapBackgroundEditor.setBackgroundImage(backgroundImage);
            }

            // הסר את הטקסט ההתחלתי אם נטענו אובייקטים
            const objects = window.mapCanvas.getObjects('text');
            objects.forEach(obj => {
                if (obj.text === 'לחץ על "מצב עריכה" כדי להתחיל') {
                    window.mapCanvas.remove(obj);
                }
            });

            // טען גבול הורה אחרי טעינת הנתונים
            loadParentBoundary();

            // נעילת אובייקטי מערכת
            lockSystemObjects();

            // עדכן מצב כפתורים
            updateToolbarButtons();

            // החל זום אם נשמר
            if (result.mapData.zoom) {
                currentZoom = result.mapData.zoom;
                window.mapCanvas.setZoom(currentZoom);
                updateZoomDisplay();
            }

            window.mapCanvas.renderAll();

            // איפוס ההיסטוריה ושמירת המצב הנוכחי כמצב התחלתי
            resetHistory();
            saveCanvasState();
        });

    } catch (error) {
        console.error('Error loading saved map data:', error);
    }
}

/**
 * טעינת גבול ההורה לתצוגה (לישויות בנים)
 */
function loadParentBoundary() {
    // איפוס
    parentBoundaryPoints = null;
    if (parentBoundaryOutline) {
        window.mapCanvas.remove(parentBoundaryOutline);
        parentBoundaryOutline = null;
    }
    if (window.mapState) {
        window.mapState.canvas.parent.points = null;
        window.mapState.canvas.parent.outline = null;
    }

    // בדיקה אם יש נתוני הורה
    if (!window.parentMapData || !window.parentMapData.canvasJSON) {
        return;
    }

    // מצא את גבול ההורה
    const parentObjects = window.parentMapData.canvasJSON.objects || [];
    let parentBoundary = null;

    for (const obj of parentObjects) {
        if (obj.objectType === 'boundaryOutline') {
            parentBoundary = obj;
            break;
        }
    }

    if (!parentBoundary || !parentBoundary.points) {
        return;
    }

    // שמור את נקודות הגבול לוולידציה
    // חישוב קואורדינטות עולמיות של נקודות ההורה
    const pathOffsetX = parentBoundary.pathOffset?.x || 0;
    const pathOffsetY = parentBoundary.pathOffset?.y || 0;
    const left = parentBoundary.left || 0;
    const top = parentBoundary.top || 0;

    const newParentPoints = parentBoundary.points.map(p => ({
        x: p.x - pathOffsetX + left,
        y: p.y - pathOffsetY + top
    }));
    parentBoundaryPoints = newParentPoints;
    if (window.mapState) {
        window.mapState.canvas.parent.points = newParentPoints;
    }

    // עדכון BoundaryEditPanel עם גבול ההורה
    if (window.mapBoundaryEditPanel) {
        window.mapBoundaryEditPanel.setParentBoundary(newParentPoints);
    }

    // יצירת קו גבול ההורה לתצוגה (צבע שונה - כתום)
    const newParentOutline = new fabric.Polygon(parentBoundaryPoints, {
        fill: 'transparent',
        stroke: '#f97316', // כתום
        strokeWidth: 3,
        strokeDashArray: [10, 5], // קו מקווקו
        selectable: false,
        evented: false,
        objectType: 'parentBoundary',
        excludeFromExport: true // לא לשמור במפת הבן
    });
    parentBoundaryOutline = newParentOutline;
    if (window.mapState) {
        window.mapState.canvas.parent.outline = newParentOutline;
    }

    window.mapCanvas.add(parentBoundaryOutline);

    // סידור שכבות נכון
    reorderLayers();

}

/**
 * טוגל מצב עריכה
 * Uses EditModeToggle if available, otherwise falls back to old implementation
 * @param {boolean} enabled - האם להפעיל מצב עריכה
 */
function toggleEditMode(enabled) {
    // Use EditModeToggle if available
    if (window.mapEditModeToggle) {
        window.mapEditModeToggle.setEnabled(enabled);
        return;
    }

    // Fallback: Should never happen (EditModeToggle always loads)
    console.error('❌ EditModeToggle not available - this should not happen!');
}

/**
 * העלאת תמונת רקע
 */
function uploadBackgroundImage() {
    document.getElementById('bgImageInput').click();
}

/**
 * טיפול בהעלאת קובץ רקע
 * REFACTORED: משתמש ב-BackgroundEditor (Step 9/15)
 */
async function handleBackgroundUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const isPdf = file.type === 'application/pdf';

    if (isPdf) {
        // טיפול בקובץ PDF
        handlePdfUpload(file, 'background');
        event.target.value = '';
        return;
    }

    if (window.mapBackgroundEditor) {
        try {
            await window.mapBackgroundEditor.upload(file);
        } catch (error) {
            console.error('❌ Failed to upload background:', error);
            alert('שגיאה בהעלאת תמונת הרקע');
        }
    } else {
        // Fallback: Should never happen (BackgroundEditor always loads)
        console.error('❌ BackgroundEditor not available for upload - this should not happen!');
    }

    // ניקוי ה-input
    event.target.value = '';
}

/**
 * סידור שכבות - סדר היררכי:
 * 1. backgroundLayer - שכבה תחתונה (מהתפריט העליון)
 * 2. parentBoundary - גבול ההורה (קו כתום מקווקו) - מעל הרקע
 * 3. grayMask - מסכה אפורה מחוץ לגבול הילד
 * 4. boundaryOutline - קו גבול הילד (אדום)
 * 5. workObject - אובייקטי עבודה (מקליק ימני) - למעלה
 */
function reorderLayers() {
    if (!window.mapCanvas) return;

    const canvas = window.mapCanvas;
    const objects = canvas.getObjects();

    // מיון אובייקטים לפי סוג
    const backgroundLayers = [];
    const workObjects = [];
    let mask = null;
    let outline = null;
    let parentOutline = null;

    objects.forEach(obj => {
        if (obj.objectType === 'grayMask') {
            mask = obj;
        } else if (obj.objectType === 'boundaryOutline') {
            outline = obj;
        } else if (obj.objectType === 'parentBoundary') {
            parentOutline = obj;
        } else if (obj.objectType === 'backgroundLayer') {
            backgroundLayers.push(obj);
        } else if (obj.objectType === 'workObject') {
            workObjects.push(obj);
        }
    });

    // סידור: שכבות רקע למטה
    backgroundLayers.forEach(obj => canvas.sendToBack(obj));

    // מסכה אפורה מעל הרקע
    if (mask) canvas.bringToFront(mask);

    // גבול ההורה מעל המסכה (כדי שיהיה נראה)
    if (parentOutline) canvas.bringToFront(parentOutline);

    // קו גבול הילד מעל גבול ההורה
    if (outline) canvas.bringToFront(outline);

    // אובייקטי עבודה למעלה מכולם
    workObjects.forEach(obj => canvas.bringToFront(obj));

    canvas.renderAll();
}

/**
 * התחלת ציור פוליגון
 * REFACTORED: משתמש ב-PolygonDrawer (Step 7/15)
 */
function startDrawPolygon() {
    if (!isEditMode) return;

    // מניעת יצירת גבול נוסף אם כבר קיים (בדיקה גם בcanvas)
    const existingBoundary = boundaryOutline ||
        window.mapCanvas?.getObjects().find(obj => obj.objectType === 'boundaryOutline');

    if (existingBoundary) {
        alert('כבר קיים גבול מפה. יש למחוק את הגבול הקיים לפני יצירת חדש.');
        return;
    }

    if (window.mapPolygonDrawer) {
        window.mapPolygonDrawer.start();
        drawingPolygon = true;
        polygonPoints = [];
        if (window.mapState) {
            window.mapState.polygon.isDrawing = true;
            window.mapState.polygon.points = [];
        }
        document.getElementById('drawPolygonBtn').classList.add('active');
        document.getElementById('mapCanvas').style.cursor = 'crosshair';
    }
}

/**
 * טיפול בלחיצה על ה-Canvas
 * REFACTORED: משתמש ב-PolygonDrawer (Step 7/15)
 */
function handleCanvasClick(options) {
    if (!drawingPolygon || !isEditMode) return;

    if (window.mapPolygonDrawer && window.mapPolygonDrawer.isActive()) {
        window.mapPolygonDrawer.handleClick(options);
        return;
    }

    // Fallback: Should never happen (PolygonDrawer always loads)
    console.error('❌ PolygonDrawer not available for handleCanvasClick - this should not happen!');
}

/**
 * טיפול בתנועת עכבר - קו תצוגה מקדימה
 * REFACTORED: משתמש ב-PolygonDrawer (Step 7/15)
 */
function handleCanvasMouseMove(options) {
    // Use PolygonDrawer's state instead of global polygonPoints
    if (!window.mapPolygonDrawer || !window.mapPolygonDrawer.isActive()) return;

    // PolygonDrawer handles its own points check internally
    window.mapPolygonDrawer.handleMouseMove(options);
}

/**
 * בדיקה אם נקודה נמצאת בתוך פוליגון
 */
function isPointInPolygon(point, polygon) {
    if (!polygon || polygon.length < 3) return true; // אין פוליגון - כל נקודה תקינה

    let inside = false;
    const x = point.x, y = point.y;

    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const xi = polygon[i].x, yi = polygon[i].y;
        const xj = polygon[j].x, yj = polygon[j].y;

        if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
            inside = !inside;
        }
    }

    return inside;
}

/**
 * יצירת גבול ומסכה מנקודות פוליגון
 * Helper function called by PolygonDrawer.finish()
 * REFACTORED: Extracted from finishPolygon (Step 7/15)
 */
function createBoundaryFromPoints(polygonPoints) {
    if (!polygonPoints || polygonPoints.length < 3) {
        console.error('Not enough points to create boundary');
        return;
    }

    // חיתוך לפי גבול ההורה (אם קיים)
    if (parentBoundaryPoints && parentBoundaryPoints.length >= 3 && window.PolygonClipperClass) {
        if (window.PolygonClipperClass.needsClipping(polygonPoints, parentBoundaryPoints)) {
            console.log('✂️ Clipping new boundary to parent...');
            const clippedPoints = window.PolygonClipperClass.clip(polygonPoints, parentBoundaryPoints);

            if (clippedPoints && clippedPoints.length >= 3) {
                console.log(`✂️ Clipped: ${polygonPoints.length} points → ${clippedPoints.length} points`);
                polygonPoints = clippedPoints;
            } else {
                console.warn('⚠️ Clipping resulted in invalid polygon, keeping original');
            }
        }
    }

    // הסרת גבול/מסכה קודמים אם קיימים
    if (grayMask) {
        window.mapCanvas.remove(grayMask);
        grayMask = null;
        if (window.mapState) window.mapState.setGrayMask(null);
    }

    const canvas = window.mapCanvas;
    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;

    // יצירת ה-clipPath לשימוש עתידי
    const newClipPath = new fabric.Polygon(polygonPoints.map(p => ({x: p.x, y: p.y})), {
        absolutePositioned: true
    });
    boundaryClipPath = newClipPath;
    if (window.mapState) {
        window.mapState.canvas.boundary.clipPath = newClipPath;
    }

    // יצירת מסכה אפורה עם "חור" בצורת הפוליגון
    const maskSize = 10000; // גודל ענק שיכסה בכל מצב זום

    // בניית נתיב SVG: מלבן גדול + פוליגון הפוך
    let pathData = `M ${-maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${-maskSize} L ${canvasWidth + maskSize} ${canvasHeight + maskSize} L ${-maskSize} ${canvasHeight + maskSize} Z `;

    // הוספת הפוליגון כ"חור" (עיגול לפיקסלים שלמים)
    pathData += `M ${Math.round(polygonPoints[0].x)} ${Math.round(polygonPoints[0].y)} `;
    for (let i = polygonPoints.length - 1; i >= 0; i--) {
        pathData += `L ${Math.round(polygonPoints[i].x)} ${Math.round(polygonPoints[i].y)} `;
    }
    pathData += 'Z';

    const newGrayMask = new fabric.Path(pathData, {
        fill: 'rgba(100, 100, 100, 0.2)',
        fillRule: 'evenodd',
        stroke: null,
        strokeWidth: 0,
        selectable: false,
        evented: false,
        objectType: 'grayMask',
        // Ensure sharp edges
        strokeLineJoin: 'miter',
        strokeLineCap: 'square',
        objectCaching: false
    });
    grayMask = newGrayMask;
    if (window.mapState) {
        window.mapState.setGrayMask(newGrayMask);
    }

    // קו גבול סביב האזור הפעיל (עיגול לפיקסלים שלמים)
    const roundedPoints = polygonPoints.map(p => ({
        x: Math.round(p.x),
        y: Math.round(p.y)
    }));

    const newBoundaryOutline = new fabric.Polygon(roundedPoints, {
        fill: 'transparent',
        stroke: '#3b82f6',
        strokeWidth: 3,
        selectable: false,
        evented: false,
        objectType: 'boundaryOutline'
    });
    boundaryOutline = newBoundaryOutline;
    if (window.mapState) {
        window.mapState.setBoundaryOutline(newBoundaryOutline);
    }

    canvas.add(grayMask);
    canvas.add(boundaryOutline);

    // סידור שכבות נכון
    reorderLayers();
    lockSystemObjects();

    // איפוס משתנים
    drawingPolygon = false;
    polygonPoints = [];
    if (window.mapState) {
        window.mapState.polygon.isDrawing = false;
        window.mapState.polygon.points = [];
    }
    document.getElementById('drawPolygonBtn')?.classList.remove('active');
    document.getElementById('mapCanvas').style.cursor = 'default';

    // הצג כפתורי עריכה ומחיקה
    const editBtn = document.getElementById('editBoundaryBtn');
    const deleteBtn = document.getElementById('deleteBoundaryBtn');
    if (editBtn) editBtn.classList.remove('hidden-btn');
    if (deleteBtn) deleteBtn.classList.remove('hidden-btn');

    saveCanvasState();
}

/**
 * סיום ציור פוליגון
 * REFACTORED: משתמש ב-PolygonDrawer (Step 7/15)
 */
function finishPolygon() {
    if (window.mapPolygonDrawer && window.mapPolygonDrawer.isActive()) {
        const points = window.mapPolygonDrawer.finish();
        // finish() will call createBoundaryFromPoints via onFinish callback
        return;
    }

    // Fallback: Should never happen (PolygonDrawer always loads)
    console.error('❌ PolygonDrawer not available for finishPolygon - this should not happen!');
}

/**
 * ביטול ציור פוליגון
 * REFACTORED: משתמש ב-PolygonDrawer (Step 7/15)
 */
function cancelPolygonDrawing() {
    if (window.mapPolygonDrawer && window.mapPolygonDrawer.isActive()) {
        window.mapPolygonDrawer.cancel();
        // cancel() will call onCancel callback
        return;
    }

    // Fallback: Should never happen (PolygonDrawer always loads)
    console.error('❌ PolygonDrawer not available for cancelPolygonDrawing - this should not happen!');
}

/**
 * הפעלה/כיבוי מצב עריכת גבול
 * REFACTORED: משתמש ב-BoundaryEditor (Step 8/15)
 */
function toggleBoundaryEdit() {
    // Sync with canvas if needed
    if (!boundaryOutline && window.mapCanvas) {
        const boundary = window.mapCanvas.getObjects().find(obj => obj.objectType === 'boundaryOutline');
        if (boundary) {
            boundaryOutline = boundary;
            if (window.mapState) window.mapState.setBoundaryOutline(boundary);
        }
    }
    if (!grayMask && window.mapCanvas) {
        const mask = window.mapCanvas.getObjects().find(obj => obj.objectType === 'grayMask');
        if (mask) {
            grayMask = mask;
            if (window.mapState) window.mapState.setGrayMask(mask);
        }
    }

    if (!boundaryOutline || !grayMask) return;

    isBoundaryEditMode = !isBoundaryEditMode;
    if (window.mapState) {
        window.mapState.canvas.boundary.isEditMode = isBoundaryEditMode;
    }

    const editBtn = document.getElementById('editBoundaryBtn');

    if (isBoundaryEditMode) {
        // הפעל מצב עריכה
        editBtn.classList.add('active');

        if (window.mapBoundaryEditor) {
            window.mapBoundaryEditor.enableEditMode(boundaryOutline, grayMask, boundaryClipPath);
        }

        // הצג את פאנל עריכת הנקודות
        if (window.mapBoundaryEditPanel) {
            window.mapBoundaryEditPanel.show(boundaryOutline, grayMask);
        }
    } else {
        // כבה מצב עריכה
        editBtn.classList.remove('active');

        if (window.mapBoundaryEditor) {
            window.mapBoundaryEditor.disableEditMode();
        }

        // הסתר את פאנל עריכת הנקודות
        if (window.mapBoundaryEditPanel) {
            window.mapBoundaryEditPanel.hide();
        }
    }

    window.mapCanvas.renderAll();
}

/**
 * הפעלה/כיבוי מצב עריכת תמונת רקע
 * REFACTORED: משתמש ב-BackgroundEditor (Step 9/15)
 */
function toggleBackgroundEdit() {
    // Ensure BackgroundEditor is synced with canvas before toggling
    if (window.mapBackgroundEditor && window.mapCanvas) {
        const bgLayer = window.mapCanvas.getObjects().find(obj => obj.objectType === 'backgroundLayer');
        if (bgLayer && !window.mapBackgroundEditor.getImage()) {
            // BackgroundEditor is out of sync - update it
            window.mapBackgroundEditor.setBackgroundImage(bgLayer);
        }
        // Also sync global variable if needed
        if (bgLayer && !backgroundImage) {
            backgroundImage = bgLayer;
            if (window.mapState) window.mapState.setBackgroundImage(bgLayer);
        }
    }

    // Check if there's a background to edit
    if (!backgroundImage && (!window.mapBackgroundEditor || !window.mapBackgroundEditor.getImage())) {
        return;
    }

    isBackgroundEditMode = !isBackgroundEditMode;

    if (window.mapBackgroundEditor) {
        if (isBackgroundEditMode) {
            window.mapBackgroundEditor.enableEditMode();
        } else {
            window.mapBackgroundEditor.disableEditMode();
            lockSystemObjects();
        }
    }

    // Update button state
    const editBtn = document.getElementById('editBackgroundBtn');
    if (editBtn) {
        if (isBackgroundEditMode) {
            editBtn.classList.add('active');
        } else {
            editBtn.classList.remove('active');
        }
    }
}

/**
 * מחיקת תמונת רקע
 * REFACTORED: משתמש ב-BackgroundEditor (Step 9/15)
 */
function deleteBackground() {
    if (!window.mapCanvas) return;

    // Always sync BackgroundEditor with canvas if it's out of sync
    // (same pattern as toggleBackgroundEdit - check BackgroundEditor, not just global)
    if (window.mapBackgroundEditor && !window.mapBackgroundEditor.getImage()) {
        const bgLayer = window.mapCanvas.getObjects().find(obj => obj.objectType === 'backgroundLayer');
        if (bgLayer) {
            window.mapBackgroundEditor.setBackgroundImage(bgLayer);
        }
    }

    // Also sync global variable if needed
    if (!backgroundImage) {
        const bgLayer = window.mapCanvas.getObjects().find(obj => obj.objectType === 'backgroundLayer');
        if (bgLayer) {
            backgroundImage = bgLayer;
            if (window.mapState) window.mapState.setBackgroundImage(bgLayer);
        }
    }

    // Check if there's anything to delete
    const hasBackground = backgroundImage || (window.mapBackgroundEditor && window.mapBackgroundEditor.getImage());
    if (!hasBackground) return;

    // כיבוי מצב עריכה אם פעיל
    if (isBackgroundEditMode) {
        isBackgroundEditMode = false;
        if (window.mapState) {
            window.mapState.canvas.background.isEditMode = false;
        }
        const editBtn = document.getElementById('editBackgroundBtn');
        if (editBtn) editBtn.classList.remove('active');
    }

    if (window.mapBackgroundEditor) {
        window.mapBackgroundEditor.delete();
        // onDelete callback will handle global variable updates
    }
}

/**
 * מחיקת גבול מפה
 * REFACTORED: משתמש ב-BoundaryEditor (Step 8/15)
 */
function deleteBoundary() {
    if (!window.mapCanvas) return;

    // כיבוי מצב עריכה אם פעיל
    if (isBoundaryEditMode) {
        isBoundaryEditMode = false;
        if (window.mapState) {
            window.mapState.canvas.boundary.isEditMode = false;
        }
        const editBtn = document.getElementById('editBoundaryBtn');
        if (editBtn) editBtn.classList.remove('active');
    }

    if (window.mapBoundaryEditor) {
        window.mapBoundaryEditor.delete();
        // onDelete callback will handle global variable updates
    }
}

/**
 * שמירת המפה לשרת
 */
async function saveMapData() {
    // Get entity from StateManager or fallback to old variables
    const entity = window.mapState?.getCurrentEntity() || { type: currentEntityType, id: currentUnicId };

    if (!window.mapCanvas || !entity.type || !entity.id) return;

    const saveBtn = document.querySelector('.map-tool-btn[onclick="saveMapData()"]');
    const originalContent = saveBtn ? saveBtn.innerHTML : '';

    try {
        // הצג מצב שמירה
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '⏳';
        }

        const mapData = {
            canvasJSON: window.mapCanvas.toJSON(['objectType', 'polygonPoint', 'polygonLine']),
            zoom: currentZoom,
            savedAt: new Date().toISOString()
        };

        const response = await fetch(
            `api/cemetery-hierarchy.php?action=save_map&type=${entity.type}&id=${entity.id}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ mapData })
            }
        );

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'שגיאה בשמירת המפה');
        }

        // הצג הודעת הצלחה
        if (saveBtn) {
            saveBtn.innerHTML = '✓';
            setTimeout(() => {
                saveBtn.innerHTML = originalContent;
                saveBtn.disabled = false;
            }, 1500);
        }

        console.log('Map saved successfully');

    } catch (error) {
        console.error('Error saving map:', error);
        alert('שגיאה בשמירת המפה: ' + error.message);

        if (saveBtn) {
            saveBtn.innerHTML = originalContent;
            saveBtn.disabled = false;
        }
    }
}

/**
 * סגירת פופאפ המפה
 * Uses MapPopup if available, otherwise falls back to old implementation
 */
function closeMapPopup() {
    if (window.mapPopupInstance) {
        window.mapPopupInstance.close();
    }
}

/**
 * ניקוי state של המפה (helper function)
 * REFACTORED: Extracted for use by MapPopup (Step 13/15)
 */
function cleanupMapState() {
    // Dispose canvas
    if (window.mapCanvas) {
        window.mapCanvas.dispose();
        window.mapCanvas = null;
    }

    // Clear all module instances
    window.mapToolbar = null;
    window.mapZoomControls = null;
    window.mapPolygonDrawer = null;
    window.mapBoundaryEditor = null;
    window.mapBackgroundEditor = null;
    window.mapHistoryManager = null;
    window.mapEditModeToggle = null;
    window.mapContextMenu = null;

    // Clear all state variables
    backgroundImage = null;
    if (window.mapState) window.mapState.setBackgroundImage(null);

    isEditMode = false;
    if (window.mapState) {
        window.mapState.isEditMode = false;
    }

    drawingPolygon = false;
    polygonPoints = [];
    previewLine = null;
    if (window.mapState) {
        window.mapState.polygon.isDrawing = false;
        window.mapState.polygon.points = [];
        window.mapState.polygon.previewLine = null;
    }

    boundaryClipPath = null;
    grayMask = null;
    boundaryOutline = null;
    if (window.mapState) {
        window.mapState.canvas.boundary.clipPath = null;
        window.mapState.setGrayMask(null);
        window.mapState.setBoundaryOutline(null);
    }

    isBoundaryEditMode = false;
    isBackgroundEditMode = false;
    if (window.mapState) {
        window.mapState.canvas.boundary.isEditMode = false;
        window.mapState.canvas.background.isEditMode = false;
    }

    currentPdfContext = null;
    currentPdfDoc = null;
    if (window.mapState) {
        window.mapState.canvas.background.pdfContext = null;
        window.mapState.canvas.background.pdfDoc = null;
    }

    // Reset undo/redo history
    canvasHistory = [];
    historyIndex = -1;
    if (window.mapState) {
        window.mapState.history.states = [];
        window.mapState.history.currentIndex = -1;
    }

    console.log('🗑️ Map state cleaned up');
}

/**
 * מעבר למצב מסך מלא / יציאה ממסך מלא
 */
function toggleMapFullscreen() {
    if (window.mapPopupInstance) {
        window.mapPopupInstance.toggleFullscreen();
    }
}

/**
 * הגדלת זום
 * REFACTORED: משתמש ב-ZoomControls (Step 5/15)
 */
function zoomMapIn() {
    if (window.mapZoomControls) {
        window.mapZoomControls.zoomIn();
    } else {
        // Fallback: Should never happen (ZoomControls always loads)
        console.error('❌ ZoomControls not available for zoom in - this should not happen!');
    }
}

/**
 * הקטנת זום
 * REFACTORED: משתמש ב-ZoomControls (Step 5/15)
 */
function zoomMapOut() {
    if (window.mapZoomControls) {
        window.mapZoomControls.zoomOut();
    } else {
        // Fallback: Should never happen (ZoomControls always loads)
        console.error('❌ ZoomControls not available for zoom out - this should not happen!');
    }
}

function updateZoomDisplay() {
    // REFACTORED: Use Toolbar.updateZoomDisplay() if available (Step 4/15)
    if (window.mapToolbar && typeof window.mapToolbar.updateZoomDisplay === 'function') {
        window.mapToolbar.updateZoomDisplay(currentZoom);
    }
}

/**
 * עריכת אחוז זום ידנית
 * REFACTORED: משתמש ב-ZoomControls (Step 5/15)
 */
function editZoomLevel() {
    const el = document.getElementById('mapZoomLevel');
    if (!el) return;

    if (window.mapZoomControls) {
        window.mapZoomControls.enableManualEdit(el);
    } else {
        console.error('❌ ZoomControls not available for manual edit - this should not happen!');
    }
}

// קיצורי מקלדת
document.addEventListener('keydown', function(e) {
    // ESC לסגירה
    if (e.key === 'Escape') {
        if (drawingPolygon) {
            cancelPolygonDrawing();
        } else {
            closeMapPopup();
            closeMapLauncher();
        }
    }

    // Ctrl+Z - ביטול פעולה
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
        if (isEditMode && window.mapCanvas) {
            e.preventDefault();
            undoCanvas();
        }
    }

    // Ctrl+Y או Ctrl+Shift+Z - ביצוע שוב
    if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
        if (isEditMode && window.mapCanvas) {
            e.preventDefault();
            redoCanvas();
        }
    }
});

// דאבל קליק לסיום פוליגון
document.addEventListener('dblclick', function(e) {
    if (drawingPolygon && polygonPoints.length >= 3) {
        finishPolygon();
    }
});

// משתנה לשמירת מיקום הקליק הימני
let contextMenuPosition = { x: 0, y: 0 };

/**
 * טיפול בקליק ימני על הקנבס
 */
function handleCanvasRightClick(e) {
    e.preventDefault();
    e.stopPropagation();

    // אם במצב עריכת נקודות של גבול - לא מציגים תפריט אייטמים
    // התפריט להסרת נקודה יטופל ע"י BoundaryEditPanel
    if (window.mapBoundaryEditPanel?.getState()?.isPointEditMode) {
        return;
    }

    if (!isEditMode || drawingPolygon) {
        hideContextMenu();
        return;
    }

    // קבל מיקום יחסית לקנבס באמצעות Fabric.js
    if (!window.mapCanvas) return;

    // מצא את ה-upper-canvas של Fabric
    const upperCanvas = document.querySelector('.upper-canvas');
    if (!upperCanvas) return;

    const rect = upperCanvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    // שמור מיקום להוספת אובייקטים
    contextMenuPosition = { x, y };

    // בדוק אם לחצנו על אובייקט קיים
    const clickedObject = window.mapCanvas.findTarget(e, false);

    if (clickedObject) {
        // התעלם מאובייקטי מערכת (מסכה, גבול, רקע) - תמיד מתייחסים אליהם כרקע
        if (clickedObject.objectType === 'grayMask' ||
            clickedObject.objectType === 'boundaryOutline' ||
            clickedObject.objectType === 'backgroundLayer') {
            // לא מציגים תפריט אובייקט - ממשיכים לתפריט הרגיל
        } else if (clickedObject.objectType === 'workObject') {
            // לחצנו על אובייקט עבודה - הצג תפריט עם אפשרות מחיקה
            showObjectContextMenu(e.clientX, e.clientY, clickedObject);
            return false;
        }
    }

    // בדוק אם הנקודה בתוך הגבול
    const isInside = isPointInsideBoundary(x, y);

    // הצג תפריט הוספה רגיל
    showContextMenu(e.clientX, e.clientY, isInside);

    return false;
}

/**
 * בדיקה אם יש גבול מוגדר
 */
function hasBoundary() {
    return boundaryOutline && boundaryOutline.points && boundaryOutline.points.length > 0;
}

/**
 * בדיקה אם נקודה נמצאת בתוך הגבול
 * משתמש באלגוריתם Ray Casting
 */
function isPointInsideBoundary(x, y) {
    // אם אין גבול מוגדר - אסור להוסיף פריטים
    if (!hasBoundary()) {
        return false;
    }

    // קבל את הנקודות של הגבול (עם טרנספורמציות)
    const matrix = boundaryOutline.calcTransformMatrix();
    const points = boundaryOutline.points.map(p => {
        const transformed = fabric.util.transformPoint(
            { x: p.x - boundaryOutline.pathOffset.x, y: p.y - boundaryOutline.pathOffset.y },
            matrix
        );
        return transformed;
    });

    // אלגוריתם Ray Casting
    let inside = false;
    for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
        const xi = points[i].x, yi = points[i].y;
        const xj = points[j].x, yj = points[j].y;

        const intersect = ((yi > y) !== (yj > y)) &&
            (x < (xj - xi) * (y - yi) / (yj - yi) + xi);

        if (intersect) inside = !inside;
    }

    return inside;
}

/**
 * הצגת תפריט הקשר כללי (canvas)
 * Uses ContextMenu if available, otherwise falls back to old implementation
 */
function showContextMenu(clientX, clientY, isInsideBoundary) {
    // Use ContextMenu if available
    if (window.mapContextMenu) {
        window.mapContextMenu.showForEmpty(clientX, clientY, isInsideBoundary);
        return;
    }

    // Fallback: Should never happen (ContextMenu always loads)
    console.error('❌ ContextMenu not available - this should not happen!');
}

/**
 * הסתרת תפריט קליק ימני
 */
function hideContextMenu() {
    // Use ContextMenu if available
    if (window.mapContextMenu) {
        window.mapContextMenu.hide();
    }
    // Silently ignore if ContextMenu not available (already cleaned up)
}

/**
 * טיפול בפעולות של תפריט ההקשר
 * REFACTORED: מרכז את כל הפעולות במקום אחד (Step 12/15)
 */
function handleContextMenuAction(action, data) {
    switch (action) {
        // Canvas actions (add items)
        case 'addImage':
            addImageFromMenu();
            break;
        case 'addText':
            addTextFromMenu();
            break;
        case 'addRect':
            addShapeFromMenu('rect');
            break;
        case 'addCircle':
            addShapeFromMenu('circle');
            break;
        case 'addLine':
            addShapeFromMenu('line');
            break;

        // Object actions
        case 'deleteObject':
            deleteContextMenuObject();
            break;
        case 'bringToFront':
            bringObjectToFront();
            break;
        case 'sendToBack':
            sendObjectToBack();
            break;

        default:
            console.warn('Unknown context menu action:', action);
    }
}

// משתנה לשמירת האובייקט שנלחץ עליו
let contextMenuTargetObject = null;

/**
 * הצגת תפריט קליק ימני לאובייקט (עם אפשרות מחיקה)
 * Uses ContextMenu if available, otherwise falls back to old implementation
 */
function showObjectContextMenu(clientX, clientY, targetObject) {
    // Use ContextMenu if available
    if (window.mapContextMenu) {
        contextMenuTargetObject = targetObject;
        window.mapContextMenu.showForObject(clientX, clientY, targetObject);
        return;
    }

    // Fallback: Should never happen (ContextMenu always loads)
    console.error('❌ ContextMenu not available for showForObject - this should not happen!');
}

/**
 * מחיקת האובייקט שנבחר בתפריט
 */
function deleteContextMenuObject() {
    hideContextMenu();
    if (!contextMenuTargetObject || !window.mapCanvas) return;

    window.mapCanvas.remove(contextMenuTargetObject);
    window.mapCanvas.renderAll();
    saveCanvasState();
    contextMenuTargetObject = null;
}

/**
 * הבאת אובייקט לחזית (מעל אובייקטי עבודה אחרים)
 */
function bringObjectToFront() {
    hideContextMenu();
    if (!contextMenuTargetObject || !window.mapCanvas) return;

    window.mapCanvas.bringToFront(contextMenuTargetObject);
    reorderLayers(); // המסכה תמיד תישאר למעלה
    window.mapCanvas.renderAll();
    saveCanvasState();
    contextMenuTargetObject = null;
}

/**
 * שליחת אובייקט לרקע (מתחת לאובייקטי עבודה אחרים, אבל מעל שכבת הרקע)
 */
function sendObjectToBack() {
    hideContextMenu();
    if (!contextMenuTargetObject || !window.mapCanvas) return;

    window.mapCanvas.sendToBack(contextMenuTargetObject);
    reorderLayers(); // שכבת הרקע תישאר למטה
    window.mapCanvas.renderAll();
    saveCanvasState();
    contextMenuTargetObject = null;
}

/**
 * הוספת תמונה מהתפריט
 */
function addImageFromMenu() {
    hideContextMenu();
    document.getElementById('addImageInput').click();
}

/**
 * טיפול בהוספת תמונה
 */
function handleAddImage(event) {
    const file = event.target.files[0];
    if (!file || !window.mapCanvas) return;

    const isPdf = file.type === 'application/pdf';

    if (isPdf) {
        // טיפול בקובץ PDF
        handlePdfUpload(file, 'workObject');
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        fabric.Image.fromURL(e.target.result, function(img) {
            // הקטנה אם התמונה גדולה מדי
            const maxSize = 200;
            let scale = 1;
            if (img.width > maxSize || img.height > maxSize) {
                scale = maxSize / Math.max(img.width, img.height);
            }

            img.set({
                left: contextMenuPosition.x,
                top: contextMenuPosition.y,
                scaleX: scale,
                scaleY: scale,
                selectable: true,
                hasControls: true,
                hasBorders: true,
                objectType: 'workObject'
            });

            window.mapCanvas.add(img);
            reorderLayers();
            window.mapCanvas.setActiveObject(img);
            window.mapCanvas.renderAll();
            saveCanvasState();
        });
    };
    reader.readAsDataURL(file);
    event.target.value = '';
}

/**
 * הוספת טקסט מהתפריט
 */
function addTextFromMenu() {
    hideContextMenu();

    if (!window.mapCanvas) return;

    const text = new fabric.IText('טקסט חדש', {
        left: contextMenuPosition.x,
        top: contextMenuPosition.y,
        fontSize: 18,
        fill: '#374151',
        fontFamily: 'Arial, sans-serif',
        selectable: true,
        hasControls: true,
        hasBorders: true,
        objectType: 'workObject'
    });

    window.mapCanvas.add(text);
    reorderLayers();
    window.mapCanvas.setActiveObject(text);
    text.enterEditing();
    window.mapCanvas.renderAll();
    saveCanvasState();
}

/**
 * הוספת צורה מהתפריט
 */
function addShapeFromMenu(shapeType) {
    hideContextMenu();

    if (!window.mapCanvas) return;

    let shape;

    switch (shapeType) {
        case 'rect':
            shape = new fabric.Rect({
                left: contextMenuPosition.x,
                top: contextMenuPosition.y,
                width: 100,
                height: 60,
                fill: 'rgba(59, 130, 246, 0.3)',
                stroke: '#3b82f6',
                strokeWidth: 2,
                rx: 4,
                ry: 4,
                objectType: 'workObject'
            });
            break;

        case 'circle':
            shape = new fabric.Circle({
                left: contextMenuPosition.x,
                top: contextMenuPosition.y,
                radius: 40,
                fill: 'rgba(16, 185, 129, 0.3)',
                stroke: '#10b981',
                strokeWidth: 2,
                objectType: 'workObject'
            });
            break;

        case 'line':
            shape = new fabric.Line([
                contextMenuPosition.x,
                contextMenuPosition.y,
                contextMenuPosition.x + 100,
                contextMenuPosition.y
            ], {
                stroke: '#6b7280',
                strokeWidth: 3,
                objectType: 'workObject'
            });
            break;
    }

    if (shape) {
        window.mapCanvas.add(shape);
        reorderLayers();
        window.mapCanvas.setActiveObject(shape);
        window.mapCanvas.renderAll();
        saveCanvasState();
    }
}


/**
 * טיפול בהעלאת קובץ PDF
 */
async function handlePdfUpload(file, context) {
    if (typeof pdfjsLib === 'undefined') {
        alert('ספריית PDF.js לא נטענה. נסה לרענן את הדף.');
        return;
    }

    currentPdfContext = context;
    if (window.mapState) {
        window.mapState.canvas.background.pdfContext = context;
    }

    // הצג מודל בחירת עמוד
    const modal = document.getElementById('pdfPageSelectorModal');
    const container = document.getElementById('pdfPagesContainer');
    const fileNameEl = document.getElementById('pdfFileName');
    const pageCountEl = document.getElementById('pdfPageCount');

    if (!modal || !container) return;

    // הצג loading
    fileNameEl.textContent = file.name;
    pageCountEl.textContent = 'טוען...';
    container.innerHTML = `
        <div class="pdf-loading">
            <div class="pdf-loading-spinner"></div>
            <div>טוען PDF...</div>
        </div>
    `;
    modal.style.display = 'flex';

    try {
        // טען את ה-PDF
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        currentPdfDoc = pdf;
        if (window.mapState) {
            window.mapState.canvas.background.pdfDoc = pdf;
        }

        const numPages = pdf.numPages;
        pageCountEl.textContent = `${numPages} עמודים`;

        // אם יש רק עמוד אחד - בחר אוטומטית
        if (numPages === 1) {
            closePdfSelector();
            await renderPdfPageToCanvas(1);
            return;
        }

        // רנדר תמונות ממוזערות לכל העמודים
        container.innerHTML = '';

        for (let pageNum = 1; pageNum <= numPages; pageNum++) {
            const thumbDiv = document.createElement('div');
            thumbDiv.className = 'pdf-page-thumb';
            thumbDiv.onclick = () => selectPdfPage(pageNum);

            const canvas = document.createElement('canvas');
            const pageNumDiv = document.createElement('div');
            pageNumDiv.className = 'pdf-page-number';
            pageNumDiv.textContent = `עמוד ${pageNum}`;

            thumbDiv.appendChild(canvas);
            thumbDiv.appendChild(pageNumDiv);
            container.appendChild(thumbDiv);

            // רנדר תמונה ממוזערת
            renderPdfThumbnail(pdf, pageNum, canvas);
        }

    } catch (error) {
        console.error('Error loading PDF:', error);
        container.innerHTML = `
            <div class="pdf-loading">
                <div style="color: #dc2626;">שגיאה בטעינת PDF</div>
                <div style="font-size: 12px; margin-top: 8px;">${error.message}</div>
            </div>
        `;
    }
}

/**
 * רנדור תמונה ממוזערת של עמוד PDF
 */
async function renderPdfThumbnail(pdf, pageNum, canvas) {
    try {
        const page = await pdf.getPage(pageNum);
        const viewport = page.getViewport({ scale: 0.3 });

        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const ctx = canvas.getContext('2d');
        await page.render({
            canvasContext: ctx,
            viewport: viewport
        }).promise;

    } catch (error) {
        console.error(`Error rendering thumbnail for page ${pageNum}:`, error);
    }
}

/**
 * בחירת עמוד PDF
 */
async function selectPdfPage(pageNum) {
    closePdfSelector();
    await renderPdfPageToCanvas(pageNum);
}

/**
 * רנדור עמוד PDF כתמונה ל-canvas
 */
async function renderPdfPageToCanvas(pageNum) {
    if (!currentPdfDoc || !window.mapCanvas) return;

    // שמור את ה-context לפני הקריאה האסינכרונית!
    const pdfContext = currentPdfContext;

    try {
        const page = await currentPdfDoc.getPage(pageNum);

        // רנדור באיכות גבוהה
        const scale = 2;
        const viewport = page.getViewport({ scale });

        // יצירת canvas זמני לרנדור
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = viewport.width;
        tempCanvas.height = viewport.height;

        const ctx = tempCanvas.getContext('2d');
        await page.render({
            canvasContext: ctx,
            viewport: viewport
        }).promise;

        // המר ל-data URL
        const dataUrl = tempCanvas.toDataURL('image/png');

        // הוסף לקנבס הראשי
        fabric.Image.fromURL(dataUrl, function(img) {
            const canvas = window.mapCanvas;

            if (pdfContext === 'background') {
                // הסרת תמונת רקע קודמת
                if (backgroundImage) {
                    canvas.remove(backgroundImage);
                }

                // התאמת גודל התמונה
                const imgScale = Math.min(
                    (canvas.width * 0.9) / img.width,
                    (canvas.height * 0.9) / img.height
                );

                img.set({
                    left: canvas.width / 2,
                    top: canvas.height / 2,
                    originX: 'center',
                    originY: 'center',
                    scaleX: imgScale,
                    scaleY: imgScale,
                    selectable: true, // מופעל אוטומטית במצב עריכה
                    evented: true,
                    hasControls: true,
                    hasBorders: true,
                    lockRotation: false,
                    objectType: 'backgroundLayer'
                });

                canvas.add(img);
                backgroundImage = img;
                if (window.mapState) window.mapState.setBackgroundImage(img);

                // הצג כפתורי עריכה ומחיקה של רקע
                const editBgBtn = document.getElementById('editBackgroundBtn');
                const deleteBgBtn = document.getElementById('deleteBackgroundBtn');
                if (editBgBtn) {
                    editBgBtn.classList.remove('hidden-btn');
                    editBgBtn.classList.add('active'); // מצב עריכה פעיל
                }
                if (deleteBgBtn) deleteBgBtn.classList.remove('hidden-btn');

                // הפעל מצב עריכת רקע אוטומטית
                isBackgroundEditMode = true;
                if (window.mapState) {
                    window.mapState.canvas.background.isEditMode = true;
                }

                // וודא שהמסכה נעולה
                if (grayMask) {
                    grayMask.set({
                        selectable: false,
                        evented: false,
                        hasControls: false,
                        hasBorders: false
                    });
                }

                // בחר את התמונה
                canvas.setActiveObject(img);

            } else {
                // הוספה כאובייקט עבודה
                const maxSize = 300;
                let imgScale = 1;
                if (img.width > maxSize || img.height > maxSize) {
                    imgScale = maxSize / Math.max(img.width, img.height);
                }

                img.set({
                    left: contextMenuPosition.x,
                    top: contextMenuPosition.y,
                    scaleX: imgScale,
                    scaleY: imgScale,
                    selectable: true,
                    hasControls: true,
                    hasBorders: true,
                    objectType: 'workObject'
                });

                canvas.add(img);
                canvas.setActiveObject(img);
            }

            reorderLayers();
            canvas.renderAll();
            saveCanvasState();
        });

    } catch (error) {
        console.error('Error rendering PDF page:', error);
        alert('שגיאה ברנדור עמוד PDF');
    }

    // נקה
    currentPdfDoc = null;
    currentPdfContext = null;
    if (window.mapState) {
        window.mapState.canvas.background.pdfDoc = null;
        window.mapState.canvas.background.pdfContext = null;
    }
}

/**
 * סגירת מודל בחירת עמוד PDF
 * לא מאפסים את currentPdfDoc כאן - זה נעשה אחרי הרנדור
 */
function closePdfSelector() {
    const modal = document.getElementById('pdfPageSelectorModal');
    if (modal) {
        modal.style.display = 'none';
    }
}


/**
 * שמירת מצב הקנבס להיסטוריה
 * Uses HistoryManager if available, otherwise falls back to old implementation
 */
function saveCanvasState() {
    if (!window.mapCanvas) return;

    // Use HistoryManager if available
    if (window.mapHistoryManager) {
        window.mapHistoryManager.save();
        return;
    }

    // Fallback: Should never happen (HistoryManager always loads)
    console.error('❌ HistoryManager not available for save - this should not happen!');
}

/**
 * ביטול פעולה אחרונה
 * Uses HistoryManager if available, otherwise falls back to old implementation
 */
function undoCanvas() {
    if (!window.mapCanvas) return;

    // Use HistoryManager if available
    if (window.mapHistoryManager) {
        window.mapHistoryManager.undo();
        return;
    }

    // Fallback: Should never happen (HistoryManager always loads)
    console.error('❌ HistoryManager not available for undo - this should not happen!');
}

/**
 * ביצוע שוב פעולה שבוטלה
 * Uses HistoryManager if available, otherwise falls back to old implementation
 */
function redoCanvas() {
    if (!window.mapCanvas) return;

    // Use HistoryManager if available
    if (window.mapHistoryManager) {
        window.mapHistoryManager.redo();
        return;
    }

    // Fallback: Should never happen (HistoryManager always loads)
    console.error('❌ HistoryManager not available for redo - this should not happen!');
}

/**
 * שחזור מצב קנבס
 */
function restoreCanvasState(state) {
    if (!state) return;

    window.mapCanvas.loadFromJSON(JSON.parse(state), function() {
        // עדכן משתנים גלובליים לפי האובייקטים שנטענו
        backgroundImage = null;
        if (window.mapState) window.mapState.setBackgroundImage(null);
        grayMask = null;
        boundaryOutline = null;
        if (window.mapState) {
            window.mapState.setGrayMask(null);
            window.mapState.setBoundaryOutline(null);
        }

        window.mapCanvas.getObjects().forEach(obj => {
            if (obj.objectType === 'backgroundLayer') {
                backgroundImage = obj;
                if (window.mapState) window.mapState.setBackgroundImage(obj);
            } else if (obj.objectType === 'grayMask') {
                grayMask = obj;
                if (window.mapState) window.mapState.setGrayMask(obj);
            } else if (obj.objectType === 'boundaryOutline') {
                boundaryOutline = obj;
                if (window.mapState) window.mapState.setBoundaryOutline(obj);
            }
        });

        // נעילת אובייקטי מערכת - תמיד נעולים אחרי שחזור
        lockSystemObjects();

        // עדכן מצב כפתורים
        updateToolbarButtons();
        window.mapCanvas.renderAll();
        updateUndoRedoButtons();
    });
}

/**
 * נעילת אובייקטי מערכת (מסכה, גבול, רקע)
 * המסכה תמיד נעולה. הגבול והרקע נעולים אלא אם הם במצב עריכה.
 */
function lockSystemObjects() {
    // המסכה האפורה תמיד נעולה לחלוטין
    if (grayMask) {
        grayMask.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });
    }

    // הגבול נעול אלא אם במצב עריכה
    if (boundaryOutline && !isBoundaryEditMode) {
        boundaryOutline.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });
    }

    // תמונת רקע נעולה אלא אם במצב עריכה
    if (backgroundImage && !isBackgroundEditMode) {
        backgroundImage.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });
    }
}

/**
 * עדכון מצב כפתורי undo/redo
 */
function updateUndoRedoButtons() {
    const undoBtn = document.getElementById('undoBtn');
    const redoBtn = document.getElementById('redoBtn');

    // Use HistoryManager if available
    if (window.mapHistoryManager) {
        const state = window.mapHistoryManager.getState();
        if (undoBtn) {
            undoBtn.disabled = !state.canUndo;
        }
        if (redoBtn) {
            redoBtn.disabled = !state.canRedo;
        }
        return;
    }

    // Fallback: Should never happen (HistoryManager always loads)
    console.error('❌ HistoryManager not available for button update - this should not happen!');
}

/**
 * עדכון כפתורי הכלים לפי מצב הקנבס
 */
function updateToolbarButtons() {
    // כפתורי רקע
    const editBgBtn = document.getElementById('editBackgroundBtn');
    const deleteBgBtn = document.getElementById('deleteBackgroundBtn');
    if (backgroundImage) {
        if (editBgBtn) editBgBtn.classList.remove('hidden-btn');
        if (deleteBgBtn) deleteBgBtn.classList.remove('hidden-btn');
    } else {
        if (editBgBtn) {
            editBgBtn.classList.add('hidden-btn');
            editBgBtn.classList.remove('active');
        }
        if (deleteBgBtn) deleteBgBtn.classList.add('hidden-btn');
    }

    // כפתורי גבול
    const editBoundaryBtn = document.getElementById('editBoundaryBtn');
    const deleteBoundaryBtn = document.getElementById('deleteBoundaryBtn');
    if (boundaryOutline) {
        if (editBoundaryBtn) editBoundaryBtn.classList.remove('hidden-btn');
        if (deleteBoundaryBtn) deleteBoundaryBtn.classList.remove('hidden-btn');
    } else {
        if (editBoundaryBtn) {
            editBoundaryBtn.classList.add('hidden-btn');
            editBoundaryBtn.classList.remove('active');
        }
        if (deleteBoundaryBtn) deleteBoundaryBtn.classList.add('hidden-btn');
    }
}

/**
 * איפוס היסטוריה
 */
function resetHistory() {
    canvasHistory = [];
    historyIndex = -1;
    if (window.mapState) {
        window.mapState.history.states = [];
        window.mapState.history.currentIndex = -1;
    }
    updateUndoRedoButtons();
}
