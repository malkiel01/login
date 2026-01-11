# תוכנית רפקטורינג - Map Launcher → Modular Architecture
## מטרה: להפוך את map-launcher.js (3,044 שורות) לארכיטקטורה מודולרית וגנרית

---

## 📋 עקרונות מנחים
1. ✅ **לא לשבור פונקציונליות** - כל שלב חייב לעבוד
2. ✅ **שלבים קטנים** - כל שלב = 1 מודול קטן
3. ✅ **קוד גנרי** - ללא התייחסויות ספציפיות ל-cemetery
4. ✅ **לבדוק אחרי כל שלב** - לוודא שהמפה עובדת
5. ✅ **לשמור את הישן בצד** - עד שהחדש עובד 100%

---

## 🎯 מבנה היעד (Target Structure)

```
dashboard/dashboards/cemeteries/
├── js/
│   └── map-launcher.js              ← יישאר רק כ-orchestrator קטן
│
└── map/
    ├── launcher/                     ← NEW: מודול הלאנצ'ר
    │   ├── LauncherModal.js          ← מודל בחירת ישות
    │   ├── MapPopup.js               ← ניהול הפופאפ
    │   └── EntitySelector.js         ← dropdown של ישויות
    │
    ├── core/                         ← כבר קיים, נשפר
    │   ├── MapManager.js             ← כבר קיים
    │   ├── CanvasManager.js          ← NEW: ניהול Canvas + Fabric.js
    │   ├── StateManager.js           ← NEW: ניהול state גלובלי
    │   ├── HistoryManager.js         ← כבר קיים, נשפר
    │   ├── BoundaryManager.js        ← כבר קיים, נשפר
    │   └── BackgroundManager.js      ← כבר קיים, נשפר
    │
    ├── ui/                           ← NEW: רכיבי UI
    │   ├── Toolbar.js                ← הטולבר עם כל הכפתורים
    │   ├── ZoomControls.js           ← בקרות זום
    │   ├── EditModeToggle.js         ← טוגל מצב עריכה
    │   └── ContextMenu.js            ← תפריט הקליק ימני
    │
    ├── editors/                      ← כבר קיים, נשפר
    │   ├── PolygonEditor.js          ← עורך פוליגונים
    │   ├── BoundaryEditor.js         ← NEW: עריכת גבול
    │   ├── BackgroundEditor.js       ← NEW: עריכת רקע
    │   └── PdfSelector.js            ← NEW: בחירת עמוד PDF
    │
    ├── api/                          ← כבר קיים
    │   └── MapAPI.js                 ← כבר קיים
    │
    ├── config/                       ← כבר קיים
    │   └── EntityConfig.js           ← כבר קיים
    │
    └── utils/                        ← כבר קיים, נרחיב
        ├── geometry.js               ← כבר קיים
        ├── canvas.js                 ← כבר קיים
        └── validation.js             ← NEW: ולידציות גנריות
```

---

## 📅 תוכנית שלבית (15 שלבים קטנים)

### **שלב 1: StateManager - ניהול State מרכזי**
**מטרה:** להעביר את כל המשתנים הגלובליים למחלקה אחת

**קובץ:** `map/core/StateManager.js`

**מה נעביר:**
```javascript
// מ-map-launcher.js שורות 7-32:
let currentMapMode, isEditMode, currentZoom, backgroundImage,
    currentEntityType, currentUnicId, drawingPolygon, polygonPoints,
    previewLine, boundaryClipPath, grayMask, boundaryOutline,
    isBoundaryEditMode, isBackgroundEditMode, currentPdfContext,
    currentPdfDoc, parentBoundaryPoints, parentBoundaryOutline,
    lastValidBoundaryState, canvasHistory, historyIndex
```

