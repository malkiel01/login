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
</body>
</html>