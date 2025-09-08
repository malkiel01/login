<?php
// dashboard/dashboards/cemeteries/includes/header.php
?>
<header class="dashboard-header">
    <div class="header-content">
        <div class="header-right">
            <!-- כפתור המבורגר - במיקום הנכון -->
            <button class="hamburger-menu" onclick="toggleSidebar()" aria-label="תפריט">
                <svg class="icon"><use xlink:href="#icon-menu"></use></svg>
            </button>

            <!-- כפתור חזרה לדף הראשי -->
            <a href="/dashboard/" class="btn-home" title="חזרה לדף הראשי">
                <svg class="icon-home" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2" fill="none"/>
                    <polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2" fill="none"/>
                </svg>
            </a>
            
            <h1 class="header-title">
                <span class="header-icon">🪦</span>
                <?php echo DASHBOARD_NAME; ?>
            </h1>
        </div>
        
        <div class="header-left">
            <!-- סטטיסטיקות ראשיות -->
            <div class="header-stats">
                <div class="header-stat">
                    <span class="stat-value" id="headerTotalGraves">0</span>
                    <span class="stat-label">קברים כללי</span>
                </div>
                <div class="header-stat stat-success">
                    <span class="stat-value" id="headerAvailableGraves">0</span>
                    <span class="stat-label">פנויים</span>
                </div>
                <div class="header-stat stat-warning">
                    <span class="stat-value" id="headerReservedGraves">0</span>
                    <span class="stat-label">שמורים</span>
                </div>
                <div class="header-stat stat-danger">
                    <span class="stat-value" id="headerOccupiedGraves">0</span>
                    <span class="stat-label">תפוסים</span>
                </div>
            </div>
            
            <!-- כפתורי פעולה מהירים -->
            <div class="header-actions">
                <button class="btn btn-sm btn-secondary" onclick="toggleFullscreen()" aria-label="מסך מלא">
                    <svg class="icon-sm"><use xlink:href="#icon-fullscreen"></use></svg>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="refreshAllData()" aria-label="רענון">
                    <svg class="icon-sm"><use xlink:href="#icon-refresh"></use></svg>
                </button>
            </div>
        </div>
    </div>
</header>