**פלט:**
```javascript
class StateManager {
    constructor() {
        this.mode = 'view';
        this.isEditMode = false;
        this.zoom = 1;
        this.canvas = {
            instance: null,
            backgroundImage: null,
            boundary: {
                outline: null,
                clipPath: null,
                grayMask: null,
                isEditMode: false
            },
            parent: {
                points: null,
                outline: null
            }
        };
        this.polygon = {
            isDrawing: false,
            points: [],
            previewLine: null
        };
        // ...
    }

    // getters/setters
    getCurrentEntity() { return { type: this.entityType, id: this.entityId }; }
    setEditMode(enabled) { this.isEditMode = enabled; }
    // ...
}
```

**איך נבדק:**
- ✅ נייבא ב-map-launcher.js
- ✅ נחליף משתנה אחד (למשל `currentZoom` → `state.zoom`)
- ✅ נבדוק שהמפה עדיין עובדת

---

### **שלב 2: EntitySelector - בחירת ישות דינמית**
**מטרה:** להפריד את לוגיקת טעינת ישויות

**קובץ:** `map/launcher/EntitySelector.js`

**מה נעביר:**
```javascript
// מ-map-launcher.js שורות 212-251:
async function loadEntitiesForType()
```

**פלט:**
```javascript
export class EntitySelector {
    constructor(entityAPI, config) {
        this.entityAPI = entityAPI;
        this.config = config;
    }

    async loadEntitiesByType(entityType) {
        const entities = await this.entityAPI.getEntitiesByType(entityType);
        return entities.map(e => ({
            id: e.unicId,
            name: e.name || e.unicId,
            type: entityType
        }));
    }

    renderDropdown(container, entities, config) {
        // render logic
    }
}
```

**איך נבדוק:**
- ✅ ה-dropdown עדיין עובד
- ✅ טעינת ישויות עובדת

---

### **שלב 3: LauncherModal - מודל בחירה**
**מטרה:** להפריד את ה-UI של המודל המקורי

**קובץ:** `map/launcher/LauncherModal.js`

**מה נעביר:**
```javascript
// מ-map-launcher.js שורות 38-194:
function createMapLauncherModal()
function openMapLauncher()
function closeMapLauncher()
```

**פלט:**
```javascript
export class LauncherModal {
    constructor(entitySelector, config) {
        this.selector = entitySelector;
        this.config = config;
        this.createModal();
    }

    createModal() {
        // HTML + styles
    }

    open() { /* ... */ }
    close() { /* ... */ }

    onLaunch(callback) {
        this.launchCallback = callback;
    }
}
```

---

### **שלב 4: Toolbar - הטולבר עם כפתורים**
**מטרה:** להפריד את כל לוגיקת הטולבר

**קובץ:** `map/ui/Toolbar.js`

**מה נעביר:**
```javascript
// מ-initializeMap() שורות 906-995:
// כל ה-HTML של הטולבר
```

**פלט:**
```javascript
export class Toolbar {
    constructor(container, config, handlers) {
        this.container = container;
        this.config = config;
        this.handlers = handlers; // { onZoomIn, onZoomOut, onSave, ... }
        this.render();
    }

    render() {
        this.container.innerHTML = this.getToolbarHTML();
        this.attachEventListeners();
    }

    getToolbarHTML() {
        return `
            <div class="map-toolbar">
                ${this.getZoomGroup()}
                ${this.getBackgroundGroup()}
                ${this.getBoundaryGroup()}
                ${this.getHistoryGroup()}
            </div>
        `;
    }

    setEditMode(enabled) {
        // show/hide edit-only groups
    }

    updateZoomDisplay(zoom) { /* ... */ }
    enableButton(id) { /* ... */ }
    disableButton(id) { /* ... */ }
}
```

---

### **שלב 5: ZoomControls - בקרות זום**
**מטרה:** לוגיקת זום נפרדת

**קובץ:** `map/ui/ZoomControls.js`

**מה נעביר:**
```javascript
// שורות 1271-1355:
function zoomMapIn(), zoomMapOut(), setZoomLevel(), editZoomLevel()
```

