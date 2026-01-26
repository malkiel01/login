/**
 * Biometric UI - ממשק משתמש לאימות ביומטרי
 *
 * @version 1.0.0
 * @requires biometric-auth.js
 */

class BiometricUI {
    constructor() {
        this.biometric = window.biometricAuth;
    }

    /**
     * יצירת כפתור התחברות ביומטרית
     * @param {HTMLElement} container - המיכל להוספת הכפתור
     * @param {Function} onSuccess - callback להצלחה
     * @param {Function} onError - callback לשגיאה
     */
    async createLoginButton(container, onSuccess, onError) {
        // בדוק תמיכה
        const hasPlatformAuth = await this.biometric.isPlatformAuthenticatorAvailable();
        const hasBiometric = await this.biometric.userHasBiometric();

        if (!hasPlatformAuth) {
            console.log('[BiometricUI] Platform authenticator not available');
            return null;
        }

        // יצירת הכפתור
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'biometric-login-btn';
        button.innerHTML = `
            <span class="biometric-icon">${this.getBiometricIcon()}</span>
            <span class="biometric-text">התחבר עם ${this.getBiometricName()}</span>
        `;

        // אם אין ביומטרי מוגדר, הסתר או השבת
        if (!hasBiometric) {
            button.style.display = 'none';
            button.disabled = true;
        }

        // אירוע לחיצה
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            button.disabled = true;
            button.classList.add('loading');

            try {
                const result = await this.biometric.authenticate();

                if (result.success) {
                    button.classList.remove('loading');
                    button.classList.add('success');
                    if (onSuccess) onSuccess(result);
                } else {
                    button.classList.remove('loading');
                    if (!result.userCancelled) {
                        button.classList.add('error');
                        setTimeout(() => button.classList.remove('error'), 2000);
                    }
                    if (onError) onError(result.error);
                }
            } catch (error) {
                button.classList.remove('loading');
                if (onError) onError(error.message);
            }

            button.disabled = false;
        });

        // הוסף סגנונות
        this.injectStyles();

        container.appendChild(button);
        return button;
    }

    /**
     * יצירת מודל רישום ביומטרי
     * @param {Function} onSuccess - callback להצלחה
     */
    async showRegistrationModal(onSuccess) {
        // בדוק תמיכה
        const hasPlatformAuth = await this.biometric.isPlatformAuthenticatorAvailable();

        if (!hasPlatformAuth) {
            alert('המכשיר שלך לא תומך באימות ביומטרי');
            return;
        }

        // יצירת המודל
        const overlay = document.createElement('div');
        overlay.className = 'biometric-modal-overlay';
        overlay.innerHTML = `
            <div class="biometric-modal">
                <div class="biometric-modal-header">
                    <span class="biometric-modal-icon">${this.getBiometricIcon()}</span>
                    <h2>הוספת אימות ביומטרי</h2>
                </div>
                <div class="biometric-modal-body">
                    <p>הוסף ${this.getBiometricName()} לחשבון שלך להתחברות מהירה ומאובטחת.</p>
                    <div class="biometric-device-name">
                        <label>שם המכשיר (אופציונלי)</label>
                        <input type="text" id="biometric-device-name" placeholder="${this.biometric.guessDeviceName()}">
                    </div>
                </div>
                <div class="biometric-modal-footer">
                    <button class="btn-cancel">ביטול</button>
                    <button class="btn-register">
                        <span class="btn-icon">${this.getBiometricIcon()}</span>
                        הוסף ${this.getBiometricName()}
                    </button>
                </div>
                <div class="biometric-modal-status" style="display:none;"></div>
            </div>
        `;

        document.body.appendChild(overlay);
        this.injectStyles();

        // אירועים
        const closeModal = () => {
            overlay.classList.add('closing');
            setTimeout(() => overlay.remove(), 300);
        };

        overlay.querySelector('.btn-cancel').addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        overlay.querySelector('.btn-register').addEventListener('click', async () => {
            const deviceName = document.getElementById('biometric-device-name').value;
            const statusEl = overlay.querySelector('.biometric-modal-status');
            const registerBtn = overlay.querySelector('.btn-register');

            registerBtn.disabled = true;
            statusEl.style.display = 'block';
            statusEl.className = 'biometric-modal-status loading';
            statusEl.innerHTML = '<span class="spinner"></span> מאמת...';

            try {
                const result = await this.biometric.register(deviceName);

                if (result.success) {
                    statusEl.className = 'biometric-modal-status success';
                    statusEl.innerHTML = '✅ נרשם בהצלחה!';

                    setTimeout(() => {
                        closeModal();
                        if (onSuccess) onSuccess(result);
                    }, 1500);
                } else {
                    statusEl.className = 'biometric-modal-status error';
                    statusEl.innerHTML = `❌ ${result.error || 'שגיאה ברישום'}`;
                    registerBtn.disabled = false;
                }
            } catch (error) {
                statusEl.className = 'biometric-modal-status error';
                statusEl.innerHTML = `❌ ${error.message}`;
                registerBtn.disabled = false;
            }
        });

        // אנימציית פתיחה
        requestAnimationFrame(() => overlay.classList.add('active'));
    }

    /**
     * יצירת מודל אישור פעולה
     * @param {string} actionType - סוג הפעולה
     * @param {Object} actionData - מידע על הפעולה
     * @param {Object} displayInfo - מידע להצגה למשתמש
     * @returns {Promise<Object>}
     */
    async showConfirmationModal(actionType, actionData, displayInfo = {}) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'biometric-modal-overlay confirmation';
            overlay.innerHTML = `
                <div class="biometric-modal">
                    <div class="biometric-modal-header">
                        <span class="biometric-modal-icon">${displayInfo.icon || '⚠️'}</span>
                        <h2>${displayInfo.title || 'אישור פעולה'}</h2>
                    </div>
                    <div class="biometric-modal-body">
                        <p>${displayInfo.message || 'האם לאשר את הפעולה?'}</p>
                        ${displayInfo.details ? `<div class="confirmation-details">${displayInfo.details}</div>` : ''}
                    </div>
                    <div class="biometric-modal-footer">
                        <button class="btn-cancel">ביטול</button>
                        <button class="btn-confirm">
                            <span class="btn-icon">${this.getBiometricIcon()}</span>
                            אשר עם ${this.getBiometricName()}
                        </button>
                    </div>
                    <div class="biometric-modal-status" style="display:none;"></div>
                </div>
            `;

            document.body.appendChild(overlay);
            this.injectStyles();

            const closeModal = (result) => {
                overlay.classList.add('closing');
                setTimeout(() => {
                    overlay.remove();
                    resolve(result);
                }, 300);
            };

            overlay.querySelector('.btn-cancel').addEventListener('click', () => {
                closeModal({ success: false, cancelled: true });
            });

            overlay.querySelector('.btn-confirm').addEventListener('click', async () => {
                const statusEl = overlay.querySelector('.biometric-modal-status');
                const confirmBtn = overlay.querySelector('.btn-confirm');

                confirmBtn.disabled = true;
                statusEl.style.display = 'block';
                statusEl.className = 'biometric-modal-status loading';
                statusEl.innerHTML = '<span class="spinner"></span> מאמת...';

                const result = await this.biometric.confirmAction(actionType, actionData);

                if (result.success) {
                    statusEl.className = 'biometric-modal-status success';
                    statusEl.innerHTML = '✅ אושר!';
                    setTimeout(() => closeModal(result), 1000);
                } else {
                    statusEl.className = 'biometric-modal-status error';
                    statusEl.innerHTML = `❌ ${result.error || 'האימות נכשל'}`;
                    confirmBtn.disabled = false;

                    if (result.userCancelled) {
                        closeModal(result);
                    }
                }
            });

            requestAnimationFrame(() => overlay.classList.add('active'));
        });
    }

    /**
     * יצירת רשימת credentials בהגדרות
     * @param {HTMLElement} container
     */
    async createCredentialsList(container) {
        const credentials = await this.biometric.listCredentials();

        const html = `
            <div class="biometric-credentials-section">
                <h3>${this.getBiometricIcon()} אימות ביומטרי</h3>
                ${credentials.length === 0 ? `
                    <p class="no-credentials">לא הוגדר אימות ביומטרי</p>
                    <button class="btn-add-biometric" onclick="biometricUI.showRegistrationModal()">
                        + הוסף ${this.getBiometricName()}
                    </button>
                ` : `
                    <ul class="credentials-list">
                        ${credentials.map(cred => `
                            <li class="credential-item" data-id="${cred.id}">
                                <div class="credential-info">
                                    <span class="credential-device">${cred.device_name || 'מכשיר לא ידוע'}</span>
                                    <span class="credential-date">נוסף: ${new Date(cred.created_at).toLocaleDateString('he-IL')}</span>
                                    ${cred.last_used_at ? `<span class="credential-last-used">שימוש אחרון: ${new Date(cred.last_used_at).toLocaleDateString('he-IL')}</span>` : ''}
                                </div>
                                <button class="btn-delete-credential" onclick="biometricUI.deleteCredential('${cred.id}', this)">
                                    🗑️
                                </button>
                            </li>
                        `).join('')}
                    </ul>
                    <button class="btn-add-biometric" onclick="biometricUI.showRegistrationModal()">
                        + הוסף מכשיר נוסף
                    </button>
                `}
            </div>
        `;

        container.innerHTML = html;
        this.injectStyles();
    }

    /**
     * מחיקת credential
     */
    async deleteCredential(credentialId, buttonEl) {
        if (!confirm('האם למחוק את האימות הביומטרי הזה?')) {
            return;
        }

        const listItem = buttonEl.closest('.credential-item');
        listItem.classList.add('deleting');

        const success = await this.biometric.deleteCredential(credentialId);

        if (success) {
            listItem.remove();
        } else {
            listItem.classList.remove('deleting');
            alert('שגיאה במחיקה');
        }
    }

    /**
     * קבלת אייקון ביומטרי לפי מערכת הפעלה
     */
    getBiometricIcon() {
        const ua = navigator.userAgent;
        if (/iPhone|iPad/.test(ua)) return '👆'; // Touch ID / Face ID
        if (/Mac/.test(ua)) return '👆';
        if (/Windows/.test(ua)) return '🔐'; // Windows Hello
        if (/Android/.test(ua)) return '👆';
        return '🔐';
    }

    /**
     * קבלת שם ביומטרי לפי מערכת הפעלה
     */
    getBiometricName() {
        const ua = navigator.userAgent;
        if (/iPhone|iPad/.test(ua)) {
            // בדוק אם יש Face ID (iPhone X ומעלה)
            if (window.screen.height >= 812) return 'Face ID';
            return 'Touch ID';
        }
        if (/Mac/.test(ua)) return 'Touch ID';
        if (/Windows/.test(ua)) return 'Windows Hello';
        if (/Android/.test(ua)) return 'טביעת אצבע';
        return 'אימות ביומטרי';
    }

    /**
     * הזרקת סגנונות
     */
    injectStyles() {
        if (document.getElementById('biometric-ui-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'biometric-ui-styles';
        styles.textContent = `
            /* כפתור התחברות ביומטרית */
            .biometric-login-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                width: 100%;
                padding: 14px 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                margin: 10px 0;
            }

            .biometric-login-btn:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            }

            .biometric-login-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .biometric-login-btn.loading {
                background: #9ca3af;
            }

            .biometric-login-btn.loading .biometric-icon {
                animation: pulse 1s infinite;
            }

            .biometric-login-btn.success {
                background: #10b981;
            }

            .biometric-login-btn.error {
                background: #ef4444;
                animation: shake 0.5s;
            }

            .biometric-icon {
                font-size: 24px;
            }

            /* מודל */
            .biometric-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .biometric-modal-overlay.active {
                opacity: 1;
            }

            .biometric-modal-overlay.closing {
                opacity: 0;
            }

            .biometric-modal {
                background: white;
                border-radius: 20px;
                padding: 30px;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                transform: scale(0.9);
                transition: transform 0.3s ease;
            }

            .biometric-modal-overlay.active .biometric-modal {
                transform: scale(1);
            }

            .biometric-modal-header {
                margin-bottom: 20px;
            }

            .biometric-modal-icon {
                font-size: 48px;
                display: block;
                margin-bottom: 10px;
            }

            .biometric-modal-header h2 {
                margin: 0;
                color: #333;
                font-size: 24px;
            }

            .biometric-modal-body {
                margin-bottom: 25px;
                color: #666;
            }

            .biometric-device-name {
                text-align: right;
                margin-top: 15px;
            }

            .biometric-device-name label {
                display: block;
                margin-bottom: 5px;
                font-size: 14px;
                color: #666;
            }

            .biometric-device-name input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                font-size: 16px;
                transition: border-color 0.3s;
            }

            .biometric-device-name input:focus {
                outline: none;
                border-color: #667eea;
            }

            .biometric-modal-footer {
                display: flex;
                gap: 10px;
                justify-content: center;
            }

            .biometric-modal-footer button {
                padding: 12px 24px;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .btn-cancel {
                background: #f3f4f6;
                border: none;
                color: #666;
            }

            .btn-cancel:hover {
                background: #e5e7eb;
            }

            .btn-register, .btn-confirm {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                color: white;
            }

            .btn-register:hover:not(:disabled), .btn-confirm:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }

            .btn-register:disabled, .btn-confirm:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .biometric-modal-status {
                margin-top: 20px;
                padding: 12px;
                border-radius: 10px;
                font-weight: 500;
            }

            .biometric-modal-status.loading {
                background: #f3f4f6;
                color: #666;
            }

            .biometric-modal-status.success {
                background: #d1fae5;
                color: #065f46;
            }

            .biometric-modal-status.error {
                background: #fee2e2;
                color: #991b1b;
            }

            .spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid #ccc;
                border-top-color: #667eea;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                vertical-align: middle;
                margin-left: 8px;
            }

            /* רשימת credentials */
            .biometric-credentials-section {
                padding: 20px;
                background: #f9fafb;
                border-radius: 12px;
            }

            .biometric-credentials-section h3 {
                margin: 0 0 15px;
                color: #333;
            }

            .credentials-list {
                list-style: none;
                padding: 0;
                margin: 0 0 15px;
            }

            .credential-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px;
                background: white;
                border-radius: 10px;
                margin-bottom: 10px;
                transition: opacity 0.3s;
            }

            .credential-item.deleting {
                opacity: 0.5;
            }

            .credential-info {
                text-align: right;
            }

            .credential-device {
                display: block;
                font-weight: 600;
                color: #333;
            }

            .credential-date, .credential-last-used {
                display: block;
                font-size: 12px;
                color: #888;
            }

            .btn-delete-credential {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                opacity: 0.5;
                transition: opacity 0.3s;
            }

            .btn-delete-credential:hover {
                opacity: 1;
            }

            .btn-add-biometric {
                width: 100%;
                padding: 12px;
                background: white;
                border: 2px dashed #d1d5db;
                border-radius: 10px;
                color: #667eea;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }

            .btn-add-biometric:hover {
                border-color: #667eea;
                background: #f5f3ff;
            }

            .no-credentials {
                color: #888;
                margin-bottom: 15px;
            }

            .confirmation-details {
                background: #f3f4f6;
                padding: 15px;
                border-radius: 10px;
                margin-top: 15px;
                text-align: right;
            }

            /* אנימציות */
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }

            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        `;

        document.head.appendChild(styles);
    }
}

// יצירת instance גלובלי
window.biometricUI = new BiometricUI();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BiometricUI;
}
