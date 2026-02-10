/**
 * Entity Pending Operations Helper
 * Handles pending approval badges and status display
 * @version 1.1.0 - Use ApprovalModal for unified UX
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

    // Entity type labels (Hebrew)
    entityLabels: {
        customers: 'לקוחות',
        purchases: 'רכישות',
        burials: 'קבורות',
        graves: 'קברים',
        blocks: 'גושים',
        plots: 'חלקות',
        cemeteries: 'בתי עלמין'
    },

    // Map singular entity type to plural for API
    entityTypeMap: {
        customer: 'customers',
        purchase: 'purchases',
        burial: 'burials',
        grave: 'graves',
        block: 'blocks',
        plot: 'plots',
        cemetery: 'cemeteries'
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
     * Open approval page - uses ApprovalModal for unified UX
     * @param {number} pendingId - The pending_entity_operations.id
     */
    openApproval(pendingId) {
        const url = `/dashboard/dashboards/cemeteries/notifications/entity-approve.php?id=${pendingId}`;

        // Use ApprovalModal for unified UX (same as notification flow)
        if (typeof ApprovalModal !== 'undefined') {
            // Initialize modal if needed
            ApprovalModal.init();

            // Set onClose callback to refresh data
            ApprovalModal.onClose = () => {
                this.cache.clear();
                if (typeof refreshData === 'function') {
                    refreshData();
                } else if (typeof loadData === 'function') {
                    loadData();
                }
            };

            // Show the entity approval iframe directly
            ApprovalModal.showEntityApprovalIframe(url);
        } else if (typeof PopupManager !== 'undefined') {
            // Fallback to PopupManager
            PopupManager.create({
                id: 'entity-approval-popup-' + pendingId,
                type: 'iframe',
                src: url,
                title: 'אישור פעולה',
                width: 650,
                height: 700,
                onClose: () => {
                    this.cache.clear();
                    if (typeof refreshData === 'function') {
                        refreshData();
                    } else if (typeof loadData === 'function') {
                        loadData();
                    }
                }
            });
        } else {
            window.open(url, '_blank');
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
            const entityLabel = (typeof EntityLabels !== 'undefined') ? EntityLabels.getEntity(item.entity_type) : item.entity_type;
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
     * ניקוי סקשן ממתינים וכפתור "הצג ממתינים"
     * קריאה בעת מעבר לישות שאינה customers
     */
    clearPendingSection() {
        // הסרת סקשן הממתינים
        const pendingSection = document.getElementById('pendingSection');
        if (pendingSection) {
            pendingSection.remove();
        }

        // איפוס instance של TableManager
        this.pendingTableInstance = null;

        // הסתרת כפתור "הצג ממתינים"
        const showPendingBtn = document.querySelector('.btn-show-pending');
        if (showPendingBtn) {
            showPendingBtn.classList.remove('visible');
        }
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
     * הגדרת עמודות לטבלת הממתינים - לפי סוג ישות
     * @param {string} entityType - 'customers', 'purchases', 'burials'
     * @returns {Array} מערך הגדרות עמודות עבור TableManager
     */
    getPendingTableColumns(entityType) {
        // עמודות בסיסיות משותפות לכל הישויות
        const baseColumns = [
            {
                field: 'status',
                label: 'סטטוס',
                width: '120px',
                render: (row) => {
                    const actionLabel = this.actionLabels[row.action] || row.action;
                    return `
                        <span class="pending-badge pending-${row.action || 'create'}">
                            ${this.getIcon(row.action || 'create')}
                            ממתין - ${actionLabel}
                        </span>
                    `;
                }
            }
        ];

        // עמודות ספציפיות לפי סוג ישות
        let entityColumns = [];

        switch (entityType) {
            case 'customers':
                entityColumns = [
                    {
                        field: 'fullName',
                        label: 'שם',
                        width: '150px',
                        render: (row) => `${row.firstName || ''} ${row.lastName || ''}`
                    },
                    {
                        field: 'numId',
                        label: 'ת.ז.',
                        width: '100px',
                        render: (row) => row.numId || '-'
                    },
                    {
                        field: 'phone',
                        label: 'טלפון',
                        width: '120px',
                        render: (row) => row.phoneMobile || row.phone || '-'
                    }
                ];
                break;

            case 'purchases':
                entityColumns = [
                    {
                        field: 'clientName',
                        label: 'לקוח',
                        width: '150px',
                        render: (row) => row.clientName || `${row.clientFirstName || ''} ${row.clientLastName || ''}`.trim() || '-'
                    },
                    {
                        field: 'graveName',
                        label: 'קבר',
                        width: '120px',
                        render: (row) => row.graveName || row.graveNameHe || '-'
                    },
                    {
                        field: 'price',
                        label: 'מחיר',
                        width: '100px',
                        render: (row) => row.price ? `₪${parseFloat(row.price).toLocaleString()}` : '-'
                    }
                ];
                break;

            case 'burials':
                entityColumns = [
                    {
                        field: 'clientName',
                        label: 'נפטר/ת',
                        width: '150px',
                        render: (row) => row.clientName || `${row.clientFirstName || ''} ${row.clientLastName || ''}`.trim() || '-'
                    },
                    {
                        field: 'graveName',
                        label: 'קבר',
                        width: '120px',
                        render: (row) => row.graveName || row.graveNameHe || '-'
                    },
                    {
                        field: 'burialDate',
                        label: 'תאריך קבורה',
                        width: '100px',
                        render: (row) => row.burialDate ? new Date(row.burialDate).toLocaleDateString('he-IL') : '-'
                    }
                ];
                break;

            default:
                entityColumns = [
                    {
                        field: 'name',
                        label: 'שם',
                        width: '200px',
                        render: (row) => row.name || row.nameHe || '-'
                    }
                ];
        }

        // עמודות סיום משותפות
        const endColumns = [
            {
                field: 'requester_name',
                label: 'מבקש',
                width: '120px',
                render: (row) => row.requester_name || '-'
            },
            {
                field: 'approvals',
                label: 'אישורים',
                width: '80px',
                render: (row) => `
                    <span class="approval-progress">${row.approved_count || 0}/${row.required_approvals}</span>
                `
            },
            {
                field: 'expires_at',
                label: 'תוקף עד',
                width: '100px',
                render: (row) => row.expires_at ? new Date(row.expires_at).toLocaleDateString('he-IL') : 'ללא'
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                render: (row) => {
                    const isOwner = row.is_owner;
                    let actions = `
                        <button class="btn btn-sm btn-outline" onclick="EntityPending.openApproval(${row.pending_id})" title="צפייה בפרטים">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    `;

                    if (isOwner) {
                        actions += `
                            <button class="btn btn-sm btn-outline" onclick="EntityPending.resendApproval(${row.pending_id})" title="שלח בקשה חוזרת">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                                </svg>
                            </button>
                            <button class="btn btn-sm btn-outline btn-danger" onclick="EntityPending.cancelPending(${row.pending_id})" title="ביטול">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        `;
                    }

                    return `<div class="action-buttons" style="display: flex; gap: 4px;">${actions}</div>`;
                }
            }
        ];

        return [...baseColumns, ...entityColumns, ...endColumns];
    },

    /**
     * יצירת סקשן של לקוחות ממתינים - HTML מינימלי (עטיפה לתאימות אחורה)
     * @param {number} count - מספר הממתינים
     * @returns {string}
     */
    createPendingCustomersSection(count) {
        return this.createPendingSection('customers', count);
    },

    /**
     * יצירת סקשן של ישויות ממתינות - HTML מינימלי
     * TableManager ייצור את הטבלה עצמה
     * @param {string} entityType - סוג הישות ('customers', 'purchases', 'burials')
     * @param {number} count - מספר הממתינים
     * @returns {string}
     */
    createPendingSection(entityType, count) {
        if (!count || count === 0) return '';

        // בדיקה אם הסקשן היה מכווץ קודם
        const wasCollapsed = localStorage.getItem('pendingSectionCollapsed') === 'true';
        const collapsedClass = wasCollapsed ? ' collapsed' : '';

        // תווית הישות בעברית
        const entityLabel = this.entityLabels[entityType] || entityType;

        return `
            <div class="pending-section${collapsedClass}" id="pendingSection" data-count="${count}" data-entity-type="${entityType}">
                <div class="pending-section-header">
                    <div class="pending-section-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        </svg>
                        <span>${entityLabel} ממתינים לאישור</span>
                        <span class="pending-section-count">${count}</span>
                    </div>
                    <button class="btn-collapse-pending" onclick="EntityPending.togglePendingSection(this)" title="צמצם ממתינים">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                        <span>צמצם</span>
                    </button>
                </div>
                <div class="pending-section-body">
                    <div class="table-container">
                        <table id="pendingTable" class="data-table">
                            <thead><tr><th>טוען...</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    },

    // שמירת instance של TableManager לממתינים
    pendingTableInstance: null,

    // שמירת סוג הישות הנוכחי
    currentEntityType: null,

    /**
     * אתחול TableManager לטבלת הממתינים
     * @param {Array} data - נתוני הממתינים
     * @param {string} entityType - סוג הישות (ברירת מחדל: 'customers')
     */
    initPendingTable(data, entityType = 'customers') {
        // בדיקה שהאלמנט קיים
        const table = document.getElementById('pendingTable');
        if (!table) {
            console.error('❌ pendingTable not found');
            this.pendingTableInstance = null;
            return;
        }

        // בדיקה אם ה-DOM של הטבלה הקיימת עדיין קיים
        // אם ה-instance קיים אבל ה-DOM שלו נמחק - צריך לאפס
        if (this.pendingTableInstance && this.currentEntityType === entityType) {
            const existingWrapper = document.querySelector('.pending-section .table-wrapper');
            if (existingWrapper) {
                // ה-DOM קיים - עדכן את הנתונים
                this.pendingTableInstance.setData(data);
                return;
            } else {
                // ה-DOM נמחק - אפס את ה-instance
                this.pendingTableInstance = null;
            }
        }

        // שמירת סוג הישות הנוכחי
        this.currentEntityType = entityType;

        // יצירת TableManager
        this.pendingTableInstance = new TableManager({
            tableSelector: '#pendingTable',
            data: data,
            columns: this.getPendingTableColumns(entityType),

            // הגדרות תצוגה
            totalItems: data.length,
            scrollLoadBatch: 999999,  // טען הכל בבת אחת
            showPagination: false,

            // גובה מותאם לסקשן
            tableHeight: '250px',
            tableMinHeight: '150px',

            // הגדרות נוספות
            sortable: true,
            resizable: true,
            filterable: false,

            // callback לדאבל-קליק
            onRowDoubleClick: (row) => {
                this.openApproval(row.pending_id);
            }
        });

        console.log(`✅ Pending TableManager initialized for ${entityType}`);
    },

    /**
     * צמצום/הרחבה של סקשן הממתינים (תבנית זהה ל-UniversalSearch.toggleSearchSection)
     * @param {HTMLElement} btn - הכפתור שנלחץ
     */
    togglePendingSection(btn) {
        const pendingSection = btn.closest('.pending-section');
        if (!pendingSection) {
            console.error('❌ pending-section not found!');
            return;
        }

        const isCollapsed = pendingSection.classList.toggle('collapsed');

        // עדכון כפתור הצמצום
        const btnText = btn.querySelector('span');
        const btnIcon = btn.querySelector('svg');

        if (isCollapsed) {
            if (btnText) btnText.textContent = 'הרחב';
            if (btnIcon) btnIcon.style.transform = 'rotate(180deg)';
        } else {
            if (btnText) btnText.textContent = 'צמצם';
            if (btnIcon) btnIcon.style.transform = 'rotate(0deg)';
        }

        // עדכון כפתור "הצג ממתינים" בשורת הפעולות
        const showPendingBtn = document.querySelector('.btn-show-pending');
        if (showPendingBtn) {
            showPendingBtn.classList.toggle('visible', isCollapsed);
            // עדכון המונה בכפתור
            const countBadge = showPendingBtn.querySelector('.pending-btn-count');
            if (countBadge) {
                countBadge.textContent = pendingSection.dataset.count || '0';
            }
        }

        // שמירה ב-localStorage
        localStorage.setItem('pendingSectionCollapsed', isCollapsed);
    },

    /**
     * הרחבת סקשן הממתינים (קריאה מכפתור "הצג ממתינים")
     */
    expandSection() {
        const pendingSection = document.getElementById('pendingSection');
        if (!pendingSection) return;

        // הרחב את הסקשן
        pendingSection.classList.remove('collapsed');

        // עדכון כפתור הצמצום בתוך הסקשן
        const collapseBtn = pendingSection.querySelector('.btn-collapse-pending');
        if (collapseBtn) {
            const btnText = collapseBtn.querySelector('span');
            const btnIcon = collapseBtn.querySelector('svg');
            if (btnText) btnText.textContent = 'צמצם';
            if (btnIcon) btnIcon.style.transform = 'rotate(0deg)';
        }

        // הסתר כפתור "הצג ממתינים"
        const showPendingBtn = document.querySelector('.btn-show-pending');
        if (showPendingBtn) {
            showPendingBtn.classList.remove('visible');
        }

        // עדכון localStorage
        localStorage.setItem('pendingSectionCollapsed', 'false');
    },

    /**
     * טעינת מצב שמור של סקשן הממתינים
     */
    loadPendingSectionState() {
        const isMobile = window.innerWidth <= 768;
        const wasCollapsed = localStorage.getItem('pendingSectionCollapsed');

        // במסכים קטנים - ברירת מחדל מכווץ (אלא אם נשמר אחרת)
        // במסכים גדולים - ברירת מחדל פתוח (אלא אם נשמר אחרת)
        const shouldCollapse = wasCollapsed !== null
            ? wasCollapsed === 'true'
            : isMobile;

        const pendingSection = document.getElementById('pendingSection');
        const showPendingBtn = document.querySelector('.btn-show-pending');

        if (shouldCollapse) {
            if (pendingSection) {
                pendingSection.classList.add('collapsed');

                // עדכון כפתור הצמצום
                const collapseBtn = pendingSection.querySelector('.btn-collapse-pending');
                if (collapseBtn) {
                    const btnText = collapseBtn.querySelector('span');
                    const btnIcon = collapseBtn.querySelector('svg');
                    if (btnText) btnText.textContent = 'הרחב';
                    if (btnIcon) btnIcon.style.transform = 'rotate(180deg)';
                }

                // הצג כפתור "הצג ממתינים"
                if (showPendingBtn) {
                    showPendingBtn.classList.add('visible');
                    const countBadge = showPendingBtn.querySelector('.pending-btn-count');
                    if (countBadge) {
                        countBadge.textContent = pendingSection.dataset.count || '0';
                    }
                }
            }
        } else {
            if (pendingSection) {
                pendingSection.classList.remove('collapsed');
            }
            if (showPendingBtn) {
                showPendingBtn.classList.remove('visible');
            }
        }
    },

    /**
     * טעינה והצגת לקוחות ממתינים (עטיפה לתאימות אחורה)
     * @param {HTMLElement} container - אלמנט להוספת הסקשן
     */
    async loadAndShowPendingCustomers(container) {
        return this.loadAndShowPending('customers', container);
    },

    /**
     * טעינה והצגת ישויות ממתינות - גרסה גנרית
     * @param {string} entityType - סוג הישות ('customers', 'purchases', 'burials')
     * @param {HTMLElement} container - אלמנט להוספת הסקשן
     */
    async loadAndShowPending(entityType, container) {
        const pending = await this.fetchPendingCreates(entityType);

        // הסרת סקשן קיים ואיפוס instance
        const existingSection = container.querySelector('.pending-section');
        if (existingSection) {
            existingSection.remove();
            this.pendingTableInstance = null;
        }

        // עדכון כפתור "הצג ממתינים" בהדר
        const showPendingBtn = document.querySelector('.btn-show-pending');

        if (pending && pending.length > 0) {
            // יצירת HTML של הסקשן (ללא הנתונים - רק מבנה)
            const html = this.createPendingSection(entityType, pending.length);
            container.insertAdjacentHTML('afterbegin', html);

            // עדכון המונה בכפתור ההדר
            if (showPendingBtn) {
                const countBadge = showPendingBtn.querySelector('.pending-btn-count');
                if (countBadge) {
                    countBadge.textContent = pending.length;
                }
            }

            // טעינת מצב שמור (collapsed/expanded)
            this.loadPendingSectionState();

            // אתחול TableManager עם הנתונים
            setTimeout(() => {
                this.initPendingTable(pending, entityType);
            }, 50);
        } else {
            // אין ממתינים - הסתר את הכפתור
            if (showPendingBtn) {
                showPendingBtn.classList.remove('visible');
            }
        }
    },

    /**
     * רענון רשימת הלקוחות וסקשן הממתינים (עטיפה לתאימות אחורה)
     */
    async refreshCustomersView() {
        return this.refreshEntityView('customers');
    },

    /**
     * רענון רשימת הישויות וסקשן הממתינים - גרסה גנרית
     * @param {string} entityType - סוג הישות ('customers', 'purchases', 'burials')
     */
    async refreshEntityView(entityType) {
        const entityLabel = this.entityLabels[entityType] || entityType;
        console.log(`[EntityPending] מרענן תצוגת ${entityLabel}...`);

        // ניקוי cache ו-TableManager instance
        this.clearCache();
        this.pendingTableInstance = null;

        // רענון סקשן ממתינים
        const mainContent = document.querySelector('.main-content');
        const mainContainer = document.querySelector('.main-container');
        const container = mainContainer || mainContent;

        if (container) {
            await this.loadAndShowPending(entityType, container);
        }

        // רענון רשימת הישויות
        const singularType = Object.keys(this.entityTypeMap).find(
            key => this.entityTypeMap[key] === entityType
        ) || entityType.replace(/s$/, '');

        if (typeof EntityManager !== 'undefined') {
            await EntityManager.refresh(singularType);
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
