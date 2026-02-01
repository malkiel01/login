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
    },

    /**
     * טעינת רשומות ממתינות ליצירה (לא קיימות עדיין במערכת)
     * @param {string} entityType - 'customers', 'purchases', 'burials'
     * @returns {Promise<Array>}
     */
    async fetchPendingCreates(entityType) {
        try {
            const response = await fetch(
                `/dashboard/dashboards/cemeteries/api/${entityType}-api.php?action=listPending`
            );
            const result = await response.json();
            return result.success ? result.data : [];
        } catch (error) {
            console.error('Error fetching pending creates:', error);
            return [];
        }
    },

    /**
     * ביטול בקשה ממתינה
     * @param {number} pendingId
     * @returns {Promise<boolean>}
     */
    async cancelPending(pendingId) {
        if (!confirm('האם לבטל את הבקשה הממתינה?')) {
            return false;
        }

        try {
            const response = await fetch(
                '/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=cancel',
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pendingId })
                }
            );
            const result = await response.json();

            if (result.success) {
                this.clearCache();
                // רענון התצוגה
                if (typeof refreshData === 'function') {
                    refreshData();
                }
                return true;
            } else {
                alert('שגיאה: ' + (result.error || 'לא ניתן לבטל'));
                return false;
            }
        } catch (error) {
            console.error('Error canceling pending:', error);
            alert('שגיאה בביטול הבקשה');
            return false;
        }
    },

    /**
     * שליחה חוזרת של בקשת אישור
     * @param {number} pendingId
     * @returns {Promise<boolean>}
     */
    async resendApproval(pendingId) {
        try {
            const response = await fetch(
                '/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=resendNotification',
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pendingId })
                }
            );
            const result = await response.json();

            if (result.success) {
                alert('בקשת האישור נשלחה מחדש');
                return true;
            } else {
                alert('שגיאה: ' + (result.error || 'לא ניתן לשלוח'));
                return false;
            }
        } catch (error) {
            console.error('Error resending approval:', error);
            alert('שגיאה בשליחת הבקשה');
            return false;
        }
    },

    /**
     * יצירת שורת טבלה עבור לקוח ממתין
     * @param {Object} pending
     * @returns {string}
     */
    createPendingCustomerRow(pending) {
        const isOwner = pending.is_owner;
        const expiresAt = pending.expires_at ? new Date(pending.expires_at).toLocaleDateString('he-IL') : 'ללא';

        let actions = `
            <button class="btn btn-sm btn-outline" onclick="EntityPending.openApproval(${pending.pending_id})" title="צפייה בפרטים">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        `;

        if (isOwner) {
            actions += `
                <button class="btn btn-sm btn-outline" onclick="EntityPending.resendApproval(${pending.pending_id})" title="שלח בקשה חוזרת">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                    </svg>
                </button>
                <button class="btn btn-sm btn-outline btn-danger" onclick="EntityPending.cancelPending(${pending.pending_id})" title="ביטול">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            `;
        }

        return `
            <tr class="pending-row" data-pending-id="${pending.pending_id}">
                <td>
                    <span class="pending-badge pending-create">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14m-7-7h14"/></svg>
                        ממתין לאישור
                    </span>
                </td>
                <td>${pending.firstName || ''} ${pending.lastName || ''}</td>
                <td>${pending.numId || '-'}</td>
                <td>${pending.phoneMobile || pending.phone || '-'}</td>
                <td>${pending.requester_name || '-'}</td>
                <td>
                    <span class="approval-progress">${pending.approved_count || 0}/${pending.required_approvals}</span>
                </td>
                <td>${expiresAt}</td>
                <td class="actions-cell">${actions}</td>
            </tr>
        `;
    },

    /**
     * יצירת סקשן של לקוחות ממתינים
     * @param {Array} pendingList
     * @returns {string}
     */
    createPendingCustomersSection(pendingList) {
        if (!pendingList || pendingList.length === 0) return '';

        let rows = pendingList.map(p => this.createPendingCustomerRow(p)).join('');

        // בדיקה אם הסקשן היה מכווץ קודם
        const wasCollapsed = localStorage.getItem('pendingSection_collapsed') === 'true';
        const collapsedClass = wasCollapsed ? ' section-collapsed' : '';

        return `
            <div class="pending-customers-section${collapsedClass}" data-count="${pendingList.length}">
                <div class="pending-customers-header" onclick="EntityPending.toggleSection()">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        </svg>
                        לקוחות ממתינים לאישור
                        <span class="pending-count">${pendingList.length}</span>
                    </h3>
                    <svg class="pending-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 15l-6-6-6 6"/>
                    </svg>
                </div>
                <div class="pending-customers-body">
                    <table class="pending-customers-table">
                        <thead>
                            <tr>
                                <th>סטטוס</th>
                                <th>שם</th>
                                <th>ת.ז.</th>
                                <th>טלפון</th>
                                <th>מבקש</th>
                                <th>אישורים</th>
                                <th>תוקף עד</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    /**
     * צמצום/הרחבה של הסקשן
     */
    toggleSection() {
        const section = document.querySelector('.pending-customers-section');
        if (!section) return;

        section.classList.toggle('section-collapsed');
        const isCollapsed = section.classList.contains('section-collapsed');
        localStorage.setItem('pendingSection_collapsed', isCollapsed);

        // עדכון כפתור ההדר
        this.updateHeaderButton();
    },

    /**
     * הסתרת הסקשן לגמרי (צמצום מלא)
     */
    collapseSection() {
        const section = document.querySelector('.pending-customers-section');
        if (section) {
            section.classList.add('collapsed');
            localStorage.setItem('pendingSection_hidden', 'true');
            this.updateHeaderButton();
        }
    },

    /**
     * הצגת הסקשן חזרה
     */
    expandSection() {
        const section = document.querySelector('.pending-customers-section');
        if (section) {
            section.classList.remove('collapsed');
            section.classList.remove('section-collapsed');
            localStorage.setItem('pendingSection_hidden', 'false');
            localStorage.setItem('pendingSection_collapsed', 'false');
            this.updateHeaderButton();
        }
    },

    /**
     * עדכון כפתור ממתינים בהדר
     */
    updateHeaderButton() {
        const section = document.querySelector('.pending-customers-section');
        const btn = document.querySelector('.btn-pending-toggle');

        if (!btn) return;

        const count = section ? section.dataset.count : 0;
        const isHidden = section && section.classList.contains('collapsed');

        if (count > 0 && isHidden) {
            btn.style.display = 'inline-flex';
            btn.querySelector('.pending-btn-count').textContent = count;
        } else {
            btn.style.display = 'none';
        }
    },

    /**
     * יצירת כפתור ממתינים להוספה להדר
     * @param {number} count
     * @returns {string}
     */
    createHeaderButton(count) {
        return `
            <button class="btn-pending-toggle" onclick="EntityPending.expandSection()" title="הצג ממתינים לאישור" style="display: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
                <span>ממתינים</span>
                <span class="pending-btn-count">${count}</span>
            </button>
        `;
    },

    /**
     * טעינה והצגת לקוחות ממתינים
     * @param {HTMLElement} container - אלמנט להוספת הסקשן
     */
    async loadAndShowPendingCustomers(container) {
        const pending = await this.fetchPendingCreates('customers');

        // הסרת סקשן קיים
        const existingSection = container.querySelector('.pending-customers-section');
        if (existingSection) {
            existingSection.remove();
        }

        // הסרת כפתור קיים
        const existingBtn = document.querySelector('.btn-pending-toggle');
        if (existingBtn) {
            existingBtn.remove();
        }

        if (pending && pending.length > 0) {
            // בדיקה אם הסקשן היה מוסתר לגמרי
            const wasHidden = localStorage.getItem('pendingSection_hidden') === 'true';

            const html = this.createPendingCustomersSection(pending);
            container.insertAdjacentHTML('afterbegin', html);

            // אם היה מוסתר, נסתיר שוב
            if (wasHidden) {
                const section = container.querySelector('.pending-customers-section');
                if (section) section.classList.add('collapsed');
            }

            // הוספת כפתור להדר
            const actionButtons = document.querySelector('.action-buttons');
            if (actionButtons) {
                const btnHtml = this.createHeaderButton(pending.length);
                // הוספה לפני כפתור ההוספה
                const addBtn = actionButtons.querySelector('.btn-add');
                if (addBtn) {
                    addBtn.insertAdjacentHTML('beforebegin', btnHtml);
                } else {
                    actionButtons.insertAdjacentHTML('afterbegin', btnHtml);
                }
            }

            // עדכון מצב הכפתור
            this.updateHeaderButton();
        }
    },

    /**
     * רענון רשימת הלקוחות וסקשן הממתינים
     */
    async refreshCustomersView() {
        console.log('[EntityPending] מרענן תצוגת לקוחות...');

        // ניקוי cache
        this.clearCache();

        // רענון סקשן ממתינים
        const mainContainer = document.querySelector('.main-container');
        if (mainContainer) {
            await this.loadAndShowPendingCustomers(mainContainer);
        }

        // רענון רשימת הלקוחות
        if (typeof EntityManager !== 'undefined' && window.currentType === 'customer') {
            await EntityManager.refresh('customer');
        } else if (typeof customersRefreshData === 'function') {
            customersRefreshData();
        } else if (typeof loadCustomers === 'function') {
            loadCustomers();
        }
    },

    /**
     * אתחול האזנה להודעות מפופאפים
     */
    initMessageListener() {
        window.addEventListener('message', (event) => {
            // אישור/דחייה של entity הושלם
            if (event.data && event.data.type === 'entityApprovalComplete') {
                console.log('[EntityPending] התקבלה הודעת השלמת אישור:', event.data);

                // סגירת הפופאפ
                if (typeof PopupManager !== 'undefined') {
                    const popupId = 'entity-approval-popup-' + event.data.pendingId;
                    PopupManager.close(popupId);
                }

                // רענון אוטומטי לפי סוג הישות הנוכחי
                setTimeout(() => {
                    if (window.currentType === 'customer') {
                        this.refreshCustomersView();
                    } else if (window.currentType === 'purchase') {
                        if (typeof purchasesRefreshData === 'function') {
                            this.clearCache();
                            purchasesRefreshData();
                        }
                    } else if (window.currentType === 'burial') {
                        if (typeof burialsRefreshData === 'function') {
                            this.clearCache();
                            burialsRefreshData();
                        }
                    } else {
                        // רענון כללי
                        this.clearCache();
                        if (typeof refreshData === 'function') {
                            refreshData();
                        }
                    }
                }, 300);
            }
        });

        console.log('[EntityPending] האזנה להודעות אותחלה');
    }
};

// אתחול האזנה להודעות בטעינת הדף
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        EntityPending.initMessageListener();
    });
} else {
    EntityPending.initMessageListener();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EntityPending;
}
