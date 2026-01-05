/**
 * Launcher Modal - מודל בחירת ישות למפה
 * Version: 1.0.0
 *
 * מודל לבחירת ישות (בית עלמין/גוש/חלקה/אחוזת קבר) לפני פתיחת המפה
 * Usage:
 *   const modal = new LauncherModal(entitySelector, config);
 *   modal.onLaunch((entityType, entityId) => { ... });
 *   modal.open();
 */

export class LauncherModal {
    constructor(entitySelector, config = {}) {
        this.selector = entitySelector;
        this.config = {
            modalId: config.modalId || 'mapLauncherModal',
            title: config.title || 'פתיחת מפת בית עלמין',
            entityTypes: config.entityTypes || [
                { value: 'cemetery', label: 'בית עלמין' },
                { value: 'block', label: 'גוש' },
                { value: 'plot', label: 'חלקה' },
                { value: 'areaGrave', label: 'אחוזת קבר' }
            ]
        };

        this.launchCallback = null;
        this.createModal();
        this.attachEventListeners();
    }

    /**
     * יצירת HTML של המודל
     */
    createModal() {
        // בדוק אם כבר קיים
        if (document.getElementById(this.config.modalId)) {
            return;
        }

        const modalHTML = this.getModalHTML();
        const styles = this.getStyles();

        // הוסף סגנונות
        const styleElement = document.createElement('style');
        styleElement.id = 'mapLauncherStyles';
        styleElement.textContent = styles;
        document.head.appendChild(styleElement);

        // הוסף HTML
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    /**
     * בניית HTML של המודל
     */
    getModalHTML() {
        const entityOptionsHTML = this.config.entityTypes
            .map(type => `<option value="${type.value}">${type.label}</option>`)
            .join('');

        return `
            <div id="${this.config.modalId}" class="map-launcher-overlay" style="display: none;">
                <div class="map-launcher-modal">
                    <div class="map-launcher-header">
                        <h3>${this.config.title}</h3>
                        <button type="button" class="map-launcher-close" data-action="close">&times;</button>
                    </div>
                    <div class="map-launcher-body">
                        <div class="map-launcher-field">
                            <label for="mapEntityType">סוג ישות:</label>
                            <select id="mapEntityType" class="map-launcher-select" data-action="typeChange">
                                <option value="">-- בחר סוג ישות --</option>
                                ${entityOptionsHTML}
                            </select>
                        </div>
                        <div class="map-launcher-field">
                            <label for="mapEntitySelect">בחר ישות:</label>
                            <select id="mapEntitySelect" class="map-launcher-select" disabled>
                                <option value="">-- תחילה בחר סוג ישות --</option>
                            </select>
                        </div>
                        <div id="entityLoadingIndicator" class="map-launcher-loading" style="display: none;">
                            טוען ישויות...
                        </div>
                    </div>
                    <div class="map-launcher-footer">
                        <button type="button" class="btn-secondary" data-action="close">ביטול</button>
                        <button type="button" class="btn-primary" data-action="launch">פתח מפה</button>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * סגנונות CSS
     */
    getStyles() {
        return `
            .map-launcher-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            }
            .map-launcher-modal {
                background: white;
                border-radius: 12px;
                width: 400px;
                max-width: 90%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                direction: rtl;
            }
            .map-launcher-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #e5e7eb;
            }
            .map-launcher-header h3 {
                margin: 0;
                font-size: 18px;
                color: #1f2937;
            }
            .map-launcher-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #6b7280;
            }
            .map-launcher-body {
                padding: 20px;
            }
            .map-launcher-field {
                margin-bottom: 16px;
            }
            .map-launcher-field label {
                display: block;
                margin-bottom: 6px;
                font-weight: 500;
                color: #374151;
            }
            .map-launcher-select,
            .map-launcher-input {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                font-size: 14px;
                direction: rtl;
            }
            .map-launcher-select:disabled {
                background: #f3f4f6;
                cursor: not-allowed;
                opacity: 0.6;
            }
            .map-launcher-loading {
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                padding: 10px;
                font-style: italic;
            }
            .map-launcher-footer {
                display: flex;
                justify-content: flex-start;
                gap: 10px;
                padding: 16px 20px;
                border-top: 1px solid #e5e7eb;
                background: #f9fafb;
                border-radius: 0 0 12px 12px;
            }
            .map-launcher-footer .btn-primary {
                background: #3b82f6;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 500;
            }
            .map-launcher-footer .btn-secondary {
                background: white;
                color: #374151;
                border: 1px solid #d1d5db;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
            }
        `;
    }

    /**
     * חיבור event listeners
     */
    attachEventListeners() {
        const modal = document.getElementById(this.config.modalId);
        if (!modal) return;

        // סגירה בלחיצה על כפתורים
        modal.addEventListener('click', (e) => {
            const action = e.target.dataset.action;
            if (action === 'close') {
                this.close();
            } else if (action === 'launch') {
                this.handleLaunch();
            } else if (action === 'typeChange') {
                this.handleTypeChange();
            }
        });

        // טיפול בשינוי סוג ישות
        const typeSelect = document.getElementById('mapEntityType');
        if (typeSelect) {
            typeSelect.addEventListener('change', () => this.handleTypeChange());
        }
    }

    /**
     * טיפול בשינוי סוג ישות
     */
    async handleTypeChange() {
        const entityType = document.getElementById('mapEntityType').value;
        const entitySelect = document.getElementById('mapEntitySelect');
        const loadingIndicator = document.getElementById('entityLoadingIndicator');

        if (!this.selector) {
            console.error('EntitySelector not available');
            return;
        }

        try {
            await this.selector.loadAndRender(entityType, entitySelect, loadingIndicator);
        } catch (error) {
            console.error('Error loading entities:', error);
            alert('שגיאה בטעינת רשימת הישויות: ' + error.message);
        }
    }

    /**
     * טיפול בלחיצה על "פתח מפה"
     */
    handleLaunch() {
        const entityType = document.getElementById('mapEntityType').value;
        const entityId = document.getElementById('mapEntitySelect').value;

        if (!entityType) {
            alert('נא לבחור סוג ישות');
            document.getElementById('mapEntityType').focus();
            return;
        }

        if (!entityId) {
            alert('נא לבחור ישות מהרשימה');
            document.getElementById('mapEntitySelect').focus();
            return;
        }

        // קריאה ל-callback
        if (this.launchCallback) {
            this.launchCallback(entityType, entityId);
        }
    }

    /**
     * פתיחת המודל
     */
    open() {
        console.log('   [LauncherModal.open] Called');
        const modal = document.getElementById(this.config.modalId);
        console.log('   [LauncherModal.open] Modal element:', modal ? 'EXISTS' : 'NOT FOUND');

        if (modal) {
            const currentDisplay = modal.style.display;
            console.log('   [LauncherModal.open] Current display:', currentDisplay);

            // אם המודל כבר פתוח - לא לפתוח שוב (מונע double-click)
            if (currentDisplay === 'flex') {
                console.log('⚠️ [LauncherModal.open] Modal already open - ignoring duplicate open()');
                return;
            }

            modal.style.display = 'flex';
            console.log('   [LauncherModal.open] Set display to: flex');

            // בדיקת visibility ומיקום
            setTimeout(() => {
                const computedStyle = window.getComputedStyle(modal);
                const rect = modal.getBoundingClientRect();
                console.log('   [LauncherModal.open] Post-open verification:', {
                    display: computedStyle.display,
                    visibility: computedStyle.visibility,
                    opacity: computedStyle.opacity,
                    zIndex: computedStyle.zIndex,
                    position: computedStyle.position,
                    rect: {
                        top: rect.top,
                        left: rect.left,
                        width: rect.width,
                        height: rect.height,
                        onScreen: rect.top >= 0 && rect.left >= 0 && rect.width > 0 && rect.height > 0
                    },
                    children: modal.children.length,
                    innerHTML: modal.innerHTML.substring(0, 100) + '...'
                });

                document.getElementById('mapEntityType')?.focus();
            }, 100);
        } else {
            console.error('❌ [LauncherModal.open] Modal element #' + this.config.modalId + ' not found in DOM!');
        }
    }

    /**
     * סגירת המודל
     */
    close() {
        console.log('   [LauncherModal.close] Called');
        const modal = document.getElementById(this.config.modalId);
        console.log('   [LauncherModal.close] Modal element:', modal ? 'EXISTS' : 'NOT FOUND');

        if (modal) {
            const currentDisplay = modal.style.display;
            console.log('   [LauncherModal.close] Current display:', currentDisplay);

            modal.style.display = 'none';
            console.log('   [LauncherModal.close] Set display to: none');

            this.reset();
            console.log('   [LauncherModal.close] Modal reset completed');
        }
    }

    /**
     * איפוס הטופס
     */
    reset() {
        const typeSelect = document.getElementById('mapEntityType');
        const entitySelect = document.getElementById('mapEntitySelect');

        if (typeSelect) typeSelect.value = '';
        if (entitySelect) {
            entitySelect.value = '';
            entitySelect.disabled = true;
            entitySelect.innerHTML = '<option value="">-- תחילה בחר סוג ישות --</option>';
        }
    }

    /**
     * הגדרת callback לפתיחת מפה
     * @param {Function} callback - פונקציה שמקבלת (entityType, entityId)
     */
    onLaunch(callback) {
        this.launchCallback = callback;
    }
}
