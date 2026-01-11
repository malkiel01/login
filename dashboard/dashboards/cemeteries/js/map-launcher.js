/**
 * Map Launcher - מנהל פתיחת המפה (גרסה חדשה ונקייה)
 * Version: 5.2.0
 *
 * קובץ זה מחליף את map-launcher-old.js (2,786 שורות)
 * משתמש ב:
 * - map/launcher/EntitySelector.js - טעינת ישויות מה-API
 * - map/launcher/LauncherModal.js - מודל בחירת ישות
 * - popup/popup-manager.js - מערכת הפופאפים
 * - map/index.php - דף המפה עצמו
 */

console.log('%c MAP LAUNCHER v5.2.0 ', 'background: #3b82f6; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;');

// ========================================
// אתחול המודולים
// ========================================

let entitySelector = null;
let launcherModal = null;
let popupManagerLoaded = false;

/**
 * טעינת PopupManager
 */
async function loadPopupManager() {
    if (popupManagerLoaded || window.PopupManager) {
        return true;
    }

    return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = '/dashboard/dashboards/cemeteries/popup/popup-manager.js';
        script.onload = () => {
            popupManagerLoaded = true;
            console.log('✅ PopupManager loaded');
            resolve(true);
        };
        script.onerror = () => {
            console.error('❌ Failed to load PopupManager');
            resolve(false);
        };
        document.head.appendChild(script);
    });
}

/**
 * טעינת המודולים בעת טעינת הדף
 */
(async function initMapLauncher() {
    try {
        // 1. טעינת PopupManager
        await loadPopupManager();

        // 2. טעינת EntitySelector
        const { EntitySelector } = await import('../map/launcher/EntitySelector.js');
        entitySelector = new EntitySelector({ apiEndpoint: 'api/map-api.php' });
        console.log('✅ EntitySelector loaded');

        // 3. טעינת LauncherModal
        const { LauncherModal } = await import('../map/launcher/LauncherModal.js');
        launcherModal = new LauncherModal(entitySelector, {
            modalId: 'mapLauncherModal',
            title: 'פתיחת מפה'
        });
        console.log('✅ LauncherModal loaded');

        // 4. הגדרת callback לפתיחת המפה
        launcherModal.onLaunch((entityType, entityId) => {
            openMapPopup(entityType, entityId);
        });

        console.log('✅ Map Launcher ready');

    } catch (error) {
        console.error('❌ Failed to initialize Map Launcher:', error);
    }
})();

// ========================================
// שמות ישויות בעברית
// ========================================

const entityNames = {
    cemetery: 'בית עלמין',
    block: 'גוש',
    plot: 'חלקה',
    areaGrave: 'אחוזת קבר'
};

// ========================================
// פתיחת מפה בפופאפ
// ========================================

/**
 * פתיחת פופאפ עם המפה
 * @param {string} entityType - סוג הישות
 * @param {string} entityId - מזהה הישות
 * @param {string} mode - מצב (view/edit)
 */
function openMapPopup(entityType, entityId, mode = 'view') {
    if (!entityType || !entityId) {
        console.error('❌ Missing entityType or entityId');
        alert('חסרים פרטי ישות');
        return;
    }

    // סגירת מודל הבחירה
    closeMapLauncher();

    // בניית URL
    const url = `map/index.php?type=${entityType}&id=${entityId}&mode=${mode}`;
    const popupId = `map-${entityType}-${entityId}`;

    console.log(`🗺️ Opening map popup: ${url}`);

    // בדיקה אם PopupManager נטען
    if (!window.PopupManager) {
        console.error('❌ PopupManager not loaded');
        // fallback - פתח בלשונית חדשה
        window.open(url, '_blank');
        return;
    }

    // פתיחת הפופאפ עם PopupManager
    window.PopupManager.create({
        id: popupId,
        type: 'iframe',
        src: url,
        title: `מפת ${entityNames[entityType] || entityType}`,
        width: Math.min(window.innerWidth * 0.95, 1400),
        height: Math.min(window.innerHeight * 0.9, 900),
        position: { x: 'center', y: 'center' },
        draggable: true,
        resizable: true,
        controls: {
            minimize: true,
            maximize: true,
            detach: true,
            close: true
        },
        onClose: () => {
            console.log('🗺️ Map popup closed');
        }
    });
}

/**
 * סגירת פופאפ מפה לפי ID
 */
function closeMapPopup(entityType, entityId) {
    if (window.PopupManager) {
        const popupId = `map-${entityType}-${entityId}`;
        window.PopupManager.close(popupId);
    }
}

/**
 * סגירת כל פופאפי המפות
 */
function closeAllMapPopups() {
    if (window.PopupManager) {
        window.PopupManager.closeAll();
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
