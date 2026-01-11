/**
 * Map Launcher - מנהל פתיחת המפה
 * Version: 4.0.0 - Modular Popup System
 * Description: Lightweight launcher that uses PopupManager to open the modular map system.
 */

// טעינת LauncherModal ו-EntitySelector
(async function initLauncher() {
    try {
        const { EntitySelector } = await import('../map/launcher/EntitySelector.js');
        window.entitySelector = new EntitySelector({ apiEndpoint: 'api/map-api.php' });

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
            // These hidden inputs might exist in the sidebar
            const typeInput = document.getElementById('mapEntityType');
            const idInput = document.getElementById('mapEntitySelect');
            
            if (typeInput) typeInput.value = entityType;
            if (idInput) idInput.value = entityId;
            
            launchMap(entityType, entityId);
        });

    } catch (error) {
        console.error('❌ Failed to load Launcher modules:', error);
    }
})();

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

/**
 * בדיקת תקינות ופתיחת המפה
 */
async function launchMap(entityType, entityId) {
    // If called without args, try to get from inputs (backwards compatibility)
    if (!entityType) entityType = document.getElementById('mapEntityType')?.value;
    if (!entityId) entityId = document.getElementById('mapEntitySelect')?.value;

    if (!entityType || !entityId) {
        alert('נא לבחור ישות מהרשימה');
        return;
    }

    const entityNames = {
        cemetery: 'בית עלמין',
        block: 'גוש',
        plot: 'חלקה',
        areaGrave: 'אחוזת קבר'
    };

    try {
        // בדיקה שהרשומה קיימת
        const response = await fetch(`api/cemetery-hierarchy.php?action=get&type=${entityType}&id=${entityId}`);
        const result = await response.json();

        if (!result.success || !result.data) {
            throw new Error('הרשומה לא נמצאה במערכת');
        }

        // הרשומה קיימת - פתח את המפה
        closeMapLauncher();
        openMapPopup(entityType, entityId);

    } catch (error) {
        alert(`שגיאה: לא נמצאה רשומת ${entityNames[entityType]} פעילה עם מזהה "${entityId}"

${error.message}`);
    }
}

/**
 * Uses PopupManager to open the new modular map
 */
function openMapPopup(entityType, unicId) {
    console.log('🔓 [OPEN] openMapPopup() called with PopupManager');
    
    // Construct the URL for the new map system
    const mapUrl = `/dashboard/dashboards/cemeteries/map/index.php?type=${entityType}&id=${unicId}&mode=view`;
    
    // Entity name mapping for title
    const entityNames = {
        cemetery: 'בית עלמין',
        block: 'גוש',
        plot: 'חלקה',
        areaGrave: 'אחוזת קבר'
    };

    // Create the popup
    PopupManager.create({
        id: `map-${entityType}-${unicId}`,
        type: 'iframe',
        src: mapUrl,
        title: `מפת ${entityNames[entityType] || entityType}`,
        width: '95%',
        height: '90%',
        position: { x: 'center', y: 'center' },
        draggable: true,
        resizable: true,
        controls: {
            minimize: true,
            maximize: true,
            detach: true,
            close: true
        }
    });
}