**פלט:**
```javascript
export class ZoomControls {
    constructor(canvas, config = { min: 0.1, max: 5, step: 0.1 }) {
        this.canvas = canvas;
        this.config = config;
        this.currentZoom = 1;
    }

    zoomIn() {
        this.setZoom(this.currentZoom + this.config.step);
    }

    zoomOut() {
        this.setZoom(this.currentZoom - this.config.step);
    }

    setZoom(level) {
        level = Math.max(this.config.min, Math.min(this.config.max, level));
        this.currentZoom = level;
        this.canvas.setZoom(level);
        this.canvas.renderAll();
        return level;
    }

    getZoom() {
        return this.currentZoom;
    }
}
```

---

### **שלב 6: CanvasManager - ניהול Canvas**
**מטרה:** כל הלוגיקה של יצירה וניהול Canvas

**קובץ:** `map/core/CanvasManager.js`

**מה נעביר:**
```javascript
// שורות 999-1128:
function createMapCanvas()
```

**פלט:**
```javascript
export class CanvasManager {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            width: options.width || 2000,
            height: options.height || 1500,
            backgroundColor: options.backgroundColor || '#e5e7eb'
        };
        this.canvas = null;
    }

    create() {
        const canvasEl = document.createElement('canvas');
        canvasEl.id = 'fabricCanvas';

        this.container.appendChild(canvasEl);

        this.canvas = new fabric.Canvas('fabricCanvas', {
            width: this.options.width,
            height: this.options.height,
            backgroundColor: this.options.backgroundColor
        });

        return this.canvas;
    }

    attachEventHandlers(handlers) {
        this.canvas.on('mouse:down', handlers.onMouseDown);
        this.canvas.on('mouse:move', handlers.onMouseMove);
        // ...
    }

    getCanvas() {
        return this.canvas;
    }
}
```

---

### **שלב 7: PolygonEditor - עורך פוליגונים משופר**
**מטרה:** עריכת פוליגונים עם preview

**קובץ:** `map/editors/PolygonEditor.js` (שיפור הקיים)

**מה נעביר:**
```javascript
// שורות 1453-1689:
startDrawPolygon(), handleCanvasClick(), handleCanvasMouseMove(),
finishPolygon(), cancelPolygonDrawing()
```

**פלט:**
```javascript
export class PolygonEditor {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.options = options;
        this.isDrawing = false;
        this.points = [];
        this.previewLine = null;
        this.tempPoints = [];
        this.tempLines = [];
    }

    startDrawing(config = {}) {
        this.isDrawing = true;
        this.points = [];
        this.config = config; // color, strokeWidth, etc.
        this.attachDrawingHandlers();
    }

    attachDrawingHandlers() {
        this.canvas.on('mouse:down', this.handleClick.bind(this));
        this.canvas.on('mouse:move', this.handleMove.bind(this));
    }

    handleClick(e) {
        const point = this.canvas.getPointer(e.e);
        this.points.push({ x: Math.round(point.x), y: Math.round(point.y) });
        this.drawTempPoint(point);
        this.updatePreviewLine(point);
    }

    handleMove(e) {
        if (!this.isDrawing || this.points.length === 0) return;
        const pointer = this.canvas.getPointer(e.e);
        this.updatePreviewLine(pointer);
    }

    finishDrawing() {
        if (this.points.length < 3) {
            this.cancel();
            return null;
        }

        this.cleanup();
        return this.points;
    }

    cancel() {
        this.cleanup();
        this.isDrawing = false;
        this.points = [];
    }

    cleanup() {
        // remove temp objects
    }
}
```

---

### **שלב 8: BoundaryEditor - עריכת גבולות**
**מטרה:** כל לוגיקת עריכת הגבול במקום אחד

**קובץ:** `map/editors/BoundaryEditor.js`

**מה נעביר:**
```javascript
// שורות 1715-1899:
toggleBoundaryEdit(), updateMaskPosition(), deleteBoundary()
```

