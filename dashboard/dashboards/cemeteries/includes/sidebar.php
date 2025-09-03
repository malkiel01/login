<?php
// dashboard/dashboards/cemeteries/includes/sidebar.php
?>
<aside class="dashboard-sidebar" id="dashboardSidebar">
    <div class="sidebar-header">
        <h3 class="sidebar-title">היררכיה</h3>
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
            <div class="hierarchy-header" onclick="toggleHierarchyLevel('cemeteries')">
                <span class="hierarchy-icon">🏛️</span>
                <span class="hierarchy-title">בתי עלמין</span>
                <span class="hierarchy-count" id="cemeteriesCount">0</span>
            </div>
            <div class="hierarchy-list" id="cemeteriesList">
                <!-- Will be populated by JS -->
            </div>
        </div>
        
        <!-- גושים -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="toggleHierarchyLevel('blocks')">
                <span class="hierarchy-icon">📦</span>
                <span class="hierarchy-title">גושים</span>
                <span class="hierarchy-count" id="blocksCount">0</span>
            </div>
            <div class="hierarchy-list collapsed" id="blocksList">
                <!-- Will be populated by JS -->
            </div>
        </div>
        
        <!-- חלקות -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="toggleHierarchyLevel('plots')">
                <span class="hierarchy-icon">📋</span>
                <span class="hierarchy-title">חלקות</span>
                <span class="hierarchy-count" id="plotsCount">0</span>
            </div>
            <div class="hierarchy-list collapsed" id="plotsList">
                <!-- Will be populated by JS -->
            </div>
        </div>
        
        <!-- שורות -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="toggleHierarchyLevel('rows')">
                <span class="hierarchy-icon">📏</span>
                <span class="hierarchy-title">שורות</span>
                <span class="hierarchy-count" id="rowsCount">0</span>
            </div>
            <div class="hierarchy-list collapsed" id="rowsList">
                <!-- Will be populated by JS -->
            </div>
        </div>
        
        <!-- אחוזות קבר -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="toggleHierarchyLevel('areaGraves')">
                <span class="hierarchy-icon">🏘️</span>
                <span class="hierarchy-title">אחוזות קבר</span>
                <span class="hierarchy-count" id="areaGravesCount">0</span>
            </div>
            <div class="hierarchy-list collapsed" id="areaGravesList">
                <!-- Will be populated by JS -->
            </div>
        </div>
        
        <!-- קברים -->
        <div class="hierarchy-level">
            <div class="hierarchy-header" onclick="toggleHierarchyLevel('graves')">
                <span class="hierarchy-icon">🪦</span>
                <span class="hierarchy-title">קברים</span>
                <span class="hierarchy-count" id="gravesCount">0</span>
            </div>
            <div class="hierarchy-list collapsed" id="gravesList">
                <!-- Will be populated by JS -->
            </div>
        </div>
    </div>
    
    <!-- פעולות מהירות -->
    <div class="sidebar-footer">
        <button class="btn btn-primary btn-block" onclick="openQuickAdd()">
            <svg class="icon-sm"><use xlink:href="#icon-plus"></use></svg>
            הוספה מהירה
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
</svg>

<style>
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

.hierarchy-list {
    margin-top: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    transition: var(--transition);
}

.hierarchy-list.collapsed {
    max-height: 0;
    margin: 0;
    overflow: hidden;
}

.hierarchy-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.25rem;
    background: var(--bg-primary);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.875rem;
}

.hierarchy-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(-2px);
}

.hierarchy-item.selected {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
}

.hierarchy-item-name {
    flex: 1;
}

.hierarchy-item-badge {
    font-size: 0.75rem;
    padding: 0.125rem 0.375rem;
    border-radius: var(--radius-sm);
    background: rgba(0, 0, 0, 0.1);
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
</style>