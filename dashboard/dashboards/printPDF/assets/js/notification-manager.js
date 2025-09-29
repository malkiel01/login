/**
 * Notification Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/notification-manager.js
 */

class NotificationManager {
    constructor() {
        this.container = null;
        this.notifications = [];
        this.notificationId = 0;
        this.position = PDFEditorConfig.notifications?.position || 'top-right';
        this.duration = PDFEditorConfig.notifications?.duration || 3000;
        
        this.init();
    }

    init() {
        this.createContainer();
        this.bindEvents();
    }

    createContainer() {
        // Check if container already exists
        if (document.getElementById('notificationContainer')) {
            this.container = document.getElementById('notificationContainer');
            return;
        }

        // Create notification container
        this.container = document.createElement('div');
        this.container.id = 'notificationContainer';
        this.container.className = `notification-container notification-${this.position}`;
        this.container.style.cssText = `
            position: fixed;
            z-index: 9999;
            pointer-events: none;
            padding: 20px;
            max-width: 400px;
            width: 100%;
        `;

        // Set position
        this.setPosition();

        document.body.appendChild(this.container);
    }

    setPosition() {
        const positions = {
            'top-right': { top: '20px', right: '20px', left: 'auto', bottom: 'auto' },
            'top-left': { top: '20px', left: '20px', right: 'auto', bottom: 'auto' },
            'bottom-right': { bottom: '20px', right: '20px', left: 'auto', top: 'auto' },
            'bottom-left': { bottom: '20px', left: '20px', right: 'auto', top: 'auto' },
            'top-center': { top: '20px', left: '50%', transform: 'translateX(-50%)', right: 'auto', bottom: 'auto' },
            'bottom-center': { bottom: '20px', left: '50%', transform: 'translateX(-50%)', right: 'auto', top: 'auto' }
        };

        const pos = positions[this.position] || positions['top-right'];
        Object.assign(this.container.style, pos);
    }

    bindEvents() {
        // Listen for language changes
        window.addEventListener('languageChanged', () => {
            // Update RTL/LTR positioning if needed
            if (this.position.includes('right') && languageManager.isRTL()) {
                this.position = this.position.replace('right', 'left');
            } else if (this.position.includes('left') && !languageManager.isRTL()) {
                this.position = this.position.replace('left', 'right');
            }
            this.setPosition();
        });
    }

    show(message, type = 'info', duration = null) {
        const id = ++this.notificationId;
        const notification = this.createNotification(id, message, type, duration || this.duration);
        
        this.notifications.push({ id, element: notification });
        this.container.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        // Auto dismiss if duration is set
        if (duration !== 0 && (duration || this.duration)) {
            setTimeout(() => {
                this.dismiss(id);
            }, duration || this.duration);
        }

        return id;
    }

    createNotification(id, message, type, duration) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.setAttribute('data-notification-id', id);
        
        const icons = {
            'success': '✓',
            'error': '✕',
            'warning': '⚠',
            'info': 'ℹ'
        };

        const colors = {
            'success': '#10b981',
            'error': '#ef4444',
            'warning': '#f59e0b',
            'info': '#3b82f6'
        };

        notification.style.cssText = `
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            pointer-events: all;
            cursor: pointer;
            transition: all 0.3s ease;
            transform: translateX(${this.position.includes('right') ? '100%' : '-100%'});
            opacity: 0;
            border-left: 4px solid ${colors[type]};
        `;

        // Add RTL support
        if (languageManager && languageManager.isRTL()) {
            notification.style.direction = 'rtl';
            notification.style.borderLeft = 'none';
            notification.style.borderRight = `4px solid ${colors[type]}`;
        }

