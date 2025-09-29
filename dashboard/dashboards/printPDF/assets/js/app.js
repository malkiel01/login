/**
 * Main Application for PDF Editor - FIXED VERSION
 * Location: /dashboard/dashboards/printPDF/assets/js/app-fixed.js
 */

class PDFEditorApp {
    constructor() {
        this.canvasManager = null;
        this.undoRedoManager = null;
        this.layersManager = null;
        this.apiConnector = null;
        this.cloudSaveManager = null;
        this.currentProject = null;
        this.autoSaveInterval = null;
        this.isInitialized = false;
    }

    async init() {
        try {
            console.log('Initializing PDF Editor...');
            
            // Initialize API connector FIRST
            this.apiConnector = window.apiConnector || new APIConnector();
            window.apiConnector = this.apiConnector;
            console.log('API Connector initialized');
            
            // Initialize canvas manager
            this.canvasManager = new CanvasManager('mainCanvas');
            window.canvasManager = this.canvasManager;
            console.log('Canvas Manager initialized');
            
            // Initialize undo/redo manager
            this.undoRedoManager = new UndoRedoManager(this.canvasManager);
            
            // Initialize layers manager
            this.layersManager = new LayersManager(this.canvasManager);
            
            // Initialize properties manager
            if (window.PropertiesManager) {
                this.propertiesManager = new PropertiesManager(this.canvasManager);
                window.propertiesManager = this.propertiesManager;
            }
            
            // Initialize templates manager
            if (window.TemplatesManager) {
                this.templatesManager = new TemplatesManager(this.canvasManager, this.apiConnector);
                window.templatesManager = this.templatesManager;
            }
            
            // Initialize cloud save manager with BOTH parameters
            if (window.CloudSaveManager) {
                this.cloudSaveManager = new CloudSaveManager(this.canvasManager, this.apiConnector);
                window.cloudSaveManager = this.cloudSaveManager;
                console.log('CloudSaveManager initialized with canvas and API');
            }
            
            // Initialize batch processor
            if (window.BatchProcessor) {
                this.batchProcessor = new BatchProcessor(this.canvasManager, this.apiConnector);
                window.batchProcessor = this.batchProcessor;
            }
            
            // Initialize UI
            this.initializeUI();
            
            // Bind events
            this.bindEvents();
            
            // Setup auto-save if enabled
            if (PDFEditorConfig && PDFEditorConfig.storage && PDFEditorConfig.storage.autoSave) {
                this.setupAutoSave();
            }
            
            // Check for shared project
            await this.checkSharedProject();
            
            // Load last project if exists
            await this.loadLastProject();
            
            this.isInitialized = true;
            console.log('PDF Editor initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize PDF Editor:', error);
            this.showError('שגיאה באתחול המערכת');
        }
    }

    initializeUI() {
        // Hide welcome screen if canvas has content
        this.updateUIState();
        
        // Initialize tooltips
        this.initTooltips();
        
        // Setup file input handlers
        this.setupFileInputs();
        
        // Setup drag and drop
        this.setupDragAndDrop();
        
        // Update zoom display if method exists
        if (this.canvasManager && this.canvasManager.updateZoomDisplay) {
            this.canvasManager.updateZoomDisplay();
        }
    }

