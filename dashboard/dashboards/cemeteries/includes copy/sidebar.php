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

<!-- <style>
    .dashboard-sidebar {
        width: 280px;
        background: var(--bg-secondary);
        border-left: 1px solid var(--border-color);
        height: calc(100vh - 80px);
        overflow-y: auto;
        transition: var(--transition);
    }

    .dashboard-sidebar.collapsed {
        width: 60px;
    }

    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .btn-toggle-sidebar {
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 0.5rem;
        display: none;
    }

    .sidebar-search {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .hierarchy-levels {
        padding: 1rem;
    }

    .hierarchy-level {
        margin-bottom: 1rem;
    }

    .hierarchy-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: var(--transition);
    }

    .hierarchy-header:hover {
        background: var(--bg-tertiary);
        transform: translateX(-2px);
    }

    .hierarchy-header.active {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
    }

    /* הוסף את הסגנונות האלה ב-sidebar.php אחרי שורה 170 בערך */

    .hierarchy-level {
        margin-bottom: 0.5rem;
    }

    .hierarchy-header.active {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        font-weight: 600;
    }

    .selected-item-container {
        margin-top: 0.5rem;
        margin-right: 2rem;
        padding: 0.5rem;
        background: var(--bg-tertiary);
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        display: none;
        animation: slideIn 0.3s ease;
    }

    .selected-item-container:not(:empty) {
        display: block;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .selected-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.5rem;
        background: white;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: var(--transition);
    }

    .selected-item:hover {
        background: var(--primary-light);
        transform: translateX(2px);
    }

    .hierarchy-icon {
        font-size: 1.25rem;
    }

    .hierarchy-title {
        flex: 1;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .hierarchy-count {
        background: var(--primary-color);
        color: white;
        padding: 0.125rem 0.5rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .hierarchy-header.active .hierarchy-count {
        background: white;
        color: var(--primary-color);
    }

    /* תצוגת פריט נבחר */
    .selected-item-container {
        margin-top: 0.5rem;
        margin-right: 2rem;
        padding: 0.5rem;
        background: var(--bg-tertiary);
        border-radius: var(--radius-sm);
        font-size: 0.8rem;
        display: none;
    }

    .selected-item-container:not(:empty) {
        display: block;
    }

    .selected-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .selected-icon {
        color: var(--primary-color);
    }

    .selected-name {
        font-weight: 500;
        color: var(--text-secondary);
    }

    .sidebar-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border-color);
    }

    .btn-block {
        width: 100%;
    }

    /* Mobile responsive */
    @media (max-width: 1024px) {
        .btn-toggle-sidebar {
            display: block;
        }
        
        .dashboard-sidebar {
            position: fixed;
            right: 0;
            top: 80px;
            z-index: 100;
            box-shadow: var(--shadow-xl);
            transform: translateX(0);
        }
        
        .dashboard-sidebar.collapsed {
            transform: translateX(100%);
        }
    }
</style> -->