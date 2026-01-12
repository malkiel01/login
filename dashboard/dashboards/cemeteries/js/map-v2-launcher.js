/**
 * Map V2 Launcher - פותח עורך מפות v2
 */

(function() {
    'use strict';

    // Entity types configuration
    const ENTITY_TYPES = [
        { value: 'cemetery', label: 'בית עלמין', icon: '🏛️' },
        { value: 'block', label: 'גוש', icon: '📦' },
        { value: 'plot', label: 'חלקה', icon: '📋' },
        { value: 'areaGrave', label: 'אחוזת קבר', icon: '🏘️' }
    ];

    let modal = null;
    let selectedEntityType = '';
    let selectedEntityId = '';
    let selectedEntityName = '';

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
                        <label>בחר סוג ישות:</label>
                        <div class="map-v2-entity-types">
                            ${ENTITY_TYPES.map(type => `
                                <button class="map-v2-type-btn" data-type="${type.value}">
                                    <span class="icon">${type.icon}</span>
                                    <span class="label">${type.label}</span>
                                </button>
                            `).join('')}
                        </div>
                    </div>

                    <div class="map-v2-form-group" id="entitySelectGroup" style="display: none;">
                        <label>בחר <span id="entityTypeLabel">ישות</span>:</label>
                        <select id="entitySelect" class="map-v2-select">
                            <option value="">-- בחר --</option>
                        </select>
                    </div>

                    <div class="map-v2-loading" id="loadingIndicator" style="display: none;">
                        <span>טוען...</span>
                    </div>
                </div>
                <div class="map-v2-modal-footer">
                    <button class="map-v2-btn map-v2-btn-secondary" onclick="closeMapV2Modal()">ביטול</button>
                    <button class="map-v2-btn map-v2-btn-primary" id="openMapBtn" disabled onclick="openMapEditor()">פתח עורך מפות</button>
                </div>
            </div>
        `;

        // Add styles
        const style = document.createElement('style');
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
                max-width: 500px;
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
            .map-v2-entity-types {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .map-v2-type-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 6px;
                padding: 16px;
                background: #f9fafb;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .map-v2-type-btn:hover {
                background: #f3f4f6;
                border-color: #d1d5db;
            }
            .map-v2-type-btn.selected {
                background: #eff6ff;
                border-color: #3b82f6;
            }
            .map-v2-type-btn .icon {
                font-size: 24px;
            }
            .map-v2-type-btn .label {
                font-size: 14px;
                color: #374151;
            }
            .map-v2-select {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
                background: white;
            }
            .map-v2-select:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            .map-v2-loading {
                text-align: center;
                padding: 20px;
                color: #6b7280;
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
        document.body.appendChild(modal);

        // Setup event listeners
        setupModalEvents();
    }

    /**
     * Setup modal event listeners
     */
    function setupModalEvents() {
        // Entity type buttons
        const typeButtons = modal.querySelectorAll('.map-v2-type-btn');
        typeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                typeButtons.forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                selectedEntityType = btn.dataset.type;
                loadEntities();
            });
        });

        // Entity select
        const entitySelect = document.getElementById('entitySelect');
        entitySelect.addEventListener('change', () => {
            const option = entitySelect.options[entitySelect.selectedIndex];
            selectedEntityId = entitySelect.value;
            selectedEntityName = option.text;
            document.getElementById('openMapBtn').disabled = !selectedEntityId;
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
     * Load entities for selected type
     */
    async function loadEntities() {
        const selectGroup = document.getElementById('entitySelectGroup');
        const entitySelect = document.getElementById('entitySelect');
        const loading = document.getElementById('loadingIndicator');
        const typeLabel = document.getElementById('entityTypeLabel');

        // Update label
        const typeConfig = ENTITY_TYPES.find(t => t.value === selectedEntityType);
        if (typeConfig) {
            typeLabel.textContent = typeConfig.label;
        }

        // Show loading
        selectGroup.style.display = 'none';
        loading.style.display = 'block';

        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/map2/entity-selector.php?entityType=${selectedEntityType}`);
            const data = await response.json();

            // Populate select
            entitySelect.innerHTML = '<option value="">-- בחר --</option>';
            data.entities.forEach(entity => {
                const option = document.createElement('option');
                option.value = entity.unicId;
                option.textContent = entity.name;
                entitySelect.appendChild(option);
            });

            loading.style.display = 'none';
            selectGroup.style.display = 'block';

            // Reset selection
            selectedEntityId = '';
            selectedEntityName = '';
            document.getElementById('openMapBtn').disabled = true;

        } catch (error) {
            console.error('Error loading entities:', error);
            loading.innerHTML = '<span style="color: #ef4444;">שגיאה בטעינה</span>';
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

        // Reset state
        selectedEntityType = '';
        selectedEntityId = '';
        selectedEntityName = '';
    };

    /**
     * Open the map editor
     */
    window.openMapEditor = function() {
        if (!selectedEntityId) return;

        // Build URL
        const url = `/dashboard/dashboards/cemeteries/map2/index.php?type=${selectedEntityType}&id=${selectedEntityId}&name=${encodeURIComponent(selectedEntityName)}`;

        // Open in popup using PopupManager if available
        if (window.PopupManager) {
            PopupManager.open({
                id: `map-v2-${selectedEntityType}-${selectedEntityId}`,
                title: `עורך מפות - ${selectedEntityName}`,
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
