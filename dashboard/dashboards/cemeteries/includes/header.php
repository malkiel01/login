<?php
// dashboard/dashboards/cemeteries/includes/header.php
?>
<header class="dashboard-header">
    <div class="header-content">
        <div class="header-right">
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
                <button class="btn btn-sm btn-secondary" onclick="toggleFullscreen()">
                    <svg class="icon-sm"><use xlink:href="#icon-fullscreen"></use></svg>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="refreshAllData()">
                    <svg class="icon-sm"><use xlink:href="#icon-refresh"></use></svg>
                </button>
            </div>
        </div>
    </div>
</header>

<style>
.dashboard-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 1.5rem 2rem;
    box-shadow: var(--shadow-lg);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1600px;
    margin: 0 auto;
}

.header-right {
    display: flex;
    align-items: center;
}

.header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 1.75rem;
    font-weight: 600;
    margin: 0;
}

.header-icon {
    font-size: 2rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.header-stats {
    display: flex;
    gap: 1.5rem;
}

.header-stat {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.5rem 1rem;
    border-radius: var(--radius-md);
    backdrop-filter: blur(10px);
    text-align: center;
    min-width: 80px;
}

.header-stat.stat-success {
    background: rgba(16, 185, 129, 0.2);
}

.header-stat.stat-warning {
    background: rgba(249, 115, 22, 0.2);
}

.header-stat.stat-danger {
    background: rgba(220, 38, 38, 0.2);
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
}

.stat-label {
    display: block;
    font-size: 0.75rem;
    opacity: 0.9;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 968px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .header-left {
        flex-direction: column;
        width: 100%;
    }
    
    .header-stats {
        width: 100%;
        justify-content: space-around;
    }
}
</style>