/* Layers Panel */
        .layers-panel {
            margin-top: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            background: white;
        }

        .layer-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .layer-item:hover {
            background: #f8f9ff;
        }

        .layer-item.active {
            background: #f0f2ff;
            border-color: #667eea;
        }

        .layer-controls {
            display: flex;
            gap: 0.5rem;
        }

        .layer-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #667eea;
            transition: all 0.3s;
        }

        .layer-btn:hover {
            color: #764ba2;
        }

        .layer-name {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .object-count {
            font-size: 0.8rem;
            color: #999;
        }

        .add-layer-btn {
            width: 100%;
            padding: 0.5rem;
            border: 1px dashed #667eea;
            background: transparent;
            border-radius: 6px;
            color: #667eea;
            cursor: pointer;
            transition: all 0.3s;
        }

        .add-layer-btn:hover {
            background: #f8f9ff;
        }

        /* Templates Panel */
        .templates-panel {
            padding: 1rem;
        }

        .templates-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .templates-categories {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .category-btn {
            padding: 0.4rem 0.8rem;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .category-btn:hover {
            background: #f8f9ff;
        }

        .category-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .templates-grid {
            max-height: 400px;
            overflow-y: auto;
        }

        .template-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .template-card:hover {
            background: #f8f9ff;
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .template-icon {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .template-name {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .template-apply-btn {
            padding: 0.3rem 0.8rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .template-apply-btn:hover {
            background: #764ba2;
        }

        /* Cloud Save Indicator */
        .save-indicator {
            position: fixed;
            top: 80px;
            left: 20px;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 100;
        }

        .save-indicator.active {
            opacity: 1;
            transform: translateY(0);
        }

        .save-indicator .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Notification System */
        .notification {
            position: fixed;
            bottom: 20px;
            left: 20px;
            padding: 15px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(-400px);
            transition: transform 0.3s;
            z-index: 1000;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification-success {
            border-right: 4px solid #38ef7d;
        }

        .notification-error {
            border-right: 4px solid #e53e3e;
        }

        .notification-info {
            border-right: 4px solid #667eea;
        }

        /* Batch Processing Panel */
        .batch-panel {
            margin-top: 1rem;
            padding: 1rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .queue-count {
            background: #667eea;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .batch-queue {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .batch-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .batch-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        #batchProgress {
            width: 0%;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
            transition: width 0.3s;
        }

        /* Enhanced Toolbar */
        .enhanced-toolbar {
            display: flex;
            gap: 0.5rem;
            padding: 0.5rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .toolbar-separator {
            width: 1px;
            background: #e2e8f0;
            margin: 0 0.5rem;
        }

        .toolbar-btn {
            width: 36px;
            height: 36px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            position: relative;
        }

        .toolbar-btn:hover {
            background: #f8f9ff;
            border-color: #667eea;
        }

        .toolbar-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .toolbar-btn .tooltip {
            position: absolute;
            bottom: -30px;
            right: 50%;
            transform: translateX(50%);
            background: #333;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .toolbar-btn:hover .tooltip {
            opacity: 1;
        }

        /* Projects Grid */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .project-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }

        .project-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .project-thumbnail {
            width: 100%;
            height: 120px;
            object-fit: cover;
            background: #f5f7fa;
        }

        .project-info {
            padding: 0.75rem;
        }

        .project-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .project-date {
            font-size: 0.85rem;
            color: #718096;
        }

        .project-actions {
            display: flex;
            gap: 0.5rem;
            padding: 0.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .project-action-btn {
            flex: 1;
            padding: 0.3rem;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #667eea;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .project-action-btn:hover {
            background: #f8f9ff;
        }<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>עורך PDF ותמונות - הוספת טקסט ואלמנטים</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;400;500;700&family=Heebo:wght@300;400;500;700&family=Assistant:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Fabric.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <!-- Additional Managers -->
    <script src="undo-redo-manager.js"></script>
    <script src="templates-manager.js"></script>
    <script src="cloud-save-manager.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rubik', 'Heebo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            color: #333;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header h1 i {
            color: #667eea;
        }

        /* Main Container */
        .main-container {
            flex: 1;
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            max-width: 1600px;
            width: 100%;
            margin: 0 auto;
        }

        /* Sidebar */
        .sidebar {
            width: 320px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow-y: auto;
            max-height: calc(100vh - 120px);
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #667eea;
            font-size: 1rem;
        }

        /* File Upload Area */
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9ff;
        }

        .upload-area:hover {
            border-color: #764ba2;
            background: #f0f2ff;
        }

        .upload-area.dragging {
            background: #e8ebff;
            border-color: #5a67d8;
        }

        .upload-area i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .upload-area p {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .upload-area .file-types {
            font-size: 0.85rem;
            color: #999;
        }

        #fileInput {
            display: none;
        }

        /* Toolbar Buttons */
        .toolbar {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .tool-btn {
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.9rem;
            color: #333;
        }

        .tool-btn:hover {
            background: #f8f9ff;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .tool-btn i {
            font-size: 1.3rem;
            color: #667eea;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            color: #555;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s;
            font-family: 'Rubik', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Color Picker */
        .color-picker-wrapper {
            position: relative;
        }

        .color-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
        }

        /* Range Sliders */
        .range-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .range-slider {
            flex: 1;
            -webkit-appearance: none;
            appearance: none;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            outline: none;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            background: #667eea;
            border-radius: 50%;
            cursor: pointer;
        }

        .range-value {
            min-width: 45px;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        /* Canvas Area */
        .canvas-container-wrapper {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .canvas-toolbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .zoom-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .zoom-btn:hover {
            background: #f8f9ff;
            border-color: #667eea;
        }

        .zoom-display {
            min-width: 60px;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        .canvas-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f5f7fa;
            overflow: auto;
            position: relative;
        }

        #pdfCanvas {
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            background: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Rubik', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #666;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f8f9ff;
            border-color: #667eea;
            color: #667eea;
        }

        /* Elements Panel */
        .elements-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.5rem;
        }

        .element-item {
            padding: 0.75rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .element-item:hover {
            background: #f8f9ff;
            border-color: #667eea;
        }

        .element-item.selected {
            background: #f0f2ff;
            border-color: #667eea;
        }

        .element-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .element-icon {
            width: 24px;
            height: 24px;
            background: #667eea;
            color: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .element-text {
            font-size: 0.9rem;
            color: #333;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .delete-element {
            color: #e53e3e;
            cursor: pointer;
            padding: 0.25rem;
            transition: all 0.3s;
        }

        .delete-element:hover {
            color: #c53030;
            transform: scale(1.2);
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes slideIn {
            from {
                transform: translateX(100px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100px);
                opacity: 0;
            }
        }

        /* API Status Indicator */
        .api-status {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 100;
        }

        .api-status.online {
            border-right: 4px solid #38ef7d;
        }

        .api-status.offline {
            border-right: 4px solid #e53e3e;
        }

        .api-status .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .api-status.online .status-dot {
            background: #38ef7d;
        }

        .api-status.offline .status-dot {
            background: #e53e3e;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(56, 239, 125, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(56, 239, 125, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(56, 239, 125, 0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>
            <i class="fas fa-file-pdf"></i>
            עורך PDF ותמונות מתקדם
        </h1>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Upload Section -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-upload"></i>
                    העלאת קובץ
                </div>
                <div class="upload-area" id="uploadArea">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>גרור קובץ לכאן או לחץ לבחירה</p>
                    <span class="file-types">PDF, JPG, PNG (עד 10MB)</span>
                    <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </div>

            <!-- Enhanced Toolbar with Undo/Redo -->
            <div class="enhanced-toolbar">
                <button class="toolbar-btn" id="undoBtn" title="בטל פעולה (Ctrl+Z)">
                    <i class="fas fa-undo"></i>
                    <span class="tooltip">בטל</span>
                </button>
                <button class="toolbar-btn" id="redoBtn" title="בצע שוב (Ctrl+Y)">
                    <i class="fas fa-redo"></i>
                    <span class="tooltip">בצע שוב</span>
                </button>
                <div class="toolbar-separator"></div>
                <button class="toolbar-btn" id="cloudSaveBtn" title="שמור בענן">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span class="tooltip">שמור בענן</span>
                </button>
                <button class="toolbar-btn" id="cloudLoadBtn" title="טען מהענן">
                    <i class="fas fa-cloud-download-alt"></i>
                    <span class="tooltip">טען מהענן</span>
                </button>
                <div class="toolbar-separator"></div>
                <button class="toolbar-btn" id="templatesBtn" title="תבניות">
                    <i class="fas fa-th-large"></i>
                    <span class="tooltip">תבניות</span>
                </button>
                <button class="toolbar-btn" id="layersBtn" title="שכבות">
                    <i class="fas fa-layer-group"></i>
                    <span class="tooltip">שכבות</span>
                </button>
                <button class="toolbar-btn" id="batchBtn" title="עיבוד קבוצתי">
                    <i class="fas fa-clone"></i>
                    <span class="tooltip">עיבוד קבוצתי</span>
                </button>
            </div>

            <!-- Tools Section -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-tools"></i>
                    כלים
                </div>
                <div class="toolbar">
                    <button class="tool-btn" id="addTextBtn">
                        <i class="fas fa-font"></i>
                        <span>הוסף טקסט</span>
                    </button>
                    <button class="tool-btn" id="addImageBtn">
                        <i class="fas fa-image"></i>
                        <span>הוסף תמונה</span>
                    </button>
                    <button class="tool-btn" id="loadJsonBtn">
                        <i class="fas fa-file-import"></i>
                        <span>טען JSON</span>
                    </button>
                    <button class="tool-btn" id="exportJsonBtn">
                        <i class="fas fa-code"></i>
                        <span>ייצא JSON</span>
                    </button>
                    <button class="tool-btn" id="downloadBtn">
                        <i class="fas fa-download"></i>
                        <span>הורד קובץ</span>
                    </button>
                    <button class="tool-btn" id="apiTestBtn">
                        <i class="fas fa-server"></i>
                        <span>בדוק API</span>
                    </button>
                </div>
            </div>

            <!-- Text Properties -->
            <div class="section" id="textPropertiesSection" style="display: none;">
                <div class="section-title">
                    <i class="fas fa-text-height"></i>
                    מאפייני טקסט
                </div>
                
                <div class="form-group">
                    <label>טקסט:</label>
                    <textarea class="form-control" id="textContent" placeholder="הקלד את הטקסט שלך כאן..."></textarea>
                </div>

                <div class="form-group">
                    <label>פונט:</label>
                    <select class="form-control" id="fontFamily">
                        <optgroup label="פונטים עבריים">
                            <option value="Rubik">Rubik</option>
                            <option value="Heebo">Heebo</option>
                            <option value="Assistant">Assistant</option>
                            <option value="Arial">Arial</option>
                        </optgroup>
                        <optgroup label="פונטים אנגליים">
                            <option value="Roboto">Roboto</option>
                            <option value="Poppins">Poppins</option>
                            <option value="Inter">Inter</option>
                            <option value="Montserrat">Montserrat</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label>גודל פונט:</label>
                    <div class="range-group">
                        <input type="range" class="range-slider" id="fontSize" min="8" max="72" value="16">
                        <span class="range-value" id="fontSizeValue">16px</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>צבע:</label>
                    <div class="color-display">
                        <input type="color" id="textColor" value="#000000">
                        <div class="color-preview" id="colorPreview" style="background: #000000;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>סיבוב:</label>
                    <div class="range-group">
                        <input type="range" class="range-slider" id="textRotation" min="0" max="360" value="0">
                        <span class="range-value" id="rotationValue">0°</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>שקיפות:</label>
                    <div class="range-group">
                        <input type="range" class="range-slider" id="textOpacity" min="0" max="100" value="100">
                        <span class="range-value" id="opacityValue">100%</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>כיוון טקסט:</label>
                    <select class="form-control" id="textDirection">
                        <option value="rtl">ימין לשמאל (עברית)</option>
                        <option value="ltr">שמאל לימין (English)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="boldText"> מודגש
                    </label>
                    <label>
                        <input type="checkbox" id="italicText"> נטוי
                    </label>
                </div>
            </div>

            <!-- Layers Panel -->
            <div class="section" id="layersSection" style="display: none;">
                <div class="section-title">
                    <i class="fas fa-layer-group"></i>
                    שכבות
                </div>
                <div class="layers-panel" id="layersPanel">
                    <!-- Layers will be added here dynamically -->
                </div>
            </div>

            <!-- Templates Panel -->
            <div class="section" id="templatesSection" style="display: none;">
                <div class="section-title">
                    <i class="fas fa-th-large"></i>
                    תבניות
                </div>
                <div class="templates-panel" id="templatesPanel">
                    <!-- Templates will be added here dynamically -->
                </div>
            </div>

            <!-- Cloud Projects Panel -->
            <div class="section" id="cloudSection" style="display: none;">
                <div class="section-title">
                    <i class="fas fa-cloud"></i>
                    פרויקטים בענן
                </div>
                <div class="projects-grid" id="projectsGrid">
                    <!-- Projects will be added here dynamically -->
                </div>
            </div>

            <!-- Batch Processing Panel -->
            <div class="section" id="batchSection" style="display: none;">
                <div class="section-title">
                    <i class="fas fa-clone"></i>
                    עיבוד קבוצתי
                </div>
                <div class="batch-panel" id="batchPanel">
                    <div class="batch-header">
                        <h3>הוסף קבצים לעיבוד</h3>
                    </div>
                    <div class="upload-area" id="batchUploadArea">
                        <i class="fas fa-folder-open"></i>
                        <p>גרור קבצים או לחץ לבחירה</p>
                        <span class="file-types">עד 20 קבצים בו-זמנית</span>
                        <input type="file" id="batchFileInput" multiple accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                    </div>
                    <div id="batchProgress"></div>
                </div>
            </div>

            <!-- Elements List -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-layer-group"></i>
                    אלמנטים בעמוד
                </div>
                <div class="elements-list" id="elementsList">
                    <p style="text-align: center; color: #999; padding: 1rem;">
                        אין אלמנטים עדיין
                    </p>
                </div>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="canvas-container-wrapper">
            <div class="canvas-toolbar">
                <div class="zoom-controls">
                    <button class="zoom-btn" id="zoomOutBtn">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <span class="zoom-display" id="zoomDisplay">100%</span>
                    <button class="zoom-btn" id="zoomInBtn">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="zoom-btn" id="zoomFitBtn" title="התאם לגודל המסך">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-secondary" id="clearAllBtn">
                        <i class="fas fa-trash"></i>
                        נקה הכל
                    </button>
                    <button class="btn btn-primary" id="previewBtn">
                        <i class="fas fa-eye"></i>
                        תצוגה מקדימה
                    </button>
                    <button class="btn btn-success" id="saveBtn">
                        <i class="fas fa-save"></i>
                        שמור ויצא
                    </button>
                </div>
            </div>
            <div class="canvas-wrapper">
                <canvas id="pdfCanvas"></canvas>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Hidden file inputs -->
    <input type="file" id="imageInput" accept="image/*" style="display: none;">
    <input type="file" id="jsonInput" accept=".json" style="display: none;">
    <input type="file" id="templateInput" accept=".json" style="display: none;">

    <!-- Include API Connector and Managers -->
    <script src="api-connector.js"></script>
    <script src="undo-redo-manager.js"></script>
    <script src="templates-manager.js"></script>
    <script src="cloud-save-manager.js"></script>

    <script>
        // Initialize PDF.js worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Global variables
        let canvas;
        let currentFile = null;
        let currentZoom = 1;
        let elements = [];
        let selectedElement = null;
        let documentSize = { width: 595, height: 842, unit: 'px' }; // A4 default
        
        // Initialize managers
        const pdfAPI = new PDFEditorAPI('/api/process-document.php');
        let undoRedoManager;
        let layersManager;
        let templatesManager;
        let cloudSaveManager;
        let batchManager;

        // Initialize Fabric.js canvas
        function initCanvas(width = 595, height = 842) {
            canvas = new fabric.Canvas('pdfCanvas', {
                width: width,
                height: height,
                backgroundColor: 'white',
                selection: true
            });

            // Canvas event listeners
            canvas.on('selection:created', handleSelection);
            canvas.on('selection:updated', handleSelection);
            canvas.on('selection:cleared', handleDeselection);
            canvas.on('object:modified', handleObjectModified);
            
            documentSize.width = width;
            documentSize.height = height;
            
            // Initialize managers after canvas is ready
            undoRedoManager = new UndoRedoManager(canvas);
            layersManager = new LayersManager(canvas);
            templatesManager = new TemplatesManager(canvas);
            cloudSaveManager = new CloudSaveManager('/api/cloud-save.php');
            batchManager = new BatchProcessingManager(pdfAPI);
        }

        // Handle file upload
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');

        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragging');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragging');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragging');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileUpload(e.target.files[0]);
            }
        });

        // Handle file upload
        async function handleFileUpload(file) {
            if (!file) return;
            
            const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!validTypes.includes(file.type)) {
                alert('אנא העלה קובץ PDF או תמונה (JPG/PNG)');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert('גודל הקובץ חורג מ-10MB');
                return;
            }

            currentFile = file;
            showLoading(true);

            try {
                if (file.type === 'application/pdf') {
                    await loadPDF(file);
                } else {
                    await loadImage(file);
                }
                
                // Update upload area to show file name
                uploadArea.innerHTML = `
                    <i class="fas fa-check-circle" style="color: #38ef7d;"></i>
                    <p>קובץ נטען בהצלחה</p>
                    <span class="file-types">${file.name}</span>
                `;
            } catch (error) {
                console.error('Error loading file:', error);
                alert('שגיאה בטעינת הקובץ');
            } finally {
                showLoading(false);
            }
        }

        // Load PDF file
        async function loadPDF(file) {
            const fileReader = new FileReader();
            
            return new Promise((resolve, reject) => {
                fileReader.onload = async function() {
                    try {
                        const typedarray = new Uint8Array(this.result);
                        const pdf = await pdfjsLib.getDocument(typedarray).promise;
                        const page = await pdf.getPage(1);
                        
                        const viewport = page.getViewport({ scale: 1.5 });
                        
                        // Initialize canvas with PDF dimensions
                        if (!canvas) {
                            initCanvas(viewport.width, viewport.height);
                        } else {
                            canvas.setWidth(viewport.width);
                            canvas.setHeight(viewport.height);
                        }
                        
                        // Render PDF to background
                        const tempCanvas = document.createElement('canvas');
                        const context = tempCanvas.getContext('2d');
                        tempCanvas.width = viewport.width;
                        tempCanvas.height = viewport.height;
                        
                        await page.render({
                            canvasContext: context,
                            viewport: viewport
                        }).promise;
                        
                        // Set as background image
                        fabric.Image.fromURL(tempCanvas.toDataURL(), (img) => {
                            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                                scaleX: 1,
                                scaleY: 1
                            });
                        });
                        
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                };
                
                fileReader.readAsArrayBuffer(file);
            });
        }

        // Load image file
        async function loadImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        // Initialize canvas with image dimensions
                        if (!canvas) {
                            initCanvas(img.width, img.height);
                        } else {
                            canvas.setWidth(img.width);
                            canvas.setHeight(img.height);
                        }
                        
                        // Set as background image
                        fabric.Image.fromURL(e.target.result, (fabricImg) => {
                            canvas.setBackgroundImage(fabricImg, canvas.renderAll.bind(canvas));
                        });
                        
                        resolve();
                    };
                    img.onerror = reject;
                    img.src = e.target.result;
                };
                
                reader.readAsDataURL(file);
            });
        }

        // Add text element
        document.getElementById('addTextBtn').addEventListener('click', () => {
            if (!canvas) {
                alert('אנא העלה קובץ תחילה');
                return;
            }
            
            const text = new fabric.IText('טקסט חדש', {
                left: 100,
                top: 100,
                fontFamily: 'Rubik',
                fontSize: 20,
                fill: '#000000',
                rtl: true
            });
            
            canvas.add(text);
            canvas.setActiveObject(text);
            canvas.renderAll();
            
            // Show text properties
            document.getElementById('textPropertiesSection').style.display = 'block';
            updateElementsList();
        });

        // Add image element
        document.getElementById('addImageBtn').addEventListener('click', () => {
            if (!canvas) {
                alert('אנא העלה קובץ תחילה');
                return;
            }
            
            document.getElementById('imageInput').click();
        });

        // Handle image upload
        document.getElementById('imageInput').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(event) {
                fabric.Image.fromURL(event.target.result, (img) => {
                    img.set({
                        left: 50,
                        top: 50,
                        scaleX: 0.5,
                        scaleY: 0.5
                    });
                    canvas.add(img);
                    canvas.setActiveObject(img);
                    canvas.renderAll();
                    updateElementsList();
                });
            };
            reader.readAsDataURL(file);
        });

        // Text properties handlers
        document.getElementById('textContent').addEventListener('input', (e) => {
            const activeObject = canvas.getActiveObject();
            if (activeObject && activeObject.type === 'i-text') {
                activeObject.set('text', e.target.value);
                canvas.renderAll();
            }
        });

        document.getElementById('fontFamily').addEventListener('change', (e) => {
            const activeObject = canvas.getActiveObject();
            if (activeObject && activeObject.type === 'i-text') {
                activeObject.set('fontFamily', e.target.value);
                canvas.renderAll();
            }
        });

        document.getElementById('fontSize').addEventListener('input', (e) => {
            const value = e.target.value;
            document.getElementById('fontSizeValue').textContent = value + 'px';
            
            const activeObject = canvas.getActiveObject();
            if (activeObject && activeObject.type === 'i-text') {
                activeObject.set('fontSize', parseInt(value));
                canvas.renderAll();
            }
        });

        document.getElementById('textColor').addEventListener('input', (e) => {
            const color = e.target.value;
            document.getElementById('colorPreview').style.background = color;
            
            const activeObject = canvas.getActiveObject();
            if (activeObject && activeObject.type === 'i-text') {
                activeObject.set('fill', color);
                canvas.renderAll();
            }
        });

        document.getElementById('textRotation').addEventListener('input', (e) => {
            const value = e.target.value;
            document.getElementById('rotationValue').textContent = value + '°';
            
            const activeObject = canvas.getActiveObject();
            if (activeObject) {
                activeObject.set('angle', parseInt(value));
                canvas.renderAll();
            }
        });

        document.getElementById('textOpacity').addEventListener('input', (e) => {
            const value = e.target.value;
            document.getElementById('opacityValue').textContent = value + '%';
            
            const activeObject = canvas.getActiveObject();
            if (activeObject) {
                activeObject.set('opacity', value / 100);
                canvas.renderAll();
            }
        });

        document.getElementById('boldText').addEventListener('change', (e) => {
            const activeObject = canvas.getActiveObject();
            if (activeObject && activeObject.type === 'i-text') {
                activeObject.set('fontWeight', e.target.checked ? 'bold' : 'normal');
                canvas.renderAll();
            }
        });

        document.getElementById('italicText').addEventListener('change', (e) => {
            const activeObject = canvas.getActiveObject();
            if (activeObject && activeObject.type === 'i-text') {
                activeObject.set('fontStyle', e.target.checked ? 'italic' : 'normal');
                canvas.renderAll();
            }
        });

        // Zoom controls
        document.getElementById('zoomInBtn').addEventListener('click', () => {
            if (currentZoom < 2) {
                currentZoom += 0.1;
                applyZoom();
            }
        });

        document.getElementById('zoomOutBtn').addEventListener('click', () => {
            if (currentZoom > 0.5) {
                currentZoom -= 0.1;
                applyZoom();
            }
        });

        document.getElementById('zoomFitBtn').addEventListener('click', () => {
            if (!canvas) return;
            
            const container = document.querySelector('.canvas-wrapper');
            const containerWidth = container.clientWidth - 80;
            const containerHeight = container.clientHeight - 80;
            
            const scaleX = containerWidth / canvas.width;
            const scaleY = containerHeight / canvas.height;
            currentZoom = Math.min(scaleX, scaleY);
            
            applyZoom();
        });

        function applyZoom() {
            if (!canvas) return;
            
            canvas.setZoom(currentZoom);
            canvas.setWidth(documentSize.width * currentZoom);
            canvas.setHeight(documentSize.height * currentZoom);
            canvas.renderAll();
            
            document.getElementById('zoomDisplay').textContent = Math.round(currentZoom * 100) + '%';
        }

        // Handle selection events
        function handleSelection(e) {
            selectedElement = e.target;
            
            if (selectedElement.type === 'i-text') {
                document.getElementById('textPropertiesSection').style.display = 'block';
                
                // Update property controls
                document.getElementById('textContent').value = selectedElement.text;
                document.getElementById('fontFamily').value = selectedElement.fontFamily;
                document.getElementById('fontSize').value = selectedElement.fontSize;
                document.getElementById('fontSizeValue').textContent = selectedElement.fontSize + 'px';
                document.getElementById('textColor').value = selectedElement.fill;
                document.getElementById('colorPreview').style.background = selectedElement.fill;
                document.getElementById('textRotation').value = selectedElement.angle || 0;
                document.getElementById('rotationValue').textContent = (selectedElement.angle || 0) + '°';
                document.getElementById('textOpacity').value = selectedElement.opacity * 100;
                document.getElementById('opacityValue').textContent = (selectedElement.opacity * 100) + '%';
                document.getElementById('boldText').checked = selectedElement.fontWeight === 'bold';
                document.getElementById('italicText').checked = selectedElement.fontStyle === 'italic';
            }
        }

        function handleDeselection() {
            selectedElement = null;
            document.getElementById('textPropertiesSection').style.display = 'none';
        }

        function handleObjectModified() {
            updateElementsList();
        }

        // Update elements list
        function updateElementsList() {
            const list = document.getElementById('elementsList');
            const objects = canvas.getObjects();
            
            if (objects.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: #999; padding: 1rem;">אין אלמנטים עדיין</p>';
                return;
            }
            
            list.innerHTML = '';
            objects.forEach((obj, index) => {
                const item = document.createElement('div');
                item.className = 'element-item';
                if (obj === selectedElement) {
                    item.classList.add('selected');
                }
                
                const icon = obj.type === 'i-text' ? 'fa-font' : 'fa-image';
                const text = obj.type === 'i-text' ? obj.text : 'תמונה';
                
                item.innerHTML = `
                    <div class="element-info">
                        <div class="element-icon">
                            <i class="fas ${icon}"></i>
                        </div>
                        <span class="element-text">${text}</span>
                    </div>
                    <i class="fas fa-trash delete-element" data-index="${index}"></i>
                `;
                
                item.addEventListener('click', (e) => {
                    if (!e.target.classList.contains('delete-element')) {
                        canvas.setActiveObject(obj);
                        canvas.renderAll();
                    }
                });
                
                list.appendChild(item);
            });
            
            // Delete element handlers
            document.querySelectorAll('.delete-element').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const index = parseInt(btn.dataset.index);
                    const objects = canvas.getObjects();
                    canvas.remove(objects[index]);
                    canvas.renderAll();
                    updateElementsList();
                });
            });
        }

        // Export JSON
        document.getElementById('exportJsonBtn').addEventListener('click', async () => {
            if (!canvas || !currentFile) {
                alert('אנא העלה קובץ והוסף אלמנטים');
                return;
            }
            
            try {
                // Use API connector to prepare and export JSON
                const documentData = await pdfAPI.prepareDocumentData(currentFile, canvas);
                pdfAPI.exportJSON(documentData);
            } catch (error) {
                console.error('Export error:', error);
                alert('שגיאה בייצוא JSON');
            }
        });

        // Clear all elements
        document.getElementById('clearAllBtn').addEventListener('click', () => {
            if (confirm('האם אתה בטוח שברצונך למחוק את כל האלמנטים?')) {
                canvas.getObjects().forEach(obj => canvas.remove(obj));
                canvas.renderAll();
                updateElementsList();
            }
        });

        // Download final document
        document.getElementById('downloadBtn').addEventListener('click', async () => {
            if (!canvas || !currentFile) {
                alert('אנא העלה קובץ תחילה');
                return;
            }
            
            // Check if there are elements to process
            const objects = canvas.getObjects();
            if (objects.length === 0) {
                // If no elements, just download the canvas as image
                const dataURL = canvas.toDataURL({
                    format: 'png',
                    quality: 1,
                    multiplier: 1 / currentZoom
                });
                
                const link = document.createElement('a');
                link.download = 'edited-' + currentFile.name.split('.')[0] + '.png';
                link.href = dataURL;
                link.click();
            } else {
                // Process through API
                await pdfAPI.processAndDownload(currentFile, canvas, showLoading);
            }
        });

        // Save button - process through API
        document.getElementById('saveBtn').addEventListener('click', async () => {
            if (!canvas || !currentFile) {
                alert('אנא העלה קובץ תחילה');
                return;
            }
            
            // Process and download through API
            const success = await pdfAPI.processAndDownload(currentFile, canvas, showLoading);
            if (success) {
                // Optionally show success message
                const message = document.createElement('div');
                message.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                    color: white;
                    padding: 15px 25px;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                    z-index: 1000;
                    animation: slideIn 0.3s ease;
                `;
                message.textContent = 'המסמך נשמר בהצלחה!';
                document.body.appendChild(message);
                
                setTimeout(() => {
                    message.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => document.body.removeChild(message), 300);
                }, 3000);
            }
        });

        // Preview button - enhanced with API
        document.getElementById('previewBtn').addEventListener('click', async () => {
            if (!canvas) {
                alert('אנא העלה קובץ תחילה');
                return;
            }
            
            // Use API connector for preview
            await pdfAPI.createPreview(currentFile, canvas);
        });

        // Loading overlay functions
        function showLoading(show) {
            document.getElementById('loadingOverlay').classList.toggle('active', show);
        }

        // Initialize canvas on page load
        window.addEventListener('load', () => {
            // Initialize with default A4 canvas
            initCanvas();
            
            // Initialize API
            pdfAPI.init(canvas);
            
            // Setup enhanced toolbar buttons
            setupEnhancedToolbar();
            
            // Setup panels
            setupPanels();
            
            // Setup batch processing
            setupBatchProcessing();
            
            // Add load JSON functionality
            document.getElementById('loadJsonBtn').addEventListener('click', () => {
                document.getElementById('jsonInput').click();
            });
            
            document.getElementById('jsonInput').addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                try {
                    showLoading(true);
                    const jsonData = await pdfAPI.loadJSON(file);
                    
                    // Load the base file if specified
                    if (jsonData.document && jsonData.document.file) {
                        // For now, just apply elements to current canvas
                        await pdfAPI.applyJSONToCanvas(jsonData, canvas);
                        updateElementsList();
                    }
                    
                    showLoading(false);
                    alert('הגדרות JSON נטענו בהצלחה!');
                } catch (error) {
                    showLoading(false);
                    console.error('Error loading JSON:', error);
                    alert('שגיאה בטעינת קובץ JSON');
                }
            });
            
            // Add API test functionality
            document.getElementById('apiTestBtn').addEventListener('click', async () => {
                const statusDiv = document.createElement('div');
                statusDiv.className = 'api-status';
                statusDiv.innerHTML = `
                    <div class="status-dot"></div>
                    <span>בודק חיבור ל-API...</span>
                `;
                document.body.appendChild(statusDiv);
                
                try {
                    const response = await fetch(pdfAPI.apiEndpoint, {
                        method: 'OPTIONS'
                    });
                    
                    if (response.ok) {
                        statusDiv.classList.add('online');
                        statusDiv.innerHTML = `
                            <div class="status-dot"></div>
                            <span>API מחובר ופעיל</span>
                        `;
                    } else {
                        throw new Error('API not responding');
                    }
                } catch (error) {
                    statusDiv.classList.add('offline');
                    statusDiv.innerHTML = `
                        <div class="status-dot"></div>
                        <span>API לא זמין</span>
                    `;
                }
                
                setTimeout(() => {
                    statusDiv.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => document.body.removeChild(statusDiv), 300);
                }, 3000);
            });
        });

        // Setup enhanced toolbar
        function setupEnhancedToolbar() {
            // Undo/Redo buttons are handled by UndoRedoManager
            
            // Cloud Save
            document.getElementById('cloudSaveBtn').addEventListener('click', async () => {
                if (!currentFile) {
                    alert('אנא העלה קובץ תחילה');
                    return;
                }
                await cloudSaveManager.saveToCloud();
            });
            
            // Cloud Load
            document.getElementById('cloudLoadBtn').addEventListener('click', async () => {
                togglePanel('cloudSection');
                await loadCloudProjects();
            });
            
            // Templates
            document.getElementById('templatesBtn').addEventListener('click', () => {
                togglePanel('templatesSection');
                templatesManager.updateTemplatesPanel();
            });
            
            // Layers
            document.getElementById('layersBtn').addEventListener('click', () => {
                togglePanel('layersSection');
                layersManager.updateLayersPanel();
            });
            
            // Batch Processing
            document.getElementById('batchBtn').addEventListener('click', () => {
                togglePanel('batchSection');
            });
        }

        // Setup panels
        function setupPanels() {
            // Initialize templates panel
            if (templatesManager) {
                templatesManager.updateTemplatesPanel();
            }
            
            // Initialize layers panel
            if (layersManager) {
                layersManager.updateLayersPanel();
            }
        }

        // Setup batch processing
        function setupBatchProcessing() {
            const batchUploadArea = document.getElementById('batchUploadArea');
            const batchFileInput = document.getElementById('batchFileInput');
            
            if (batchUploadArea && batchFileInput) {
                batchUploadArea.addEventListener('click', () => batchFileInput.click());
                
                batchUploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    batchUploadArea.classList.add('dragging');
                });
                
                batchUploadArea.addEventListener('dragleave', () => {
                    batchUploadArea.classList.remove('dragging');
                });
                
                batchUploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    batchUploadArea.classList.remove('dragging');
                    
                    const files = Array.from(e.dataTransfer.files);
                    handleBatchFiles(files);
                });
                
                batchFileInput.addEventListener('change', (e) => {
                    const files = Array.from(e.target.files);
                    handleBatchFiles(files);
                });
            }
        }

        // Handle batch files
        function handleBatchFiles(files) {
            if (files.length === 0) return;
            
            if (files.length > 20) {
                alert('ניתן לעבד עד 20 קבצים בו-זמנית');
                files = files.slice(0, 20);
            }
            
            // Get current canvas settings as template
            const settings = {
                elements: canvas.getObjects().map(obj => {
                    if (obj.type === 'i-text' || obj.type === 'text') {
                        return {
                            type: 'text',
                            value: obj.text,
                            position: { left: obj.left, top: obj.top },
                            style: {
                                fontSize: obj.fontSize,
                                fontFamily: obj.fontFamily,
                                color: obj.fill
                            }
                        };
                    }
                    return null;
                }).filter(Boolean)
            };
            
            batchManager.addToQueue(files, settings);
        }

        // Toggle panel visibility
        function togglePanel(panelId) {
            const panels = ['layersSection', 'templatesSection', 'cloudSection', 'batchSection'];
            panels.forEach(id => {
                const panel = document.getElementById(id);
                if (panel) {
                    panel.style.display = id === panelId && panel.style.display === 'none' ? 'block' : 'none';
                }
            });
        }

        // Load cloud projects
        async function loadCloudProjects() {
            const grid = document.getElementById('projectsGrid');
            if (!grid) return;
            
            grid.innerHTML = '<p>טוען פרויקטים...</p>';
            
            const projects = await cloudSaveManager.getProjectsList();
            
            if (projects.length === 0) {
                grid.innerHTML = '<p>אין פרויקטים שמורים</p>';
                return;
            }
            
            let html = '';
            projects.forEach(project => {
                html += `
                    <div class="project-card" data-project="${project.id}">
                        <img src="${project.thumbnail || ''}" class="project-thumbnail" alt="${project.name}">
                        <div class="project-info">
                            <div class="project-name">${project.name}</div>
                            <div class="project-date">${new Date(project.lastSaved).toLocaleDateString('he-IL')}</div>
                        </div>
                        <div class="project-actions">
                            <button class="project-action-btn" onclick="loadProject('${project.id}')">
                                <i class="fas fa-folder-open"></i> פתח
                            </button>
                            <button class="project-action-btn" onclick="shareProject('${project.id}')">
                                <i class="fas fa-share"></i> שתף
                            </button>
                            <button class="project-action-btn" onclick="deleteProject('${project.id}')">
                                <i class="fas fa-trash"></i> מחק
                            </button>
                        </div>
                    </div>
                `;
            });
            
            grid.innerHTML = html;
        }

        // Project actions
        window.loadProject = async (projectId) => {
            await cloudSaveManager.loadFromCloud(projectId);
        };

        window.shareProject = async (projectId) => {
            await cloudSaveManager.shareProject(projectId);
        };

        window.deleteProject = async (projectId) => {
            if (await cloudSaveManager.deleteFromCloud(projectId)) {
                await loadCloudProjects();
            }
        };
    </script>
</body>
</html>