**פלט:**
```javascript
export class BoundaryEditor {
    constructor(canvas, boundaryManager) {
        this.canvas = canvas;
        this.boundaryManager = boundaryManager;
        this.isEditMode = false;
        this.lastValidState = null;
    }

    enableEditMode(boundary) {
        this.isEditMode = true;
        this.lastValidState = this.saveBoundaryState(boundary);

        boundary.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true
        });

        this.canvas.setActiveObject(boundary);

        boundary.on('moving', () => this.onBoundaryMove(boundary));
        boundary.on('scaling', () => this.onBoundaryScale(boundary));
    }

    disableEditMode(boundary) {
        boundary.off('moving');
        boundary.off('scaling');
        boundary.set({ selectable: false, evented: false });
        this.isEditMode = false;
    }

    onBoundaryMove(boundary) {
        // validate + update mask
    }

    validateWithinParent(boundary, parentBoundary) {
        // check if all points inside parent
    }
}
```

---

### **שלב 9: BackgroundEditor - עריכת רקע**
**מטרה:** ניהול תמונות רקע ו-PDF

**קובץ:** `map/editors/BackgroundEditor.js`

**מה נעביר:**
```javascript
// שורות 1293-1588:
uploadBackgroundImage(), handleBackgroundUpload(),
toggleBackgroundEdit(), deleteBackground()
```

**פלט:**
```javascript
export class BackgroundEditor {
    constructor(canvas, backgroundManager) {
        this.canvas = canvas;
        this.backgroundManager = backgroundManager;
        this.isEditMode = false;
    }

    async uploadImage(file) {
        if (file.type === 'application/pdf') {
            return await this.handlePdfUpload(file);
        } else {
            return await this.handleImageUpload(file);
        }
    }

    async handleImageUpload(file) {
        const reader = new FileReader();
        return new Promise((resolve) => {
            reader.onload = (e) => {
                fabric.Image.fromURL(e.target.result, (img) => {
                    this.backgroundManager.setBackground(img);
                    resolve(img);
                });
            };
            reader.readAsDataURL(file);
        });
    }

    async handlePdfUpload(file) {
        // PDF loading logic
    }

    enableEditMode(background) {
        this.isEditMode = true;
        background.set({ selectable: true, evented: true });
        this.canvas.setActiveObject(background);
    }

    disableEditMode(background) {
        background.set({ selectable: false, evented: false });
        this.isEditMode = false;
    }

    delete() {
        this.backgroundManager.removeBackground();
    }
}
```

---

### **שלב 10: PdfSelector - בחירת עמוד PDF**
**מטרה:** מודל בחירת עמוד PDF

**קובץ:** `map/editors/PdfSelector.js`

**מה נעביר:**
```javascript
// שורות 2350-2585:
showPdfPageSelector(), selectPdfPage()
```

**פלט:**
```javascript
export class PdfSelector {
    constructor(pdfDocument) {
        this.pdfDoc = pdfDocument;
        this.modal = null;
    }

    async show() {
        this.createModal();
        await this.renderPages();
        return new Promise((resolve) => {
            this.onSelect = resolve;
        });
    }

    createModal() {
        // create modal HTML
    }

    async renderPages() {
        for (let i = 1; i <= this.pdfDoc.numPages; i++) {
            const page = await this.pdfDoc.getPage(i);
            const canvas = await this.renderPageThumbnail(page);
            this.addPageToGrid(canvas, i);
        }
    }

    async renderPageThumbnail(page) {
        // render page to canvas
    }

    selectPage(pageNum) {
        this.close();
        this.onSelect(pageNum);
    }

    close() {
        this.modal.remove();
    }
}
```

---

### **שלב 11: EditModeToggle - טוגל מצב עריכה**
**מטרה:** הפרדת לוגיקת מצב עריכה

**קובץ:** `map/ui/EditModeToggle.js`

**מה נעביר:**
```javascript
// שורה 1265-1291:
toggleEditMode()
```

