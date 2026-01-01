/**
 * Map Launcher - מנהל פתיחת המפה
 * Version: 1.0.0
 */

// יצירת המודל בטעינה
document.addEventListener('DOMContentLoaded', function() {
    createMapLauncherModal();
});

/**
 * יצירת מודל בחירת ישות למפה
 */
function createMapLauncherModal() {
    // בדיקה אם המודל כבר קיים
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

        <style>
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
                padding: 0;
                line-height: 1;
            }

            .map-launcher-close:hover {
                color: #1f2937;
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

            .map-launcher-select:focus,
            .map-launcher-input:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

            .map-launcher-footer .btn-primary:hover {
                background: #2563eb;
            }

            .map-launcher-footer .btn-secondary {
                background: white;
                color: #374151;
                border: 1px solid #d1d5db;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
            }

            .map-launcher-footer .btn-secondary:hover {
                background: #f3f4f6;
            }
        </style>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * פתיחת מודל בחירת הישות
 */
function openMapLauncher() {
    const modal = document.getElementById('mapLauncherModal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('mapUnicId').focus();
    }
}

/**
 * סגירת המודל
 */
function closeMapLauncher() {
    const modal = document.getElementById('mapLauncherModal');
    if (modal) {
        modal.style.display = 'none';
        // ניקוי השדות
        document.getElementById('mapUnicId').value = '';
        document.getElementById('mapEntityType').value = 'cemetery';
    }
}

/**
 * פתיחת המפה בפופאפ
 */
function launchMap() {
    const entityType = document.getElementById('mapEntityType').value;
    const unicId = document.getElementById('mapUnicId').value.trim();

    if (!unicId) {
        alert('נא להזין מזהה ייחודי (unicId)');
        document.getElementById('mapUnicId').focus();
        return;
    }

    // סגירת המודל
    closeMapLauncher();

    // פתיחת המפה בפופאפ
    openMapPopup(entityType, unicId);
}

/**
 * פתיחת פופאפ המפה
 */
function openMapPopup(entityType, unicId) {
    // בדיקה אם כבר יש פופאפ פתוח
    let existingPopup = document.getElementById('mapPopupOverlay');
    if (existingPopup) {
        existingPopup.remove();
    }

    const popupHTML = `
        <div id="mapPopupOverlay" class="map-popup-overlay">
            <div class="map-popup-container">
                <div class="map-popup-header">
                    <h3 id="mapPopupTitle">טוען מפה...</h3>
                    <div class="map-popup-controls">
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

        <style>
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
                gap: 8px;
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

            .map-popup-close:hover {
                color: #f87171;
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
        </style>
    `;

    document.body.insertAdjacentHTML('beforeend', popupHTML);

    // טעינת נתוני הישות והמפה
    loadMapData(entityType, unicId);
}

/**
 * טעינת נתוני המפה
 */
async function loadMapData(entityType, unicId) {
    try {
        // קבלת פרטי הישות
        const response = await fetch(`api/cemetery-hierarchy.php?action=get&type=${entityType}&id=${unicId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'לא נמצאה ישות');
        }

        const entity = result.data;

        // עדכון כותרת
        const titleEl = document.getElementById('mapPopupTitle');
        const entityNames = {
            cemetery: 'בית עלמין',
            block: 'גוש',
            plot: 'חלקה',
            areaGrave: 'אחוזת קבר'
        };
        const entityName = entity.cemeteryNameHe || entity.blockNameHe || entity.plotNameHe || entity.areaGraveNameHe || unicId;
        titleEl.textContent = `מפת ${entityNames[entityType]}: ${entityName}`;

        // יצירת המפה
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

    // ניקוי הטעינה
    container.innerHTML = `
        <div class="map-toolbar">
            <div class="map-toolbar-group">
                <button class="map-tool-btn active" data-mode="view" onclick="setMapMode('view')" title="צפייה">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                </button>
                <button class="map-tool-btn" data-mode="draw" onclick="setMapMode('draw')" title="ציור פוליגון">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                    </svg>
                </button>
            </div>
            <div class="map-toolbar-group">
                <button class="map-tool-btn" onclick="zoomMapIn()" title="הגדל">+</button>
                <span id="mapZoomLevel" class="map-zoom-level">100%</span>
                <button class="map-tool-btn" onclick="zoomMapOut()" title="הקטן">−</button>
            </div>
            <div class="map-toolbar-group">
                <button class="map-tool-btn" onclick="saveMapData()" title="שמור">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                    </svg>
                </button>
            </div>
        </div>
        <div id="mapCanvas" class="map-canvas"></div>
    `;

    // הוספת סגנונות לטולבר
    if (!document.getElementById('mapToolbarStyles')) {
        const styles = document.createElement('style');
        styles.id = 'mapToolbarStyles';
        styles.textContent = `
            .map-toolbar {
                display: flex;
                gap: 16px;
                padding: 10px 16px;
                background: white;
                border-bottom: 1px solid #e5e7eb;
                align-items: center;
            }

            .map-toolbar-group {
                display: flex;
                align-items: center;
                gap: 4px;
                padding: 4px;
                background: #f3f4f6;
                border-radius: 8px;
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
        `;
        document.head.appendChild(styles);
    }

    // יצירת Canvas עם Fabric.js (אם קיים) או canvas רגיל
    createMapCanvas(entityType, unicId, entity);
}

/**
 * יצירת ה-Canvas למפה
 */
function createMapCanvas(entityType, unicId, entity) {
    const canvasContainer = document.getElementById('mapCanvas');
    const width = canvasContainer.clientWidth;
    const height = canvasContainer.clientHeight;

    // יצירת אלמנט canvas
    const canvasEl = document.createElement('canvas');
    canvasEl.id = 'fabricCanvas';
    canvasEl.width = width;
    canvasEl.height = height;
    canvasContainer.appendChild(canvasEl);

    // בדיקה אם Fabric.js זמין
    if (typeof fabric !== 'undefined') {
        window.mapCanvas = new fabric.Canvas('fabricCanvas', {
            backgroundColor: '#f9fafb',
            selection: true
        });

        // הוספת טקסט מרכזי
        const text = new fabric.Text(`מפת ${entityType}: ${unicId}`, {
            left: width / 2,
            top: height / 2,
            fontSize: 24,
            fill: '#6b7280',
            originX: 'center',
            originY: 'center'
        });
        window.mapCanvas.add(text);

        console.log('Fabric.js canvas initialized');
    } else {
        // fallback - canvas רגיל
        const ctx = canvasEl.getContext('2d');
        ctx.fillStyle = '#f9fafb';
        ctx.fillRect(0, 0, width, height);
        ctx.fillStyle = '#6b7280';
        ctx.font = '24px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(`מפת ${entityType}: ${unicId}`, width / 2, height / 2);

        console.log('Regular canvas initialized (Fabric.js not found)');
    }
}

/**
 * סגירת פופאפ המפה
 */
function closeMapPopup() {
    const popup = document.getElementById('mapPopupOverlay');
    if (popup) {
        // ניקוי Canvas
        if (window.mapCanvas) {
            window.mapCanvas.dispose();
            window.mapCanvas = null;
        }
        popup.remove();
    }
}

/**
 * מעבר למסך מלא
 */
function toggleMapFullscreen() {
    const container = document.querySelector('.map-popup-container');
    if (container) {
        container.classList.toggle('fullscreen');

        // עדכון גודל Canvas
        setTimeout(() => {
            if (window.mapCanvas) {
                const canvasContainer = document.getElementById('mapCanvas');
                window.mapCanvas.setWidth(canvasContainer.clientWidth);
                window.mapCanvas.setHeight(canvasContainer.clientHeight);
                window.mapCanvas.renderAll();
            }
        }, 100);
    }
}

// פונקציות placeholder לטולבר
let currentMapMode = 'view';
let currentZoom = 1;

function setMapMode(mode) {
    currentMapMode = mode;
    document.querySelectorAll('.map-tool-btn[data-mode]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    console.log('Map mode:', mode);
}

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

function saveMapData() {
    alert('שמירת מפה - יופעל בהמשך');
}

// ESC לסגירה
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMapPopup();
        closeMapLauncher();
    }
});
