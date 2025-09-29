/**
 * Cloud Save Manager - FIXED VERSION
 * Location: /dashboard/dashboards/printPDF/assets/js/cloud-save-manager-fixed.js
 */

class CloudSaveManager {
    constructor(canvasManager, apiConnector) {
        this.canvasManager = canvasManager;
        this.api = apiConnector || window.apiConnector || new APIConnector();
        this.currentProject = null;
        this.autoSaveEnabled = false;
        this.autoSaveInterval = null;
        this.lastSaveTime = null;
        this.isSaving = false;
        
        console.log('CloudSaveManager initialized with API:', this.api);
    }

    /**
     * Save project
     */
    async saveProject() {
        if (this.isSaving) {
            console.log('Already saving, skipping...');
            return;
        }

        try {
            this.isSaving = true;
            this.showSaveIndicator(true);

            // Get canvas data
            let canvasData = null;
            if (window.app?.canvasManager) {
                canvasData = window.app.canvasManager.getCanvasJSON();
            } else if (this.canvasManager) {
                canvasData = this.canvasManager.getCanvasJSON();
            }

            if (!canvasData) {
                throw new Error('אין נתונים לשמירה');
            }

            // Get project name
            const nameInput = document.getElementById('projectName');
            const projectName = nameInput?.value || 'Untitled Project';

            // Prepare project data
            const projectData = {
                id: this.currentProject?.id || `project_${Date.now()}`,
                name: projectName,
                canvas: canvasData,
                version: '1.0.0',
                timestamp: new Date().toISOString()
            };

            console.log('Saving project:', projectData.id);

            // Check if API is available
            if (!this.api || typeof this.api.saveProject !== 'function') {
                console.error('API not available, using localStorage');
                this.saveToLocalStorage(projectData);
                return;
            }

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
                
                // Also save to localStorage as backup
                this.saveToLocalStorage(projectData);
            } else {
                throw new Error(response.message || 'שגיאה בשמירה');
            }

        } catch (error) {
            console.error('Save failed:', error);
            
            // Try localStorage as fallback
            try {
                const projectData = {
                    id: this.currentProject?.id || `project_${Date.now()}`,
                    name: document.getElementById('projectName')?.value || 'Untitled',
                    canvas: window.app?.canvasManager?.getCanvasJSON() || {},
                    timestamp: new Date().toISOString()
                };
                
                this.saveToLocalStorage(projectData);
                this.showInfo('הפרויקט נשמר באופן מקומי');
            } catch (localError) {
                console.error('Local save also failed:', localError);
                this.showError(`שגיאה בשמירה: ${error.message}`);
            }
        } finally {
            this.isSaving = false;
            this.showSaveIndicator(false);
        }
    }

    /**
     * Save to localStorage
     */
    saveToLocalStorage(projectData) {
        try {
            const key = `pdf_editor_project_${projectData.id}`;
            localStorage.setItem(key, JSON.stringify(projectData));
            
            // Update projects list
            let projectsList = JSON.parse(localStorage.getItem('pdf_editor_projects') || '[]');
            const existingIndex = projectsList.findIndex(p => p.id === projectData.id);
            
            const projectInfo = {
                id: projectData.id,
                name: projectData.name,
                updated: new Date().toISOString()
            };
            
            if (existingIndex >= 0) {
                projectsList[existingIndex] = projectInfo;
            } else {
                projectsList.unshift(projectInfo);
            }
            
            // Keep only last 20 projects
            projectsList = projectsList.slice(0, 20);
            localStorage.setItem('pdf_editor_projects', JSON.stringify(projectsList));
            
            this.currentProject = {
                id: projectData.id,
                name: projectData.name
            };
            this.lastSaveTime = new Date();
            
            console.log('Project saved to localStorage:', projectData.id);
        } catch (error) {
            console.error('Failed to save to localStorage:', error);
            throw error;
        }
    }

    /**
     * Load project
     */
    async loadProject(projectId) {
        try {
            this.showLoading(true);

            let projectData = null;

            // Try to load from server first
            if (this.api && typeof this.api.loadProject === 'function') {
                try {
                    const response = await this.api.loadProject(projectId);
                    if (response.success) {
                        projectData = response.data.project;
                    }
                } catch (error) {
                    console.error('Failed to load from server:', error);
                }
            }

            // If not found on server, try localStorage
            if (!projectData) {
                const key = `pdf_editor_project_${projectId}`;
                const stored = localStorage.getItem(key);
                if (stored) {
                    projectData = JSON.parse(stored);
                    console.log('Loaded from localStorage');
                }
            }

            if (!projectData) {
                throw new Error('הפרויקט לא נמצא');
            }

            // Load into canvas
            if (window.app?.canvasManager && projectData.canvas) {
                window.app.canvasManager.loadFromJSON(projectData.canvas);
            } else if (this.canvasManager && projectData.canvas) {
                this.canvasManager.loadFromJSON(projectData.canvas);
            }

            this.currentProject = {
                id: projectId,
                name: projectData.name
            };

            this.showSuccess('הפרויקט נטען בהצלחה');
            
            // Close modal if exists
            const modal = document.getElementById('cloudModal');
            if (modal) modal.style.display = 'none';

        } catch (error) {
            console.error('Load failed:', error);
            this.showError(`שגיאה בטעינה: ${error.message}`);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * List projects
     */
    async loadProjectsList() {
        const listContainer = document.getElementById('projectsList');
        if (!listContainer) return;

        try {
            let projects = [];

            // Try to load from server
            if (this.api && typeof this.api.listProjects === 'function') {
                try {
                    const response = await this.api.listProjects();
                    if (response.success) {
                        projects = response.data.projects || [];
                    }
                } catch (error) {
                    console.error('Failed to load from server:', error);
                }
            }

            // Also load from localStorage
            const localProjects = JSON.parse(localStorage.getItem('pdf_editor_projects') || '[]');
            
            // Merge and deduplicate
            const projectMap = new Map();
            [...projects, ...localProjects].forEach(p => {
                projectMap.set(p.id, p);
            });
            projects = Array.from(projectMap.values());

            if (projects.length === 0) {
                listContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>אין פרויקטים שמורים</p>
                        <button class="btn btn-primary" onclick="cloudSaveManager.createNewProject()">
                            צור פרויקט חדש
                        </button>
                    </div>
                `;
                return;
            }

            // Display projects
            listContainer.innerHTML = projects.map(project => `
                <div class="project-item">
                    <div class="project-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="project-info">
                        <h4>${project.name || 'Untitled'}</h4>
                        <small>${this.formatDate(project.updated || project.timestamp)}</small>
                    </div>
                    <div class="project-actions">
                        <button class="btn-icon" onclick="cloudSaveManager.loadProject('${project.id}')" title="טען">
                            <i class="fas fa-folder-open"></i>
                        </button>
                        <button class="btn-icon danger" onclick="cloudSaveManager.deleteProject('${project.id}')" title="מחק">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');

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
     * Delete project
     */
    async deleteProject(projectId) {
        if (!confirm('האם אתה בטוח שברצונך למחוק את הפרויקט?')) {
            return;
        }

        try {
            // Try to delete from server
            if (this.api && typeof this.api.deleteProject === 'function') {
                try {
                    const response = await this.api.deleteProject(projectId);
                    if (response.success) {
                        console.log('Deleted from server');
                    }
                } catch (error) {
                    console.error('Failed to delete from server:', error);
                }
            }

            // Delete from localStorage
            const key = `pdf_editor_project_${projectId}`;
            localStorage.removeItem(key);
            
            // Update projects list
            let projectsList = JSON.parse(localStorage.getItem('pdf_editor_projects') || '[]');
            projectsList = projectsList.filter(p => p.id !== projectId);
            localStorage.setItem('pdf_editor_projects', JSON.stringify(projectsList));

            if (this.currentProject?.id === projectId) {
                this.currentProject = null;
            }

            this.showSuccess('הפרויקט נמחק בהצלחה');
            this.loadProjectsList();

        } catch (error) {
            console.error('Delete failed:', error);
            this.showError(`שגיאה במחיקה: ${error.message}`);
        }
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
        } else if (this.canvasManager) {
            this.canvasManager.clear();
        }

        // Reset project
        this.currentProject = null;
        this.lastSaveTime = null;

        // Close modal
        const modal = document.getElementById('cloudModal');
        if (modal) modal.style.display = 'none';

        this.showSuccess('פרויקט חדש נוצר');
    }

    /**
     * UI Helper Methods
     */
    showSaveIndicator(show) {
        const indicator = document.getElementById('saveIndicator');
        if (indicator) {
            if (show) {
                indicator.classList.add('active');
                indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            } else {
                indicator.classList.remove('active');
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    indicator.innerHTML = '';
                }, 2000);
            }
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
        if (!date) return '';
        
        const now = new Date();
        const diff = now - new Date(date);
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
        if (!dateStr) return '';
        
        const date = new Date(dateStr);
        return date.toLocaleDateString('he-IL') + ' ' + 
               date.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' });
    }

    showLoading(show) {
        if (window.loadingManager) {
            if (show) {
                window.loadingManager.show('טוען...');
            } else {
                window.loadingManager.hide();
            }
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

    showInfo(message) {
        if (window.notificationManager) {
            window.notificationManager.info(message);
        } else {
            console.log('Info:', message);
            this.showNotification(message, 'info');
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'success' ? '#48bb78' : type === 'error' ? '#f56565' : '#4299e1'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            font-family: 'Rubik', sans-serif;
        `;
        
        const icon = type === 'success' ? 'check-circle' : 
                     type === 'error' ? 'exclamation-circle' : 'info-circle';
        
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Export for global use
window.CloudSaveManager = CloudSaveManager;