**פלט:**
```javascript
export class EditModeToggle {
    constructor(container, mapManager) {
        this.container = container;
        this.mapManager = mapManager;
        this.isEnabled = false;
    }

    render() {
        const html = `
            <div class="edit-mode-toggle">
                <span class="toggle-label">מצב עריכה</span>
                <label class="toggle-switch">
                    <input type="checkbox" id="editModeToggle">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `;
        this.container.innerHTML = html;
        this.attachListeners();
    }

    attachListeners() {
        const toggle = this.container.querySelector('#editModeToggle');
        toggle.addEventListener('change', (e) => {
            this.setEnabled(e.target.checked);
        });
    }

    setEnabled(enabled) {
        this.isEnabled = enabled;
        this.mapManager.setEditMode(enabled);

        // Update UI
        const container = document.getElementById('mapContainer');
        if (enabled) {
            container.classList.add('edit-mode');
        } else {
            container.classList.remove('edit-mode');
        }
    }
}
```

---

### **שלב 12: ContextMenu - תפריט הקשר**
**מטרה:** תפריט קליק ימני

**קובץ:** `map/ui/ContextMenu.js` (שיפור הקיים)

**מה נעביר:**
```javascript
// שורות 2168-2348:
showContextMenu(), hideContextMenu(), handleContextMenuAction()
```

**פלט:**
```javascript
export class ContextMenu {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.options = options;
        this.menu = null;
        this.currentTarget = null;
    }

    show(x, y, target, menuItems) {
        this.hide();
        this.currentTarget = target;

        this.menu = this.createMenu(menuItems);
        this.menu.style.left = x + 'px';
        this.menu.style.top = y + 'px';

        document.body.appendChild(this.menu);

        // Close on outside click
        document.addEventListener('click', () => this.hide(), { once: true });
    }

    createMenu(items) {
        const menu = document.createElement('div');
        menu.className = 'map-context-menu';

        items.forEach(item => {
            const menuItem = this.createMenuItem(item);
            menu.appendChild(menuItem);
        });

        return menu;
    }

    createMenuItem(item) {
        const el = document.createElement('div');
        el.className = 'context-menu-item';
        el.innerHTML = `
            <span class="context-menu-icon">${item.icon}</span>
            <span>${item.label}</span>
        `;
        el.addEventListener('click', () => {
            item.action(this.currentTarget);
            this.hide();
        });
        return el;
    }

    hide() {
        if (this.menu) {
            this.menu.remove();
            this.menu = null;
        }
    }
}
```

---

### **שלב 13: MapPopup - ניהול הפופאפ**
**מטרה:** כל לוגיקת הפופאפ במקום אחד

**קובץ:** `map/launcher/MapPopup.js`

**מה נעביר:**
```javascript
// שורות 351-833:
openMapPopup(), closeMapPopup(), toggleMapFullscreen()
```

**פלט:**
```javascript
export class MapPopup {
    constructor(config) {
        this.config = config;
        this.container = null;
        this.mapManager = null;
    }

    async open(entityType, entityId) {
        this.createPopup(entityType, entityId);
        await this.initializeMap(entityType, entityId);
    }

    createPopup(entityType, entityId) {
        const entityNames = this.config.entities[entityType].labelHe;

        const html = `
            <div id="mapPopupOverlay" class="map-popup-overlay">
                <div class="map-popup-container">
                    <div class="map-popup-header">
                        <h3 id="mapPopupTitle">טוען מפה...</h3>
                        <div class="map-popup-controls">
                            <!-- EditModeToggle here -->
                            <!-- Fullscreen button -->
                            <!-- Close button -->
                        </div>
                    </div>
                    <div class="map-popup-body">
                        <div id="mapContainer" class="map-container">
                            <div class="map-loading">
                                <div class="map-spinner"></div>
                                <p>טוען מפה...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
        this.container = document.getElementById('mapPopupOverlay');
        this.addStyles();
    }

    async initializeMap(entityType, entityId) {
        const mapContainer = document.getElementById('mapContainer');

        // Create MapManager
        this.mapManager = new MapManager({
            entityType,
            entityId,
            mode: 'edit',
            container: mapContainer
        });

        await this.mapManager.init();

        // Hide loading
        const loading = mapContainer.querySelector('.map-loading');
        if (loading) loading.remove();
    }

    close() {
        if (this.container) {
            this.container.remove();
        }
        document.body.style.overflow = '';
    }

    toggleFullscreen() {
        const container = this.container.querySelector('.map-popup-container');
        container.classList.toggle('fullscreen');
    }

    addStyles() {
        // Inject CSS if not exists
    }
}
```

---

### **שלב 14: שילוב HistoryManager משופר**
**מטרה:** undo/redo עם ה-StateManager

**קובץ:** `map/core/HistoryManager.js` (שיפור הקיים)

**מה נעביר:**
```javascript
// שורות 2891-2948:
saveState(), undoCanvas(), redoCanvas(), resetHistory()
```

**פלט:**
```javascript
export class HistoryManager {
    constructor(canvas, stateManager, maxStates = 30) {
        this.canvas = canvas;
        this.stateManager = stateManager;
        this.maxStates = maxStates;
        this.history = [];
        this.currentIndex = -1;
    }

