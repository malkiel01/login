<?php
/**
 * Dashboard Header
 * v2.0.0 - 2026-01-25
 *
 * Uses company settings for branding
 * Theme-compatible with CSS variables
 */

// Load company settings
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/settings/api/CompanySettingsManager.php';
$conn = getDBConnection();
$companyManager = CompanySettingsManager::getInstance($conn);
$branding = $companyManager->getBranding();
?>
<header class="dashboard-header">
    <div class="header-content">
        <div class="header-right">
            <!-- Hamburger Menu Button -->
            <button class="hamburger-menu" onclick="toggleSidebar()" aria-label="תפריט">
                <svg class="icon"><use xlink:href="#icon-menu"></use></svg>
            </button>

            <!-- Back to Main Button -->
            <a href="/dashboard/" class="btn-home-responsive" title="חזרה לדף הראשי">
                <svg class="icon-home" viewBox="0 0 24 24" fill="none">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2"/>
                    <polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span class="btn-home-text">חזרה לראשי</span>
            </a>

            <!-- Company Branding -->
            <div class="header-branding">
                <?php if ($branding['hasLogo']): ?>
                    <img src="<?php echo htmlspecialchars($branding['logo']); ?>" alt="לוגו" class="header-logo">
                <?php else: ?>
                    <span class="header-icon">🪦</span>
                <?php endif; ?>
                <h1 class="header-title"><span class="header-title-text"><?php echo htmlspecialchars($branding['name']); ?></span></h1>
            </div>
        </div>

        <div class="header-left">
            <!-- Statistics (controlled by user preferences) -->
            <div class="header-stats" id="headerStats">
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

            <!-- Quick Actions -->
            <div class="header-actions">
                <button class="btn btn-sm btn-secondary" onclick="openUserSettings()" aria-label="הגדרות" title="הגדרות אישיות">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </button>
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
