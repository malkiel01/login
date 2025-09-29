/**
 * Fixed API Connector for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/api-connector-fixed.js
 */

class APIConnector {
    constructor() {
        this.baseUrl = '/dashboard/dashboards/printPDF';
        this.endpoints = {
            upload: this.baseUrl + '/api/upload-file.php',
            processDocument: this.baseUrl + '/api/process-document.php',
            saveProject: this.baseUrl + '/api/save-project.php',
            loadProject: this.baseUrl + '/api/load-project.php',
            getTemplates: this.baseUrl + '/api/templates.php',
            cloudSync: this.baseUrl + '/api/cloud-sync.php'
        };
        
        // Get CSRF token from page
        this.csrfToken = document.getElementById('csrfToken')?.value || '';
        
        // Configuration
        this.timeout = 30000; // 30 seconds
        this.retries = 3;
        this.retryDelay = 1000; // 1 second
    }

    /**
     * Upload file to server
     */
    async uploadFile(file, onProgress = null) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', this.csrfToken);

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
                    const response = JSON.parse(xhr.responseText);
                    
                    if (xhr.status === 200 && response.success) {
                        resolve(response);
                    } else {
                        reject(new Error(response.message || 'Upload failed'));
                    }
                } catch (error) {
                    reject(new Error('Invalid server response'));
                }
            });

            // Error event
            xhr.addEventListener('error', () => {
                reject(new Error('Network error during upload'));
            });

            // Timeout event
            xhr.addEventListener('timeout', () => {
                reject(new Error('Upload timeout'));
            });

            // Abort event
            xhr.addEventListener('abort', () => {
                reject(new Error('Upload cancelled'));
            });

            // Configure and send request
            xhr.open('POST', this.endpoints.upload);
            xhr.timeout = this.timeout;
            xhr.send(formData);
        });
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
            method: 'POST',
            body: payload
        });
    }

    /**
     * Save project to server
     */
    async saveProject(projectData) {
        return this.request(this.endpoints.saveProject, {
            method: 'POST',
            body: projectData
        });
    }

    /**
     * Load project from server
     */
    async loadProject(projectId) {
        return this.request(this.endpoints.loadProject + '?id=' + projectId, {
            method: 'GET'
        });
    }

    /**
     * Get templates list
     */
    async getTemplates(category = null) {
        const url = category ? 
            `${this.endpoints.getTemplates}?category=${category}` :
            this.endpoints.getTemplates;
            
        return this.request(url, {
            method: 'GET'
        });
    }

    /**
     * Sync with cloud storage
     */
    async cloudSync(action, data = null) {
        return this.request(this.endpoints.cloudSync, {
            method: 'POST',
            body: {
                action: action,
                data: data
            }
        });
    }

    /**
     * Generic request method with retry logic
     */
    async request(url, options = {}, retryCount = 0) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'X-CSRF-Token': this.csrfToken
            }
        };

        // Add JSON headers for POST requests
        if (options.body) {
            defaultOptions.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const finalOptions = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, finalOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Request failed');
            }

            return data;

        } catch (error) {
            console.error(`API request failed: ${url}`, error);

            // Retry logic
            if (retryCount < this.retries) {
                console.log(`Retrying request... (${retryCount + 1}/${this.retries})`);
                await this.sleep(this.retryDelay * (retryCount + 1));
                return this.request(url, options, retryCount + 1);
            }

            throw error;
        }
    }

    /**
     * Helper method to sleep
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
     * Convert file to base64
     */
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    /**
     * Validate file
     */
    validateFile(file) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp'
        ];
        
        if (file.size > maxSize) {
            throw new Error('הקובץ גדול מדי. הגודל המקסימלי הוא 10MB');
        }
        
        if (!allowedTypes.includes(file.type)) {
            const extension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            
            if (!allowedExtensions.includes(extension)) {
                throw new Error('סוג הקובץ אינו נתמך');
            }
        }
        
        return true;
    }

    /**
     * Show error message
     */
    showError(message) {
        if (window.notificationManager) {
            window.notificationManager.error(message);
        } else {
            alert(message);
        }
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        if (window.notificationManager) {
            window.notificationManager.success(message);
        } else {
            console.log(message);
        }
    }
}

// Export for global use
window.APIConnector = APIConnector;