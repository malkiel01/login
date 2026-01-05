/**
 * Map Launcher - Orchestrator
 * Version: 4.0.0 - Refactored to modular architecture
 *
 * This file is now a slim orchestrator that loads and coordinates modules.
 * All functionality has been extracted into specialized modules.
 */

// ========================================
// MODULE IMPORTS
// Load all required modules
// ========================================

(async function initStateManager() {
    try {
        const { StateManager } = await import('../map/core/StateManager.js');
        window.mapState = new StateManager();
        console.log('✅ StateManager loaded');
    } catch (error) {
        console.error('❌ Failed to load StateManager:', error);
        window.mapState = {
            zoom: 1,
            getZoom: function() { return this.zoom; },
            setZoom: function(z) { this.zoom = z; }
        };
    }
})();

(async function initEntitySelector() {
    try {
        const { EntitySelector } = await import('../map/launcher/EntitySelector.js');
        window.entitySelector = new EntitySelector({ apiEndpoint: 'api/map-api.php' });
        console.log('✅ EntitySelector loaded');
    } catch (error) {
        console.error('❌ Failed to load EntitySelector:', error);
    }
})();

(async function initLauncherModal() {
    try {
        const { LauncherModal } = await import('../map/launcher/LauncherModal.js');

        while (!window.entitySelector) {
            await new Promise(resolve => setTimeout(resolve, 50));
        }

        window.launcherModal = new LauncherModal(window.entitySelector, {
            modalId: 'mapLauncherModal',
            title: 'פתיחת מפת בית עלמין'
        });

        window.launcherModal.onLaunch((entityType, entityId) => {
            document.getElementById('mapEntityType').value = entityType;
            document.getElementById('mapEntitySelect').value = entityId;
            launchMap();
        });

        console.log('✅ LauncherModal loaded');
    } catch (error) {
        console.error('❌ Failed to load LauncherModal:', error);
    }
})();

(async function initToolbar() {
    try {
        const { Toolbar } = await import('../map/ui/Toolbar.js');
        window.ToolbarClass = Toolbar;
        console.log('✅ Toolbar class loaded');
    } catch (error) {
        console.error('❌ Failed to load Toolbar:', error);
    }
})();

(async function initZoomControls() {
    try {
        const { ZoomControls } = await import('../map/ui/ZoomControls.js');
        window.ZoomControlsClass = ZoomControls;
        console.log('✅ ZoomControls class loaded');
    } catch (error) {
        console.error('❌ Failed to load ZoomControls:', error);
    }
})();

(async function initCanvasManager() {
    try {
        const { CanvasManager } = await import('../map/core/CanvasManager.js');
        window.CanvasManagerClass = CanvasManager;
        console.log('✅ CanvasManager class loaded');
    } catch (error) {
        console.error('❌ Failed to load CanvasManager:', error);
    }
})();

(async function initPolygonDrawer() {
    try {
        const { PolygonDrawer } = await import('../map/editors/PolygonDrawer.js');
        window.PolygonDrawerClass = PolygonDrawer;
        console.log('✅ PolygonDrawer class loaded');
    } catch (error) {
        console.error('❌ Failed to load PolygonDrawer:', error);
    }
})();

(async function initBoundaryEditor() {
    try {
        const { BoundaryEditor } = await import('../map/editors/BoundaryEditor.js');
        window.BoundaryEditorClass = BoundaryEditor;
        console.log('✅ BoundaryEditor class loaded');
    } catch (error) {
        console.error('❌ Failed to load BoundaryEditor:', error);
    }
})();

(async function initBackgroundEditor() {
    try {
        const { BackgroundEditor } = await import('../map/editors/BackgroundEditor.js');
        window.BackgroundEditorClass = BackgroundEditor;
        console.log('✅ BackgroundEditor class loaded');
    } catch (error) {
        console.error('❌ Failed to load BackgroundEditor:', error);
    }
})();

