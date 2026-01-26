<?php
/*
 * File: dashboards/dashboard/cemeteries/includes/sidebar.php
 * Version: 2.0.0
 * Updated: 2025-11-04
 * Author: Malkiel
 * Change Summary:
 * - הוספת מנגנון Active State אחיד לכל פריטי הסיידבר
 * - שיפור חווית משתמש עם סימון ויזואלי של הפריט הנבחר
 * - שמירת מצב הבחירה ב-localStorage
 * - תמיכה בכל סוגי הפריטים: היררכיה, ניהול ומערכת
 */
?>

<aside class="dashboard-sidebar" id="dashboardSidebar">
    <div class="sidebar-header">
        <h3 class="sidebar-title">ניווט</h3>
        <button class="btn-toggle-sidebar" onclick="toggleSidebar()">
            <svg class="icon-sm"><use xlink:href="#icon-menu"></use></svg>
        </button>
    </div>

    <!-- כפתור חזרה לראשי - מובייל בלבד -->
    <a href="/dashboard/" class="sidebar-home-link" onclick="closeSidebarOnMobile()">
        <span class="hierarchy-icon">🏠</span>
        <span class="hierarchy-title">חזרה לדף הראשי</span>
    </a>

    <!-- חיפוש מהיר -->
    <div class="sidebar-search">
        <input type="text" 
               class="form-control" 
               id="sidebarSearch" 
               placeholder="חיפוש מהיר..."
               onkeyup="performQuickSearch(this.value)">
    </div>
    
    <!-- רמות ההירארכיה -->
    <div class="hierarchy-levels">
        <!-- בתי עלמין -->
        <?php if (isAdmin() || hasModulePermission('cemeteries', 'view') || hasModulePermission('cemeteries', 'edit') || hasModulePermission('cemeteries', 'create')): ?>
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="cemeteriesItem" onclick="handleSidebarClick('cemeteriesItem', loadCemeteries)">
                <span class="hierarchy-icon">🏛️</span>
                <span class="hierarchy-title">בתי עלמין</span>
                <span class="hierarchy-count" id="cemeteriesCount">0</span>
            </div>
            <div id="cemeterySelectedItem" class="selected-item-container"></div>
        </div>
        <?php endif; ?>

        <!-- גושים -->
        <?php if (isAdmin() || hasModulePermission('blocks', 'view') || hasModulePermission('blocks', 'edit') || hasModulePermission('blocks', 'create')): ?>
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="blocksItem" onclick="handleSidebarClick('blocksItem', loadBlocks)">
                <span class="hierarchy-icon">📦</span>
                <span class="hierarchy-title">גושים</span>
                <span class="hierarchy-count" id="blocksCount">0</span>
            </div>
            <div id="blockSelectedItem" class="selected-item-container"></div>
        </div>
        <?php endif; ?>

        <!-- חלקות -->
        <?php if (isAdmin() || hasModulePermission('plots', 'view') || hasModulePermission('plots', 'edit') || hasModulePermission('plots', 'create')): ?>
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="plotsItem" onclick="handleSidebarClick('plotsItem', loadPlots)">
                <span class="hierarchy-icon">📋</span>
                <span class="hierarchy-title">חלקות</span>
                <span class="hierarchy-count" id="plotsCount">0</span>
            </div>
            <div id="plotSelectedItem" class="selected-item-container"></div>
        </div>
        <?php endif; ?>

        <!-- אחוזות קבר -->
        <?php if (isAdmin() || hasModulePermission('areaGraves', 'view') || hasModulePermission('areaGraves', 'edit') || hasModulePermission('areaGraves', 'create')): ?>
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="areaGravesItem" onclick="handleSidebarClick('areaGravesItem', loadAreaGraves)">
                <span class="hierarchy-icon">🏘️</span>
                <span class="hierarchy-title">אחוזות קבר</span>
                <span class="hierarchy-count" id="areaGravesCount">0</span>
            </div>
            <div id="areaGraveSelectedItem" class="selected-item-container"></div>
        </div>
        <?php endif; ?>

        <!-- קברים -->
        <?php if (isAdmin() || hasModulePermission('graves', 'view') || hasModulePermission('graves', 'edit') || hasModulePermission('graves', 'create')): ?>
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="gravesItem" onclick="handleSidebarClick('gravesItem', loadGraves)">
                <span class="hierarchy-icon">🪦</span>
                <span class="hierarchy-title">קברים</span>
                <span class="hierarchy-count" id="gravesCount">0</span>
            </div>
            <div id="graveSelectedItem" class="selected-item-container"></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- קו מפריד -->
    <div class="sidebar-divider"></div>
    
    <!-- ניהול נוסף -->
    <div class="management-section">
        <h4>ניהול</h4>

        <!-- לקוחות -->
        <?php if (isAdmin() || hasModulePermission('customers', 'view') || hasModulePermission('customers', 'edit') || hasModulePermission('customers', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="customersItem" onclick="handleSidebarClick('customersItem', loadCustomers)">
                <span class="hierarchy-icon">👥</span>
                <span class="hierarchy-title">לקוחות</span>
                <span class="hierarchy-count" id="customersCount">0</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- רכישות -->
        <?php if (isAdmin() || hasModulePermission('purchases', 'view') || hasModulePermission('purchases', 'edit') || hasModulePermission('purchases', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="purchasesItem" onclick="handleSidebarClick('purchasesItem', loadPurchases)">
                <span class="hierarchy-icon">💰</span>
                <span class="hierarchy-title">רכישות</span>
                <span class="hierarchy-count" id="purchasesCount">0</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- קבורות -->
        <?php if (isAdmin() || hasModulePermission('burials', 'view') || hasModulePermission('burials', 'edit') || hasModulePermission('burials', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="burialsItem" onclick="handleSidebarClick('burialsItem', loadBurials)">
                <span class="hierarchy-icon">⚱️</span>
                <span class="hierarchy-title">קבורות</span>
                <span class="hierarchy-count" id="burialsCount">0</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- קו מפריד -->
    <div class="sidebar-divider"></div>


    <!-- <button onclick="GravesInventoryReport.open()" class="btn-primary">
        📊 דוח יתרות קברים
    </button> -->

    <!-- ניהול מערכת -->
    <div class="system-management-section">
        <h4>ניהול מערכת</h4>

        <!-- דוח קברים -->
        <?php if (isAdmin() || hasModulePermission('reports', 'view') || hasModulePermission('reports', 'edit') || hasModulePermission('reports', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="gravesReportItem" onclick="handleSidebarClick('gravesReportItem', function() { GravesInventoryReport.open(); })">
                <span class="hierarchy-icon">📊</span>
                <span class="hierarchy-title">דוח יתרות קברים</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- תשלומים -->
        <?php if (isAdmin() || hasModulePermission('payments', 'view') || hasModulePermission('payments', 'edit') || hasModulePermission('payments', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="paymentsItem" onclick="handleSidebarClick('paymentsItem', loadPayments)">
                <span class="hierarchy-icon">💳</span>
                <span class="hierarchy-title">ניהול תשלומים</span>
                <span class="hierarchy-count" id="paymentsCount">0</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- תושבויות -->
        <?php if (isAdmin() || hasModulePermission('residency', 'view') || hasModulePermission('residency', 'edit') || hasModulePermission('residency', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="residencyItem" onclick="handleSidebarClick('residencyItem', function() { if(typeof loadResidencies === 'function') loadResidencies(); })">
                <span class="hierarchy-icon">🏠</span>
                <span class="hierarchy-title">הגדרות תושבות</span>
                <span class="hierarchy-count" id="residencyCount">0</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- מדינות -->
        <?php if (isAdmin() || hasModulePermission('countries', 'view') || hasModulePermission('countries', 'edit') || hasModulePermission('countries', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="countriesItem" onclick="handleSidebarClick('countriesItem', function() { if(typeof loadCountries === 'function') loadCountries(); })">
                <span class="hierarchy-icon">🌍</span>
                <span class="hierarchy-title">ניהול מדינות</span>
                <span class="hierarchy-count" id="countryCount">0</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ערים -->
        <?php if (isAdmin() || hasModulePermission('cities', 'view') || hasModulePermission('cities', 'edit') || hasModulePermission('cities', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="citiesItem" onclick="handleSidebarClick('citiesItem', function() { if(typeof loadCities === 'function') loadCities(); })">
                <span class="hierarchy-icon">🏙️</span>
                <span class="hierarchy-title">ניהול ערים</span>
                <span class="hierarchy-count" id="cityCount">0</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Popup Manager Demo - רק ל-admin -->
        <?php if (isAdmin()): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="popupDemoItem" onclick="handleSidebarClick('popupDemoItem', loadPopupDemo)">
                <span class="hierarchy-icon">🎯</span>
                <span class="hierarchy-title">Popup Manager - Demo</span>
                <span class="badge badge-new">חדש</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- מפות -->
        <?php if (isAdmin() || hasModulePermission('map', 'view') || hasModulePermission('map', 'edit')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="mapItem" onclick="handleSidebarClick('mapItem', openMap)">
                <span class="hierarchy-icon">🗺️</span>
                <span class="hierarchy-title">ניהול מפות</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ניהול משתמשים -->
        <?php if (isAdmin() || hasModulePermission('users', 'view') || hasModulePermission('users', 'edit') || hasModulePermission('users', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header" id="usersItem" onclick="handleSidebarClick('usersItem', openUsersManagement)">
                <span class="hierarchy-icon">👥</span>
                <span class="hierarchy-title">ניהול משתמשים</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ניהול דוחות - בקרוב -->
        <?php if (isAdmin() || hasModulePermission('reports', 'view') || hasModulePermission('reports', 'edit') || hasModulePermission('reports', 'create')): ?>
        <div class="management-item">
            <div class="hierarchy-header disabled">
                <span class="hierarchy-icon">📊</span>
                <span class="hierarchy-title">ניהול דוחות</span>
                <span class="badge badge-soon">בקרוב</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- כפתור התנתקות -->
    <div class="sidebar-footer">
        <a href="/auth/logout.php" class="btn btn-logout btn-block" onclick="return confirmLogout()">
            <svg class="icon-sm"><use xlink:href="#icon-logout"></use></svg>
            התנתק
        </a>
    </div>
</aside>

<!-- SVG Icons -->
<svg style="display: none;">
    <symbol id="icon-menu" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
    </symbol>
    <symbol id="icon-plus" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 5v14m-7-7h14"/>
    </symbol>
    <symbol id="icon-download" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5l5 5l5-5m-5 5V3"/>
    </symbol>
    <symbol id="icon-fullscreen" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
    </symbol>
    <symbol id="icon-enter" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M9 10l5-5m0 0h-4m4 0v4m1 7H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>
    </symbol>
    <symbol id="icon-logout" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14l5-5l-5-5m5 5H9"/>
    </symbol>
</svg>

<script>
/*
 * ============================================
 * SIDEBAR ACTIVE STATE MANAGER
 * Version: 2.0.0
 * Updated: 2025-11-04
 * Author: Malkiel
 * Description: מנגנון אחיד לניהול מצב אקטיבי בסיידבר
 * ============================================
 */

/**
 * פונקציה מרכזית לטיפול בלחיצה על פריט בסיידבר
 * @param {string} itemId - מזהה הפריט שנלחץ (למשל: 'customersItem')
 * @param {Function} callbackFunction - הפונקציה המקורית שטוענת את התוכן
 */
function handleSidebarClick(itemId, callbackFunction) {
    // קריאה לפונקציה שמעדכנת את המצב הויזואלי
    setActiveSidebarItem(itemId);

    // קריאה לפונקציה המקורית
    if (typeof callbackFunction === 'function') {
        callbackFunction();
    }

    // סגירת הסיידבר במובייל/טאבלט אחרי לחיצה על פריט
    closeSidebarOnMobile();
}

/**
 * סוגר את הסיידבר במובייל/טאבלט
 */
function closeSidebarOnMobile() {
    // בדוק אם אנחנו במסך קטן (768px ומטה)
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('dashboardSidebar');
        if (sidebar && sidebar.classList.contains('open')) {
            // השתמש בפונקציה הגלובלית שסוגרת גם את ה-overlay
            if (typeof closeSidebar === 'function') {
                closeSidebar();
            } else {
                // fallback אם הפונקציה לא זמינה
                sidebar.classList.remove('open');
                const overlay = document.getElementById('sidebarOverlay');
                if (overlay) {
                    overlay.classList.remove('active');
                }
                document.body.style.overflow = '';
            }
            console.log('📱 סיידבר נסגר אוטומטית במובייל');
        }
    }
}

/**
 * מעדכן את מצב ה-active של פריט בסיידבר
 * @param {string} itemId - ה-ID של הפריט שנבחר
 */
function setActiveSidebarItem(itemId) {
    try {
        // הסרת active מכל הפריטים
        const allHeaders = document.querySelectorAll('.hierarchy-header');
        allHeaders.forEach(header => {
            header.classList.remove('active');
        });
        
        // הוספת active לפריט הנוכחי
        const selectedItem = document.getElementById(itemId);
        if (selectedItem && !selectedItem.classList.contains('disabled')) {
            selectedItem.classList.add('active');
            
            // שמירה ב-localStorage
            localStorage.setItem('activeSidebarItem', itemId);
            
            // לוג לצורכי דיבוג
            console.log('✅ פריט אקטיבי עודכן:', itemId);
        }
    } catch (error) {
        console.error('❌ שגיאה בעדכון מצב אקטיבי:', error);
    }
}

/**
 * משחזר את מצב ה-active בעת טעינת הדף
 */
function restoreActiveSidebarItem() {
    try {
        const savedItem = localStorage.getItem('activeSidebarItem');
        if (savedItem) {
            const element = document.getElementById(savedItem);
            if (element && !element.classList.contains('disabled')) {
                element.classList.add('active');
                console.log('🔄 שוחזר פריט אקטיבי:', savedItem);
            }
        }
    } catch (error) {
        console.error('❌ שגיאה בשחזור מצב אקטיבי:', error);
    }
}

/**
 * ניקוי מצב אקטיבי (שימושי לריסט או logout)
 */
function clearActiveSidebarItem() {
    try {
        localStorage.removeItem('activeSidebarItem');
        const allHeaders = document.querySelectorAll('.hierarchy-header');
        allHeaders.forEach(header => {
            header.classList.remove('active');
        });
        console.log('🧹 מצב אקטיבי נוקה');
    } catch (error) {
        console.error('❌ שגיאה בניקוי מצב אקטיבי:', error);
    }
}

// ====================================
// אתחול אוטומטי בטעינת הדף
// ====================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restoreActiveSidebarItem);
} else {
    // אם המסמך כבר נטען
    restoreActiveSidebarItem();
}

// ====================================
// Compatibility Layer - תאימות לאחור
// ====================================
// אם קיימות פונקציות ישנות שלא עודכנו, נוסיף להן את המנגנון אוטומטית

/**
 * עוטף פונקציה קיימת עם מנגנון ה-active state
 * @param {Function} originalFunc - הפונקציה המקורית
 * @param {string} itemId - מזהה הפריט
 * @returns {Function} הפונקציה העטופה
 */
function wrapWithActiveState(originalFunc, itemId) {
    return function() {
        setActiveSidebarItem(itemId);
        return originalFunc.apply(this, arguments);
    };
}

// דוגמה לשימוש (אם צריך):
// window.loadCustomers = wrapWithActiveState(window.loadCustomers, 'customersItem');

console.log('✨ מנגנון Sidebar Active State אותחל בהצלחה - גרסה 2.0.0');

/**
 * פתיחת מסך ניהול משתמשים
 */
function openUsersManagement() {
    if (typeof PopupManager !== 'undefined') {
        PopupManager.create({
            id: 'users-management-popup',
            type: 'iframe',
            src: '/dashboard/dashboards/cemeteries/users/',
            title: 'ניהול משתמשים',
            width: 1200,
            height: 800
        });
    } else {
        window.location.href = '/dashboard/dashboards/cemeteries/users/';
    }
}

/**
 * אישור התנתקות
 */
function confirmLogout() {
    return confirm('האם אתה בטוח שברצונך להתנתק?');
}

/**
 * תיקון גובה viewport למובייל (iOS Safari fix)
 * מחשב את הגובה האמיתי של ה-viewport ללא כותרת הדפדפן
 */
function fixMobileViewportHeight() {
    // חישוב הגובה האמיתי
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);

    // עדכון גובה הסיידבר
    const sidebar = document.getElementById('dashboardSidebar');
    if (sidebar && window.innerWidth <= 768) {
        const headerHeight = window.innerWidth <= 480 ? 50 : 56;
        sidebar.style.height = `calc(${window.innerHeight}px - ${headerHeight}px)`;
    }
}

// הפעלה ראשונית ובכל שינוי גודל/אוריינטציה
fixMobileViewportHeight();
window.addEventListener('resize', fixMobileViewportHeight);
window.addEventListener('orientationchange', function() {
    setTimeout(fixMobileViewportHeight, 100);
});
</script>