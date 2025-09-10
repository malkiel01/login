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
    
    <!-- רמות ההיררכיה - גרסה חדשה -->
    <div class="hierarchy-levels">
        <!-- בתי עלמין -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="loadAllCemeteries()">
                <span class="hierarchy-icon">🏛️</span>
                <span class="hierarchy-title">בתי עלמין</span>
                <span class="hierarchy-count" id="cemeteriesCount">0</span>
            </div>
            <div id="cemeterySelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- גושים -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="loadAllBlocks()">
                <span class="hierarchy-icon">📦</span>
                <span class="hierarchy-title">גושים</span>
                <span class="hierarchy-count" id="blocksCount">0</span>
            </div>
            <div id="blockSelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- חלקות -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="if(typeof loadAllPlots === 'function') loadAllPlots()">
                <span class="hierarchy-icon">📋</span>
                <span class="hierarchy-title">חלקות</span>
                <span class="hierarchy-count" id="plotsCount">0</span>
            </div>
            <div id="plotSelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- אחוזות קבר -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="if(typeof loadAllAreaGraves === 'function') loadAllAreaGraves()">
                <span class="hierarchy-icon">🏘️</span>
                <span class="hierarchy-title">אחוזות קבר</span>
                <span class="hierarchy-count" id="areaGravesCount">0</span>
            </div>
            <div id="areaGraveSelectedItem" class="selected-item-container"></div>
        </div>
        
        <!-- קברים -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="if(typeof loadAllGraves === 'function') loadAllGraves()">
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
        <h4 style="font-size: 14px; color: #718096; margin-bottom: 10px; font-weight: 600;">ניהול</h4>
        
        <!-- לקוחות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" onclick="loadCustomers()" style="background: #f7fafc;">
                <span class="hierarchy-icon">👥</span>
                <span class="hierarchy-title">לקוחות</span>
                <span class="hierarchy-count" id="customersCount" style="background: #4facfe; color: white;">0</span>
            </div>
        </div>
        
        <!-- רכישות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" onclick="if(typeof loadPurchases === 'function') loadPurchases()" style="background: #f7fafc;">
                <span class="hierarchy-icon">💰</span>
                <span class="hierarchy-title">רכישות</span>
                <span class="hierarchy-count" id="purchasesCount" style="background: #43e97b; color: white;">0</span>
            </div>
        </div>
        
        <!-- קבורות -->
        <div class="management-item" style="margin-bottom: 10px;">
            <div class="hierarchy-header" onclick="if(typeof loadBurials === 'function') loadBurials()" style="background: #f7fafc;">
                <span class="hierarchy-icon">⚱️</span>
                <span class="hierarchy-title">קבורות</span>
                <span class="hierarchy-count" id="burialsCount" style="background: #fa709a; color: white;">0</span>
            </div>
        </div>
    </div>

    בדיקה

    <!-- רכישות וקבורות -->
<div class="sidebar-section" style="margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
    <h4 class="sidebar-subtitle" style="margin-bottom: 15px; color: #4b5563; font-size: 14px; text-transform: uppercase;">ניהול עסקאות</h4>
    
    <!-- רכישות -->
    <div class="sidebar-item" onclick="loadAllPurchases()" style="padding: 10px 15px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-radius: 6px; transition: background 0.2s;">
        <div style="display: flex; align-items: center;">
            <span style="margin-right: 10px;">💰</span>
            <span>רכישות</span>
        </div>
        <span class="badge" id="purchasesCount" style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">0</span>
    </div>
    
    <!-- קבורות -->
    <div class="sidebar-item" onclick="loadAllBurials()" style="padding: 10px 15px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-radius: 6px; transition: background 0.2s;">
        <div style="display: flex; align-items: center;">
            <span style="margin-right: 10px;">⚰️</span>
            <span>קבורות</span>
        </div>
        <span class="badge" id="burialsCount" style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">0</span>
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
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5l5 5l5-5m-5 5V3"/>
    </symbol>
    <symbol id="icon-fullscreen" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
    </symbol>
    <symbol id="icon-enter" viewBox="0 0 24 24">
        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M9 10l5-5m0 0h-4m4 0v4m1 7H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>
    </symbol>
</svg>