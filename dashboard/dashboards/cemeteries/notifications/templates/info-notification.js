/**
 * Info Notification Template
 * Simple notification displayed at the bottom of the screen (toast style)
 * Does NOT require approval - just informational
 *
 * Features:
 * - Appears at bottom of screen (half height max)
 * - Can be dismissed by clicking X or tapping outside
 * - Auto-dismisses after timeout (optional)
 * - Shows title, body, and optional action button
 *
 * @version 1.0.0
 */

(function() {
    // Add to NotificationTemplates
    if (!window.NotificationTemplates) {
        console.error('[InfoNotification] NotificationTemplates not loaded');
        return;
    }

    /**
     * Show info notification (bottom toast/sheet)
     * @param {Object} notification - Notification data
     * @param {Object} options - Display options
     */
    NotificationTemplates.showInfoNotification = function(notification, options = {}) {
        // Close any existing modal
        this.close();

        // Default options
        const config = {
            autoDismiss: options.autoDismiss ?? true,
            autoDismissDelay: options.autoDismissDelay ?? 5000,
            showCloseButton: options.showCloseButton ?? true,
            showCounter: options.showCounter ?? null, // מונה התראות (1/5)
            actionButton: options.actionButton ?? null, // { text: 'פתח', onClick: function }
            ...options
        };

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'notification-overlay info-notification-overlay';
        overlay.innerHTML = `
            <div class="info-notification-container">
                <div class="info-notification-card">
                    ${config.showCounter ? `
                    <span class="info-notification-counter">${config.showCounter}</span>
                    ` : ''}
                    ${config.showCloseButton ? `
                    <button class="info-notification-close" aria-label="סגור">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                    ` : ''}
                    <div class="info-notification-icon">
                        ${this.getNotificationIcon(notification.level || notification.type || 'info')}
                    </div>
                    <div class="info-notification-content">
                        <h4 class="info-notification-title">${this.escapeHtml(notification.title || 'הודעה')}</h4>
                        <p class="info-notification-body">${this.escapeHtml(notification.body || '')}</p>
                        ${notification.creator_name ? `
                        <span class="info-notification-sender">מאת: ${this.escapeHtml(notification.creator_name)}</span>
                        ` : ''}
                    </div>
                    ${config.actionButton ? `
                    <div class="info-notification-actions">
                        <button class="nt-btn nt-btn-primary info-action-btn">
                            ${this.escapeHtml(config.actionButton.text || 'פתח')}
                        </button>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;

        // Add styles if not exists
        this.addInfoNotificationStyles();

        // Add to DOM
        document.body.appendChild(overlay);
        this.activeModal = overlay;

        // Event handlers
        const closeBtn = overlay.querySelector('.info-notification-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }

        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                this.close();
            }
        });

        // Action button
        const actionBtn = overlay.querySelector('.info-action-btn');
        if (actionBtn && config.actionButton?.onClick) {
            actionBtn.addEventListener('click', () => {
                config.actionButton.onClick();
                this.close();
            });
        }

        // Auto dismiss
        if (config.autoDismiss) {
            setTimeout(() => {
                if (this.activeModal === overlay) {
                    this.close();
                }
            }, config.autoDismissDelay);
        }

        // Mark as read if notification ID exists
        if (notification.id) {
            this.markNotificationAsRead(notification.id);
        }

        return overlay;
    };

    /**
     * Get icon SVG based on notification level/type
     */
    NotificationTemplates.getNotificationIcon = function(level) {
        const icons = {
            info: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 16v-4M12 8h.01"/>
            </svg>`,
            success: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>`,
            warning: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>`,
            error: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>`,
            urgent: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>`
        };
        return icons[level] || icons.info;
    };

    /**
     * Escape HTML to prevent XSS
     */
    NotificationTemplates.escapeHtml = function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Mark notification as read
     */
    NotificationTemplates.markNotificationAsRead = async function(notificationId) {
        try {
            await fetch('/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
            });
        } catch (e) {
            console.log('[InfoNotification] Failed to mark as read:', e);
        }
    };

    /**
     * Add styles for info notification
     */
    NotificationTemplates.addInfoNotificationStyles = function() {
        if (document.getElementById('infoNotificationStyles')) return;

        const style = document.createElement('style');
        style.id = 'infoNotificationStyles';
        style.textContent = `
            /* ============================================
               Info Notification - Bottom Toast/Sheet
               ============================================ */

            .info-notification-overlay {
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.3);
                align-items: flex-end;
                justify-content: center;
                padding: 0;
            }

            .info-notification-container {
                width: 100%;
                max-width: 500px;
                max-height: 50vh;
                animation: ntSlideUp 0.3s ease;
            }

            .info-notification-card {
                background: var(--bg-primary, white);
                border-radius: 20px 20px 0 0;
                padding: 24px;
                position: relative;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
            }

            .info-notification-close {
                position: absolute;
                top: 16px;
                left: 16px;
                background: var(--bg-tertiary, #f1f5f9);
                border: none;
                border-radius: 50%;
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: var(--text-muted, #64748b);
                transition: all 0.2s;
            }

            .info-notification-close:hover {
                background: var(--border-color, #e2e8f0);
                color: var(--text-primary, #1e293b);
            }

            .info-notification-icon {
                text-align: center;
                margin-bottom: 16px;
            }

            .info-notification-content {
                text-align: center;
            }

            .info-notification-title {
                margin: 0 0 8px;
                font-size: 20px;
                font-weight: 600;
                color: var(--text-primary, #1e293b);
            }

            .info-notification-body {
                margin: 0 0 12px;
                font-size: 16px;
                color: var(--text-secondary, #475569);
                line-height: 1.5;
            }

            .info-notification-sender {
                display: block;
                font-size: 13px;
                color: var(--text-muted, #94a3b8);
            }

            .info-notification-actions {
                margin-top: 20px;
                display: flex;
                gap: 12px;
            }

            .info-notification-actions .nt-btn-primary {
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
                color: white;
            }

            .info-notification-actions .nt-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color, #667eea) 40%, transparent);
            }

            /* RTL */
            [dir="rtl"] .info-notification-close {
                left: auto;
                right: 16px;
            }

            /* Desktop adjustments */
            @media (min-width: 768px) {
                .info-notification-container {
                    margin-bottom: 20px;
                }

                .info-notification-card {
                    border-radius: 20px;
                }
            }

            /* Counter badge */
            .info-notification-counter {
                position: absolute;
                top: 16px;
                left: 60px;
                background: var(--primary-color, #667eea);
                color: white;
                font-size: 12px;
                font-weight: 600;
                padding: 4px 10px;
                border-radius: 12px;
            }

            [dir="rtl"] .info-notification-counter {
                left: auto;
                right: 60px;
            }
        `;
        document.head.appendChild(style);
    };

    console.log('[InfoNotification] Template loaded');
})();