(async function initHistoryManager() {
    try {
        const { HistoryManager } = await import('../map/core/HistoryManager.js');
        window.HistoryManagerClass = HistoryManager;
        console.log('✅ HistoryManager class loaded');
    } catch (error) {
        console.error('❌ Failed to load HistoryManager:', error);
    }
})();

(async function initEditModeToggle() {
    try {
        const { EditModeToggle } = await import('../map/ui/EditModeToggle.js');
        window.EditModeToggleClass = EditModeToggle;
        console.log('✅ EditModeToggle class loaded');
    } catch (error) {
        console.error('❌ Failed to load EditModeToggle:', error);
    }
})();

(async function initContextMenu() {
    try {
        const { ContextMenu } = await import('../map/ui/ContextMenu.js');
        window.ContextMenuClass = ContextMenu;
        console.log('✅ ContextMenu class loaded');
    } catch (error) {
        console.error('❌ Failed to load ContextMenu:', error);
    }
})();

(async function initMapPopup() {
    try {
        const { MapPopup } = await import('../map/launcher/MapPopup.js');
        window.MapPopupClass = MapPopup;
        console.log('✅ MapPopup class loaded');
    } catch (error) {
        console.error('❌ Failed to load MapPopup:', error);
    }
})();

// ========================================
// GLOBAL API - Called from sidebar.php
// ========================================

/**
 * Open launcher modal - called from sidebar
 */
function openMapLauncher() {
    if (window.launcherModal) {
        window.launcherModal.open();
    } else {
        console.error('❌ LauncherModal not loaded yet');
    }
}

/**
 * Close launcher modal
 */
function closeMapLauncher() {
    if (window.launcherModal) {
        window.launcherModal.close();
    }
}

/**
 * Launch map for selected entity
 */
async function launchMap() {
    const entityType = document.getElementById('mapEntityType').value;
    const unicId = document.getElementById('mapEntitySelect').value;

    if (!entityType || !unicId) {
        alert('אנא בחר ישות מהרשימה');
        return;
    }

    // Close launcher
    closeMapLauncher();

    // Open map popup using MapPopup module
    if (window.MapPopupClass) {
        if (!window.mapPopup) {
            window.mapPopup = new window.MapPopupClass({
                onOpen: (type, id) => console.log('Map opened:', type, id),
                onClose: () => {
                    console.log('Map closed');
                    cleanupMapState();
                },
                onMapInit: initializeMap,
                apiEndpoint: 'api/cemetery-hierarchy.php'
            });
        }
        await window.mapPopup.open(entityType, unicId);
    } else {
        console.error('❌ MapPopup class not loaded');
    }
}

/**
 * Open map popup directly (called from external code)
 */
function openMapPopup(entityType, unicId) {
    document.getElementById('mapEntityType').value = entityType;
    document.getElementById('mapEntitySelect').value = unicId;
    launchMap();
}

/**
 * Close map popup
 */
function closeMapPopup() {
    if (window.mapPopup) {
        window.mapPopup.close();
    }
    cleanupMapState();
}

/**
 * Toggle fullscreen mode
 */
function toggleMapFullscreen() {
    if (window.mapPopup) {
        window.mapPopup.toggleFullscreen();
    }
}

// ========================================
// MAP INITIALIZATION
// Orchestrates all modules to create the map
// ========================================

/**
 * Initialize map - called from MapPopup.onMapInit
 */