    saveState(metadata = {}) {
        // Remove future states if we're not at the end
        this.history = this.history.slice(0, this.currentIndex + 1);

        const state = {
            canvasJSON: JSON.stringify(this.canvas.toJSON()),
            timestamp: Date.now(),
            metadata
        };

        this.history.push(state);

        // Limit history size
        if (this.history.length > this.maxStates) {
            this.history.shift();
        } else {
            this.currentIndex++;
        }

        this.updateButtons();
    }

    undo() {
        if (!this.canUndo()) return false;

        this.currentIndex--;
        this.restoreState(this.history[this.currentIndex]);
        this.updateButtons();
        return true;
    }

    redo() {
        if (!this.canRedo()) return false;

        this.currentIndex++;
        this.restoreState(this.history[this.currentIndex]);
        this.updateButtons();
        return true;
    }

    canUndo() {
        return this.currentIndex > 0;
    }

    canRedo() {
        return this.currentIndex < this.history.length - 1;
    }

    restoreState(state) {
        this.canvas.loadFromJSON(state.canvasJSON, () => {
            this.stateManager.syncFromCanvas(this.canvas);
            this.canvas.renderAll();
        });
    }

    updateButtons() {
        const undoBtn = document.getElementById('undoBtn');
        const redoBtn = document.getElementById('redoBtn');

        if (undoBtn) undoBtn.disabled = !this.canUndo();
        if (redoBtn) redoBtn.disabled = !this.canRedo();
    }

    reset() {
        this.history = [];
        this.currentIndex = -1;
        this.updateButtons();
    }
}
```

---

### **שלב 15: Orchestrator - החיבור הסופי**
**מטרה:** map-launcher.js יהיה רק חיבור קטן בין כל המודולים

**קובץ:** `js/map-launcher.js` (יקטן מ-3,044 לכ-200 שורות)

**פלט:**
```javascript
/**
 * Map Launcher - Orchestrator
 * Version: 3.0.0 - Modular Architecture
 */

import { LauncherModal } from '../map/launcher/LauncherModal.js';
import { MapPopup } from '../map/launcher/MapPopup.js';
import { EntitySelector } from '../map/launcher/EntitySelector.js';
import { EntityAPI } from '../map/api/MapAPI.js';
import { EntityConfig, CEMETERY_ENTITIES } from '../map/config/EntityConfig.js';

// Global instances
let launcherModal = null;
let entityAPI = null;
let entityConfig = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeLauncher();
});

function initializeLauncher() {
    // Create entity API and config
    entityAPI = new EntityAPI();
    entityConfig = new EntityConfig(CEMETERY_ENTITIES);

    // Create entity selector
    const entitySelector = new EntitySelector(entityAPI, entityConfig);

    // Create launcher modal
    launcherModal = new LauncherModal(entitySelector, entityConfig);

    // Handle launch event
    launcherModal.onLaunch(async (entityType, entityId) => {
        await openMapPopup(entityType, entityId);
    });
}

