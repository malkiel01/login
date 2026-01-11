/**
 * Map Launcher - מנהל פתיחת המפה (גרסה חדשה ונקייה)
 * Version: 5.1.0
 *
 * קובץ זה מחליף את map-launcher-old.js (2,786 שורות)
 * משתמש במודולים מתיקיית map/
 *
 * תלויות:
 * - map/launcher/EntitySelector.js - טעינת ישויות מה-API
 * - map/launcher/LauncherModal.js - מודל בחירת ישות
 * - map/index.php - דף המפה עצמו (צריך ?type=X&id=Y)
 */

console.log('%c MAP LAUNCHER v5.1.0 ', 'background: #3b82f6; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;');

// ========================================
// CSS לפופאפ
// ========================================

const popupStyles = `
.map-popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.map-popup-container {
    background: #fff;
    border-radius: 12px;
    width: 95%;
    height: 90%;
    max-width: 1400px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.map-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
}

.map-popup-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.map-popup-controls {
    display: flex;
    gap: 8px;
}

.map-popup-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.map-popup-btn:hover {
    background: rgba(255,255,255,0.3);
}

.map-popup-content {
    flex: 1;
    overflow: hidden;
}

.map-popup-content iframe {
    width: 100%;
    height: 100%;
    border: none;
}
`;

// הוסף CSS לדף
if (!document.getElementById('mapPopupStyles')) {
    const style = document.createElement('style');
    style.id = 'mapPopupStyles';
    style.textContent = popupStyles;
    document.head.appendChild(style);
}

// ========================================
// אתחול המודולים
// ========================================

let entitySelector = null;
let launcherModal = null;
let currentPopup = null;

/**
 * טעינת המודולים בעת טעינת הדף
 */
(async function initMapLauncher() {
    try {
        // 1. טעינת EntitySelector
        const { EntitySelector } = await import('../map/launcher/EntitySelector.js');
        entitySelector = new EntitySelector({ apiEndpoint: 'api/map-api.php' });
        console.log('✅ EntitySelector loaded');

        // 2. טעינת LauncherModal
        const { LauncherModal } = await import('../map/launcher/LauncherModal.js');
        launcherModal = new LauncherModal(entitySelector, {
            modalId: 'mapLauncherModal',
            title: 'פתיחת מפה'
        });
        console.log('✅ LauncherModal loaded');

        // 3. הגדרת callback לפתיחת המפה
        launcherModal.onLaunch((entityType, entityId) => {
            openMapPopup(entityType, entityId);
        });

        console.log('✅ Map Launcher ready');

    } catch (error) {
        console.error('❌ Failed to initialize Map Launcher:', error);
    }
})();

// ========================================
// פופאפ המפה
// ========================================

/**
 * שמות הישויות בעברית
 */
const entityNames = {
    cemetery: 'בית עלמין',
    block: 'גוש',
    plot: 'חלקה',
    areaGrave: 'אחוזת קבר'
};

/**
 * פתיחת פופאפ עם המפה
 */
function openMapPopup(entityType, entityId, mode = 'view') {
    if (!entityType || !entityId) {
        console.error('❌ Missing entityType or entityId');
        alert('חסרים פרטי ישות');
        return;
    }

    // סגירת מודל הבחירה
    closeMapLauncher();

    // סגירת פופאפ קיים
    closeMapPopup();

    // בניית URL
    const url = `map/index.php?type=${entityType}&id=${entityId}&mode=${mode}`;
    console.log(`🗺️ Opening map popup: ${url}`);

    // יצירת הפופאפ
    const overlay = document.createElement('div');
    overlay.id = 'mapPopupOverlay';
    overlay.className = 'map-popup-overlay';
    overlay.innerHTML = `
        <div class="map-popup-container">
            <div class="map-popup-header">
                <h3>מפת ${entityNames[entityType] || entityType}</h3>
                <div class="map-popup-controls">
                    <button class="map-popup-btn" onclick="openMapInNewTab()" title="פתח בלשונית חדשה">↗</button>
                    <button class="map-popup-btn" onclick="closeMapPopup()" title="סגור">✕</button>
                </div>
            </div>
            <div class="map-popup-content">
                <iframe src="${url}" allow="fullscreen"></iframe>
            </div>
        </div>
    `;

    // סגירה בלחיצה על הרקע
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeMapPopup();
        }
    });

    // סגירה ב-Escape
    document.addEventListener('keydown', handleEscKey);

    document.body.appendChild(overlay);
    currentPopup = { overlay, entityType, entityId, mode, url };
}

/**
 * סגירת פופאפ המפה
 */
function closeMapPopup() {
    const overlay = document.getElementById('mapPopupOverlay');
    if (overlay) {
        overlay.remove();
    }
    document.removeEventListener('keydown', handleEscKey);
    currentPopup = null;
}

/**
 * פתיחת המפה בלשונית חדשה
 */
function openMapInNewTab() {
    if (currentPopup) {
        window.open(currentPopup.url, '_blank');
    }
}

/**
 * טיפול במקש Escape
 */
function handleEscKey(e) {
    if (e.key === 'Escape') {
        closeMapPopup();
    }
}

// ========================================
// פונקציות ציבוריות
// ========================================

/**
 * פתיחת מודל בחירת הישות (נקרא מה-sidebar)
 */
function openMapLauncher() {
    if (launcherModal) {
        launcherModal.open();
    } else {
        console.error('❌ LauncherModal not ready yet');
        alert('המערכת עדיין נטענת, נסה שוב');
    }
}

/**
 * סגירת מודל בחירת הישות
 */
function closeMapLauncher() {
    if (launcherModal) {
        launcherModal.close();
    }
}

/**
 * פתיחת המפה ישירות (ללא מודל בחירה)
 */
function openMap(entityType, entityId, mode = 'view') {
    openMapPopup(entityType, entityId, mode);
}

/**
 * פתיחת מפה ישירות לבית עלמין
 */
function openCemeteryMap(cemeteryId) {
    openMapPopup('cemetery', cemeteryId);
}

/**
 * פתיחת מפה ישירות לגוש
 */
function openBlockMap(blockId) {
    openMapPopup('block', blockId);
}

/**
 * פתיחת מפה ישירות לחלקה
 */
function openPlotMap(plotId) {
    openMapPopup('plot', plotId);
}

// ========================================
// Backwards Compatibility
// ========================================

/**
 * פונקציה ישנה - לתאימות אחורה
 */
function launchMap() {
    const entityType = document.getElementById('mapEntityType')?.value;
    const entityId = document.getElementById('mapEntitySelect')?.value;

    if (entityType && entityId) {
        openMapPopup(entityType, entityId);
    } else {
        console.warn('⚠️ launchMap() called without entityType/entityId');
    }
}
