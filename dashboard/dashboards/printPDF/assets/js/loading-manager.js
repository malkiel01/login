/**
 * Loading Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/loading-manager.js
 */

class LoadingManager {
    constructor() {
        this.overlay = null;
        this.loadingStack = 0;
        this.currentLoadingId = null;
        
        this.init();
    }

    init() {
        this.createOverlay();
    }

    createOverlay() {
        // Check if overlay already exists
        if (document.getElementById('loadingOverlay')) {
            this.overlay = document.getElementById('loadingOverlay');
            return;
        }

        // Create loading overlay
        this.overlay = document.createElement('div');
        this.overlay.id = 'loadingOverlay';
        this.overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9998;
            transition: opacity 0.3s;
        `;

        // Create loading content
        const content = document.createElement('div');
        content.className = 'loading-content';
        content.style.cssText = `
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            min-width: 200px;
            max-width: 400px;
        `;

        content.innerHTML = `
            <div class="loading-spinner" style="
                width: 50px;
                height: 50px;
                border: 4px solid #e5e7eb;
                border-top-color: #667eea;
                border-radius: 50%;
                margin: 0 auto 20px;
                animation: loadingSpin 1s linear infinite;
            "></div>
            <div class="loading-text" style="
                color: #1f2937;
                font-size: 16px;
                font-weight: 500;
                margin-bottom: 10px;
            ">טוען...</div>
            <div class="loading-progress" style="
                display: none;
                height: 4px;
                background: #e5e7eb;
                border-radius: 2px;
                overflow: hidden;
                margin-top: 15px;
            ">
                <div class="loading-progress-bar" style="
                    height: 100%;
                    background: linear-gradient(90deg, #667eea, #764ba2);
                    width: 0%;
                    transition: width 0.3s;
                "></div>
            </div>
            <div class="loading-details" style="
                color: #6b7280;
                font-size: 14px;
                margin-top: 10px;
                display: none;
            "></div>
        `;

        this.overlay.appendChild(content);
        document.body.appendChild(this.overlay);

        // Add animation styles
        if (!document.head.querySelector('#loading-styles')) {
            const style = document.createElement('style');
            style.id = 'loading-styles';
            style.textContent = `
                @keyframes loadingSpin {
                    to { transform: rotate(360deg); }
                }
                .loading-overlay-show {
                    display: flex !important;
                    opacity: 1 !important;
                }
                .loading-overlay-hide {
                    opacity: 0 !important;
                }
            `;
            document.head.appendChild(style);
        }
    }

    show(message = null, showProgress = false) {
        this.loadingStack++;
        
        if (this.loadingStack === 1) {
            this.overlay.classList.add('loading-overlay-show');
            this.overlay.classList.remove('loading-overlay-hide');
        }

        if (message) {
            this.setMessage(message);
        }

        if (showProgress) {
            this.showProgress();
        }

        // Return a loading ID for tracking
        const loadingId = Date.now() + Math.random();
        this.currentLoadingId = loadingId;
        return loadingId;
    }

    hide(loadingId = null) {
        // If specific loading ID provided, only hide if it matches current
        if (loadingId && loadingId !== this.currentLoadingId) {
            return;
        }

        this.loadingStack = Math.max(0, this.loadingStack - 1);
        
        if (this.loadingStack === 0) {
            this.overlay.classList.add('loading-overlay-hide');
            
            setTimeout(() => {
                this.overlay.classList.remove('loading-overlay-show');
                this.overlay.classList.remove('loading-overlay-hide');
                this.reset();
            }, 300);
        }
    }

    forceHide() {
        this.loadingStack = 0;
        this.hide();
    }

    setMessage(message) {
        const textElement = this.overlay.querySelector('.loading-text');
        if (textElement) {
            textElement.textContent = message;
        }
    }

    setDetails(details) {
        const detailsElement = this.overlay.querySelector('.loading-details');
        if (detailsElement) {
            detailsElement.textContent = details;
            detailsElement.style.display = details ? 'block' : 'none';
        }
    }

    showProgress() {
        const progressElement = this.overlay.querySelector('.loading-progress');
        if (progressElement) {
            progressElement.style.display = 'block';
        }
    }

    hideProgress() {
        const progressElement = this.overlay.querySelector('.loading-progress');
        if (progressElement) {
            progressElement.style.display = 'none';
        }
    }

    setProgress(percentage, message = null) {
        const progressBar = this.overlay.querySelector('.loading-progress-bar');
        if (progressBar) {
            progressBar.style.width = `${Math.min(100, Math.max(0, percentage))}%`;
        }

        if (message) {
            this.setMessage(message);
        }

        // Auto hide when complete
        if (percentage >= 100) {
            setTimeout(() => {
                this.hide();
            }, 500);
        }
    }

    reset() {
        this.setMessage('טוען...');
        this.setDetails('');
        this.setProgress(0);
        this.hideProgress();
    }

    // Quick loading for async operations
    async withLoading(asyncFunction, message = 'מעבד...') {
        const loadingId = this.show(message);
        
        try {
            const result = await asyncFunction();
            this.hide(loadingId);
            return result;
        } catch (error) {
            this.hide(loadingId);
            throw error;
        }
    }

    // File upload with progress
    showUploadProgress(filename) {
        this.show(`מעלה ${filename}...`, true);
    }

    updateUploadProgress(percentage, filename = null) {
        if (filename) {
            this.setMessage(`מעלה ${filename}...`);
        }
        this.setProgress(percentage);
        this.setDetails(`${percentage}% הושלם`);
    }

    // Processing indicator
    showProcessing(message = 'מעבד...') {
        return this.show(message, false);
    }

    // Saving indicator
    showSaving(message = 'שומר...') {
        return this.show(message, false);
    }

    // Loading project
    showLoadingProject(projectName = null) {
        const message = projectName ? `טוען פרויקט: ${projectName}` : 'טוען פרויקט...';
        return this.show(message, true);
    }

    // Batch processing
    showBatchProgress(current, total) {
        this.show(`מעבד קובץ ${current} מתוך ${total}`, true);
        this.setProgress((current / total) * 100);
    }

    // Custom loading component
    createInlineLoader(container, message = 'טוען...') {
        const loader = document.createElement('div');
        loader.className = 'inline-loader';
        loader.style.cssText = `
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            gap: 10px;
        `;
        
        loader.innerHTML = `
            <div style="
                width: 20px;
                height: 20px;
                border: 2px solid #e5e7eb;
                border-top-color: #667eea;
                border-radius: 50%;
                animation: loadingSpin 1s linear infinite;
            "></div>
            <span style="color: #6b7280;">${message}</span>
        `;
        
        container.appendChild(loader);
        
        return {
            remove: () => loader.remove(),
            setMessage: (msg) => {
                const span = loader.querySelector('span');
                if (span) span.textContent = msg;
            }
        };
    }
}

// Create global instance
window.loadingManager = new LoadingManager();

// Convenience global functions
window.showLoading = (message) => window.loadingManager.show(message);
window.hideLoading = () => window.loadingManager.hide();
window.setLoadingProgress = (percentage, message) => window.loadingManager.setProgress(percentage, message);