        notification.innerHTML = `
            <div class="notification-icon" style="
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: ${colors[type]};
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                flex-shrink: 0;
            ">${icons[type]}</div>
            <div class="notification-content" style="flex: 1;">
                <div class="notification-message" style="
                    color: #1f2937;
                    font-size: 14px;
                    line-height: 1.5;
                ">${message}</div>
                ${duration && duration > 0 ? `
                <div class="notification-progress" style="
                    height: 2px;
                    background: ${colors[type]}20;
                    margin-top: 8px;
                    border-radius: 2px;
                    overflow: hidden;
                ">
                    <div class="notification-progress-bar" style="
                        height: 100%;
                        background: ${colors[type]};
                        width: 100%;
                        animation: progress ${duration}ms linear;
                    "></div>
                </div>` : ''}
            </div>
            <button class="notification-close" style="
                background: none;
                border: none;
                color: #6b7280;
                cursor: pointer;
                padding: 4px;
                font-size: 18px;
                line-height: 1;
                opacity: 0.7;
                transition: opacity 0.2s;
            " onclick="notificationManager.dismiss(${id})">×</button>
        `;

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            .notification.show {
                transform: translateX(0) !important;
                opacity: 1 !important;
            }
            @keyframes progress {
                from { width: 100%; }
                to { width: 0%; }
            }
        `;
        if (!document.head.querySelector('#notification-styles')) {
            style.id = 'notification-styles';
            document.head.appendChild(style);
        }

        // Click to dismiss
        notification.addEventListener('click', (e) => {
            if (!e.target.classList.contains('notification-close')) {
                this.dismiss(id);
            }
        });

        return notification;
    }

    dismiss(id) {
        const index = this.notifications.findIndex(n => n.id === id);
        if (index === -1) return;

        const notification = this.notifications[index].element;
        notification.classList.remove('show');
        
        // Remove after animation
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
            this.notifications.splice(index, 1);
        }, 300);
    }

    dismissAll() {
        this.notifications.forEach(n => {
            this.dismiss(n.id);
        });
    }

    // Convenience methods
    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration || 5000);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration || 4000);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }

    showSuccess(message) {
        return this.success(message);
    }

    showError(message) {
        return this.error(message);
    }

    showWarning(message) {
        return this.warning(message);
    }

    showInfo(message) {
        return this.info(message);
    }

    // Loading notification with no auto-dismiss
    showLoading(message = 'טוען...') {
        const id = this.show(
            `<div style="display: flex; align-items: center; gap: 10px;">
                <div class="spinner" style="
                    width: 16px;
                    height: 16px;
                    border: 2px solid #e5e7eb;
                    border-top-color: #3b82f6;
                    border-radius: 50%;
                    animation: spin 0.6s linear infinite;
                "></div>
                <span>${message}</span>
            </div>`,
            'info',
            0 // No auto-dismiss
        );

        // Add spinner animation
        if (!document.head.querySelector('#spinner-style')) {
            const style = document.createElement('style');
            style.id = 'spinner-style';
            style.textContent = `
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }

        return id;
    }

    hideLoading(loadingId) {
        this.dismiss(loadingId);
    }

    // Progress notification
    showProgress(message, progress = 0) {
        const id = this.show(
            `<div>
                <div>${message}</div>
                <div style="
                    margin-top: 8px;
                    height: 4px;
                    background: #e5e7eb;
                    border-radius: 2px;
                    overflow: hidden;
                ">
                    <div style="
                        height: 100%;
                        background: #3b82f6;
                        width: ${progress}%;
                        transition: width 0.3s;
                    "></div>
                </div>
            </div>`,
            'info',
            0
        );
        return id;
    }

    updateProgress(id, progress, message = null) {
        const notification = this.notifications.find(n => n.id === id);
        if (!notification) return;

        const progressBar = notification.element.querySelector('div[style*="background: #3b82f6"]');
        if (progressBar) {
            progressBar.style.width = `${progress}%`;
        }

        if (message) {
            const messageEl = notification.element.querySelector('.notification-message > div');
            if (messageEl) {
                messageEl.firstChild.textContent = message;
            }
        }

        if (progress >= 100) {
            setTimeout(() => {
                this.dismiss(id);
            }, 1000);
        }
    }
}

// Create global instance
window.notificationManager = new NotificationManager();

// Convenience global functions
window.showNotification = (message, type, duration) => window.notificationManager.show(message, type, duration);
window.showSuccess = (message) => window.notificationManager.success(message);
window.showError = (message) => window.notificationManager.error(message);
window.showWarning = (message) => window.notificationManager.warning(message);
window.showInfo = (message) => window.notificationManager.info(message);