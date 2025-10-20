<?php
// dashboard/dashboards/cemeteries/includes/sidebar.php
?>
<aside class="dashboard-sidebar" id="dashboardSidebar">
    <div class="sidebar-header">
        <h3 class="sidebar-title">ניווט</h3>
        <button class="btn-toggle-sidebar" onclick="toggleSidebar()">
            <svg class="icon-sm"><use xlink:href="#icon-menu"></use></svg>
        </button>
    </div>
    
    <!-- חיפוש מהיר -->
    <div class="sidebar-search">
        <input type="text" 
               class="form-control" 
               id="sidebarSearch" 
               placeholder="חיפוש מהיר..."
               onkeyup="performQuickSearch(this.value)">
    </div>
    
    <!-- רמות ההיררכיה -->
    <div class="hierarchy-levels">
        <!-- בתי עלמין -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="cemeteryItem" onclick="loadAllCemeteries()">
                <span class="hierarchy-icon">🏛️</span>
                <span class="hierarchy-title">בתי עלמין</span>
                <span class="hierarchy-count" id="cemeteriesCount">0</span>
            </div>
            <div id="cemeterySelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- גושים -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="blockItem" onclick="loadAllBlocks()">
                <span class="hierarchy-icon">📦</span>
                <span class="hierarchy-title">גושים</span>
                <span class="hierarchy-count" id="blocksCount">0</span>
            </div>
            <div id="blockSelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- חלקות -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="plotItem" onclick="if(typeof loadAllPlots === 'function') 
loadAllPlots()">
                <span class="hierarchy-icon">📋</span>
                <span class="hierarchy-title">חלקות</span>
                <span class="hierarchy-count" id="plotsCount">0</span>
            </div>
            <div id="plotSelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- אחוזות קבר -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="areaGraveItem" onclick="if(typeof loadAllAreaGraves === 
'function') loadAllAreaGraves()">
                <span class="hierarchy-icon">🏘️</span>
                <span class="hierarchy-title">אחוזות קבר</span>
                <span class="hierarchy-count" id="areaGravesCount">0</span>
            </div>
            <div id="areaGraveSelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- קברים -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" id="graveItem" onclick="if(typeof loadAllGraves === 'function') 
loadAllGraves()">
                <span class="hierarchy-icon">🪦</span>
                <span class="hierarchy-title">קברים</span>
                <span class="hierarchy-count" id="gravesCount">0</span>
            </div>
            <div id="graveSelectedItem" class="selected-item-container"></div>
        </div>
    </div>

    <!-- קו מפריד -->
    <div style="margin: 20px 15px; border-top: 2px solid #e5e7eb;"></div>
    
    <!-- ניהול נוסף -->
    <div class="management-section" style="padding: 0 15px;">
        <h4 style="font-size: 14px; color: #718096; margin-bottom: 10px; font-weight: 
600;">ניהול</h4>
        
        <!-- לקוחות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" id="customersItem" onclick="loadCustomers()">
                <span class="hierarchy-icon">👥</span>
                <span class="hierarchy-title">לקוחות</span>
                <span class="hierarchy-count" id="customersCount">0</span>
            </div>
        </div>
        
        <!-- רכישות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" id="purchasesItem" onclick="loadAllPurchases()">
                <span class="hierarchy-icon">💰</span>
                <span class="hierarchy-title">רכישות</span>
                <span class="hierarchy-count" id="purchasesCount">0</span>
            </div>
        </div>
        
        <!-- קבורות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" id="burialsItem" onclick="loadAllBurials()">
                <span class="hierarchy-icon">⚱️</span>
                <span class="hierarchy-title">קבורות</span>
                <span class="hierarchy-count" id="burialsCount">0</span>
            </div>
        </div>
    </div>

    <!-- קו מפריד -->
    <div style="margin: 20px 15px; border-top: 2px solid #e5e7eb;"></div>

    <!-- ניהול מערכת -->
    <div class="system-management-section" style="padding: 0 15px;">
        <h4 style="font-size: 14px; color: #718096; margin-bottom: 10px; font-weight: 600;">
            <span style="margin-left: 5px;">⚙️</span>
            ניהול מערכת
        </h4>
        
        <!-- תשלומים -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" id="paymentsItem" onclick="loadPayments()">
                <span class="hierarchy-icon">💳</span>
                <span class="hierarchy-title">ניהול תשלומים</span>
                <span class="hierarchy-count" id="paymentsCount">0</span>
            </div>
        </div>

        <!-- תושבויות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" id="residencyItem" onclick="loadResidencies()">
                <span class="hierarchy-icon">🏠</span>
                <span class="hierarchy-title">הגדרות תושבות</span>
                <span class="hierarchy-count" id="residencyCount">0</span>
            </div>
        </div>

        <!-- מדינות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" id="countryItem" onclick="loadCountries()">
                <span class="hierarchy-icon">🌍</span>
                <span class="hierarchy-title">ניהול מדינות</span>
                <span class="hierarchy-count" id="countryCount">0</span>
            </div>
        </div>

        <!-- ערים -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" id="cityItem" onclick="loadCities()">
                <span class="hierarchy-icon">🏙️</span>
                <span class="hierarchy-title">ניהול ערים</span>
                <span class="hierarchy-count" id="cityCount">0</span>
            </div>
        </div>
        
        <!-- אופציות עתידיות -->
        <div class="management-item" style="margin-bottom: 10px; opacity: 0.5;">
            <div class="hierarchy-header disabled">
                <span class="hierarchy-icon">🗺️</span>
                <span class="hierarchy-title">טריטוריית בית עלמין</span>
                <span class="badge" style="background: #fbbf24; color: #78350f; font-size: 10px; padding: 
