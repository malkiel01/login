/**
 * Main Application for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/app.js
 */

class PDFEditorApp {
    constructor() {
        this.canvasManager = null;
        this.undoRedoManager = null;
        this.layersManager = null;
        this.apiConnector = null;
        this.currentProject = null;
        this.autoSaveInterval = null;
        this.isInitialized = false;
    }

    async init() {
        try {
            console.log('Initializing PDF Editor...');
            
            // Initialize API connector
            this.apiConnector = new APIConnector();
            
            // Initialize canvas manager
            this.canvasManager = new CanvasManager('mainCanvas');
            
            // Initialize undo/redo manager
            this.undoRedoManager = new UndoRedoManager(this.canvasManager);
            
            // Initialize layers manager
            this.layersManager = new LayersManager(this.canvasManager);
            
            // Initialize UI
            this.initializeUI();
            
            // Bind events
            this.bindEvents();
            
            // Setup auto-save if enabled
            if (PDFEditorConfig.storage.autoSave) {
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
            this.showError(t('errors.initFailed'));
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
        
        // Update zoom display
        this.canvasManager.updateZoomDisplay();
    }

    bindEvents() {
        // Toolbar buttons
        document.getElementById('btnNew')?.addEventListener('click', () => this.newDocument());
        document.getElementById('btnOpen')?.addEventListener('click', () => this.openDocument());
        document.getElementById('btnSave')?.addEventListener('click', () => this.saveDocument());
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
                this.canvasManager.setTool(tool);
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
        
        // Canvas events
        window.addEventListener('object:added', (e) => this.onObjectAdded(e.detail));
        window.addEventListener('object:modified', (e) => this.onObjectModified(e.detail));
        window.addEventListener('object:deleted', (e) => this.onObjectDeleted(e.detail));
        
        // Language change event
        window.addEventListener('languageChanged', (e) => this.onLanguageChanged(e.detail));
        
        // Window resize
        window.addEventListener('resize', () => this.onWindowResize());
        
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
                }
            }
        });
    }

    async loadFile(file) {
        try {
            // Validate file
            if (!this.validateFile(file)) {
                return;
            }
            
            this.showLoading(t('loading.file'));
            
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
                
                this.showSuccess(t('success.loaded'));
            }
            
        } catch (error) {
            console.error('Failed to load file:', error);
            this.showError(t('errors.loadFailed'));
        } finally {
            this.hideLoading();
        }
    }

    async loadPDFFromUrl(url) {
        // Fetch PDF data
        const response = await fetch(url);
        const arrayBuffer = await response.arrayBuffer();
        
        // Load into canvas
        this.canvasManager.loadPDF(arrayBuffer);
    }

    async loadImageFromUrl(url) {
        this.canvasManager.loadImage(url);
    }

    validateFile(file) {
        // Check file size
        if (file.size > PDFEditorConfig.file.maxSize) {
            this.showError(t('errors.fileTooBig'));
            return false;
        }
        
        // Check file type
        const allowedTypes = PDFEditorConfig.file.allowedTypes;
        if (!allowedTypes.includes(file.type)) {
            // Check by extension
            const extension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = PDFEditorConfig.file.allowedExtensions;
            
            if (!allowedExtensions.includes(extension)) {
                this.showError(t('errors.invalidFormat'));
                return false;
            }
        }
        
        return true;
    }

    newDocument() {
        if (this.canvasManager.canvas.getObjects().length > 0) {
            if (!confirm(t('dialogs.unsavedChanges'))) {
                return;
            }
        }
        
        // Clear canvas
        this.canvasManager.clear();
        
        // Reset managers
        this.undoRedoManager.clear();
        this.layersManager.init();
        
        // Reset project
        this.currentProject = null;
        
        // Show canvas
        this.showCanvas();
    }

    openDocument() {
        document.getElementById('fileInput')?.click();
    }

    async saveDocument() {
        try {
            if (!this.currentProject) {
                this.currentProject = {
                    id: `project_${Date.now()}`,
                    name: 'Untitled',
                    created: new Date()
                };
            }
            
            this.showLoading(t('saving'));
            
            // Get canvas data
            const canvasData = this.canvasManager.getCanvasJSON();
            
            // Save project
            const projectData = {
                ...this.currentProject,
                canvas: canvasData,
                layers: this.layersManager.exportLayers(),
                history: this.undoRedoManager.exportHistory(),
                updated: new Date()
            };
            
            // Save to server or local storage
            if (PDFEditorConfig.storage.mode === 'server') {
                const response = await this.apiConnector.saveProject(projectData);
                if (response.success) {
                    this.currentProject.id = response.data.projectId;
                    this.showSuccess(t('success.saved'));
                }
            } else {
                // Save to local storage
                localStorage.setItem(
                    `${PDFEditorConfig.storage.projectPrefix}${projectData.id}`,
                    JSON.stringify(projectData)
                );
                this.showSuccess(t('success.saved'));
            }
            
        } catch (error) {
            console.error('Failed to save document:', error);
            this.showError(t('errors.saveFailed'));
        } finally {
            this.hideLoading();
        }
    }

    setupAutoSave() {
        const interval = PDFEditorConfig.storage.autoSaveInterval;
        
        this.autoSaveInterval = setInterval(() => {
            if (this.currentProject && this.canvasManager.canvas.getObjects().length > 0) {
                this.saveDocument();
                this.updateSaveIndicator();
            }
        }, interval);
    }

    updateSaveIndicator() {
        const indicator = document.getElementById('saveIndicator');
        if (indicator) {
            indicator.classList.add('saving');
            setTimeout(() => {
                indicator.classList.remove('saving');
            }, 2000);
        }
    }

    async loadLastProject() {
        try {
            if (PDFEditorConfig.storage.mode === 'local') {
                const projectKeys = Object.keys(localStorage).filter(key => 
                    key.startsWith(PDFEditorConfig.storage.projectPrefix)
                );
                
                if (projectKeys.length > 0) {
                    // Get most recent project
                    const projects = projectKeys.map(key => {
                        const project = JSON.parse(localStorage.getItem(key));
                        return project;
                    }).sort((a, b) => new Date(b.updated) - new Date(a.updated));
                    
                    if (projects.length > 0) {
                        await this.loadProject(projects[0]);
                    }
                }
            }
        } catch (error) {
            console.error('Failed to load last project:', error);
        }
    }

    async loadProject(projectData) {
        try {
            this.showLoading(t('loading.project'));
            
            // Load canvas
            if (projectData.canvas) {
                this.canvasManager.loadFromJSON(projectData.canvas);
            }
            
            // Load layers
            if (projectData.layers) {
                this.layersManager.importLayers(projectData.layers);
            }
            
            // Load history
            if (projectData.history) {
                this.undoRedoManager.importHistory(projectData.history);
            }
            
            // Set current project
            this.currentProject = projectData;
            
            // Show canvas
            this.showCanvas();
            
            this.showSuccess(t('success.loaded'));
            
        } catch (error) {
            console.error('Failed to load project:', error);
            this.showError(t('errors.loadFailed'));
        } finally {
            this.hideLoading();
        }
    }

    async checkSharedProject() {
        // Check URL for share token
        const urlParams = new URLSearchParams(window.location.search);
        const shareToken = urlParams.get('share');
        
        if (shareToken) {
            try {
                const response = await this.apiConnector.loadSharedProject(shareToken);
                if (response.success) {
                    await this.loadProject(response.data.project);
                }
            } catch (error) {
                console.error('Failed to load shared project:', error);
            }
        }
    }

    showCanvas() {
        document.getElementById('welcomeScreen')?.style.setProperty('display', 'none');
        document.getElementById('canvasWrapper')?.style.setProperty('display', 'block');
        document.getElementById('rightSidebar')?.style.setProperty('display', 'flex');
        
        // Fit canvas to screen
        setTimeout(() => {
            this.canvasManager.zoomToFit();
        }, 100);
    }

    hideCanvas() {
        document.getElementById('welcomeScreen')?.style.setProperty('display', 'flex');
        document.getElementById('canvasWrapper')?.style.setProperty('display', 'none');
        document.getElementById('rightSidebar')?.style.setProperty('display', 'none');
    }

    updateUIState() {
        if (this.canvasManager && this.canvasManager.canvas.getObjects().length > 0) {
            this.showCanvas();
        } else {
            this.hideCanvas();
        }
    }

    toggleLayersPanel() {
        const sidebar = document.getElementById('rightSidebar');
        if (sidebar) {
            const isVisible = sidebar.style.display !== 'none';
            sidebar.style.display = isVisible ? 'none' : 'flex';
        }
    }

    openTemplatesModal() {
        this.openModal('templatesModal');
        this.loadTemplates();
    }

    openBatchModal() {
        this.openModal('batchModal');
    }

    openCloudModal() {
        this.openModal('cloudModal');
        this.loadCloudProjects();
    }

    openAPIModal() {
        this.openModal('apiModal');
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    async loadTemplates() {
        try {
            const response = await this.apiConnector.getTemplates();
            if (response.success) {
                this.displayTemplates(response.data.templates);
            }
        } catch (error) {
            console.error('Failed to load templates:', error);
        }
    }

    displayTemplates(templates) {
        const grid = document.getElementById('templatesGrid');
        if (!grid) return;
        
        grid.innerHTML = '';
        
        templates.forEach(template => {
            const item = document.createElement('div');
            item.className = 'template-item';
            item.innerHTML = `
                <div class="template-preview">
                    ${template.thumbnail ? 
                        `<img src="${template.thumbnail}" alt="${template.name}">` :
                        `<i class="fas fa-file-alt"></i>`
                    }
                </div>
                <div class="template-name">${template.name}</div>
            `;
            
            item.addEventListener('click', () => {
                this.useTemplate(template);
                this.closeModal('templatesModal');
            });
            
            grid.appendChild(item);
        });
    }

    async useTemplate(template) {
        if (template.data) {
            this.canvasManager.loadFromJSON(template.data);
            this.showCanvas();
        }
    }

    async loadCloudProjects() {
        try {
            const response = await this.apiConnector.listProjects();
            if (response.success) {
                this.displayCloudProjects(response.data.projects);
            }
        } catch (error) {
            console.error('Failed to load cloud projects:', error);
        }
    }

    displayCloudProjects(projects) {
        const grid = document.getElementById('projectsGrid');
        if (!grid) return;
        
        grid.innerHTML = '';
        
        projects.forEach(project => {
            const item = document.createElement('div');
            item.className = 'project-item';
            item.innerHTML = `
                <div class="project-preview">
                    ${project.thumbnail ? 
                        `<img src="${project.thumbnail}" alt="${project.name}">` :
                        `<i class="fas fa-file"></i>`
                    }
                </div>
                <div class="project-info">
                    <div class="project-name">${project.name}</div>
                    <div class="project-date">${new Date(project.updated).toLocaleDateString()}</div>
                </div>
            `;
            
            item.addEventListener('click', async () => {
                await this.loadCloudProject(project.id);
                this.closeModal('cloudModal');
            });
            
            grid.appendChild(item);
        });
    }

    async loadCloudProject(projectId) {
        try {
            const response = await this.apiConnector.loadProject(projectId);
            if (response.success) {
                await this.loadProject(response.data.project);
            }
        } catch (error) {
            console.error('Failed to load cloud project:', error);
            this.showError(t('errors.loadFailed'));
        }
    }

    async loadBatchFiles(files) {
        // Validate files
        const validFiles = files.filter(file => this.validateFile(file));
        
        if (validFiles.length === 0) {
            return;
        }
        
        if (validFiles.length > PDFEditorConfig.batch.maxFiles) {
            this.showError(t('errors.tooManyFiles'));
            return;
        }
        
        // Open batch modal
        this.openBatchModal();
        
        // Display files in queue
        this.displayBatchQueue(validFiles);
    }

    displayBatchQueue(files) {
        const queue = document.getElementById('batchQueue');
        if (!queue) return;
        
        queue.innerHTML = '';
        
        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'batch-item';
            item.innerHTML = `
                <span class="batch-index">${index + 1}</span>
                <span class="batch-name">${file.name}</span>
                <span class="batch-size">${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                <span class="batch-status" data-status="waiting">${t('batch.status.waiting')}</span>
            `;
            queue.appendChild(item);
        });
    }

    onObjectAdded(detail) {
        console.log('Object added:', detail);
    }

    onObjectModified(detail) {
        console.log('Object modified:', detail);
    }

    onObjectDeleted(detail) {
        console.log('Object deleted:', detail);
    }

    onLanguageChanged(detail) {
        console.log('Language changed:', detail.language);
        // Refresh UI texts if needed
    }

    onWindowResize() {
        // Adjust canvas size if needed
    }

    initTooltips() {
        // Initialize any tooltip library if used
    }

    showLoading(message = '') {
        // Implement loading indicator
        console.log('Loading:', message);
    }

    hideLoading() {
        // Hide loading indicator
        console.log('Loading complete');
    }

    showError(message) {
        alert(message);
    }

    showSuccess(message) {
        console.log('Success:', message);
    }

    showRecentFiles() {
        // Load and display recent files
        console.log('Showing recent files');
    }

    destroy() {
        // Cleanup
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