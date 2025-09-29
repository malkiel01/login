<?php
// Location: /dashboard/dashboards/printPDF/index.php

// טען את הקבצים בסדר הנכון - בדיוק כמו בבתי עלמין
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';

// בדיקת הרשאות
if (!checkPermission('view', 'pdf_editor')) {
    die('אין לך הרשאה לצפות בעמוד זה');
}

// יצירת CSRF Token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo DASHBOARD_NAME; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700&family=Heebo:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main Styles -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/rtl.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/cloud-storage.css">
    
    <!-- Initial Loading Styles -->
    <style>
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
        
        /* Welcome icon style */
        .welcome-icon {
            font-size: 80px;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        /* Quick actions grid */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .quick-action-btn {
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .quick-action-btn:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .quick-action-btn i {
            font-size: 30px;
            color: #667eea;
            display: block;
            margin-bottom: 10px;
        }
        
        .quick-action-btn span {
            font-size: 14px;
            color: #4a5568;
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
                <h1><?php echo DASHBOARD_NAME; ?></h1>
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
                    <button class="toolbar-btn" id="btnLayers" title="שכבות">
                        <i class="fas fa-layer-group"></i>
                    </button>
                    <button class="toolbar-btn" id="btnTemplates" title="תבניות">
                        <i class="fas fa-object-group"></i>
                    </button>
                    <button class="toolbar-btn cloud-btn" id="btnCloudStorage" title="אחסון ענן">
                        <i class="fas fa-cloud"></i>
                    </button>
                </div>
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
                    <button class="tool-btn active" id="toolSelect" data-tool="select" title="בחירה">
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
                    <canvas id="mainCanvas"></canvas>
                </div>
            </div>

            <!-- Right Sidebar - Layers (Hidden by default) -->
            <aside class="sidebar sidebar-right" id="rightSidebar" style="display: none;">
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
            </aside>
        </div>
    </div>

    <!-- Hidden CSRF Token -->
    <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">

    <!-- JavaScript Libraries from CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Configure PDF.js worker
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>
    
    <!-- Application Scripts -->
    <script src="assets/js/config.js"></script>
    <script src="assets/js/canvas-manager.js"></script>
    <script src="assets/js/undo-redo-manager.js"></script>
    <script src="assets/js/layers-manager.js"></script>
    <script src="assets/js/templates-manager.js"></script>
    <script src="assets/js/batch-processor.js"></script>
    <script src="assets/js/api-connector-fixed.js"></script>
    <script src="assets/js/cloud-save-manager-fixed.js"></script>
    <script src="assets/js/app.js"></script>
    
    <!-- Initialize Application -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading screen and show app
            setTimeout(() => {
                const loadingScreen = document.getElementById('loadingScreen');
                const appContainer = document.getElementById('appContainer');
                
                if (loadingScreen && appContainer) {
                    loadingScreen.style.opacity = '0';
                    
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                        appContainer.style.display = 'flex';
                        
                        // Initialize the application
                        if (typeof PDFEditorApp !== 'undefined') {
                            try {
                                // Create main app instance
                                window.app = new PDFEditorApp();
                                
                                // Initialize API connector
                                if (typeof APIConnector !== 'undefined') {
                                    window.apiConnector = new APIConnector();
                                }
                                
                                // Initialize cloud save manager
                                if (typeof CloudSaveManager !== 'undefined' && window.apiConnector) {
                                    window.cloudSaveManager = new CloudSaveManager(window.apiConnector);
                                }
                                
                                // Initialize the main app
                                window.app.init();
                                
                                console.log('✅ PDF Editor initialized successfully');
                                
                                // Bind button events
                                document.getElementById('btnBrowse')?.addEventListener('click', () => {
                                    document.getElementById('fileInput')?.click();
                                });
                                
                                document.getElementById('btnQuickCloud')?.addEventListener('click', () => {
                                    if (window.cloudSaveManager) {
                                        window.cloudSaveManager.showCloudDialog();
                                    }
                                });
                                
                            } catch (error) {
                                console.error('❌ Failed to initialize:', error);
                                alert('שגיאה באתחול המערכת. אנא רענן את הדף.');
                            }
                        } else {
                            console.error('❌ PDFEditorApp not found');
                            alert('קובץ האפליקציה לא נמצא. אנא בדוק את הקבצים.');
                        }
                    }, 500);
                }
            }, 1000);
        });
        
        // Global error handler
        window.addEventListener('error', function(event) {
            console.error('Global error:', event.error);
        });
    </script>
</body>
</html>