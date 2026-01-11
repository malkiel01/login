<?php
/**
 * Cemetery Map - מפת בית העלמין
 *
 * דף המפה האינטראקטיבית להצגת גושים, חלקות ואחוזות קבר
 *
 * פרמטרים:
 * - type: סוג הישות (cemetery, block, plot, areaGrave)
 * - id: מזהה ייחודי (unicId)
 * - mode: מצב (view, edit)
 */

// טעינת הגדרות
require_once dirname(__DIR__) . '/../../config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// קבלת פרמטרים
$entityType = $_GET['type'] ?? 'cemetery';
$entityId = $_GET['id'] ?? null;
$mode = $_GET['mode'] ?? 'view';

// וידוא פרמטרים
if (!$entityId) {
    die('חסר מזהה ישות');
}

// מיפוי סוגי ישויות
$entityConfig = [
    'cemetery' => [
        'table' => 'cemeteries',
        'nameField' => 'cemeteryNameHe',
        'title' => 'בית עלמין',
        'color' => '#1976D2',
        'children' => 'blocks'
    ],
    'block' => [
        'table' => 'blocks',
        'nameField' => 'blockNameHe',
        'title' => 'גוש',
        'color' => '#388E3C',
        'children' => 'plots',
        'parentField' => 'cemeteryId'
    ],
    'plot' => [
        'table' => 'plots',
        'nameField' => 'plotNameHe',
        'title' => 'חלקה',
        'color' => '#F57C00',
        'children' => 'areaGraves',
        'parentField' => 'blockId'
    ],
    'areaGrave' => [
        'table' => 'areaGraves',
        'nameField' => 'areaGraveNameHe',
        'title' => 'אחוזת קבר',
        'color' => '#7B1FA2',
        'children' => null,
        'parentField' => 'lineId'
    ]
];

$config = $entityConfig[$entityType] ?? $entityConfig['cemetery'];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מפת <?php echo $config['title']; ?></title>

    <!-- Fabric.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

    <!-- Styles -->
    <link rel="stylesheet" href="css/cemetery-map.css">

    <!-- Dashboard Styles (for consistency) -->
    <link rel="stylesheet" href="../css/main.css">
