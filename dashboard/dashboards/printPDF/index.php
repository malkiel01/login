<?php
// Location: /dashboard/dashboards/printPDF/index.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// בדיקת הרשאות - בדיוק כמו בבתי עלמין
if (!checkPermission('view', 'pdf_editor')) {
    die('אין לך הרשאה לצפות בעמוד זה');
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>עורך PDF ותמונות - מערכת מתקדמת</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700&family=Heebo:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main Styles -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/rtl.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/cloud-storage.css">
    
    <!-- Libraries CSS -->
    <style>
        /* Initial loading styles */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            transition: opacity 0.5s;
        }
        
        .loading-content {
            text-align: center;
            color: white;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            font-size: 20px;
            font-family: 'Rubik', sans-serif;
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">טוען מערכת עריכה...</div>
        </div>
    </div>

    <!-- Main Application -->
    <div class="app-container" id="appContainer" style="display: none;">
        
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="top-bar-section logo-section">
                <i class="fas fa-file-pdf"></i>
                <h1>עורך PDF ותמונות</h1>
            </div>
            
            <div class="top-bar-section toolbar-section">
                <div class="toolbar-group">
                    <button class="toolbar-btn" id="btnNew" title="מסמך חדש">
                        <i class="fas fa-file-plus"></i>
                    </button>
                    <button class="toolbar-btn" id="btnOpen" title="פתח קובץ">
                        <i class="fas fa-folder-open"></i>
                    </button>
                    <button class="toolbar-btn" id="btnSave" title="שמור">
                        <i class="fas fa-save"></i>
                    </button>
                    <div class="toolbar-separator"></div>
                    <button class="toolbar-btn" id="btnUndo" title="ביטול" disabled>
                        <i class="fas fa-undo"></i>
                    </button>
                    <button class="toolbar-btn" id="btnRedo" title="בצע שוב" disabled>
                        <i class="fas fa-redo"></i>
                    </button>
                    <div class="toolbar-separator"></div>
                    <button class="toolbar-btn" id="btnZoomOut" title="הקטן">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="zoom-display" id="zoomDisplay">100%</span>
                    <button class="toolbar-btn" id="btnZoomIn" title="הגדל">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="toolbar-btn" id="btnZoomFit" title="התאם לגודל">
                        <i class="fas fa-compress"></i>
                    </button>
                    <div class="toolbar-separator"></div>
                    <button class="toolbar-btn" id="btnGrid" title="רשת">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="toolbar-btn" id="btnGuides" title="קווי עזר">
                        <i class="fas fa-ruler-combined"></i>
                    </button>
                    <button class="toolbar-btn" id="btnLayers" title="שכבות">
                        <i class="fas fa-layer-group"></i>
                    </button>
                    <button class="toolbar-btn" id="btnTemplates" title="תבניות">
                        <i class="fas fa-object-group"></i>
                    </button>
                    <button class="toolbar-btn" id="btnBatch" title="עיבוד קבוצתי">
                        <i class="fas fa-clone"></i>
                    </button>
                    <button class="toolbar-btn cloud-btn" id="btnCloudSave" title="שמירה בענן">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span class="save-indicator" id="saveIndicator"></span>
                    </button>
                </div>
            </div>
            
            <div class="top-bar-section actions-section">
                <div class="language-switcher">
                    <button class="lang-btn active" data-lang="he">עברית</button>
                    <button class="lang-btn" data-lang="en">English</button>
                    <button class="lang-btn" data-lang="ar">العربية</button>
                </div>
                <button class="btn-api" id="btnAPI" title="API">
                    <i class="fas fa-code"></i>
                </button>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="main-content">
            
            <!-- Left Sidebar - Tools -->
            <aside class="sidebar sidebar-left" id="toolsSidebar">
                <div class="sidebar-header">
                    <h3>כלים</h3>
                </div>
                <div class="tools-panel">
                    <button class="tool-btn" id="toolSelect" data-tool="select" title="בחירה">
                        <i class="fas fa-mouse-pointer"></i>
                        <span>בחירה</span>
                    </button>
                    <button class="tool-btn" id="toolText" data-tool="text" title="טקסט">
                        <i class="fas fa-font"></i>
                        <span>טקסט</span>
                    </button>
                    <button class="tool-btn" id="toolImage" data-tool="image" title="תמונה">
                        <i class="fas fa-image"></i>
                        <span>תמונה</span>
                    </button>
                    <button class="tool-btn" id="toolShape" data-tool="shape" title="צורה">
                        <i class="fas fa-shapes"></i>
                        <span>צורה</span>
                    </button>
                    <button class="tool-btn" id="toolDraw" data-tool="draw" title="ציור">
                        <i class="fas fa-pencil-alt"></i>
                        <span>ציור</span>
                    </button>
                </div>
                
                <div class="properties-panel" id="propertiesPanel">
                    <div class="sidebar-header">
                        <h3>מאפיינים</h3>
                    </div>
                    <div class="properties-content" id="propertiesContent">
                        <!-- Dynamic properties will be loaded here -->
                    </div>
                </div>
            </aside>

            <!-- Canvas Area -->
            <div class="canvas-container" id="canvasContainer">
                <!-- Welcome Screen -->
                <div class="welcome-screen" id="welcomeScreen">
                    <div class="welcome-content">
                        <i class="fas fa-file-pdf welcome-icon"></i>
                        <h2>ברוכים הבאים לעורך PDF ותמונות</h2>
                        <p>עורך מקצועי לעיצוב והוספת טקסט ותמונות למסמכים</p>
                        
                        <div class="welcome-actions">
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h3>גרור קבצים לכאן</h3>
                                <p>או</p>
                                <button class="btn btn-primary" id="btnBrowse">
                                    <i class="fas fa-folder-open"></i>
                                    בחר קבצים
                                </button>
                                <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                                <p class="file-info">PDF, JPG, PNG | עד 10MB</p>
                            </div>
                            
                            <div class="quick-actions">
                                <button class="quick-action-btn" id="btnQuickTemplate">
                                    <i class="fas fa-object-group"></i>
                                    <span>בחר תבנית</span>
                                </button>
                                <button class="quick-action-btn" id="btnQuickRecent">
                                    <i class="fas fa-history"></i>
                                    <span>קבצים אחרונים</span>
                                </button>
                                <button class="quick-action-btn" id="btnQuickCloud">
                                    <i class="fas fa-cloud"></i>
                                    <span>פתח מהענן</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Canvas -->
                <div class="canvas-wrapper" id="canvasWrapper" style="display: none;">
                    <div class="canvas-scroll">
                        <div class="ruler ruler-horizontal" id="rulerH"></div>
                        <div class="ruler ruler-vertical" id="rulerV"></div>
                        <canvas id="mainCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar - Layers & History -->
            <aside class="sidebar sidebar-right" id="rightSidebar" style="display: none;">
                <!-- Layers Panel -->
                <div class="panel-section layers-panel" id="layersPanel">
                    <div class="panel-header">
                        <h3>שכבות</h3>
                        <div class="panel-actions">
                            <button class="panel-action-btn" id="btnAddLayer" title="הוסף שכבה">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="panel-action-btn" id="btnDeleteLayer" title="מחק שכבה">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="layers-list" id="layersList">
                        <!-- Layers will be added here dynamically -->
                    </div>
                </div>
                
                <!-- History Panel -->
                <div class="panel-section history-panel" id="historyPanel">
                    <div class="panel-header">
                        <h3>היסטוריה</h3>
                        <button class="panel-action-btn" id="btnClearHistory" title="נקה היסטוריה">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                    <div class="history-list" id="historyList">
                        <!-- History items will be added here -->
                    </div>
                </div>
            </aside>
        </div>

        <!-- Templates Modal -->
        <div class="modal" id="templatesModal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>תבניות</h2>
                    <button class="modal-close" id="btnCloseTemplates">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="template-categories">
                        <button class="category-btn active" data-category="all">הכל</button>
                        <button class="category-btn" data-category="business">עסקי</button>
                        <button class="category-btn" data-category="certificates">תעודות</button>
                        <button class="category-btn" data-category="presentations">מצגות</button>
                        <button class="category-btn" data-category="receipts">קבלות</button>
                        <button class="category-btn" data-category="custom">מותאם אישית</button>
                    </div>
                    <div class="templates-grid" id="templatesGrid">
                        <!-- Templates will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Processing Modal -->
        <div class="modal" id="batchModal" style="display: none;">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h2>עיבוד קבוצתי</h2>
                    <button class="modal-close" id="btnCloseBatch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="batch-upload-area">
                        <i class="fas fa-file-upload"></i>
                        <h3>העלה עד 20 קבצים</h3>
                        <button class="btn btn-primary" id="btnBatchBrowse">בחר קבצים</button>
                        <input type="file" id="batchFileInput" multiple accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                    </div>
                    <div class="batch-queue" id="batchQueue">
                        <!-- Queue items will be shown here -->
                    </div>
                    <div class="batch-actions">
                        <button class="btn btn-primary" id="btnStartBatch">התחל עיבוד</button>
                        <button class="btn btn-secondary" id="btnClearBatch">נקה רשימה</button>
                        <button class="btn btn-success" id="btnDownloadAll" style="display: none;">הורד הכל</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cloud Storage Modal -->
        <div class="modal" id="cloudModal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>אחסון ענן</h2>
                    <button class="modal-close" id="btnCloseCloud">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="cloud-tabs">
                        <button class="cloud-tab active" data-tab="projects">הפרויקטים שלי</button>
                        <button class="cloud-tab" data-tab="shared">שותף איתי</button>
                        <button class="cloud-tab" data-tab="settings">הגדרות</button>
                    </div>
                    <div class="cloud-content">
                        <div class="projects-grid" id="projectsGrid">
                            <!-- Projects will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Documentation Modal -->
        <div class="modal" id="apiModal" style="display: none;">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h2>API Documentation</h2>
                    <button class="modal-close" id="btnCloseAPI">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="api-content">
                        <h3>שימוש ב-API</h3>
                        <div class="api-endpoint">
                            <h4>POST /api/process-document.php</h4>
                            <pre><code>{
  "document": {
    "file": {
      "base64": "...",
      "path": "...",
      "url": "..."
    },
    "size": {
      "unit": "mm",
      "width": 210,
      "height": 297
    }
  },
  "elements": [
    {
      "type": "text",
      "value": "טקסט לדוגמה",
      "position": {
        "unit": "mm",
        "from_top": 50,
        "from_left": 30
      },
      "style": {
        "font_size_pt": 14,
        "font_family": "Rubik",
        "color": "#000000"
      }
    }
  ]
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden CSRF Token -->
    <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">

    <!-- JavaScript Libraries -->
    <!-- Fabric.js for Canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <script>
        if (typeof fabric === 'undefined') {
            document.write('<script src="assets/js/lib/fabric.min.js"><\/script>');
        }
    </script>
    
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        if (typeof pdfjsLib === 'undefined') {
            document.write('<script src="assets/js/lib/pdf.min.js"><\/script>');
        }
        // Set worker source for PDF.js
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>
    
    <!-- Application Scripts -->
   <!-- קודם את כל הספריות -->
    <script src="assets/js/config.js"></script>
    <script src="assets/js/language-manager.js"></script>
    <script src="assets/js/notification-manager.js"></script>
    <script src="assets/js/loading-manager.js"></script>
    <script src="assets/js/canvas-manager.js"></script>
    <script src="assets/js/undo-redo-manager.js"></script>
    <script src="assets/js/layers-manager.js"></script>
    <script src="assets/js/properties-manager.js"></script>
    <script src="assets/js/templates-manager.js"></script>
    <script src="assets/js/cloud-save-manager.js"></script>
    <script src="assets/js/batch-processor.js"></script>
    <script src="assets/js/api-connector.js"></script>
    <!-- Replace the existing api-connector.js with the fixed version -->
    <script src="assets/js/api-connector-fixed.js"></script>
    <!-- Replace the existing cloud-save-manager.js with the fixed version -->
    <script src="assets/js/cloud-save-manager-fixed.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Initialize application when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading screen
            setTimeout(() => {
                document.getElementById('loadingScreen').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('loadingScreen').style.display = 'none';
                    document.getElementById('appContainer').style.display = 'flex';
                    
                    // Initialize the application
                    if (typeof PDFEditorApp !== 'undefined') {
                        window.app = new PDFEditorApp();
                        window.app.init();
                    }
                }, 500);
            }, 1000);
        });

        // Initialize the application
        if (typeof PDFEditorApp !== 'undefined') {
            window.app = new PDFEditorApp();
            // Initialize API connector
            window.apiConnector = new APIConnector();
            // Initialize cloud save manager
            window.cloudSaveManager = new CloudSaveManager(window.apiConnector);
            // Initialize app
            window.app.init();
        }
    </script>

    <!-- 
    הוסיפי את הקוד הזה בתחתית קובץ index.php, במקום הקוד הקיים
    מיד אחרי טעינת כל קבצי ה-JS ולפני סגירת ה-body 
    -->

    <script>
        // Initialize application when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing PDF Editor Application...');
            
            // Hide loading screen after a delay
            setTimeout(() => {
                const loadingScreen = document.getElementById('loadingScreen');
                const appContainer = document.getElementById('appContainer');
                
                if (loadingScreen) {
                    loadingScreen.style.opacity = '0';
                    
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                        if (appContainer) {
                            appContainer.style.display = 'flex';
                        }
                        
                        // Initialize the application
                        try {
                            // 1. First create API Connector
                            if (typeof APIConnector !== 'undefined') {
                                window.apiConnector = new APIConnector();
                                console.log('✅ API Connector initialized');
                            } else {
                                console.error('❌ APIConnector not found');
                            }
                            
                            // 2. Then create the main app
                            if (typeof PDFEditorApp !== 'undefined') {
                                window.app = new PDFEditorApp();
                                console.log('✅ PDFEditorApp created');
                                
                                // 3. Initialize the app (this will create other managers)
                                window.app.init().then(() => {
                                    console.log('✅ App initialized successfully');
                                    
                                    // 4. Create CloudSaveManager with proper parameters
                                    if (typeof CloudSaveManager !== 'undefined') {
                                        // Pass both canvas manager and API connector
                                        window.cloudSaveManager = new CloudSaveManager(
                                            window.app.canvasManager,
                                            window.apiConnector
                                        );
                                        console.log('✅ CloudSaveManager initialized');
                                        
                                        // Update app's reference
                                        window.app.cloudSaveManager = window.cloudSaveManager;
                                        
                                        // Load projects list if cloud modal exists
                                        if (document.getElementById('projectsList')) {
                                            window.cloudSaveManager.loadProjectsList();
                                        }
                                    }
                                    
                                    // 5. Bind save button directly
                                    const saveBtn = document.getElementById('btnSave');
                                    if (saveBtn && window.cloudSaveManager) {
                                        saveBtn.onclick = function() {
                                            console.log('Save button clicked');
                                            window.cloudSaveManager.saveProject();
                                        };
                                    }
                                    
                                    // 6. Bind cloud button
                                    const cloudBtn = document.getElementById('btnCloudSave');
                                    if (cloudBtn && window.cloudSaveManager) {
                                        cloudBtn.onclick = function() {
                                            console.log('Cloud button clicked');
                                            showCloudModal();
                                        };
                                    }
                                    
                                    console.log('✅ All systems ready!');
                                }).catch(error => {
                                    console.error('❌ App initialization failed:', error);
                                });
                            } else {
                                console.error('❌ PDFEditorApp not found');
                            }
                            
                        } catch (error) {
                            console.error('❌ Fatal initialization error:', error);
                        }
                    }, 500);
                }
            }, 1000);
        });
        
        // Cloud Modal function
        function showCloudModal() {
            // Remove existing modal if any
            const existingModal = document.getElementById('cloudModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create new modal
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'cloudModal';
            modal.style.display = 'block';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>שמירה בענן</h2>
                        <button class="modal-close" onclick="closeCloudModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="cloud-tabs">
                            <button class="cloud-tab active" data-tab="projects" onclick="switchCloudTab('projects', this)">הפרויקטים שלי</button>
                            <button class="cloud-tab" data-tab="save" onclick="switchCloudTab('save', this)">שמור פרויקט</button>
                            <button class="cloud-tab" data-tab="settings" onclick="switchCloudTab('settings', this)">הגדרות</button>
                        </div>
                        
                        <div class="tab-content" id="projectsTab" style="display: block;">
                            <div id="projectsList" style="min-height: 200px;">
                                <div class="loading-spinner" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #667eea;"></i>
                                    <p>טוען פרויקטים...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="saveTab" style="display: none;">
                            <div class="save-form">
                                <h3>שמור פרויקט</h3>
                                <div class="form-group">
                                    <label>שם הפרויקט:</label>
                                    <input type="text" id="projectName" class="form-control" 
                                        placeholder="הכנס שם לפרויקט">
                                </div>
                                <div class="save-actions">
                                    <button class="btn btn-primary" onclick="window.cloudSaveManager.saveProject()">
                                        <i class="fas fa-save"></i> שמור עכשיו
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="settingsTab" style="display: none;">
                            <div class="settings-form">
                                <h3>הגדרות אחסון</h3>
                                <p>השמירה מתבצעת באופן מקומי במחשב שלך</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Load projects list
            if (window.cloudSaveManager) {
                window.cloudSaveManager.loadProjectsList();
            }
        }
        
        function closeCloudModal() {
            const modal = document.getElementById('cloudModal');
            if (modal) {
                modal.remove();
            }
        }
        
        function switchCloudTab(tabName, buttonElement) {
            // Update active button
            document.querySelectorAll('.cloud-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            buttonElement.classList.add('active');
            
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Show selected tab
            const selectedTab = document.getElementById(tabName + 'Tab');
            if (selectedTab) {
                selectedTab.style.display = 'block';
            }
            
            // Load projects if switching to projects tab
            if (tabName === 'projects' && window.cloudSaveManager) {
                window.cloudSaveManager.loadProjectsList();
            }
        }
        
        // Override the default saveDocument function
        window.saveDocument = function() {
            console.log('saveDocument called');
            if (window.cloudSaveManager) {
                window.cloudSaveManager.saveProject();
            } else {
                console.error('CloudSaveManager not available');
            }
        };
    </script>

    <!-- 
    הוסיפי את הקוד הזה בדיוק לפני </body> בקובץ index.php
    אחרי כל טעינת קבצי ה-JS
    -->

    <script>
        // Override save function globally
        window.saveCurrentDocument = function() {
            console.log('Save triggered');
            
            // Option 1: Try CloudSaveManager
            if (window.cloudSaveManager && typeof window.cloudSaveManager.saveProject === 'function') {
                console.log('Using cloudSaveManager');
                window.cloudSaveManager.saveProject();
                return;
            }
            
            // Option 2: Try app.saveDocument
            if (window.app && typeof window.app.saveDocument === 'function') {
                console.log('Using app.saveDocument');
                window.app.saveDocument();
                return;
            }
            
            // Option 3: Direct save
            console.log('Using direct save');
            const projectData = {
                id: 'project_' + Date.now(),
                name: document.getElementById('projectName')?.value || 'Untitled',
                canvas: window.app?.canvasManager?.getCanvasJSON() || {},
                timestamp: new Date().toISOString()
            };
            
            // Save to localStorage
            localStorage.setItem('pdf_editor_project_' + projectData.id, JSON.stringify(projectData));
            
            // Show notification
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 15px 20px;
                background: #48bb78;
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000;
                font-family: 'Rubik', sans-serif;
            `;
            notification.textContent = 'הפרויקט נשמר מקומית';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        };

        // Wait for DOM and initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Starting PDF Editor initialization...');
            
            // Initialize after loading screen
            setTimeout(() => {
                const loadingScreen = document.getElementById('loadingScreen');
                const appContainer = document.getElementById('appContainer');
                
                if (loadingScreen) {
                    loadingScreen.style.opacity = '0';
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                        if (appContainer) appContainer.style.display = 'flex';
                        
                        initializeApplication();
                    }, 500);
                } else {
                    initializeApplication();
                }
            }, 1000);
        });

        async function initializeApplication() {
            try {
                console.log('Initializing application components...');
                
                // 1. Create API Connector
                if (typeof APIConnector !== 'undefined') {
                    window.apiConnector = new APIConnector();
                    console.log('✅ API Connector created');
                }
                
                // 2. Create main app
                if (typeof PDFEditorApp !== 'undefined') {
                    window.app = new PDFEditorApp();
                    console.log('✅ Main app created');
                    
                    // 3. Initialize app
                    await window.app.init();
                    console.log('✅ App initialized');
                    
                    // 4. Fix CloudSaveManager if needed
                    if (!window.cloudSaveManager && window.CloudSaveManager) {
                        window.cloudSaveManager = new CloudSaveManager(
                            window.app.canvasManager,
                            window.apiConnector
                        );
                        window.app.cloudSaveManager = window.cloudSaveManager;
                        console.log('✅ CloudSaveManager fixed');
                    }
                    
                    // 5. Bind save button
                    const saveBtn = document.getElementById('btnSave');
                    if (saveBtn) {
                        // Remove old listeners
                        const newSaveBtn = saveBtn.cloneNode(true);
                        saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                        
                        // Add new listener
                        newSaveBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('Save button clicked!');
                            window.saveCurrentDocument();
                        });
                        console.log('✅ Save button bound');
                    }
                    
                    // 6. Bind keyboard shortcut
                    document.addEventListener('keydown', function(e) {
                        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                            e.preventDefault();
                            console.log('Ctrl+S pressed');
                            window.saveCurrentDocument();
                        }
                    });
                    
                    console.log('✅ All systems ready!');
                    
                } else {
                    console.error('❌ PDFEditorApp not found');
                }
                
            } catch (error) {
                console.error('❌ Initialization error:', error);
            }
        }

        // Test function - you can call this from console
        window.testSave = function() {
            console.log('Testing save functionality...');
            console.log('app:', window.app);
            console.log('apiConnector:', window.apiConnector);
            console.log('cloudSaveManager:', window.cloudSaveManager);
            console.log('canvasManager:', window.app?.canvasManager);
            
            // Try to save
            window.saveCurrentDocument();
        };
    </script>
</body>
</html> 