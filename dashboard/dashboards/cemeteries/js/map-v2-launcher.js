/**
 * Map V2 Launcher - פותח עורך מפות v2
 * גרסה פשוטה עם 2 סלקטים
 */

(function() {
    'use strict';

    // Entity types configuration
    const ENTITY_TYPES = [
        { value: '', label: '-- בחר סוג ישות --' },
        { value: 'cemetery', label: 'בית עלמין' },
        { value: 'block', label: 'גוש' },
        { value: 'plot', label: 'חלקה' },
        { value: 'areaGrave', label: 'אחוזת קבר' }
    ];

    let modal = null;

    /**
     * Open the Map V2 selector
     */
    window.openMapV2 = function() {
        createModal();
        showModal();
    };

    /**
     * Create the modal HTML
     */
    function createModal() {
        if (modal) {
            document.body.removeChild(modal);
        }

        modal = document.createElement('div');
        modal.className = 'map-v2-modal-overlay';
        modal.innerHTML = `
            <div class="map-v2-modal">
                <div class="map-v2-modal-header">
                    <h3>עורך מפות v2</h3>
                    <button class="map-v2-close-btn" onclick="closeMapV2Modal()">&times;</button>
                </div>
                <div class="map-v2-modal-body">
                    <div class="map-v2-form-group">
                        <label for="entityTypeSelect">סוג ישות:</label>
                        <select id="entityTypeSelect" class="map-v2-select">
                            ${ENTITY_TYPES.map(t => `<option value="${t.value}">${t.label}</option>`).join('')}
                        </select>
                    </div>

                    <div class="map-v2-form-group">
                        <label for="entitySelect">בחר פריט:</label>
                        <select id="entitySelect" class="map-v2-select" disabled>
                            <option value="">-- בחר קודם סוג ישות --</option>
                        </select>
                    </div>
                </div>
                <div class="map-v2-modal-footer">
                    <button class="map-v2-btn map-v2-btn-secondary" onclick="closeMapV2Modal()">ביטול</button>
                    <button class="map-v2-btn map-v2-btn-primary" id="openMapBtn" disabled onclick="openMapEditor()">פתח עורך מפות</button>
                </div>
            </div>
        `;

        // Add styles if not already added
        if (!document.getElementById('map-v2-styles')) {
            const style = document.createElement('style');
            style.id = 'map-v2-styles';
            style.textContent = `
                .map-v2-modal-overlay {
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
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                .map-v2-modal-overlay.show {
                    opacity: 1;
                }
                .map-v2-modal {
                    background: white;
                    border-radius: 12px;
                    width: 90%;
                    max-width: 450px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                    transform: translateY(-20px);
                    transition: transform 0.3s;
                }
                .map-v2-modal-overlay.show .map-v2-modal {
                    transform: translateY(0);
                }
                .map-v2-modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 16px 20px;
                    border-bottom: 1px solid #e5e7eb;
                }
                .map-v2-modal-header h3 {
                    margin: 0;
                    font-size: 18px;
                    color: #1f2937;
                }
                .map-v2-close-btn {
                    background: none;
                    border: none;
                    font-size: 24px;
                    color: #6b7280;
                    cursor: pointer;
                    padding: 0;
                    line-height: 1;
                }
                .map-v2-close-btn:hover {
                    color: #1f2937;
                }
                .map-v2-modal-body {
                    padding: 20px;
                }
                .map-v2-form-group {
                    margin-bottom: 16px;
                }
                .map-v2-form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                    color: #374151;
                }
                .map-v2-select {
                    width: 100%;
                    padding: 10px 12px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    font-size: 14px;
                    background: white;
                    cursor: pointer;
                }
                .map-v2-select:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                .map-v2-select:disabled {
                    background: #f3f4f6;
                    cursor: not-allowed;
                }
                .map-v2-modal-footer {
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                    padding: 16px 20px;
                    border-top: 1px solid #e5e7eb;
                }
                .map-v2-btn {
                    padding: 10px 20px;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .map-v2-btn-secondary {
                    background: white;
                    border: 1px solid #d1d5db;
                    color: #374151;
                }
                .map-v2-btn-secondary:hover {
                    background: #f9fafb;
                }
                .map-v2-btn-primary {
                    background: #3b82f6;
                    border: 1px solid #3b82f6;
                    color: white;
                }
                .map-v2-btn-primary:hover:not(:disabled) {
                    background: #2563eb;
                }
                .map-v2-btn-primary:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(modal);

        // Setup event listeners
        setupModalEvents();
    }

    /**
     * Setup modal event listeners
     */
    function setupModalEvents() {
        const entityTypeSelect = document.getElementById('entityTypeSelect');
        const entitySelect = document.getElementById('entitySelect');
        const openMapBtn = document.getElementById('openMapBtn');

        // Entity type change
        entityTypeSelect.addEventListener('change', async () => {
            const type = entityTypeSelect.value;

            // Reset entity select
            entitySelect.innerHTML = '<option value="">טוען...</option>';
            entitySelect.disabled = true;
            openMapBtn.disabled = true;

            if (!type) {
                entitySelect.innerHTML = '<option value="">-- בחר קודם סוג ישות --</option>';
                return;
            }

            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/map2/entity-selector.php?entityType=${type}`);
                const data = await response.json();

                entitySelect.innerHTML = '<option value="">-- בחר פריט --</option>';

                if (data.entities && data.entities.length > 0) {
                    data.entities.forEach(entity => {
                        const option = document.createElement('option');
                        option.value = entity.unicId;
                        option.textContent = entity.name;
                        entitySelect.appendChild(option);
                    });
                    entitySelect.disabled = false;
                } else {
                    entitySelect.innerHTML = '<option value="">אין פריטים</option>';
                }
            } catch (error) {
                console.error('Error loading entities:', error);
                entitySelect.innerHTML = '<option value="">שגיאה בטעינה</option>';
            }
        });

        // Entity select change
        entitySelect.addEventListener('change', () => {
            openMapBtn.disabled = !entitySelect.value;
        });

        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeMapV2Modal();
            }
        });

        // Close on Escape
        document.addEventListener('keydown', handleEscape);
    }

    function handleEscape(e) {
        if (e.key === 'Escape' && modal) {
            closeMapV2Modal();
        }
    }

    /**
     * Show the modal
     */
    function showModal() {
        setTimeout(() => modal.classList.add('show'), 10);
    }

    /**
     * Close the modal
     */
    window.closeMapV2Modal = function() {
        if (!modal) return;

        modal.classList.remove('show');
        setTimeout(() => {
            if (modal && modal.parentNode) {
                document.body.removeChild(modal);
            }
            modal = null;
        }, 300);

        document.removeEventListener('keydown', handleEscape);
    };

    /**
     * Open the map editor
     */
    window.openMapEditor = function() {
        const entityTypeSelect = document.getElementById('entityTypeSelect');
        const entitySelect = document.getElementById('entitySelect');

        if (!entitySelect.value) return;

        const entityType = entityTypeSelect.value;
        const entityId = entitySelect.value;
        const entityName = entitySelect.options[entitySelect.selectedIndex].text;

        // Build URL
        const url = `/dashboard/dashboards/cemeteries/map2/index.php?type=${entityType}&id=${entityId}&name=${encodeURIComponent(entityName)}`;

        // Open in popup using PopupManager if available
        if (window.PopupManager && window.PopupManager.create) {
            window.PopupManager.create({
                id: `map-v2-${entityType}-${entityId}`,
                title: `עורך מפות - ${entityName}`,
                url: url,
                width: '90%',
                height: '90%'
            });
        } else {
            // Fallback - open in new window
            window.open(url, '_blank', 'width=1200,height=800');
        }

        closeMapV2Modal();
    };

})();