</head>
<body class="map-body">
    <!-- Header -->
    <header class="map-header">
        <div class="header-right">
            <button class="btn-icon" id="btnBack" title="חזור">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                </svg>
            </button>
            <h1 class="map-title">
                <span id="entityTitle">טוען...</span>
            </h1>
        </div>

        <div class="header-center">
            <!-- Zoom Controls -->
            <div class="zoom-controls">
                <button class="btn-icon" id="btnZoomOut" title="הקטן">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19 13H5v-2h14v2z"/>
                    </svg>
                </button>
                <span class="zoom-level" id="zoomLevel">100%</span>
                <button class="btn-icon" id="btnZoomIn" title="הגדל">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </button>
                <button class="btn-icon" id="btnZoomFit" title="התאם לחלון">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M3 5v4h2V5h4V3H5c-1.1 0-2 .9-2 2zm2 10H3v4c0 1.1.9 2 2 2h4v-2H5v-4zm14 4h-4v2h4c1.1 0 2-.9 2-2v-4h-2v4zm0-16h-4v2h4v4h2V5c0-1.1-.9-2-2-2z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="header-left">
            <!-- Mode Toggle (Edit mode only) -->
            <?php if ($mode === 'edit'): ?>
            <div class="mode-controls">
                <button class="btn-tool" id="btnSelect" title="בחירה" data-active="true">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51L3 3z"/>
                    </svg>
                </button>
                <button class="btn-tool" id="btnDraw" title="ציור פוליגון">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M17 15.7V13h-4v4h2.7l-4.7 4.7-1.4-1.4 4.7-4.7V13h-4V9h4V6.3L9.6 11l1.4 1.4 4.7-4.7V10h4v4h-2.7l4.7 4.7-1.4 1.4-4.7-4.7z"/>
                    </svg>
                </button>
                <button class="btn-tool" id="btnEdit" title="עריכת נקודות">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </button>
                <div class="divider"></div>
                <button class="btn-tool" id="btnAddImage" title="הוסף תמונת רקע">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                    </svg>
                </button>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="action-controls">
                <?php if ($mode === 'edit'): ?>
                <button class="btn-primary" id="btnSave">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="currentColor" d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                    </svg>
                    שמור
                </button>
                <?php else: ?>
                <button class="btn-secondary" id="btnEditMode">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                    עריכה
                </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Sidebar - Entity List -->
    <aside class="map-sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3 id="sidebarTitle">רשימה</h3>
            <button class="btn-icon btn-sm" id="btnToggleSidebar">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
            </button>
        </div>
        <div class="sidebar-search">
            <input type="text" id="sidebarSearch" placeholder="חיפוש...">
        </div>
        <div class="sidebar-list" id="sidebarList">
            <!-- Items will be loaded dynamically -->
        </div>
        <div class="sidebar-stats" id="sidebarStats">
            <!-- Statistics -->
        </div>
    </aside>

    <!-- Main Canvas Container -->
    <main class="map-container" id="mapContainer">
        <div class="canvas-wrapper" id="canvasWrapper">
            <canvas id="mapCanvas"></canvas>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
            <span>טוען מפה...</span>
        </div>
    </main>

    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu">
        <ul>
            <li data-action="viewCard">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
                צפה בכרטיס
            </li>
            <li data-action="zoomTo">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
                התמקד
            </li>
            <li data-action="drillDown" id="menuDrillDown">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M9.29 6.71L9 7l3 3H3v2h9l-3 3 .29.29 4.71-4.71L9.29 6.71zM20 13v-2h1v-1h-1V9h1V8h-1V7h1V6h-1V5h-1v14h1v-1h-1v-1h1v-1h-1v-1h1v-1h-1v-1h1z"/>
                </svg>
                הצג ילדים
            </li>
            <hr class="menu-divider edit-only">
            <li data-action="editPolygon" class="edit-only">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                </svg>
                ערוך פוליגון
            </li>
            <li data-action="deletePolygon" class="edit-only danger">
                <svg viewBox="0 0 24 24" width="16" height="16">
                    <path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                </svg>
                מחק פוליגון
            </li>
        </ul>
    </div>

    <!-- Tooltip -->
    <div class="map-tooltip" id="mapTooltip"></div>

    <!-- Info Panel (shown on hover/click) -->
    <div class="info-panel" id="infoPanel">
        <div class="info-panel-header">
            <span class="info-panel-title" id="infoPanelTitle"></span>
            <button class="btn-icon btn-sm" id="btnCloseInfo">×</button>
        </div>
        <div class="info-panel-content" id="infoPanelContent">
            <!-- Dynamic content -->
        </div>
    </div>

    <!-- Hidden file input for background image -->
    <input type="file" id="backgroundImageInput" accept="image/*" style="display: none;">

    <!-- Scripts -->
    <script>
        // Global configuration
        window.MAP_CONFIG = {
            entityType: '<?php echo $entityType; ?>',
            entityId: '<?php echo $entityId; ?>',
            mode: '<?php echo $mode; ?>',
            apiBase: '../api/',
            entityConfig: <?php echo json_encode($entityConfig); ?>,
            colors: {
                cemetery: '#1976D2',
                block: '#388E3C',
                plot: '#F57C00',
                areaGrave: '#7B1FA2',
                available: '#4CAF50',
                purchased: '#2196F3',
                buried: '#9E9E9E',
                saved: '#FF9800'
            },
            statusLabels: {
                1: 'פנוי',
                2: 'נרכש',
                3: 'קבור',
                4: 'שמור'
            }
        };
    </script>

    <!-- Debug: Check if page loads -->
    <script>
        console.log('📄 Map page loaded, MAP_CONFIG:', window.MAP_CONFIG);

        // Catch module loading errors
        window.addEventListener('error', function(e) {
            console.error('🔴 Script error:', e.message, e.filename, e.lineno);
        });
    </script>

    <!-- Test module loading one by one -->
    <script type="module">
        console.log('📦 Starting module imports...');

        // First - test if file is accessible via fetch
        console.log('🔍 Testing file accessibility...');
        try {
            const testFetch = await fetch('./config/EntityConfig.js');
            console.log('📥 Fetch status:', testFetch.status, testFetch.statusText);
            console.log('📥 Content-Type:', testFetch.headers.get('content-type'));
            const text = await testFetch.text();
            console.log('📥 File size:', text.length, 'chars');
            console.log('📥 First 200 chars:', text.substring(0, 200));
        } catch (fetchError) {
            console.error('❌ Fetch failed:', fetchError);
        }

        const cacheBust = Date.now(); // Force fresh load

        async function loadModules() {
            try {
                // Test with simple module first
                console.log('⏳ Loading test-module...');
                const testMod = await import(`./config/test-module.js?v=${cacheBust}`);
                console.log('✅ test-module loaded:', testMod.TEST_VALUE);

                console.log('⏳ Loading EntityConfigV2...');
                const { EntityConfig } = await import(`./config/EntityConfigV2.js?v=${cacheBust}`);
                console.log('✅ EntityConfigV2 loaded');

                console.log('⏳ Loading MapAPI...');
                const { MapAPI, EntityAPI } = await import(`./api/MapAPI.js?v=${cacheBust}`);
                console.log('✅ MapAPI loaded');

                console.log('⏳ Loading MapManager...');
                const { MapManager } = await import(`./core/MapManager.js?v=${cacheBust}`);
                console.log('✅ MapManager loaded');

                return { EntityConfig, MapAPI, EntityAPI, MapManager };
            } catch (error) {
                console.error('❌ Module loading failed:', error);
                console.error('Stack:', error.stack);
                throw error;
            }
        }

        const modules = await loadModules();
        const { MapManager } = modules;

        console.log('✅ All modules imported successfully');

        // Initialize the map
        const loadingOverlay = document.getElementById('loadingOverlay');

        try {
            console.log('🚀 Starting map initialization...');
            const config = window.MAP_CONFIG;
            console.log('📋 Config:', config);

            // Create map manager instance
            const mapManager = new MapManager({
                entityType: config.entityType,
                entityId: config.entityId,
                mode: config.mode,
                canvasId: 'mapCanvas'
            });

            console.log('✅ MapManager created');

            // Listen for all events for debugging
            mapManager.on('loading:start', () => console.log('📥 Loading started...'));
            mapManager.on('data:loaded', (data) => console.log('📊 Data loaded:', data));
            mapManager.on('data:error', (data) => console.error('❌ Data error:', data.error));

            // Listen for initialization complete
            mapManager.on('init:complete', () => {
                console.log('✅ Map initialized successfully');
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
            });

            // Listen for initialization error
            mapManager.on('init:error', (data) => {
                console.error('❌ Init error:', data.error);
                if (loadingOverlay) {
                    loadingOverlay.innerHTML = `
                        <div style="text-align:center; color:#dc2626; padding:20px;">
                            <p style="font-size:18px; font-weight:bold;">שגיאה בטעינת המפה</p>
                            <p style="font-size:14px; margin-top:10px; background:#fee; padding:10px; border-radius:8px; direction:ltr; text-align:left;">${data.error.message}</p>
                            <p style="font-size:12px; margin-top:10px; color:#666;">בדוק את ה-Console לפרטים נוספים</p>
                        </div>
                    `;
                }
            });

            console.log('🔧 Starting init()...');
            // Initialize and load the map
            await mapManager.init();

            // Make it globally accessible for debugging
            window.mapManager = mapManager;
            console.log('🎉 All done!');

        } catch (error) {
            console.error('💥 Failed to initialize map:', error);
            console.error('Stack:', error.stack);
            if (loadingOverlay) {
                loadingOverlay.innerHTML =
                    '<div style="text-align:center; color:#dc2626; padding:20px;">' +
                    '<p style="font-size:18px; font-weight:bold;">שגיאה בטעינת המפה</p>' +
                    '<p style="font-size:14px; margin-top:10px; background:#fee; padding:10px; border-radius:8px; direction:ltr; text-align:left;">' + error.message + '</p>' +
                    '<p style="font-size:12px; margin-top:10px; color:#666;">בדוק את ה-Console לפרטים נוספים</p>' +
                    '</div>';
            }
        }
    </script>
</body>
</html>
