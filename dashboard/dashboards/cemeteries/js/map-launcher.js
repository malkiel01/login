/**
 * Map Launcher - מנהל פתיחת המפה
 * Version: 2.0.0
 * Features: Edit mode, Background image, Polygon drawing
 */

// משתנים גלובליים
let currentMapMode = 'view';
let isEditMode = false;
let currentZoom = 1;
let backgroundImage = null;
let currentEntityType = null;
let currentUnicId = null;
let drawingPolygon = false;
let polygonPoints = [];
let previewLine = null; // קו תצוגה מקדימה
let boundaryClipPath = null; // גבול החיתוך
let grayMask = null; // מסכה אפורה
let boundaryOutline = null; // קו הגבול
let isBoundaryEditMode = false; // מצב עריכת גבול
let isBackgroundEditMode = false; // מצב עריכת תמונת רקע

// יצירת המודל בטעינה
document.addEventListener('DOMContentLoaded', function() {
    createMapLauncherModal();
});

/**
 * יצירת מודל בחירת ישות למפה
 */
function createMapLauncherModal() {
    if (document.getElementById('mapLauncherModal')) return;

    const modalHTML = `
        <div id="mapLauncherModal" class="map-launcher-overlay" style="display: none;">
            <div class="map-launcher-modal">
                <div class="map-launcher-header">
                    <h3>פתיחת מפת בית עלמין</h3>
                    <button type="button" class="map-launcher-close" onclick="closeMapLauncher()">&times;</button>
                </div>
                <div class="map-launcher-body">
                    <div class="map-launcher-field">
                        <label for="mapEntityType">סוג ישות:</label>
                        <select id="mapEntityType" class="map-launcher-select">
                            <option value="cemetery">בית עלמין</option>
                            <option value="block">גוש</option>
                            <option value="plot">חלקה</option>
                            <option value="areaGrave">אחוזת קבר</option>
                        </select>
                    </div>
                    <div class="map-launcher-field">
                        <label for="mapUnicId">מזהה ייחודי (unicId):</label>
                        <input type="text" id="mapUnicId" class="map-launcher-input" placeholder="הזן unicId...">
                    </div>
                </div>
                <div class="map-launcher-footer">
                    <button type="button" class="btn-secondary" onclick="closeMapLauncher()">ביטול</button>
                    <button type="button" class="btn-primary" onclick="launchMap()">פתח מפה</button>
                </div>
            </div>
        </div>
    `;

    // הוספת סגנונות
    const styles = document.createElement('style');
    styles.id = 'mapLauncherStyles';
    styles.textContent = `
        .map-launcher-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .map-launcher-modal {
            background: white;
            border-radius: 12px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            direction: rtl;
        }
        .map-launcher-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .map-launcher-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1f2937;
        }
        .map-launcher-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .map-launcher-body {
            padding: 20px;
        }
        .map-launcher-field {
            margin-bottom: 16px;
        }
        .map-launcher-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }
        .map-launcher-select,
        .map-launcher-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            direction: rtl;
        }
        .map-launcher-footer {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 0 0 12px 12px;
        }
        .map-launcher-footer .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .map-launcher-footer .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
    `;

    document.head.appendChild(styles);
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function openMapLauncher() {
    const modal = document.getElementById('mapLauncherModal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('mapUnicId').focus();
    }
}

function closeMapLauncher() {
    const modal = document.getElementById('mapLauncherModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('mapUnicId').value = '';
        document.getElementById('mapEntityType').value = 'cemetery';
    }
}

function launchMap() {
    const entityType = document.getElementById('mapEntityType').value;
    const unicId = document.getElementById('mapUnicId').value.trim();

    if (!unicId) {
        alert('נא להזין מזהה ייחודי (unicId)');
        document.getElementById('mapUnicId').focus();
        return;
    }

    closeMapLauncher();
    openMapPopup(entityType, unicId);
}

/**
 * פתיחת פופאפ המפה
 */
function openMapPopup(entityType, unicId) {
    currentEntityType = entityType;
    currentUnicId = unicId;

    let existingPopup = document.getElementById('mapPopupOverlay');
    if (existingPopup) existingPopup.remove();

    const popupHTML = `
        <div id="mapPopupOverlay" class="map-popup-overlay">
            <div class="map-popup-container">
                <div class="map-popup-header">
                    <h3 id="mapPopupTitle">טוען מפה...</h3>
                    <div class="map-popup-controls">
                        <!-- טוגל מצב עריכה -->
                        <div class="edit-mode-toggle">
                            <span class="toggle-label">מצב עריכה</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="editModeToggle" onchange="toggleEditMode(this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <button type="button" class="map-popup-btn" onclick="toggleMapFullscreen()" title="מסך מלא">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                            </svg>
                        </button>
                        <button type="button" class="map-popup-close" onclick="closeMapPopup()">&times;</button>
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

    // הוספת סגנונות הפופאפ
    if (!document.getElementById('mapPopupStyles')) {
        const styles = document.createElement('style');
        styles.id = 'mapPopupStyles';
        styles.textContent = `
            .map-popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            }
            .map-popup-container {
                background: white;
                border-radius: 12px;
                width: 90%;
                height: 85%;
                max-width: 1400px;
                display: flex;
                flex-direction: column;
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
                overflow: hidden;
            }
            .map-popup-container.fullscreen {
                width: 100%;
                height: 100%;
                max-width: none;
                border-radius: 0;
            }
            .map-popup-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 20px;
                background: #1f2937;
                color: white;
            }
            .map-popup-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 500;
            }
            .map-popup-controls {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .edit-mode-toggle {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 4px 12px;
                background: rgba(255,255,255,0.1);
                border-radius: 20px;
            }
            .toggle-label {
                font-size: 13px;
                color: #d1d5db;
            }
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
            }
            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #4b5563;
                transition: .3s;
                border-radius: 24px;
            }
            .toggle-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
            }
            .toggle-switch input:checked + .toggle-slider {
                background-color: #3b82f6;
            }
            .toggle-switch input:checked + .toggle-slider:before {
                transform: translateX(20px);
            }
            .map-popup-btn {
                background: rgba(255, 255, 255, 0.1);
                border: none;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .map-popup-btn:hover {
                background: rgba(255, 255, 255, 0.2);
            }
            .map-popup-close {
                background: none;
                border: none;
                color: white;
                font-size: 28px;
                cursor: pointer;
                padding: 0 8px;
                line-height: 1;
            }
            .map-popup-body {
                flex: 1;
                overflow: hidden;
                position: relative;
            }
            .map-container {
                width: 100%;
                height: 100%;
                background: #f3f4f6;
                position: relative;
            }
            .map-loading {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
                color: #6b7280;
            }
            .map-spinner {
                width: 40px;
                height: 40px;
                border: 3px solid #e5e7eb;
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 12px;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            /* Toolbar Styles */
            .map-toolbar {
                display: flex;
                gap: 16px;
                padding: 10px 16px;
                background: white;
                border-bottom: 1px solid #e5e7eb;
                align-items: center;
                flex-wrap: wrap;
            }
            .map-toolbar-group {
                display: flex;
                align-items: center;
                gap: 4px;
                padding: 4px;
                background: #f3f4f6;
                border-radius: 8px;
            }
            .map-toolbar-group.edit-only {
                display: none;
            }
            .map-container.edit-mode .map-toolbar-group.edit-only {
                display: flex;
            }
            .map-tool-btn {
                width: 36px;
                height: 36px;
                border: none;
                background: transparent;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #4b5563;
                font-size: 18px;
                font-weight: 600;
            }
            .map-tool-btn:hover {
                background: #e5e7eb;
            }
            .map-tool-btn.active {
                background: #3b82f6;
                color: white;
            }
            .map-tool-btn:disabled {
                opacity: 0.4;
                cursor: not-allowed;
            }
            .map-zoom-level {
                padding: 0 8px;
                font-size: 13px;
                color: #6b7280;
                min-width: 50px;
                text-align: center;
            }
            .map-canvas {
                width: 100%;
                height: calc(100% - 56px);
                background: #e5e7eb;
                position: relative;
                overflow: hidden;
            }
            #fabricCanvas {
                position: absolute;
                top: 0;
                left: 0;
            }

            /* File input hidden */
            .hidden-file-input {
                display: none;
            }

            /* Edit mode indicator */
            .edit-mode-indicator {
                position: absolute;
                top: 10px;
                right: 10px;
                background: #3b82f6;
                color: white;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 500;
                z-index: 100;
                display: none;
            }
            .map-container.edit-mode .edit-mode-indicator {
                display: block;
            }

            /* Context Menu */
            .map-context-menu {
                position: absolute;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                z-index: 1000;
                min-width: 180px;
                overflow: hidden;
                border: 1px solid #e5e7eb;
            }
            .context-menu-content {
                padding: 4px 0;
            }
            .context-menu-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 16px;
                cursor: pointer;
                transition: background 0.15s;
                font-size: 14px;
                color: #374151;
            }
            .context-menu-item:hover {
                background: #f3f4f6;
            }
            .context-menu-item.disabled {
                color: #9ca3af;
                cursor: not-allowed;
                background: #f9fafb;
            }
            .context-menu-item.disabled:hover {
                background: #f9fafb;
            }
            .context-menu-icon {
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }
            .context-menu-separator {
                height: 1px;
                background: #e5e7eb;
                margin: 4px 0;
            }
            .no-entry-icon {
                color: #9ca3af;
                font-size: 18px;
            }
        `;
        document.head.appendChild(styles);
    }

    document.body.insertAdjacentHTML('beforeend', popupHTML);
    loadMapData(entityType, unicId);
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
    const container = document.getElementById('mapContainer');

    container.innerHTML = `
        <!-- Toolbar -->
        <div class="map-toolbar">
            <!-- כלי צפייה - תמיד מוצגים -->
            <div class="map-toolbar-group">
                <button class="map-tool-btn" onclick="zoomMapIn()" title="הגדל">+</button>
                <span id="mapZoomLevel" class="map-zoom-level">100%</span>
                <button class="map-tool-btn" onclick="zoomMapOut()" title="הקטן">−</button>
            </div>

            <!-- כלי עריכה - רק במצב עריכה -->
            <div class="map-toolbar-group edit-only">
                <button class="map-tool-btn" onclick="uploadBackgroundImage()" title="העלאת תמונת רקע">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                </button>
                <button class="map-tool-btn" onclick="uploadPdfFile()" title="העלאת PDF">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </button>
            </div>

            <div class="map-toolbar-group edit-only">
                <button class="map-tool-btn" id="drawPolygonBtn" onclick="startDrawPolygon()" title="ציור גבולות">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/>
                    </svg>
                </button>
                <button class="map-tool-btn" id="editBoundaryBtn" onclick="toggleBoundaryEdit()" title="עריכת/הזזת גבול" style="display:none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <button class="map-tool-btn" id="editBackgroundBtn" onclick="toggleBackgroundEdit()" title="עריכת/הזזת תמונת רקע" style="display:none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                </button>
                <button class="map-tool-btn" onclick="clearPolygon()" title="מחק גבולות">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                </button>
            </div>

            <div class="map-toolbar-group edit-only">
                <button class="map-tool-btn" onclick="saveMapData()" title="שמור">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Canvas Area -->
        <div id="mapCanvas" class="map-canvas">
            <div class="edit-mode-indicator">מצב עריכה פעיל</div>
        </div>

        <!-- Hidden file inputs -->
        <input type="file" id="bgImageInput" class="hidden-file-input" accept="image/*,.pdf" onchange="handleBackgroundUpload(event)">
        <input type="file" id="addImageInput" class="hidden-file-input" accept="image/*" onchange="handleAddImage(event)">

        <!-- Context Menu -->
        <div id="mapContextMenu" class="map-context-menu" style="display:none;">
            <div class="context-menu-content" id="contextMenuContent">
                <!-- ימולא דינמית -->
            </div>
        </div>
    `;

    // סגירת תפריט בלחיצה מחוץ
    document.addEventListener('click', hideContextMenu);

    createMapCanvas(entityType, unicId, entity);
}

/**
 * יצירת ה-Canvas
 */
function createMapCanvas(entityType, unicId, entity) {
    const canvasContainer = document.getElementById('mapCanvas');
    const width = canvasContainer.clientWidth;
    const height = canvasContainer.clientHeight - 40; // minus indicator height

    const canvasEl = document.createElement('canvas');
    canvasEl.id = 'fabricCanvas';
    canvasEl.width = width;
    canvasEl.height = height;
    canvasContainer.appendChild(canvasEl);

    if (typeof fabric !== 'undefined') {
        window.mapCanvas = new fabric.Canvas('fabricCanvas', {
            backgroundColor: '#ffffff',
            selection: true
        });

        // הוספת טקסט התחלתי
        const text = new fabric.Text('לחץ על "מצב עריכה" כדי להתחיל', {
            left: width / 2,
            top: height / 2,
            fontSize: 20,
            fill: '#9ca3af',
            originX: 'center',
            originY: 'center',
            selectable: false
        });
        window.mapCanvas.add(text);

        // אירועי ציור פוליגון
        window.mapCanvas.on('mouse:down', handleCanvasClick);
        window.mapCanvas.on('mouse:move', handleCanvasMouseMove);

        // אירוע קליק ימני - חובה להוסיף למכל שFabric יוצר
        // Fabric.js יוצר upper-canvas מעל ה-canvas הרגיל
        const fabricWrapper = canvasContainer.querySelector('.canvas-container');
        if (fabricWrapper) {
            fabricWrapper.addEventListener('contextmenu', handleCanvasRightClick);
        } else {
            // fallback - אם אין עטיפה, נוסיף למכל הראשי
            canvasContainer.addEventListener('contextmenu', handleCanvasRightClick);
        }

        console.log('Map canvas initialized');
    } else {
        console.error('Fabric.js not loaded!');
        canvasContainer.innerHTML += '<p style="text-align:center; color:red; padding:20px;">שגיאה: Fabric.js לא נטען</p>';
    }
}

/**
 * טוגל מצב עריכה
 */
function toggleEditMode(enabled) {
    isEditMode = enabled;
    const container = document.getElementById('mapContainer');

    if (enabled) {
        container.classList.add('edit-mode');
        // הסרת הטקסט ההתחלתי
        if (window.mapCanvas) {
            const objects = window.mapCanvas.getObjects('text');
            objects.forEach(obj => {
                if (obj.text === 'לחץ על "מצב עריכה" כדי להתחיל') {
                    window.mapCanvas.remove(obj);
                }
            });
            window.mapCanvas.renderAll();
        }
    } else {
        container.classList.remove('edit-mode');
        // ביטול ציור פוליגון אם פעיל
        if (drawingPolygon) {
            cancelPolygonDrawing();
        }
    }
}

/**
 * העלאת תמונת רקע
 */
function uploadBackgroundImage() {
    document.getElementById('bgImageInput').click();
}

/**
 * העלאת PDF
 */
function uploadPdfFile() {
    document.getElementById('bgImageInput').click();
}

/**
 * טיפול בהעלאת קובץ רקע
 */
function handleBackgroundUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const isPdf = file.type === 'application/pdf';

    if (isPdf) {
        alert('תמיכה ב-PDF תתווסף בקרוב. כרגע ניתן להעלות תמונות בלבד.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        fabric.Image.fromURL(e.target.result, function(img) {
            // הסרת תמונת רקע קודמת
            if (backgroundImage) {
                window.mapCanvas.remove(backgroundImage);
            }

            // התאמת גודל התמונה ל-canvas
            const canvas = window.mapCanvas;
            const scale = Math.min(
                (canvas.width * 0.9) / img.width,
                (canvas.height * 0.9) / img.height
            );

            img.set({
                left: canvas.width / 2,
                top: canvas.height / 2,
                originX: 'center',
                originY: 'center',
                scaleX: scale,
                scaleY: scale,
                selectable: false, // נעול כברירת מחדל
                evented: false,
                hasControls: true,
                hasBorders: true,
                lockRotation: false,
                objectType: 'backgroundLayer' // שכבה תחתונה - תמיד מתחת לאובייקטי עבודה
            });

            canvas.add(img);
            backgroundImage = img;

            // הצג כפתור עריכת רקע
            const editBgBtn = document.getElementById('editBackgroundBtn');
            if (editBgBtn) editBgBtn.style.display = 'inline-flex';

            // סידור שכבות
            reorderLayers();

            console.log('Background layer image added (locked)');
        });
    };
    reader.readAsDataURL(file);

    // ניקוי ה-input
    event.target.value = '';
}

/**
 * סידור שכבות - סדר היררכי:
 * 1. backgroundLayer - שכבה תחתונה (מהתפריט העליון)
 * 2. workObject - אובייקטי עבודה (מקליק ימני)
 * 3. grayMask + boundaryOutline - תמיד למעלה
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

    objects.forEach(obj => {
        if (obj.objectType === 'grayMask') {
            mask = obj;
        } else if (obj.objectType === 'boundaryOutline') {
            outline = obj;
        } else if (obj.objectType === 'backgroundLayer') {
            backgroundLayers.push(obj);
        } else if (obj.objectType === 'workObject') {
            workObjects.push(obj);
        }
    });

    // סידור: שכבות רקע למטה
    backgroundLayers.forEach(obj => canvas.sendToBack(obj));

    // אובייקטי עבודה מעל שכבות הרקע
    workObjects.forEach(obj => canvas.bringToFront(obj));

    // מסכה וקו גבול תמיד למעלה
    if (mask) canvas.bringToFront(mask);
    if (outline) canvas.bringToFront(outline);

    canvas.renderAll();
}

/**
 * התחלת ציור פוליגון
 */
function startDrawPolygon() {
    if (!isEditMode) return;

    drawingPolygon = true;
    polygonPoints = [];

    document.getElementById('drawPolygonBtn').classList.add('active');

    // שינוי הסמן
    const canvasContainer = document.getElementById('mapCanvas');
    canvasContainer.style.cursor = 'crosshair';

    console.log('Started polygon drawing');
}

/**
 * טיפול בלחיצה על ה-Canvas
 */
function handleCanvasClick(options) {
    if (!drawingPolygon || !isEditMode) return;

    const pointer = window.mapCanvas.getPointer(options.e);
    polygonPoints.push({ x: pointer.x, y: pointer.y });

    // הוספת נקודה ויזואלית
    const point = new fabric.Circle({
        left: pointer.x,
        top: pointer.y,
        radius: 5,
        fill: '#3b82f6',
        stroke: '#1e40af',
        strokeWidth: 2,
        originX: 'center',
        originY: 'center',
        selectable: false,
        polygonPoint: true
    });
    window.mapCanvas.add(point);

    // אם יש לפחות 2 נקודות, צייר קו
    if (polygonPoints.length >= 2) {
        const lastIdx = polygonPoints.length - 1;
        const line = new fabric.Line([
            polygonPoints[lastIdx - 1].x,
            polygonPoints[lastIdx - 1].y,
            polygonPoints[lastIdx].x,
            polygonPoints[lastIdx].y
        ], {
            stroke: '#3b82f6',
            strokeWidth: 2,
            selectable: false,
            polygonLine: true
        });
        window.mapCanvas.add(line);
    }

    window.mapCanvas.renderAll();

    // דאבל קליק לסיום
    if (options.e.detail === 2 && polygonPoints.length >= 3) {
        finishPolygon();
    }
}

/**
 * טיפול בתנועת עכבר - קו תצוגה מקדימה
 */
function handleCanvasMouseMove(options) {
    if (!drawingPolygon || polygonPoints.length === 0) return;

    const pointer = window.mapCanvas.getPointer(options.e);
    const lastPoint = polygonPoints[polygonPoints.length - 1];

    // הסרת קו התצוגה הקודם
    if (previewLine) {
        window.mapCanvas.remove(previewLine);
    }

    // יצירת קו תצוגה מקדימה מהנקודה האחרונה למיקום העכבר
    previewLine = new fabric.Line([
        lastPoint.x,
        lastPoint.y,
        pointer.x,
        pointer.y
    ], {
        stroke: '#3b82f6',
        strokeWidth: 2,
        strokeDashArray: [5, 5], // קו מקווקו
        selectable: false,
        evented: false,
        previewLine: true
    });

    window.mapCanvas.add(previewLine);
    window.mapCanvas.renderAll();
}

/**
 * סיום ציור פוליגון
 */
function finishPolygon() {
    if (polygonPoints.length < 3) {
        alert('נדרשות לפחות 3 נקודות ליצירת גבול');
        return;
    }

    // הסרת קו התצוגה המקדימה
    if (previewLine) {
        window.mapCanvas.remove(previewLine);
        previewLine = null;
    }

    // הסרת נקודות וקווים זמניים
    const objects = window.mapCanvas.getObjects();
    objects.forEach(obj => {
        if (obj.polygonPoint || obj.polygonLine) {
            window.mapCanvas.remove(obj);
        }
    });

    // הסרת גבול/מסכה קודמים אם קיימים
    if (grayMask) {
        window.mapCanvas.remove(grayMask);
        grayMask = null;
    }

    const canvas = window.mapCanvas;
    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;

    // יצירת ה-clipPath לשימוש עתידי
    boundaryClipPath = new fabric.Polygon(polygonPoints.map(p => ({x: p.x, y: p.y})), {
        absolutePositioned: true
    });

    // יצירת מסכה אפורה עם "חור" בצורת הפוליגון
    // נשתמש ב-SVG path שמכסה את כל הקנבס ואז חותך את הפוליגון

    // בניית נתיב SVG: מלבן גדול + פוליגון הפוך
    let pathData = `M 0 0 L ${canvasWidth} 0 L ${canvasWidth} ${canvasHeight} L 0 ${canvasHeight} Z `;

    // הוספת הפוליגון כ"חור" (בכיוון הפוך)
    pathData += `M ${polygonPoints[0].x} ${polygonPoints[0].y} `;
    for (let i = polygonPoints.length - 1; i >= 0; i--) {
        pathData += `L ${polygonPoints[i].x} ${polygonPoints[i].y} `;
    }
    pathData += 'Z';

    grayMask = new fabric.Path(pathData, {
        fill: 'rgba(128, 128, 128, 0.7)',
        selectable: false,
        evented: false,
        objectType: 'grayMask'
    });

    // קו גבול סביב האזור הפעיל
    boundaryOutline = new fabric.Polygon(polygonPoints, {
        fill: 'transparent',
        stroke: '#3b82f6',
        strokeWidth: 3,
        selectable: false,
        evented: false,
        objectType: 'boundaryOutline'
    });

    canvas.add(grayMask);
    canvas.add(boundaryOutline);

    // המסכה תמיד למעלה
    canvas.bringToFront(grayMask);
    canvas.bringToFront(boundaryOutline);

    canvas.renderAll();

    // איפוס
    drawingPolygon = false;
    polygonPoints = [];
    document.getElementById('drawPolygonBtn').classList.remove('active');
    document.getElementById('mapCanvas').style.cursor = 'default';

    // הצג כפתור עריכת גבול
    const editBtn = document.getElementById('editBoundaryBtn');
    if (editBtn) editBtn.style.display = 'flex';

    console.log('Boundary with mask completed');
}

/**
 * ביטול ציור פוליגון
 */
function cancelPolygonDrawing() {
    // הסרת קו התצוגה המקדימה
    if (previewLine) {
        window.mapCanvas.remove(previewLine);
        previewLine = null;
    }

    // הסרת נקודות וקווים זמניים
    const objects = window.mapCanvas.getObjects();
    objects.forEach(obj => {
        if (obj.polygonPoint || obj.polygonLine) {
            window.mapCanvas.remove(obj);
        }
    });

    drawingPolygon = false;
    polygonPoints = [];
    document.getElementById('drawPolygonBtn')?.classList.remove('active');
    document.getElementById('mapCanvas').style.cursor = 'default';
    window.mapCanvas?.renderAll();
}

/**
 * הפעלה/כיבוי מצב עריכת גבול
 */
function toggleBoundaryEdit() {
    if (!boundaryOutline || !grayMask) return;

    isBoundaryEditMode = !isBoundaryEditMode;

    const editBtn = document.getElementById('editBoundaryBtn');

    if (isBoundaryEditMode) {
        // הפעל מצב עריכה - אפשר להזיז את הגבול בלבד
        editBtn.classList.add('active');

        // הפוך רק את הגבול לניתן לבחירה - המסכה תמיד נעולה
        boundaryOutline.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            lockRotation: true
        });

        // המסכה האפורה תמיד נשארת נעולה - לא ניתנת לבחירה
        grayMask.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        // בחר את הגבול
        window.mapCanvas.setActiveObject(boundaryOutline);

        // האזן לשינויים בגבול - המסכה תעודכן אוטומטית
        boundaryOutline.on('moving', updateMaskPosition);
        boundaryOutline.on('scaling', updateMaskPosition);

        console.log('Boundary edit mode: ON');
    } else {
        // כבה מצב עריכה - נעל את הגבול
        editBtn.classList.remove('active');

        boundaryOutline.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        // הסר האזנה
        boundaryOutline.off('moving', updateMaskPosition);
        boundaryOutline.off('scaling', updateMaskPosition);

        window.mapCanvas.discardActiveObject();

        console.log('Boundary edit mode: OFF');
    }

    window.mapCanvas.renderAll();
}

/**
 * הפעלה/כיבוי מצב עריכת תמונת רקע
 */
function toggleBackgroundEdit() {
    if (!backgroundImage) return;

    isBackgroundEditMode = !isBackgroundEditMode;

    const editBtn = document.getElementById('editBackgroundBtn');

    if (isBackgroundEditMode) {
        // הפעל מצב עריכה - אפשר להזיז את תמונת הרקע
        editBtn.classList.add('active');

        backgroundImage.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true
        });

        window.mapCanvas.setActiveObject(backgroundImage);

        console.log('Background edit mode: ON');
    } else {
        // כבה מצב עריכה - נעל את תמונת הרקע
        editBtn.classList.remove('active');

        backgroundImage.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        window.mapCanvas.discardActiveObject();

        console.log('Background edit mode: OFF');
    }

    window.mapCanvas.renderAll();
}

/**
 * עדכון מיקום המסכה בעת הזזת הגבול
 */
function updateMaskPosition() {
    if (!boundaryOutline || !grayMask) return;

    // קבל את הנקודות החדשות של הגבול
    const matrix = boundaryOutline.calcTransformMatrix();
    const points = boundaryOutline.points.map(p => {
        const transformed = fabric.util.transformPoint(
            { x: p.x - boundaryOutline.pathOffset.x, y: p.y - boundaryOutline.pathOffset.y },
            matrix
        );
        return transformed;
    });

    // בנה מחדש את המסכה
    const canvas = window.mapCanvas;
    const canvasWidth = canvas.width;
    const canvasHeight = canvas.height;

    let pathData = `M 0 0 L ${canvasWidth} 0 L ${canvasWidth} ${canvasHeight} L 0 ${canvasHeight} Z `;
    pathData += `M ${points[0].x} ${points[0].y} `;
    for (let i = points.length - 1; i >= 0; i--) {
        pathData += `L ${points[i].x} ${points[i].y} `;
    }
    pathData += 'Z';

    // עדכן את נתיב המסכה
    grayMask.set({ path: fabric.util.parsePath(pathData) });
    canvas.renderAll();
}

/**
 * ניקוי גבולות
 */
function clearPolygon() {
    if (!window.mapCanvas) return;

    // כיבוי מצב עריכה אם פעיל
    if (isBoundaryEditMode) {
        isBoundaryEditMode = false;
        const editBtn = document.getElementById('editBoundaryBtn');
        if (editBtn) editBtn.classList.remove('active');
    }

    const objects = window.mapCanvas.getObjects();
    objects.forEach(obj => {
        if (obj.objectType === 'boundary' ||
            obj.objectType === 'grayMask' ||
            obj.objectType === 'boundaryOutline' ||
            obj.polygonPoint ||
            obj.polygonLine) {
            window.mapCanvas.remove(obj);
        }
    });

    // איפוס משתנים
    boundaryClipPath = null;
    grayMask = null;
    boundaryOutline = null;

    // הסתר כפתור עריכת גבול
    const editBtn = document.getElementById('editBoundaryBtn');
    if (editBtn) editBtn.style.display = 'none';

    window.mapCanvas.renderAll();
}

/**
 * שמירת המפה
 */
function saveMapData() {
    if (!window.mapCanvas) return;

    const mapData = {
        entityType: currentEntityType,
        unicId: currentUnicId,
        canvasJSON: window.mapCanvas.toJSON(['objectType', 'polygonPoint', 'polygonLine']),
        zoom: currentZoom
    };

    console.log('Saving map data:', mapData);
    alert('המפה נשמרה (בקונסול).\nשמירה לשרת תתווסף בהמשך.');
}

/**
 * סגירת הפופאפ
 */
function closeMapPopup() {
    const popup = document.getElementById('mapPopupOverlay');
    if (popup) {
        if (window.mapCanvas) {
            window.mapCanvas.dispose();
            window.mapCanvas = null;
        }
        backgroundImage = null;
        isEditMode = false;
        drawingPolygon = false;
        polygonPoints = [];
        previewLine = null;
        boundaryClipPath = null;
        grayMask = null;
        boundaryOutline = null;
        isBoundaryEditMode = false;
        isBackgroundEditMode = false;
        popup.remove();
    }
}

/**
 * מסך מלא
 */
function toggleMapFullscreen() {
    const container = document.querySelector('.map-popup-container');
    if (container) {
        container.classList.toggle('fullscreen');
        setTimeout(() => {
            if (window.mapCanvas) {
                const canvasContainer = document.getElementById('mapCanvas');
                window.mapCanvas.setWidth(canvasContainer.clientWidth);
                window.mapCanvas.setHeight(canvasContainer.clientHeight - 40);
                window.mapCanvas.renderAll();
            }
        }, 100);
    }
}

/**
 * זום
 */
function zoomMapIn() {
    currentZoom = Math.min(currentZoom + 0.1, 3);
    updateZoomDisplay();
    if (window.mapCanvas) {
        window.mapCanvas.setZoom(currentZoom);
        window.mapCanvas.renderAll();
    }
}

function zoomMapOut() {
    currentZoom = Math.max(currentZoom - 0.1, 0.3);
    updateZoomDisplay();
    if (window.mapCanvas) {
        window.mapCanvas.setZoom(currentZoom);
        window.mapCanvas.renderAll();
    }
}

function updateZoomDisplay() {
    const el = document.getElementById('mapZoomLevel');
    if (el) {
        el.textContent = Math.round(currentZoom * 100) + '%';
    }
}

// ESC לסגירה
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (drawingPolygon) {
            cancelPolygonDrawing();
        } else {
            closeMapPopup();
            closeMapLauncher();
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

    // בדוק אם הנקודה בתוך הגבול
    const isInside = isPointInsideBoundary(x, y);

    // הצג תפריט מתאים
    showContextMenu(e.clientX, e.clientY, isInside);

    return false;
}

/**
 * בדיקה אם נקודה נמצאת בתוך הגבול
 * משתמש באלגוריתם Ray Casting
 */
function isPointInsideBoundary(x, y) {
    // אם אין גבול מוגדר - הכל מותר
    if (!boundaryOutline || !boundaryOutline.points) {
        return true;
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
 * הצגת תפריט קליק ימני
 */
function showContextMenu(clientX, clientY, isInsideBoundary) {
    const menu = document.getElementById('mapContextMenu');
    const content = document.getElementById('contextMenuContent');

    if (!menu || !content) return;

    if (isInsideBoundary) {
        // תפריט רגיל - בתוך הגבול
        content.innerHTML = `
            <div class="context-menu-item" onclick="addImageFromMenu()">
                <span class="context-menu-icon">🖼️</span>
                <span>הוסף תמונה</span>
            </div>
            <div class="context-menu-item" onclick="addTextFromMenu()">
                <span class="context-menu-icon">📝</span>
                <span>הוסף טקסט</span>
            </div>
            <div class="context-menu-separator"></div>
            <div class="context-menu-item" onclick="addShapeFromMenu('rect')">
                <span class="context-menu-icon">⬜</span>
                <span>הוסף מלבן</span>
            </div>
            <div class="context-menu-item" onclick="addShapeFromMenu('circle')">
                <span class="context-menu-icon">⭕</span>
                <span>הוסף עיגול</span>
            </div>
            <div class="context-menu-item" onclick="addShapeFromMenu('line')">
                <span class="context-menu-icon">📏</span>
                <span>הוסף קו</span>
            </div>
        `;
    } else {
        // תפריט אין כניסה - מחוץ לגבול
        content.innerHTML = `
            <div class="context-menu-item disabled">
                <span class="context-menu-icon no-entry-icon">🚫</span>
                <span>לא ניתן להוסיף מחוץ לגבול</span>
            </div>
        `;
    }

    // מיקום התפריט - שימוש ב-fixed position ליד הסמן
    menu.style.position = 'fixed';
    menu.style.left = clientX + 'px';
    menu.style.top = clientY + 'px';

    // וודא שהתפריט לא יוצא מהמסך
    menu.style.display = 'block';

    // בדיקה אם התפריט יוצא מהמסך מימין
    const menuRect = menu.getBoundingClientRect();
    if (menuRect.right > window.innerWidth) {
        menu.style.left = (clientX - menuRect.width) + 'px';
    }
    // בדיקה אם יוצא מלמטה
    if (menuRect.bottom > window.innerHeight) {
        menu.style.top = (clientY - menuRect.height) + 'px';
    }
}

/**
 * הסתרת תפריט קליק ימני
 */
function hideContextMenu() {
    const menu = document.getElementById('mapContextMenu');
    if (menu) {
        menu.style.display = 'none';
    }
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
    }
}
