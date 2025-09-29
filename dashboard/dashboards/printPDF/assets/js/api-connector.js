/**
 * API Connector for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/api-connector.js
 */

class APIConnector {
    constructor() {
        this.baseUrl = PDFEditorConfig.api.baseUrl;
        this.endpoints = PDFEditorConfig.api.endpoints;
        this.timeout = PDFEditorConfig.request.timeout || 30000;
        this.retries = PDFEditorConfig.request.retries || 3;
        this.retryDelay = PDFEditorConfig.request.retryDelay || 1000;
        
        // Get CSRF token
        this.csrfToken = document.getElementById('csrfToken')?.value || '';
    }

    /**
     * Make API request with retry logic
     */
    async request(endpoint, options = {}, retryCount = 0) {
        const url = this.baseUrl + endpoint;
        
        // Default options
        const defaultOptions = {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.csrfToken,
                ...options.headers
            }
        };

        // Merge options
        const requestOptions = { ...defaultOptions, ...options };

        // Add body if provided
        if (options.body && !(options.body instanceof FormData)) {
            requestOptions.headers['Content-Type'] = 'application/json';
            requestOptions.body = JSON.stringify(options.body);
        }

        try {
            // Create abort controller for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);

            const response = await fetch(url, {
                ...requestOptions,
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
            if (retryCount < this.retries) {
                await this.sleep(this.retryDelay * (retryCount + 1));
                return this.request(endpoint, options, retryCount + 1);
            }

            throw error;
        }
    }

    /**
     * Process document with elements
     */
    async processDocument(documentData, elements) {
        const payload = {
            document: documentData,
            elements: elements
        };

        return this.request(this.endpoints.processDocument, {
            body: payload
        });
    }

    /**
     * Upload file
     */
    async uploadFile(file, onProgress = null) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', this.csrfToken);

        // Create XMLHttpRequest for progress tracking
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            // Progress event
            if (onProgress) {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        onProgress(percentComplete);
                    }
                });
            }

            // Load event
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Upload failed'));
                        }
                    } catch (e) {
                        reject(new Error('Invalid response'));
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}`));
                }
            });

            // Error event
            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });

            // Timeout event
            xhr.addEventListener('timeout', () => {
                reject(new Error('Request timeout'));
            });

            // Setup request
            xhr.open('POST', this.baseUrl + this.endpoints.uploadFile);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = this.timeout;

            // Send request
            xhr.send(formData);
        });
    }

    /**
     * Cloud save operations
     */
    async cloudSave(action, data = {}) {
        return this.request(this.endpoints.cloudSave, {
            body: {
                action: action,
                ...data
            }
        });
    }

    async saveProject(projectData) {
        return this.cloudSave('save', {
            project: projectData
        });
    }

    async loadProject(projectId) {
        return this.cloudSave('load', {
            projectId: projectId
        });
    }

    async listProjects(page = 1, limit = 20) {
        return this.cloudSave('list', {
            page: page,
            limit: limit
        });
    }

    async deleteProject(projectId) {
        return this.cloudSave('delete', {
            projectId: projectId
        });
    }

    async shareProject(projectId, expiresIn = 7) {
        return this.cloudSave('share', {
            projectId: projectId,
            expiresIn: expiresIn * 24 * 60 * 60 // Convert days to seconds
        });
    }

    /**
     * Template operations
     */
    async getTemplates(category = 'all') {
        return this.request(this.endpoints.templates, {
            method: 'GET',
            body: null
        });
    }

    async saveTemplate(templateData) {
        return this.request(this.endpoints.templates, {
            body: {
                action: 'save',
                template: templateData
            }
        });
    }

    async deleteTemplate(templateId) {
        return this.request(this.endpoints.templates, {
            body: {
                action: 'delete',
                templateId: templateId
            }
        });
    }

    /**
     * Batch processing
     */
    async processBatch(files, elements, onProgress = null) {
        const formData = new FormData();
        
        // Add files
        files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
        
        // Add elements as JSON
        formData.append('elements', JSON.stringify(elements));
        formData.append('csrf_token', this.csrfToken);

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            // Progress event
            if (onProgress) {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        onProgress(percentComplete, 'upload');
                    }
                });
            }

            // Load event
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        reject(new Error('Invalid response'));
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}`));
                }
            });

            // Error event
            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });

            // Setup request
            xhr.open('POST', this.baseUrl + this.endpoints.batchProcess);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = this.timeout * files.length; // Increase timeout for batch

            // Send request
            xhr.send(formData);
        });
    }

    /**
     * Get available fonts
     */
    async getFonts(language = 'all') {
        return this.request(this.endpoints.getFonts, {
            method: 'GET',
            body: null
        });
    }

    /**
     * Load template data
     */
    async loadTemplate(templateId) {
        return this.request(this.endpoints.templates, {
            body: {
                action: 'load',
                templateId: templateId
            }
        });
    }

    /**
     * Load shared project
     */
    async loadSharedProject(shareToken) {
        return this.cloudSave('loadShared', {
            shareToken: shareToken
        });
    }

    /**
     * Export document as PDF
     */
    async exportPDF(canvasData, documentInfo) {
        return this.request('export-pdf.php', {
            body: {
                canvas: canvasData,
                document: documentInfo,
                format: 'pdf'
            }
        });
    }

    /**
     * Export document as image
     */
    async exportImage(canvasData, format = 'png') {
        return this.request('export-image.php', {
            body: {
                canvas: canvasData,
                format: format
            }
        });
    }

    /**
     * Helper function to sleep
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Download file from URL
     */
    downloadFile(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    /**
     * Convert base64 to Blob
     */
    base64ToBlob(base64, contentType = '') {
        const byteCharacters = atob(base64.split(',')[1]);
        const byteArrays = [];

        for (let offset = 0; offset < byteCharacters.length; offset += 512) {
            const slice = byteCharacters.slice(offset, offset + 512);
            const byteNumbers = new Array(slice.length);
            
            for (let i = 0; i < slice.length; i++) {
                byteNumbers[i] = slice.charCodeAt(i);
            }
            
            const byteArray = new Uint8Array(byteNumbers);
            byteArrays.push(byteArray);
        }

        return new Blob(byteArrays, { type: contentType });
    }

    /**
     * Show loading indicator
     */
    showLoading(message = '') {
        if (window.loadingManager) {
            window.loadingManager.show(message);
        }
    }

    /**
     * Hide loading indicator
     */
    hideLoading() {
        if (window.loadingManager) {
            window.loadingManager.hide();
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        if (window.notificationManager) {
            window.notificationManager.showError(message);
        } else {
            alert(message);
        }
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        if (window.notificationManager) {
            window.notificationManager.showSuccess(message);
        } else {
            console.log(message);
        }
    }
}

// Export for global use
window.APIConnector = APIConnector;