2px 6px; border-radius: 4px;">בקרוב</span>
            </div>
        </div>
        
        <div class="management-item" style="margin-bottom: 10px; opacity: 0.5;">
            <div class="hierarchy-header disabled">
                <span class="hierarchy-icon">🗺️</span>
                <span class="hierarchy-title">ניהול מפות</span>
                <span class="badge" style="background: #fbbf24; color: #78350f; font-size: 10px; padding: 
2px 6px; border-radius: 4px;">בקרוב</span>
            </div>
        </div>
        
        <div class="management-item" style="margin-bottom: 10px; opacity: 0.5;">
            <div class="hierarchy-header disabled">
                <span class="hierarchy-icon">🔐</span>
                <span class="hierarchy-title">ניהול הרשאות</span>
                <span class="badge" style="background: #fbbf24; color: #78350f; font-size: 10px; padding: 
2px 6px; border-radius: 4px;">בקרוב</span>
            </div>
        </div>
        
        <div class="management-item" style="margin-bottom: 10px; opacity: 0.5;">
            <div class="hierarchy-header disabled">
                <span class="hierarchy-icon">📊</span>
                <span class="hierarchy-title">ניהול דוחות</span>
                <span class="badge" style="background: #fbbf24; color: #78350f; font-size: 10px; padding: 
2px 6px; border-radius: 4px;">בקרוב</span>
            </div>
        </div>
    </div>

    <!-- פעולות מהירות -->
    <div class="sidebar-footer">
        <button class="btn btn-primary btn-block" onclick="openAddModal()">
            <svg class="icon-sm"><use xlink:href="#icon-plus"></use></svg>
            הוספה חדשה
        </button>
        <button class="btn btn-secondary btn-block mt-2" onclick="exportData()">
            <svg class="icon-sm"><use xlink:href="#icon-download"></use></svg>
            ייצוא נתונים
        </button>
    </div>
</aside>

<!-- SVG Icons -->
<svg style="display: none;">
    <symbol id="icon-menu" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
    </symbol>
    <symbol id="icon-download" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 
2 0 0 1-2-2v-4m4-5l5 5l5-5m-5 5V3"/>
    </symbol>
    <symbol id="icon-fullscreen" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 
0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
    </symbol>
    <symbol id="icon-enter" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M9 10l5-5m0 0h-4m4 0v4m1 
7H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>
    </symbol>
</svg>

<style>
/* ============================================
   SIDEBAR UNIFIED DESIGN - עיצוב אחיד מלא
   ============================================ */

/* Base State - מצב רגיל */
.hierarchy-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #475569;
    font-weight: 500;
}

/* Hover State - ריחוף */
.hierarchy-header:hover:not(.disabled) {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateX(-2px);
}

/* Active State - מצב פעיל (כמו התשלומים!) */
.hierarchy-header.active {
    background: #f0f9ff;
    border: 1px solid #3b82f6;
    color: #1e40af;
    font-weight: 600;
    transform: translateX(-3px);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
}

/* Active State - Count Badge בעיצוב כחול */
.hierarchy-header.active .hierarchy-count {
    background: #3b82f6;
    color: white;
}

/* Disabled State */
.hierarchy-header.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.hierarchy-header.disabled:hover {
    transform: none;
}

/* Icon Styling */
.hierarchy-icon {
    font-size: 1.5rem;
    transition: transform 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.hierarchy-header:hover:not(.disabled) .hierarchy-icon {
    transform: scale(1.05);
}

.hierarchy-header.active .hierarchy-icon {
    transform: scale(1.1);
}

/* Title Styling */
.hierarchy-title {
    flex: 1;
    font-size: 0.9375rem;
    line-height: 1.4;
}

/* Count Badge - עיצוב אחיד */
.hierarchy-count {
    background: #e2e8f0;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    min-width: 28px;
    text-align: center;
    transition: all 0.3s ease;
}

.hierarchy-header:hover:not(.disabled) .hierarchy-count {
    background: #cbd5e1;
    transform: scale(1.05);
}

/* Loading State for Count */
.hierarchy-count.loading {
    background: #f1f5f9;
    color: transparent;
    position: relative;
    overflow: hidden;
}

.hierarchy-count.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, 
        transparent, 
        rgba(255, 255, 255, 0.6), 
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    to { left: 100%; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .hierarchy-header {
        padding: 0.75rem 0.875rem;
    }
    
    .hierarchy-icon {
        font-size: 1.25rem;
        width: 28px;
        height: 28px;
    }
    
    .hierarchy-title {
        font-size: 0.875rem;
    }
    
    .hierarchy-count {
        font-size: 0.75rem;
        padding: 0.2rem 0.6rem;
    }
}
</style>
