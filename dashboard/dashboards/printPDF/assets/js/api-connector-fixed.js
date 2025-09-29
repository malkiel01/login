/**
 * API Connector for PDF Editor - FIXED VERSION
 * Location: /dashboard/dashboards/printPDF/assets/js/api-connector.js
 */

class APIConnector {
    constructor() {
        this.baseUrl = PDFEditorConfig.api.baseUrl || '/dashboard/dashboards/printPDF/api/';
        this.endpoints = PDFEditorConfig.api.endpoints;
        this.csrfToken = this.getCSRFToken();
        this.timeout = 30000; // 30 seconds
        this.retries = 3;
        this.retryDelay = 1000; // 1 second
    }

    /**
     * Get CSRF token from DOM
     */
    getCSRFToken() {
        const tokenElement = document.getElementById('csrfToken');
        if (tokenElement) {
            return tokenElement.value;
        }
        console.warn('CSRF token not found');
        return '';
    }

    /**
     * Sleep function for retry delay
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Base request method with retry logic
     */
    async request(endpoint, options = {}, retryCount = 0) {
        const url = this.baseUrl + endpoint;
        
        const defaultOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.csrfToken
            },
            body: options.body ? JSON.stringify(options.body) : undefined
        };

        const fetchOptions = { ...defaultOptions, ...options };

        // Override headers if provided
        if (options.headers) {
            fetchOptions.headers = { ...defaultOptions.headers, ...options.headers };
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);

            const response = await fetch(url, {
                ...fetchOptions,
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Request failed');
            }

            return data;

        } catch (error) {
            console.error(`API request failed: ${endpoint}`, error);

            // Retry logic
            if (retryCount < this.retries && !error.message.includes('abort')) {
                console.log(`Retrying request... Attempt ${retryCount + 1}/${this.retries}`);
                await this.sleep(this.retryDelay * (retryCount + 1));
                return this.request(endpoint, options, retryCount + 1);
            }

            // Return error response
            return {
                success: false,
                message: error.message || 'שגיאה בחיבור לשרת'
            };
        }
    }

    /**
     * Upload file with progress tracking
     */
    async uploadFile(file, onProgress = null) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', file);
            
            const xhr = new XMLHttpRequest();

            // Progress event
            if (onProgress) {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        onProgress(percentComplete);
                    }
                });
            }

            // Load event
            xhr.addEventListener('load', () => {
                try {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'העלאה נכשלה'));
                        }
                    } else {
                        reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                    }
                } catch (e) {
                    reject(new Error('תגובה לא תקינה מהשרת'));
                }
            });

            // Error event
            xhr.addEventListener('error', () => {
                reject(new Error('שגיאת רשת'));
            });

            // Timeout event
            xhr.addEventListener('timeout', () => {
                reject(new Error('הבקשה חרגה מזמן המתנה'));
            });

            // Abort event
            xhr.addEventListener('abort', () => {
                reject(new Error('העלאה בוטלה'));
            });

            // Setup request
            xhr.open('POST', this.baseUrl + this.endpoints.uploadFile);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-Token', this.csrfToken);
            xhr.timeout = this.timeout;

            // Send request
            xhr.send(formData);
        });
    }

    /**
     * Save project
     */
    async saveProject(projectData) {
        return this.request(this.endpoints.cloudSave, {
            body: {
                action: 'save',
                project: projectData
            }
        });
    }

    /**
     * Load project
     */
    async loadProject(projectId) {
        return this.request(this.endpoints.cloudSave, {
            body: {
                action: 'load',
                projectId: projectId
            }
        });
    }

    /**
     * List projects
     */
    async listProjects(page = 1, limit = 20) {
        return this.request(this.endpoints.cloudSave, {
            body: {
                action: 'list',
                page: page,
                limit: limit
            }
        });
    }

    /**
     * Delete project
     */
    async deleteProject(projectId) {
        return this.request(this.endpoints.cloudSave, {
            body: {
                action: 'delete',
                projectId: projectId
            }
        });
    }

    /**
     * Share project
     */
    async shareProject(projectId, expiresIn = 604800) {
        return this.request(this.endpoints.cloudSave, {
            body: {
                action: 'share',
                projectId: projectId,
                expiresIn: expiresIn
            }
        });
    }

    /**
     * Load shared project
     */
    async loadSharedProject(shareToken) {
        return this.request(this.endpoints.cloudSave, {
            body: {
                action: 'loadShared',
                shareToken: shareToken
            }
        });
    }

    /**
     * Get templates
     */
    async getTemplates(category = 'all') {
        return this.request(this.endpoints.templates, {
            body: {
                action: 'list',
                category: category
            }
        });
    }

    /**
     * Save template
     */
    async saveTemplate(templateData) {
        return this.request(this.endpoints.templates, {
            body: {
                action: 'save',
                ...templateData
            }
        });
    }

    /**
     * Process document
     */
    async processDocument(documentData, elements) {
        return this.request(this.endpoints.processDocument, {
            body: {
                document: documentData,
                elements: elements
            }
        });
    }

    /**
     * Batch process
     */
    async batchProcess(files, elements, onProgress = null) {
        const formData = new FormData();
        
        // Add files
        files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
        
        // Add elements data
        formData.append('elements', JSON.stringify(elements));
        
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            // Progress event
            if (onProgress) {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        onProgress(percentComplete);
                    }
                });
            }

            // Load event
            xhr.addEventListener('load', () => {
                try {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } else {
                        reject(new Error(`HTTP ${xhr.status}`));
                    }
                } catch (e) {
                    reject(new Error('Invalid response'));
                }
            });

            // Error event
            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });

            // Setup request
            xhr.open('POST', this.baseUrl + this.endpoints.batchProcess);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-Token', this.csrfToken);
            xhr.timeout = 60000; // 60 seconds for batch

            // Send request
            xhr.send(formData);
        });
    }

    /**
     * Get fonts list
     */
    async getFonts(language = 'all') {
        const url = this.baseUrl + this.endpoints.getFonts + '?language=' + language;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
            
        } catch (error) {
            console.error('Failed to get fonts:', error);
            return {
                success: false,
                message: error.message
            };
        }
    }
}

// Export for global use
window.APIConnector = APIConnector;