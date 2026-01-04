/**
 * MapPopup - ����� ����� ����
 * Version: 1.0.0
 *
 * ����� ������ ����� ���� - �����, �����, �����, ��� ���
 * Usage:
 *   const popup = new MapPopup({
 *     onOpen: (entityType, entityId) => {...},
 *     onClose: () => {...},
 *     onMapInit: (canvas) => {...}
 *   });
 *   popup.open(entityType, unicId);
 */

export class MapPopup {
    constructor(options = {}) {
        this.options = {
            onOpen: options.onOpen || null,
            onClose: options.onClose || null,
            onMapInit: options.onMapInit || null,
            apiEndpoint: options.apiEndpoint || 'api/cemetery-hierarchy.php'
        };

        this.overlay = null;
        this.container = null;
        this.isFullscreen = false;
    }

    /**
     * ����� ������
     * @param {string} entityType - cemetery, block, plot, areaGrave
     * @param {string} unicId - ID of the entity
     */
    async open(entityType, unicId) {
        // Remove existing popup if exists
        this.close();

        // Create popup HTML
        this.createPopup();

        // Load map data
        await this.loadMapData(entityType, unicId);

        // Callback
        if (this.options.onOpen) {
            this.options.onOpen(entityType, unicId);
        }

        console.log(` MapPopup opened for ${entityType}:${unicId}`);
    }