function initializeMap(entityType, unicId, entity) {
    console.log('🎨 Initializing map for:', entityType, unicId);

    // Wait for container to be ready
    const mapContainer = document.getElementById('mapContainer');
    if (!mapContainer) {
        console.error('❌ mapContainer not found');
        return;
    }

    // Create canvas using CanvasManager
    if (window.CanvasManagerClass) {
        const canvasManager = new window.CanvasManagerClass('mapContainer', {
            width: mapContainer.clientWidth,
            height: mapContainer.clientHeight,
            backgroundColor: '#e5e7eb'
        });

        window.mapCanvas = canvasManager.canvas;
        window.canvasManager = canvasManager;
        console.log('✅ Canvas created');
    } else {
        console.error('❌ CanvasManager not loaded');
        return;
    }

    // Initialize Toolbar
    if (window.ToolbarClass) {
        const toolbarContainer = document.getElementById('mapToolbarContainer');
        if (!toolbarContainer) {
            // Create toolbar container if it doesn't exist
            const toolbar = document.createElement('div');
            toolbar.id = 'mapToolbarContainer';
            mapContainer.insertBefore(toolbar, mapContainer.firstChild);
        }

        window.mapToolbar = new window.ToolbarClass(
            document.getElementById('mapToolbarContainer'),
            {
                onSave: saveMapData,
                onUndo: () => window.mapHistory?.undo(),
                onRedo: () => window.mapHistory?.redo(),
                onZoomIn: () => window.zoomControls?.zoomIn(),
                onZoomOut: () => window.zoomControls?.zoomOut(),
                onStartDrawPolygon: () => window.polygonDrawer?.start(),
                onToggleBoundaryEdit: toggleBoundaryEdit,
                onDeleteBoundary: () => window.boundaryEditor?.delete(),
                onUploadBackground: uploadBackground,
                onToggleBackgroundEdit: toggleBackgroundEdit,
                onDeleteBackground: () => window.backgroundEditor?.delete()
            }
        );
        console.log('✅ Toolbar initialized');
    }

    // Initialize ZoomControls
    if (window.ZoomControlsClass && window.mapCanvas) {
        window.zoomControls = new window.ZoomControlsClass(window.mapCanvas, window.mapState);
        console.log('✅ ZoomControls initialized');
    }

    // Initialize HistoryManager
    if (window.HistoryManagerClass && window.mapCanvas) {
        window.mapHistory = new window.HistoryManagerClass(window.mapCanvas);
        console.log('✅ HistoryManager initialized');
    }

    // Initialize PolygonDrawer
    if (window.PolygonDrawerClass && window.mapCanvas) {
        window.polygonDrawer = new window.PolygonDrawerClass(window.mapCanvas, window.mapState);
        console.log('✅ PolygonDrawer initialized');
    }

    // Initialize BoundaryEditor
    if (window.BoundaryEditorClass && window.mapCanvas) {
        window.boundaryEditor = new window.BoundaryEditorClass(window.mapCanvas, window.mapState);
        console.log('✅ BoundaryEditor initialized');
    }

    // Initialize BackgroundEditor
    if (window.BackgroundEditorClass && window.mapCanvas) {
        window.backgroundEditor = new window.BackgroundEditorClass(window.mapCanvas, window.mapState);
        console.log('✅ BackgroundEditor initialized');
    }

    // Initialize EditModeToggle
    if (window.EditModeToggleClass) {
        const toggleElement = document.getElementById('editModeToggle');
        if (toggleElement) {
            window.editModeToggle = new window.EditModeToggleClass(toggleElement, {
                onToggle: (enabled) => {
                    window.mapState.setEditMode(enabled);
                    console.log('Edit mode:', enabled ? 'ON' : 'OFF');
                }
            });
            console.log('✅ EditModeToggle initialized');
        }
    }

    // Initialize ContextMenu
    if (window.ContextMenuClass) {
        window.mapContextMenu = new window.ContextMenuClass({
            menuId: 'mapContextMenu',
            contentId: 'contextMenuContent',
            onAction: handleContextMenuAction,
            checkBoundary: () => {
                // Check if boundary exists on canvas
                const hasBoundary = window.mapCanvas?.getObjects().some(obj =>
                    obj.objectType === 'boundaryOutline'
                );
                return hasBoundary || false;
            }
        });
        console.log('✅ ContextMenu initialized');
    }

    // Load saved map data
    loadSavedMapData(entityType, unicId);
}

/**
 * Load saved map data from server
 */
