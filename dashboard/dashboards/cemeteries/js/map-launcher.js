/**
 * Map Launcher - מנהל פתיחת המפה (גרסה חדשה ונקייה)
 * Version: 5.0.0
 *
 * קובץ זה מחליף את map-launcher-old.js (2,786 שורות)
 * משתמש במודולים מתיקיית map/
 *
 * תלויות:
 * - map/launcher/EntitySelector.js - טעינת ישויות מה-API
 * - map/launcher/LauncherModal.js - מודל בחירת ישות
 * - map/index.php - דף המפה עצמו (צריך ?type=X&id=Y)
 */

console.log('%c MAP LAUNCHER v5.0.0 ', 'background: #3b82f6; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;');

// ========================================
// אתחול המודולים
// ========================================

let entitySelector = null;
let launcherModal = null;

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
            openMap(entityType, entityId);
        });

        console.log('✅ Map Launcher ready');

    } catch (error) {
        console.error('❌ Failed to initialize Map Launcher:', error);
    }
})();

// ========================================
// פונקציות ציבוריות
// ========================================

/**
 * פתיחת מודל בחירת הישות
 * נקרא מה-sidebar
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
 * פתיחת המפה עם ישות מסוימת
 * @param {string} entityType - סוג הישות (cemetery/block/plot/areaGrave)
 * @param {string} entityId - מזהה הישות (unicId)
 * @param {string} mode - מצב (view/edit)
 */
function openMap(entityType, entityId, mode = 'view') {
    if (!entityType || !entityId) {
        console.error('❌ Missing entityType or entityId');
        alert('חסרים פרטי ישות');
        return;
    }

    // סגירת המודל
    closeMapLauncher();

    // בניית URL
    const url = `map/index.php?type=${entityType}&id=${entityId}&mode=${mode}`;

    console.log(`🗺️ Opening map: ${url}`);

    // פתיחה בטאב חדש
    window.open(url, '_blank');
}

/**
 * פתיחת מפה ישירות לבית עלמין
 * @param {string} cemeteryId
 */
function openCemeteryMap(cemeteryId) {
    openMap('cemetery', cemeteryId);
}

/**
 * פתיחת מפה ישירות לגוש
 * @param {string} blockId
 */
function openBlockMap(blockId) {
    openMap('block', blockId);
}

/**
 * פתיחת מפה ישירות לחלקה
 * @param {string} plotId
 */
function openPlotMap(plotId) {
    openMap('plot', plotId);
}

// ========================================
// Backwards Compatibility
// ========================================

// פונקציה ישנה - לתאימות אחורה
function launchMap() {
    const entityType = document.getElementById('mapEntityType')?.value;
    const entityId = document.getElementById('mapEntitySelect')?.value;

    if (entityType && entityId) {
        openMap(entityType, entityId);
    } else {
        console.warn('⚠️ launchMap() called without entityType/entityId');
    }
}