// Global function for sidebar
window.openMapLauncher = function() {
    launcherModal.open();
};

async function openMapPopup(entityType, entityId) {
    const popup = new MapPopup(entityConfig);
    await popup.open(entityType, entityId);

    // Make globally accessible
    window.currentMapPopup = popup;
}

window.closeMapPopup = function() {
    if (window.currentMapPopup) {
        window.currentMapPopup.close();
        window.currentMapPopup = null;
    }
};

window.toggleMapFullscreen = function() {
    if (window.currentMapPopup) {
        window.currentMapPopup.toggleFullscreen();
    }
};
```

---

## ✅ איך מבצעים כל שלב

### תהליך לכל שלב:

1. **יצירת הקובץ החדש**
   ```bash
   touch dashboard/dashboards/cemeteries/map/core/StateManager.js
   ```

2. **כתיבת המודול** (ES6 class עם export)

3. **בדיקה יחידה** (unit test אופציונלי)

4. **שילוב ב-map-launcher.js**
   ```javascript
   import { StateManager } from '../map/core/StateManager.js';
   const state = new StateManager();

   // Replace global var with state property
   // OLD: let currentZoom = 1;
   // NEW: state.zoom = 1;
   ```

5. **בדיקת התנהגות**
   - פתיחת מפה
   - בדיקת הפונקציונליות הספציפית
   - וידוא שלא נשבר כלום

6. **הסרת הקוד הישן** (רק אחרי שהחדש עובד!)
   ```javascript
   // DEPRECATED - moved to StateManager
   // let currentZoom = 1;
   ```

7. **Commit**
   ```bash
   git add .
   git commit -m "Refactor: Extract StateManager (Step 1/15)"
   git push
   ```

---

## 🎯 סדר ביצוע מומלץ

| # | שלב | זמן משוער | קריטיות |
|---|-----|-----------|----------|
| 1 | StateManager | 30 דקות | 🔴 גבוהה |
| 2 | EntitySelector | 20 דקות | 🟡 בינונית |
| 3 | LauncherModal | 25 דקות | 🟡 בינונית |
| 4 | Toolbar | 40 דקות | 🔴 גבוהה |
| 5 | ZoomControls | 15 דקות | 🟢 נמוכה |
| 6 | CanvasManager | 35 דקות | 🔴 גבוהה |
| 7 | PolygonEditor | 45 דקות | 🔴 גבוהה |
| 8 | BoundaryEditor | 40 דקות | 🔴 גבוהה |
| 9 | BackgroundEditor | 40 דקות | 🟡 בינונית |
| 10 | PdfSelector | 30 דקות | 🟡 בינונית |
| 11 | EditModeToggle | 15 דקות | 🟢 נמוכה |
| 12 | ContextMenu | 25 דקות | 🟡 בינונית |
| 13 | MapPopup | 50 דקות | 🔴 גבוהה |
| 14 | HistoryManager | 30 דקות | 🔴 גבוהה |
| 15 | Orchestrator | 60 דקות | 🔴 גבוהה |

**סה"כ:** ~8 שעות עבודה (ניתן לפצל ל-2-3 ימים)

---

## 📊 מדדי הצלחה

לאחר השלמת כל 15 השלבים:

✅ **קוד:**
- map-launcher.js: 3,044 → ~200 שורות (-93%)
- 15 מודולים קטנים וממוקדים
- כל מודול < 300 שורות

✅ **גנריות:**
- 0 התייחסויות ל-"cemetery" בקוד
- ניתן לשימוש עם כל היררכיית ישויות

✅ **תחזוקה:**
- קל להוסיף פיצ'רים
- קל למצוא באגים
- קל לבדוק

✅ **ביצועים:**
- טעינה עצלה (lazy loading)
- זיכרון מנוהל טוב יותר

---

## 🚀 מוכן להתחיל?

**שלב 1 מחכה:** StateManager - ניהול State מרכזי

האם להתחיל בשלב 1?