async function loadSavedMapData(entityType, unicId) {
    try {
        const response = await fetch(`api/map-api.php?action=get&type=${entityType}&id=${unicId}`);
        const result = await response.json();

        if (!result.success || !result.mapData) {
            console.log('ℹ️ No saved map data found');
            return;
        }

        // Load canvas state
        if (result.mapData.canvasJSON && window.mapCanvas) {
            window.mapCanvas.loadFromJSON(result.mapData.canvasJSON, function() {
                // Fix textBaseline for compatibility
                const allObjects = window.mapCanvas.getObjects();
                allObjects.forEach(obj => {
                    if ((obj.type === 'text' || obj.type === 'i-text' || obj.type === 'textbox')
                        && obj.textBaseline === 'alphabetical') {
                        obj.set('textBaseline', 'alphabetic');
                    }
                });

                window.mapCanvas.renderAll();
                console.log('✅ Canvas data loaded');

                // Save initial state to history
                if (window.mapHistory) {
                    window.mapHistory.save();
                }
            });
        }

        // Load zoom level
        if (result.mapData.zoom && window.zoomControls) {
            window.zoomControls.setZoom(result.mapData.zoom);
        }

    } catch (error) {
        console.error('❌ Error loading map data:', error);
    }
}

/**
 * Save map data to server
 */
async function saveMapData() {
    if (!window.mapCanvas) {
        alert('אין מפה לשמירה');
        return;
    }

    try {
        const entityType = document.getElementById('mapEntityType').value;
        const unicId = document.getElementById('mapEntitySelect').value;

        const mapData = {
            canvasJSON: window.mapCanvas.toJSON(),
            zoom: window.mapState?.getZoom() || 1,
            lastModified: new Date().toISOString()
        };

        const response = await fetch('api/map-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save',
                type: entityType,
                id: unicId,
                mapData: mapData
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ המפה נשמרה בהצלחה');
            console.log('✅ Map saved successfully');
        } else {
            throw new Error(result.error || 'שגיאה בשמירה');
        }
    } catch (error) {
        console.error('❌ Save error:', error);
        alert('שגיאה בשמירת המפה: ' + error.message);
    }
}

/**
 * Handle context menu actions
 */
function handleContextMenuAction(action, data) {
    console.log('Context menu action:', action);

    switch (action) {
        case 'addImage':
            // Trigger file input for image/PDF
            const imageInput = document.createElement('input');
            imageInput.type = 'file';
            imageInput.accept = 'image/*,application/pdf';
            imageInput.onchange = async (e) => {
                const file = e.target.files[0];
                if (file && window.mapCanvas) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        fabric.Image.fromURL(event.target.result, function(img) {
                            // Scale if too large
                            const maxSize = 200;
                            let scale = 1;
                            if (img.width > maxSize || img.height > maxSize) {
                                scale = maxSize / Math.max(img.width, img.height);
                            }

                            img.set({
                                left: data.position.x,
                                top: data.position.y,
                                scaleX: scale,
                                scaleY: scale,
                                selectable: true,
                                hasControls: true,
                                hasBorders: true,
                                objectType: 'workObject'
                            });

                            window.mapCanvas.add(img);
                            window.mapCanvas.setActiveObject(img);
                            window.mapCanvas.renderAll();
                            window.mapHistory?.save();
                        });
                    };
                    reader.readAsDataURL(file);
                }
            };
            imageInput.click();
            break;

        case 'addText':
            if (window.mapCanvas && data.position) {
                const text = new fabric.IText('טקסט חדש', {
                    left: data.position.x,
                    top: data.position.y,
                    fontFamily: 'Arial',
                    fill: '#000000',
                    objectType: 'workObject'
                });
                window.mapCanvas.add(text);
                window.mapCanvas.setActiveObject(text);
                window.mapCanvas.renderAll();
                window.mapHistory?.save();
            }
            break;

        case 'addRect':
            if (window.mapCanvas && data.position) {
                const rect = new fabric.Rect({
                    left: data.position.x,
                    top: data.position.y,
                    width: 100,
                    height: 100,
                    fill: 'rgba(59, 130, 246, 0.3)',
                    stroke: '#3b82f6',
                    strokeWidth: 2,
                    objectType: 'workObject'
                });
                window.mapCanvas.add(rect);
                window.mapCanvas.setActiveObject(rect);
                window.mapCanvas.renderAll();
                window.mapHistory?.save();
            }
            break;

        case 'addCircle':
            if (window.mapCanvas && data.position) {
                const circle = new fabric.Circle({
                    left: data.position.x,
                    top: data.position.y,
                    radius: 50,
                    fill: 'rgba(16, 185, 129, 0.3)',
                    stroke: '#10b981',
                    strokeWidth: 2,
                    objectType: 'workObject'
                });
                window.mapCanvas.add(circle);
                window.mapCanvas.setActiveObject(circle);
                window.mapCanvas.renderAll();
                window.mapHistory?.save();
            }
            break;

        case 'addLine':
            if (window.mapCanvas && data.position) {
                const line = new fabric.Line([data.position.x, data.position.y, data.position.x + 100, data.position.y], {
                    stroke: '#ef4444',
                    strokeWidth: 3,
                    objectType: 'workObject'
                });
                window.mapCanvas.add(line);
                window.mapCanvas.setActiveObject(line);
                window.mapCanvas.renderAll();
                window.mapHistory?.save();
            }
            break;

        case 'deleteObject':
            if (data.target && window.mapCanvas) {
                window.mapCanvas.remove(data.target);
                window.mapCanvas.renderAll();
                window.mapHistory?.save();
            }
            break;

        case 'bringToFront':
            if (data.target && window.mapCanvas) {
                window.mapCanvas.bringToFront(data.target);
                window.mapCanvas.renderAll();
                window.mapHistory?.save();
            }
            break;

        case 'sendToBack':
            if (data.target && window.mapCanvas) {
                window.mapCanvas.sendToBack(data.target);
                window.mapCanvas.renderAll();
                window.mapHistory?.save();
            }
            break;

        default:
            console.warn('Unknown context menu action:', action);
    }
}