    /**
     * ����� HTML �� ������
     * @private
     */
    createPopup() {
        const popupHTML = `
            <div id="mapPopupOverlay" class="map-popup-overlay">
                <div class="map-popup-container">
                    <div class="map-popup-header">
                        <h3 id="mapPopupTitle">טוען מפה...</h3>
                        <div class="map-popup-controls">
                            <!-- כפתור מצב עריכה -->
                            <div class="edit-mode-toggle">
                                <span class="toggle-label">מצב עריכה</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="editModeToggle">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <button type="button" class="map-popup-btn" onclick="toggleMapFullscreen()" title="מסך מלא">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                                </svg>
                            </button>
                            <button type="button" class="map-popup-close" onclick="closeMapPopup()">&times;</button>
                        </div>
                    </div>
                    <div class="map-popup-body">
                        <div id="mapContainer" class="map-container">
                            <div class="map-loading">
                                <div class="map-spinner"></div>
                                <p>טוען מפה...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add styles if not exist
        this.injectStyles();

        // Insert popup
        document.body.insertAdjacentHTML('beforeend', popupHTML);

        // Store references
        this.overlay = document.getElementById('mapPopupOverlay');
        this.container = this.overlay.querySelector('.map-popup-container');

        // Debug: Check initial container after creation
        const initialMapContainer = document.getElementById('mapContainer');
        console.log('🔍 MapPopup.createPopup() - Initial mapContainer after HTML insertion:', initialMapContainer ? {
            clientWidth: initialMapContainer.clientWidth,
            clientHeight: initialMapContainer.clientHeight,
            display: window.getComputedStyle(initialMapContainer).display,
            flexDirection: window.getComputedStyle(initialMapContainer).flexDirection
        } : 'NOT FOUND');
    }

    /**
     * ����� ����� ���� ��-API
     * @param {string} entityType
     * @param {string} unicId
     * @private
     */
    async loadMapData(entityType, unicId) {
        try {
            const response = await fetch(`${this.options.apiEndpoint}?action=get&type=${entityType}&id=${unicId}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'לא נמצאה מפה');
            }

            const entity = result.data;

            // Update title
            this.updateTitle(entityType, entity);

            // Wait for DOM to be ready before initializing map
            // This ensures container dimensions are calculated correctly
            console.log('⏳ Waiting 50ms for DOM to be ready...');
            await new Promise(resolve => setTimeout(resolve, 50));

            // Check if container is ready
            const mapContainer = document.getElementById('mapContainer');
            console.log('🔍 MapPopup - Container dimensions after delay:', mapContainer ? {
                clientWidth: mapContainer.clientWidth,
                clientHeight: mapContainer.clientHeight,
                offsetWidth: mapContainer.offsetWidth,
                offsetHeight: mapContainer.offsetHeight
            } : 'NOT FOUND');

            // Initialize map callback (will be handled by map-launcher)
            console.log('🚀 Calling onMapInit callback...');
            if (this.options.onMapInit) {
                this.options.onMapInit(entityType, unicId, entity);
            }

        } catch (error) {
            console.error('שגיאה בטעינת מפה:', error);
            this.showError(error.message);
        }
    }

    /**
     * ����� ����� ������
     * @param {string} entityType
     * @param {object} entity
     * @private
     */
    updateTitle(entityType, entity) {
        const entityNames = {
            cemetery: 'בית עלמין',
            block: 'גוש',
            plot: 'חלקה',
            areaGrave: 'שטח קבר'
        };

        const entityName = entity.cemeteryNameHe ||
                          entity.blockNameHe ||
                          entity.plotNameHe ||
                          entity.areaGraveNameHe ||
                          'לא ידוע';

        const title = document.getElementById('mapPopupTitle');
        if (title) {
            title.textContent = `מפת ${entityNames[entityType]}: ${entityName}`;
        }
    }

    /**
     * ���� �����
     * @param {string} message
     * @private
     */
    showError(message) {
        const container = document.getElementById('mapContainer');
        if (container) {
            container.innerHTML = `
                <div class="map-loading">
                    <p style="color: #dc2626;">שגיאה: ${message}</p>
                    <button onclick="closeMapPopup()" style="margin-top: 12px; padding: 8px 16px; cursor: pointer;">סגור</button>
                </div>
            `;
        }
    }

    /**
     * ����� ������ ������
     */
    close() {
        if (this.overlay) {
            this.overlay.remove();
            this.overlay = null;
            this.container = null;
        }

        // Callback
        if (this.options.onClose) {
            this.options.onClose();
        }

        console.log(' MapPopup closed');
    }

    /**
     * ���� ���� ��� ��� / ����� ���� ���
     */
    toggleFullscreen() {
        if (!this.container) return;

        this.isFullscreen = !this.isFullscreen;
        this.container.classList.toggle('fullscreen');

        // Resize canvas after transition
        setTimeout(() => {
            if (window.mapCanvas) {
                const canvasContainer = document.getElementById('mapCanvas');
                if (canvasContainer) {
                    window.mapCanvas.setWidth(canvasContainer.clientWidth);
                    window.mapCanvas.setHeight(canvasContainer.clientHeight - 40);
                    window.mapCanvas.renderAll();
                }
            }
        }, 100);

        console.log(this.isFullscreen ? ' Fullscreen ON' : ' Fullscreen OFF');
    }

    /**
     * ����� ������� CSS ���
     * @private
     */
    injectStyles() {
        // Check if styles already exist
        if (document.getElementById('mapPopupStyles')) return;

        const styles = document.createElement('style');
        styles.id = 'mapPopupStyles';
        styles.textContent = `
            .map-popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            }
            .map-popup-container {
                background: white;
                border-radius: 12px;
                width: 90%;
                height: 85%;
                max-width: 1400px;
                display: flex;
                flex-direction: column;
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
                overflow: hidden;
            }
            .map-popup-container.fullscreen {
                width: 100%;
                height: 100%;
                max-width: none;
                border-radius: 0;
            }
            .map-popup-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 20px;
                background: #1f2937;
                color: white;
            }
            .map-popup-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 500;
            }
            .map-popup-controls {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .edit-mode-toggle {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 4px 12px;
                background: rgba(255,255,255,0.1);
                border-radius: 20px;
            }
            .toggle-label {
                font-size: 13px;
                color: #d1d5db;
            }
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
            }
            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #4b5563;
                transition: .3s;
                border-radius: 24px;
            }
            .toggle-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
            }
            .toggle-switch input:checked + .toggle-slider {
                background-color: #3b82f6;
            }
            .toggle-switch input:checked + .toggle-slider:before {
                transform: translateX(20px);
            }
            .map-popup-btn {
                background: rgba(255, 255, 255, 0.1);
                border: none;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }
            .map-popup-btn:hover {
                background: rgba(255, 255, 255, 0.2);
            }
            .map-popup-close {
                background: rgba(239, 68, 68, 0.8);
                border: none;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 24px;
                line-height: 1;
                transition: background 0.2s;
            }
            .map-popup-close:hover {
                background: rgba(239, 68, 68, 1);
            }
            .map-popup-body {
                flex: 1;
                overflow: hidden;
            }
            .map-container {
                width: 100%;
                height: 100%;
                display: flex;
                flex-direction: column;
            }
            .map-canvas {
                width: 100%;
                flex: 1;
                background: #e5e7eb;
                position: relative;
                overflow: hidden;
            }
            .map-loading {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
            }
            .map-spinner {
                border: 4px solid #f3f4f6;
                border-top: 4px solid #3b82f6;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto 16px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;

        document.head.appendChild(styles);
        console.log('✅ [CSS-1] MapPopup CSS injected - ID: mapPopupStyles');
        console.log('   [CSS-1] Includes: .map-popup-overlay, .map-popup-container, .map-popup-header, .map-popup-body, .map-container (flex), .map-canvas (flex: 1), .map-loading');
    }

    /**
     * Debug info
     */
    debug() {
        console.group('=� MapPopup');
        console.log('Overlay:', this.overlay ? 'open' : 'closed');
        console.log('Fullscreen:', this.isFullscreen);
        console.groupEnd();
    }
}
