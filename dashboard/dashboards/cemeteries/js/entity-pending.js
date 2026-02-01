/**
 * Entity Pending Operations Helper
 * Handles pending approval badges and status display
 * @version 1.0.0
 */

const EntityPending = {
    // Cache for pending operations
    cache: new Map(),

    // Labels
    actionLabels: {
        create: 'יצירה',
        edit: 'עריכה',
        delete: 'מחיקה'
    },

    /**
     * Fetch pending operations for an entity type
     * @param {string} entityType - 'purchases', 'burials', 'customers'
     * @returns {Promise<Array>}
     */
    async fetchPending(entityType) {
        const cacheKey = `pending_${entityType}`;

        // Check cache (valid for 30 seconds)
        const cached = this.cache.get(cacheKey);
        if (cached && Date.now() - cached.timestamp < 30000) {
            return cached.data;
        }

        try {
            const response = await fetch(
                `/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=listPending&entityType=${entityType}&status=pending`
            );
            const result = await response.json();

            if (result.success) {
                this.cache.set(cacheKey, {
                    data: result.data,
                    timestamp: Date.now()
                });
                return result.data;
            }
        } catch (error) {
            console.error('Error fetching pending operations:', error);
        }

        return [];
    },

    /**
     * Check if entity has pending operation
     * @param {string} entityType
     * @param {string} entityId
     * @returns {Promise<Object|null>}
     */
    async getPendingForEntity(entityType, entityId) {
        try {
            const response = await fetch(
                `/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=getPendingForEntity&entityType=${entityType}&entityId=${entityId}`
            );
            const result = await response.json();
            return result.success ? result.data : null;
        } catch (error) {
            console.error('Error checking pending:', error);
            return null;
        }
    },

    /**
     * Create pending badge HTML
     * @param {string} action - 'create', 'edit', 'delete'
     * @param {Object} options
     * @returns {string}
     */
    createBadge(action, options = {}) {
        const label = this.actionLabels[action] || action;
        const icon = this.getIcon(action);
        const tooltip = options.tooltip || `ממתין לאישור ${label}`;
        const pendingId = options.pendingId || '';

        let html = `<span class="pending-badge pending-${action}" title="${tooltip}"`;

        if (pendingId) {
            html += ` data-pending-id="${pendingId}" onclick="EntityPending.openApproval(${pendingId})"`;
            html += ` style="cursor: pointer;"`;
        }

        html += `>${icon} ממתין לאישור ${label}</span>`;

        return html;
    },

    /**
     * Get icon SVG for action
     * @param {string} action
     * @returns {string}
     */
    getIcon(action) {
        const icons = {
            create: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14m-7-7h14"/></svg>',
            edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            delete: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
        };
        return icons[action] || '';
    },

    /**
     * Open approval page
     * @param {number} pendingId
     */
    openApproval(pendingId) {
        if (typeof PopupManager !== 'undefined') {
            PopupManager.create({
                id: 'entity-approval-popup-' + pendingId,
                type: 'iframe',
                src: `/dashboard/dashboards/cemeteries/notifications/entity-approve.php?id=${pendingId}`,
                title: 'אישור פעולה',
                width: 650,
                height: 700,
                onClose: () => {
                    // Clear cache and refresh
                    this.cache.clear();
                    if (typeof refreshData === 'function') {
                        refreshData();
                    } else if (typeof loadData === 'function') {
                        loadData();
                    }
                }
            });
        } else {
            window.open(`/dashboard/dashboards/cemeteries/notifications/entity-approve.php?id=${pendingId}`, '_blank');
        }
    },

    /**
     * Get pending operations awaiting current user's approval
     * @returns {Promise<Array>}
     */
    async getAwaitingMyApproval() {
        try {
            const response = await fetch(
                '/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=awaitingMyApproval'
            );
            const result = await response.json();
            return result.success ? result.data : [];
        } catch (error) {
            console.error('Error fetching awaiting approval:', error);
            return [];
        }
    },

    /**
     * Create banner for pending operations
     * @param {Array} pending
     * @returns {string}
     */
    createBanner(pending) {
        if (!pending || pending.length === 0) return '';

        return `
            <div class="pending-operations-banner">
                <div class="pending-operations-banner-text">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    <span>
                        <span class="pending-operations-banner-count">${pending.length}</span>
                        פעולות ממתינות לאישורך
                    </span>
                </div>
                <a href="#" class="pending-operations-banner-link" onclick="EntityPending.showPendingList(); return false;">
                    צפה בכולן
                </a>
            </div>
        `;
    },

    /**
     * Show list of pending operations in popup
     */
    async showPendingList() {
        const pending = await this.getAwaitingMyApproval();

        if (pending.length === 0) {
            alert('אין פעולות ממתינות לאישורך');
            return;
        }

        // Create simple list popup
        let html = '<div style="padding: 20px; max-height: 500px; overflow-y: auto;">';
        html += '<h3 style="margin-bottom: 16px;">פעולות ממתינות לאישורך</h3>';
        html += '<ul style="list-style: none; padding: 0; margin: 0;">';

        for (const item of pending) {
            const entityLabel = {purchases: 'רכישה', burials: 'קבורה', customers: 'לקוח'}[item.entity_type] || item.entity_type;
            const actionLabel = this.actionLabels[item.action] || item.action;

            html += `
                <li style="padding: 12px; border-bottom: 1px solid #e5e7eb; cursor: pointer;"
                    onclick="EntityPending.openApproval(${item.id})">
                    <div style="font-weight: 500;">${actionLabel} ${entityLabel}</div>
                    <div style="font-size: 13px; color: #6b7280;">
                        מאת: ${item.requester_name || 'לא ידוע'} |
                        ${new Date(item.created_at).toLocaleDateString('he-IL')}
                    </div>
                </li>
            `;
        }

        html += '</ul></div>';

        if (typeof PopupManager !== 'undefined') {
            PopupManager.create({
                id: 'pending-list-popup',
                type: 'html',
                content: html,
                title: 'פעולות ממתינות',
                width: 450,
                height: 550
            });
        }
    },

    /**
     * Add pending badges to table rows
     * @param {string} entityType
     * @param {HTMLElement} tableBody
     * @param {string} idColumn - Column name or index for entity ID
     */
    async decorateTable(entityType, tableBody, idColumn = 'unicId') {
        const pending = await this.fetchPending(entityType);
        if (!pending || pending.length === 0) return;

        // Create map for quick lookup
        const pendingMap = new Map();
        pending.forEach(p => {
            if (p.entity_id) {
                pendingMap.set(p.entity_id, p);
            }
        });

        // Find rows with pending operations
        const rows = tableBody.querySelectorAll('tr[data-id]');
        rows.forEach(row => {
            const entityId = row.dataset.id;
            const pendingItem = pendingMap.get(entityId);

            if (pendingItem) {
                row.classList.add('has-pending-operation');

                // Find status column or first cell
                const statusCell = row.querySelector('.status-cell, td:last-child');
                if (statusCell) {
                    const badge = this.createBadge(pendingItem.action, {
                        pendingId: pendingItem.id,
                        tooltip: `ממתין לאישור ${this.actionLabels[pendingItem.action]}`
                    });
                    statusCell.insertAdjacentHTML('beforeend', ' ' + badge);
                }
            }
        });
    },

    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }
};

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EntityPending;
}