/**
 * Upload background image
 */
function uploadBackground() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,application/pdf';
    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (file && window.backgroundEditor) {
            try {
                await window.backgroundEditor.upload(file);
                window.mapHistory?.save();
            } catch (error) {
                console.error('❌ Upload failed:', error);
                alert('שגיאה בהעלאת הקובץ: ' + error.message);
            }
        }
    };
    input.click();
}

/**
 * Toggle background edit mode
 */
function toggleBackgroundEdit() {
    if (!window.backgroundEditor) return;

    if (window.backgroundEditor.isEditMode) {
        window.backgroundEditor.disableEditMode();
    } else {
        window.backgroundEditor.enableEditMode();
    }
}

/**
 * Toggle boundary edit mode
 */
function toggleBoundaryEdit() {
    if (!window.boundaryEditor) return;

    // Get boundary objects from canvas
    const boundaryOutline = window.mapCanvas?.getObjects().find(obj => obj.objectType === 'boundaryOutline');
    const grayMask = window.mapCanvas?.getObjects().find(obj => obj.objectType === 'grayMask');

    if (window.boundaryEditor.isEditMode) {
        window.boundaryEditor.disableEditMode();
    } else if (boundaryOutline && grayMask) {
        window.boundaryEditor.enableEditMode(boundaryOutline, grayMask);
    }
}

/**
 * Cleanup map state when closing
 */
function cleanupMapState() {
    console.log('🧹 Cleaning up map state');

    // Dispose canvas
    if (window.mapCanvas) {
        window.mapCanvas.dispose();
        window.mapCanvas = null;
    }

    // Clear module instances
    window.canvasManager = null;
    window.mapToolbar = null;
    window.zoomControls = null;
    window.mapHistory = null;
    window.polygonDrawer = null;
    window.boundaryEditor = null;
    window.backgroundEditor = null;
    window.editModeToggle = null;
    window.mapContextMenu = null;

    console.log('✅ Cleanup complete');
}

console.log('✅ Map Launcher Orchestrator v4.0.0 loaded');