    bindEvents() {
        // Toolbar buttons
        document.getElementById('btnNew')?.addEventListener('click', () => this.newDocument());
        document.getElementById('btnOpen')?.addEventListener('click', () => this.openDocument());
        
        // IMPORTANT: Bind save button properly
        const saveBtn = document.getElementById('btnSave');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                console.log('Save button clicked');
                this.saveDocument();
            });
        }
        
        document.getElementById('btnUndo')?.addEventListener('click', () => this.undoRedoManager.undo());
        document.getElementById('btnRedo')?.addEventListener('click', () => this.undoRedoManager.redo());
        document.getElementById('btnZoomIn')?.addEventListener('click', () => this.canvasManager.zoomIn());
        document.getElementById('btnZoomOut')?.addEventListener('click', () => this.canvasManager.zoomOut());
        document.getElementById('btnZoomFit')?.addEventListener('click', () => this.canvasManager.zoomToFit());
        document.getElementById('btnGrid')?.addEventListener('click', () => this.canvasManager.toggleGrid());
        document.getElementById('btnLayers')?.addEventListener('click', () => this.toggleLayersPanel());
        document.getElementById('btnTemplates')?.addEventListener('click', () => this.openTemplatesModal());
        document.getElementById('btnBatch')?.addEventListener('click', () => this.openBatchModal());
        document.getElementById('btnCloudSave')?.addEventListener('click', () => this.openCloudModal());
        document.getElementById('btnAPI')?.addEventListener('click', () => this.openAPIModal());
        
        // Tool buttons
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tool = btn.getAttribute('data-tool');
                if (this.canvasManager && this.canvasManager.setTool) {
                    this.canvasManager.setTool(tool);
                }
            });
        });
        
        // Quick action buttons
        document.getElementById('btnBrowse')?.addEventListener('click', () => {
            document.getElementById('fileInput').click();
        });
        document.getElementById('btnQuickTemplate')?.addEventListener('click', () => this.openTemplatesModal());
        document.getElementById('btnQuickRecent')?.addEventListener('click', () => this.showRecentFiles());
        document.getElementById('btnQuickCloud')?.addEventListener('click', () => this.openCloudModal());
        
        // Modal close buttons
        document.getElementById('btnCloseTemplates')?.addEventListener('click', () => this.closeModal('templatesModal'));
        document.getElementById('btnCloseBatch')?.addEventListener('click', () => this.closeModal('batchModal'));
        document.getElementById('btnCloseCloud')?.addEventListener('click', () => this.closeModal('cloudModal'));
        document.getElementById('btnCloseAPI')?.addEventListener('click', () => this.closeModal('apiModal'));
        
        // Keyboard shortcuts
        this.setupGlobalShortcuts();
    }

    setupFileInputs() {
        const fileInput = document.getElementById('fileInput');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.loadFile(file);
                }
            });
        }
        
        const batchFileInput = document.getElementById('batchFileInput');
        if (batchFileInput) {
            batchFileInput.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                if (files.length > 0) {
                    this.loadBatchFiles(files);
                }
            });
        }
    }

    setupDragAndDrop() {
        const uploadArea = document.getElementById('uploadArea');
        if (!uploadArea) return;
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            
            const files = Array.from(e.dataTransfer.files);
            if (files.length === 1) {
                this.loadFile(files[0]);
            } else if (files.length > 1) {
                this.loadBatchFiles(files);
            }
        });
    }

    setupGlobalShortcuts() {
        document.addEventListener('keydown', (e) => {
            const ctrl = e.ctrlKey || e.metaKey;
            
            if (ctrl) {
                switch(e.key.toLowerCase()) {
                    case 's':
                        e.preventDefault();
                        this.saveDocument();
                        break;
                    case 'o':
                        e.preventDefault();
                        this.openDocument();
                        break;
                    case 'n':
                        e.preventDefault();
                        this.newDocument();
                        break;
                    case 'z':
                        e.preventDefault();
                        if (e.shiftKey) {
                            this.undoRedoManager?.redo();
                        } else {
                            this.undoRedoManager?.undo();
                        }
                        break;
                }
            }
        });
    }

    async saveDocument() {
        console.log('saveDocument called');
        
        // Use CloudSaveManager if available
        if (this.cloudSaveManager) {
            console.log('Using CloudSaveManager');
            await this.cloudSaveManager.saveProject();
            return;
        }
        
        // Fallback to direct save
        console.log('CloudSaveManager not available, using fallback');
        
        try {
            if (!this.currentProject) {
                this.currentProject = {
                    id: `project_${Date.now()}`,
                    name: 'Untitled',
                    created: new Date()
                };
            }
            
            this.showLoading('שומר...');
            
            // Get canvas data
            const canvasData = this.canvasManager?.getCanvasJSON();
            if (!canvasData) {
                throw new Error('אין נתונים לשמירה');
            }
            
            // Prepare project data
            const projectData = {
                ...this.currentProject,
                canvas: canvasData,
                layers: this.layersManager?.exportLayers(),
                history: this.undoRedoManager?.exportHistory(),
                updated: new Date()
            };
            
            // Try server save first
            if (this.apiConnector) {
                try {
                    const response = await this.apiConnector.saveProject(projectData);
                    if (response.success) {
                        this.currentProject.id = response.data.projectId;
                        this.showSuccess('הפרויקט נשמר בהצלחה');
                        return;
                    }
                } catch (error) {
                    console.error('Server save failed:', error);
                }
            }
            
            // Fallback to localStorage
            const key = `pdf_editor_project_${projectData.id}`;
            localStorage.setItem(key, JSON.stringify(projectData));
            this.showSuccess('הפרויקט נשמר מקומית');
            
        } catch (error) {
            console.error('Failed to save document:', error);
            this.showError('שגיאה בשמירה: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }

    async loadFile(file) {
        try {
            // Validate file
            if (!this.validateFile(file)) {
                return;
            }
            
            this.showLoading('טוען קובץ...');
            
            // Upload file to server
            const response = await this.apiConnector.uploadFile(file, (progress) => {
                console.log(`Upload progress: ${progress}%`);
            });
            
            if (response.success) {
                // Load into canvas
                if (file.type === 'application/pdf') {
                    await this.loadPDFFromUrl(response.data.url);
                } else {
                    await this.loadImageFromUrl(response.data.url);
                }
                
                // Hide welcome screen
                this.showCanvas();
                
                // Save as current project
                this.currentProject = {
                    name: file.name,
                    type: file.type,
                    url: response.data.url,
                    created: new Date()
                };
                
                this.showSuccess('הקובץ נטען בהצלחה');
            }
            
        } catch (error) {
            console.error('Failed to load file:', error);
            this.showError('שגיאה בטעינת הקובץ');
        } finally {
            this.hideLoading();
        }
    }

    async loadPDFFromUrl(url) {
        try {
            // Check if PDF.js is loaded
            if (typeof pdfjsLib === 'undefined') {
                console.error('PDF.js is not loaded');
                this.showError('ספריית PDF לא נטענה');
                return;
            }
            
            // Fetch PDF data
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error('Failed to fetch PDF');
            }
            const arrayBuffer = await response.arrayBuffer();
            
            // Load into canvas
            if (this.canvasManager && this.canvasManager.loadPDF) {
                this.canvasManager.loadPDF(arrayBuffer);
            }
            
        } catch (error) {
            console.error('Failed to load PDF:', error);
            this.showError('שגיאה בטעינת PDF');
        }
    }

    async loadImageFromUrl(url) {
        if (this.canvasManager && this.canvasManager.loadImage) {
            this.canvasManager.loadImage(url);
        }
    }

    validateFile(file) {
        // Check file size
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            this.showError('הקובץ גדול מדי');
            return false;
        }
        
        // Check file type
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            const extension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            
            if (!allowedExtensions.includes(extension)) {
                this.showError('סוג קובץ לא נתמך');
                return false;
            }
        }
        
        return true;
    }

    // UI State Management
    updateUIState() {
        const welcomeScreen = document.getElementById('welcomeScreen');
        const canvasWrapper = document.getElementById('canvasWrapper');
        
        if (this.canvasManager && this.canvasManager.canvas && this.canvasManager.canvas.getObjects().length > 0) {
            if (welcomeScreen) welcomeScreen.style.display = 'none';
            if (canvasWrapper) canvasWrapper.style.display = 'block';
        }
    }

    showCanvas() {
        const welcomeScreen = document.getElementById('welcomeScreen');
        const canvasWrapper = document.getElementById('canvasWrapper');
        
        if (welcomeScreen) welcomeScreen.style.display = 'none';
        if (canvasWrapper) canvasWrapper.style.display = 'block';
    }

    // Loading/Notification helpers
    showLoading(message = 'טוען...') {
        if (window.loadingManager) {
            window.loadingManager.show(message);
        } else {
            console.log('Loading:', message);
        }
    }

    hideLoading() {
        if (window.loadingManager) {
            window.loadingManager.hide();
        }
    }

    showSuccess(message) {
        if (window.notificationManager) {
            window.notificationManager.success(message);
        } else {
            console.log('Success:', message);
            this.showNotification(message, 'success');
        }
    }

    showError(message) {
        if (window.notificationManager) {
            window.notificationManager.error(message);
        } else {
            console.error('Error:', message);
            this.showNotification(message, 'error');
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#48bb78' : type === 'error' ? '#f56565' : '#4299e1'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            font-family: 'Rubik', sans-serif;
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Document Management
    newDocument() {
        if (this.canvasManager && this.canvasManager.canvas && this.canvasManager.canvas.getObjects().length > 0) {
            if (!confirm('יש נתונים לא שמורים. להמשיך?')) {
                return;
            }
        }
        
        // Clear canvas
        if (this.canvasManager) this.canvasManager.clear();
        
        // Reset managers
        if (this.undoRedoManager) this.undoRedoManager.clear();
        if (this.layersManager) this.layersManager.init();
        
        // Reset project
        this.currentProject = null;
        
        // Show canvas
        this.showCanvas();
    }

    openDocument() {
        document.getElementById('fileInput')?.click();
    }

    // Modal Management
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'block';
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    }

    openTemplatesModal() {
        this.openModal('templatesModal');
        if (this.templatesManager) {
            this.templatesManager.loadTemplates();
        }
    }

    openBatchModal() {
        this.openModal('batchModal');
    }

    openCloudModal() {
        // Create cloud modal dynamically
        if (window.showCloudModal) {
            window.showCloudModal();
        } else {
            this.openModal('cloudModal');
        }
        
        // Load projects list
        if (this.cloudSaveManager) {
            this.cloudSaveManager.loadProjectsList();
        }
    }

    openAPIModal() {
        this.openModal('apiModal');
    }

    // Placeholder methods
    toggleLayersPanel() {
        const panel = document.getElementById('rightSidebar');
        if (panel) {
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
    }

    showRecentFiles() {
        console.log('Show recent files');
        // Implementation needed
    }

    setupAutoSave() {
        const interval = 120000; // 2 minutes
        
        this.autoSaveInterval = setInterval(() => {
            if (this.currentProject && this.canvasManager?.canvas?.getObjects().length > 0) {
                this.saveDocument();
            }
        }, interval);
    }

    async checkSharedProject() {
        // Check URL for shared project token
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('share');
        if (token) {
            // Load shared project
            console.log('Loading shared project:', token);
        }
    }

    async loadLastProject() {
        // Try to load last opened project
        const lastProjectId = localStorage.getItem('pdf_editor_last_project');
        if (lastProjectId && this.cloudSaveManager) {
            try {
                await this.cloudSaveManager.loadProject(lastProjectId);
            } catch (error) {
                console.error('Failed to load last project:', error);
            }
        }
    }

    initTooltips() {
        // Initialize tooltips if needed
    }

    // Cleanup
    destroy() {
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
        }
        
        if (this.canvasManager) {
            this.canvasManager.destroy();
        }
    }
}

// Export for global use
window.PDFEditorApp = PDFEditorApp;