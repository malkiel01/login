/**
 * Cloud Save Manager - FIXED VERSION
 * Location: /dashboard/dashboards/printPDF/assets/js/cloud-save-manager.js
 */

class CloudSaveManager {
    constructor(apiConnector) {
        this.api = apiConnector;
        this.currentProject = null;
        this.autoSaveEnabled = false;
        this.autoSaveInterval = null;
        this.lastSaveTime = null;
        this.isSaving = false;
        
        this.init();
    }

    /**
     * Initialize cloud save manager
     */
    init() {
        this.bindEvents();
        this.loadProjectsList();
        
        // Check auto-save setting
        const autoSaveSetting = localStorage.getItem('pdf_editor_autosave');
        if (autoSaveSetting === 'true') {
            this.enableAutoSave();
        }
    }

    /**
     * Bind UI events
     */
    bindEvents() {
        // Cloud storage button
        const cloudBtn = document.getElementById('btnCloudStorage');
        if (cloudBtn) {
            cloudBtn.addEventListener('click', () => this.showCloudDialog());
        }

        // Save button
        const saveBtn = document.getElementById('btnSave');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveProject());
        }

        // Auto-save toggle
        const autoSaveToggle = document.getElementById('autoSaveToggle');
        if (autoSaveToggle) {
            autoSaveToggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.enableAutoSave();
                } else {
                    this.disableAutoSave();
                }
            });
        }
    }

    /**
     * Show cloud storage dialog
     */
    showCloudDialog() {
        const dialogHTML = `
            <div class="modal" id="cloudModal">
                <div class="modal-content modal-large">
                    <div class="modal-header">
                        <h2><i class="fas fa-cloud"></i> אחסון ענן</h2>
                        <button class="modal-close" onclick="this.closest('.modal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="cloud-tabs">
                            <button class="tab-btn active" data-tab="projects">
                                <i class="fas fa-folder"></i> הפרויקטים שלי
                            </button>
                            <button class="tab-btn" data-tab="save">
                                <i class="fas fa-save"></i> שמירה
                            </button>
                            <button class="tab-btn" data-tab="share">
                                <i class="fas fa-share"></i> שיתוף
                            </button>
                            <button class="tab-btn" data-tab="settings">
                                <i class="fas fa-cog"></i> הגדרות
                            </button>
                        </div>
                        
                        <div class="tab-content active" id="projectsTab">
                            <div class="projects-header">
                                <h3>הפרויקטים שלי</h3>
                                <button class="btn btn-primary btn-sm" onclick="cloudSaveManager.createNewProject()">
                                    <i class="fas fa-plus"></i> פרויקט חדש
                                </button>
                            </div>
                            <div class="projects-list" id="projectsList">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>טוען פרויקטים...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="saveTab">
                            <div class="save-form">
                                <h3>שמור פרויקט</h3>
                                <div class="form-group">
                                    <label>שם הפרויקט:</label>
                                    <input type="text" id="projectName" class="form-control" 
                                           placeholder="הכנס שם לפרויקט" value="${this.currentProject?.name || ''}">
                                </div>
                                <div class="form-group">
                                    <label>תיאור (אופציונלי):</label>
                                    <textarea id="projectDescription" class="form-control" 
                                              placeholder="הוסף תיאור לפרויקט"></textarea>
                                </div>
                                <div class="save-actions">
                                    <button class="btn btn-primary" onclick="cloudSaveManager.saveProject()">
                                        <i class="fas fa-save"></i> שמור עכשיו
                                    </button>
                                    <button class="btn btn-secondary" onclick="cloudSaveManager.saveAsTemplate()">
                                        <i class="fas fa-object-group"></i> שמור כתבנית
                                    </button>
                                </div>
                                ${this.lastSaveTime ? `
                                    <div class="last-save-info">
                                        <i class="fas fa-check-circle"></i>
                                        נשמר לאחרונה: ${this.formatTime(this.lastSaveTime)}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="tab-content" id="shareTab">
                            <div class="share-form">
                                <h3>שתף פרויקט</h3>
                                ${this.currentProject ? `
                                    <div class="share-options">
                                        <button class="btn btn-primary" onclick="cloudSaveManager.generateShareLink()">
                                            <i class="fas fa-link"></i> צור קישור לשיתוף
                                        </button>
                                    </div>
                                    <div id="shareResult"></div>
                                ` : `
                                    <p class="text-muted">יש לשמור את הפרויקט לפני שיתוף</p>
                                `}
                            </div>
                        </div>
                        
                        <div class="tab-content" id="settingsTab">
                            <div class="settings-form">
                                <h3>הגדרות אחסון</h3>
                                <div class="form-group">
                                    <label class="switch">
                                        <input type="checkbox" id="autoSaveToggle" 
                                               ${this.autoSaveEnabled ? 'checked' : ''}>
                                        <span class="slider"></span>
                                        <span class="label">שמירה אוטומטית</span>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>תדירות שמירה אוטומטית:</label>
                                    <select id="autoSaveInterval" class="form-control">
                                        <option value="60">כל דקה</option>
                                        <option value="120" selected>כל 2 דקות</option>
                                        <option value="300">כל 5 דקות</option>
                                        <option value="600">כל 10 דקות</option>
                                    </select>
                                </div>
                                <div class="storage-info">
                                    <h4>מידע אחסון:</h4>
                                    <p>מצב: <span class="status-badge active">מחובר</span></p>
                                    <p>סוג אחסון: שרת מקומי</p>
                                    <p>פרויקטים שמורים: <span id="projectCount">0</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if present
        const existingModal = document.getElementById('cloudModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', dialogHTML);

        // Setup tab switching
        this.setupTabs();

        // Load projects if tab is active
        this.loadProjectsList();
    }

    /**
     * Setup tab switching
     */
    setupTabs() {
        const tabButtons = document.querySelectorAll('.cloud-tabs .tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.dataset.tab;

                // Update buttons
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Update content
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === targetTab + 'Tab') {
                        content.classList.add('active');
                    }
                });
            });
        });
    }

    /**
     * Load projects list
     */
    async loadProjectsList() {
        const listContainer = document.getElementById('projectsList');
        if (!listContainer) return;

        try {
            const response = await this.api.listProjects();
            
            if (response.success) {
                const projects = response.data.projects || [];
                
                if (projects.length === 0) {
                    listContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>אין פרויקטים שמורים</p>
                            <button class="btn btn-primary" onclick="cloudSaveManager.createNewProject()">
                                צור פרויקט ראשון
                            </button>
                        </div>
                    `;
                } else {
                    let html = '<div class="projects-grid">';
                    
                    projects.forEach(project => {
                        html += `
                            <div class="project-card" data-id="${project.project_id}">
                                <div class="project-thumbnail">
                                    ${project.thumbnail ? 
                                        `<img src="${project.thumbnail}" alt="">` :
                                        '<i class="fas fa-file-pdf"></i>'
                                    }
                                </div>
                                <div class="project-info">
                                    <h4>${project.name}</h4>
                                    <p class="project-date">
                                        עודכן: ${this.formatDate(project.updated_at)}
                                    </p>
                                </div>
                                <div class="project-actions">
                                    <button class="btn-icon" onclick="cloudSaveManager.loadProject('${project.project_id}')" title="פתח">
                                        <i class="fas fa-folder-open"></i>
                                    </button>
                                    <button class="btn-icon" onclick="cloudSaveManager.shareProject('${project.project_id}')" title="שתף">
                                        <i class="fas fa-share"></i>
                                    </button>
                                    <button class="btn-icon danger" onclick="cloudSaveManager.deleteProject('${project.project_id}')" title="מחק">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    listContainer.innerHTML = html;
                }

                // Update project count
                const countElement = document.getElementById('projectCount');
                if (countElement) {
                    countElement.textContent = projects.length;
                }
            } else {
                throw new Error(response.message);
            }
            
        } catch (error) {
            console.error('Failed to load projects:', error);
            listContainer.innerHTML = `
                <div class="error-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>שגיאה בטעינת פרויקטים</p>
                    <button class="btn btn-secondary" onclick="cloudSaveManager.loadProjectsList()">
                        נסה שוב
                    </button>
                </div>
            `;
        }
    }

    /**
     * Save project
     */
    async saveProject() {
        if (this.isSaving) return;

        const nameInput = document.getElementById('projectName');
        const projectName = nameInput?.value || 'Untitled Project';

        try {
            this.isSaving = true;
            this.showSaveIndicator(true);

            // Get canvas data from the main app
            const canvasData = window.app?.canvasManager?.getCanvasJSON();
            if (!canvasData) {
                throw new Error('אין נתונים לשמירה');
            }

            // Prepare project data
            const projectData = {
                id: this.currentProject?.id || `project_${Date.now()}`,
                name: projectName,
                canvas: canvasData,
                version: PDFEditorConfig.version || '1.0.0',
                timestamp: new Date().toISOString()
            };

            // Save to server
            const response = await this.api.saveProject(projectData);

            if (response.success) {
                this.currentProject = {
                    id: response.data.projectId,
                    name: projectName
                };
                this.lastSaveTime = new Date();
                this.showSuccess('הפרויקט נשמר בהצלחה');
                
                // Update UI
                this.updateSaveStatus();
                this.loadProjectsList();
            } else {
                throw new Error(response.message);
            }

        } catch (error) {
            console.error('Save failed:', error);
            this.showError(`שגיאה בשמירה: ${error.message}`);
        } finally {
            this.isSaving = false;
            this.showSaveIndicator(false);
        }
    }

    /**
     * Load project
     */
    async loadProject(projectId) {
        try {
            this.showLoading(true);

            const response = await this.api.loadProject(projectId);

            if (response.success) {
                const projectData = response.data.project;
                
                // Load into canvas
                if (window.app?.canvasManager && projectData.canvas) {
                    window.app.canvasManager.loadFromJSON(projectData.canvas);
                }

                this.currentProject = {
                    id: projectId,
                    name: projectData.name
                };

                this.showSuccess('הפרויקט נטען בהצלחה');
                
                // Close modal
                const modal = document.getElementById('cloudModal');
                if (modal) modal.remove();

            } else {
                throw new Error(response.message);
            }

        } catch (error) {
            console.error('Load failed:', error);
            this.showError(`שגיאה בטעינה: ${error.message}`);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Delete project
     */
    async deleteProject(projectId) {
        if (!confirm('האם אתה בטוח שברצונך למחוק את הפרויקט?')) {
            return;
        }

        try {
            const response = await this.api.deleteProject(projectId);

            if (response.success) {
                this.showSuccess('הפרויקט נמחק בהצלחה');
                this.loadProjectsList();

                if (this.currentProject?.id === projectId) {
                    this.currentProject = null;
                }
            } else {
                throw new Error(response.message);
            }

        } catch (error) {
            console.error('Delete failed:', error);
            this.showError(`שגיאה במחיקה: ${error.message}`);
        }
    }

    /**
     * Generate share link
     */
    async generateShareLink() {
        if (!this.currentProject) {
            this.showError('יש לשמור את הפרויקט לפני שיתוף');
            return;
        }

        try {
            const response = await this.api.shareProject(this.currentProject.id);

            if (response.success) {
                const shareUrl = window.location.origin + response.data.shareUrl;
                
                const resultDiv = document.getElementById('shareResult');
                if (resultDiv) {
                    resultDiv.innerHTML = `
                        <div class="share-link-box">
                            <label>קישור לשיתוף:</label>
                            <div class="input-group">
                                <input type="text" value="${shareUrl}" id="shareLink" readonly class="form-control">
                                <button class="btn btn-primary" onclick="cloudSaveManager.copyShareLink()">
                                    <i class="fas fa-copy"></i> העתק
                                </button>
                            </div>
                            <p class="text-muted">הקישור יהיה תקף למשך 7 ימים</p>
                        </div>
                    `;
                }
            } else {
                throw new Error(response.message);
            }

        } catch (error) {
            console.error('Share failed:', error);
            this.showError(`שגיאה ביצירת קישור: ${error.message}`);
        }
    }

    /**
     * Copy share link to clipboard
     */
    copyShareLink() {
        const linkInput = document.getElementById('shareLink');
        if (linkInput) {
            linkInput.select();
            document.execCommand('copy');
            this.showSuccess('הקישור הועתק ללוח');
        }
    }

    /**
     * Enable auto-save
     */
    enableAutoSave() {
        this.autoSaveEnabled = true;
        localStorage.setItem('pdf_editor_autosave', 'true');
        
        const interval = parseInt(document.getElementById('autoSaveInterval')?.value || '120') * 1000;
        
        this.autoSaveInterval = setInterval(() => {
            if (this.currentProject && !this.isSaving) {
                this.saveProject();
            }
        }, interval);
        
        this.showSuccess('שמירה אוטומטית הופעלה');
    }

    /**
     * Disable auto-save
     */
    disableAutoSave() {
        this.autoSaveEnabled = false;
        localStorage.setItem('pdf_editor_autosave', 'false');
        
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
            this.autoSaveInterval = null;
        }
        
        this.showInfo('שמירה אוטומטית כובתה');
    }

    /**
     * Create new project
     */
    createNewProject() {
        if (window.app?.canvasManager?.canvas.getObjects().length > 0) {
            if (!confirm('יצירת פרויקט חדש תמחק את העבודה הנוכחית. להמשיך?')) {
                return;
            }
        }

        // Clear canvas
        if (window.app?.canvasManager) {
            window.app.canvasManager.clear();
        }

        // Reset project
        this.currentProject = null;
        this.lastSaveTime = null;

        // Close modal
        const modal = document.getElementById('cloudModal');
        if (modal) modal.remove();

        this.showSuccess('פרויקט חדש נוצר');
    }

    /**
     * UI Helper Methods
     */
    showSaveIndicator(show) {
        const indicator = document.getElementById('saveIndicator');
        if (indicator) {
            indicator.style.display = show ? 'block' : 'none';
        }
    }

    updateSaveStatus() {
        const statusElement = document.querySelector('.last-save-info');
        if (statusElement && this.lastSaveTime) {
            statusElement.innerHTML = `
                <i class="fas fa-check-circle"></i>
                נשמר לאחרונה: ${this.formatTime(this.lastSaveTime)}
            `;
        }
    }

    formatTime(date) {
        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        
        if (minutes < 1) return 'כרגע';
        if (minutes === 1) return 'לפני דקה';
        if (minutes < 60) return `לפני ${minutes} דקות`;
        
        const hours = Math.floor(minutes / 60);
        if (hours === 1) return 'לפני שעה';
        if (hours < 24) return `לפני ${hours} שעות`;
        
        return this.formatDate(date);
    }

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('he-IL') + ' ' + 
               date.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' });
    }

    showLoading(show) {
        // Implement loading indicator
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showInfo(message) {
        this.showNotification(message, 'info');
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Export for global use
window.CloudSaveManager = CloudSaveManager;