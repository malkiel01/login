/*
 * File: table-module/js/table-permissions.js
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: ניהול הרשאות לטבלאות - צפיה, עריכה, מחיקה, ייצוא
 */

const TablePermissions = (function() {
    // Cache להרשאות
    let permissionsCache = new Map();
    let isLoaded = false;
    let loadingPromise = null;

    // API endpoint
    const API_URL = '/dashboard/dashboards/cemeteries/table-module/api/permissions-api.php';

    /**
     * טעינת הרשאות מהשרת
     */
    async function load(entityType = null, forceRefresh = false) {
        // בדיקת cache
        if (!forceRefresh && entityType && permissionsCache.has(entityType)) {
            return permissionsCache.get(entityType);
        }

        // אם יש בקשה בתהליך
        if (loadingPromise && !forceRefresh) {
            return loadingPromise;
        }

        try {
            const url = entityType
                ? `${API_URL}?action=get&entityType=${encodeURIComponent(entityType)}`
                : `${API_URL}?action=getAll`;

            loadingPromise = fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (entityType) {
                            permissionsCache.set(entityType, data.permissions);
                            return data.permissions;
                        } else {
                            // טען את כל ההרשאות
                            Object.entries(data.permissions).forEach(([type, perms]) => {
                                permissionsCache.set(type, perms);
                            });
                            isLoaded = true;
                            return data.permissions;
                        }
                    }
                    throw new Error(data.error || 'Failed to load permissions');
                })
                .finally(() => {
                    loadingPromise = null;
                });

            return loadingPromise;

        } catch (error) {
            console.error('TablePermissions: Load error', error);
            // החזר ברירות מחדל במקרה של שגיאה
            return getDefaultPermissions();
        }
    }

    /**
     * קבלת הרשאות ברירת מחדל
     */
    function getDefaultPermissions() {
        return {
            canView: true,
            canEdit: false,
            canDelete: false,
            canExport: false,
            canCreate: false,
            visibleColumns: null,
            editableColumns: null
        };
    }

    /**
     * קבלת הרשאות לסוג entity
     */
    function get(entityType) {
        if (permissionsCache.has(entityType)) {
            return permissionsCache.get(entityType);
        }
        return getDefaultPermissions();
    }

    /**
     * קבלת הרשאות אסינכרונית
     */
    async function getAsync(entityType) {
        if (!permissionsCache.has(entityType)) {
            await load(entityType);
        }
        return get(entityType);
    }

    /**
     * בדיקת הרשאה ספציפית
     */
    function can(entityType, action) {
        const perms = get(entityType);

        switch (action) {
            case 'view': return perms.canView;
            case 'edit': return perms.canEdit;
            case 'delete': return perms.canDelete;
            case 'export': return perms.canExport;
            case 'create': return perms.canCreate;
            default: return false;
        }
    }

    /**
     * בדיקה אם עמודה נראית
     */
    function isColumnVisible(entityType, columnField) {
        const perms = get(entityType);

        // אם אין הגבלה - הכל נראה
        if (!perms.visibleColumns) return true;

        return perms.visibleColumns.includes(columnField);
    }

    /**
     * בדיקה אם עמודה ניתנת לעריכה
     */
    function isColumnEditable(entityType, columnField) {
        const perms = get(entityType);

        // אם אין הרשאת עריכה כללית
        if (!perms.canEdit) return false;

        // אם אין הגבלה על עמודות
        if (!perms.editableColumns) return true;

        return perms.editableColumns.includes(columnField);
    }

    /**
     * סינון עמודות לפי הרשאות
     */
    function filterColumns(entityType, columns) {
        const perms = get(entityType);

        if (!perms.visibleColumns) return columns;

        return columns.filter(col =>
            perms.visibleColumns.includes(col.field)
        );
    }

    /**
     * קבלת כפתורי פעולה מותרים
     */
    function getAllowedActions(entityType) {
        const perms = get(entityType);
        const actions = [];

        if (perms.canView) actions.push('view');
        if (perms.canEdit) actions.push('edit');
        if (perms.canDelete) actions.push('delete');
        if (perms.canExport) actions.push('export');
        if (perms.canCreate) actions.push('create');

        return actions;
    }

    /**
     * יצירת כפתורי פעולה לשורה
     */
    function createRowActions(entityType, rowData, options = {}) {
        const perms = get(entityType);
        const actions = [];

        if (perms.canView) {
            actions.push({
                type: 'view',
                label: 'צפה',
                icon: '👁️',
                handler: options.onView
            });
        }

        if (perms.canEdit) {
            actions.push({
                type: 'edit',
                label: 'ערוך',
                icon: '✏️',
                handler: options.onEdit
            });
        }

        if (perms.canDelete) {
            actions.push({
                type: 'delete',
                label: 'מחק',
                icon: '🗑️',
                handler: options.onDelete,
                confirm: true,
                confirmMessage: 'האם למחוק רשומה זו?'
            });
        }

        return actions;
    }

    /**
     * יצירת HTML לכפתורי פעולה
     */
    function renderActionButtons(entityType, rowId, options = {}) {
        const perms = get(entityType);
        let html = '<div class="tm-action-buttons">';

        if (perms.canView) {
            html += `
                <button class="tm-action-btn tm-action-view"
                        onclick="${options.viewHandler || `TablePermissions.handleView('${entityType}', '${rowId}')`}"
                        title="צפה">
                    <span>👁️</span>
                </button>
            `;
        }

        if (perms.canEdit) {
            html += `
                <button class="tm-action-btn tm-action-edit"
                        onclick="${options.editHandler || `TablePermissions.handleEdit('${entityType}', '${rowId}')`}"
                        title="ערוך">
                    <span>✏️</span>
                </button>
            `;
        }

        if (perms.canDelete) {
            html += `
                <button class="tm-action-btn tm-action-delete"
                        onclick="${options.deleteHandler || `TablePermissions.handleDelete('${entityType}', '${rowId}')`}"
                        title="מחק">
                    <span>🗑️</span>
                </button>
            `;
        }

        html += '</div>';
        return html;
    }

    /**
     * Handler לצפייה
     */
    function handleView(entityType, rowId) {
        const event = new CustomEvent('tableAction', {
            detail: { action: 'view', entityType, rowId }
        });
        window.dispatchEvent(event);
    }

    /**
     * Handler לעריכה
     */
    function handleEdit(entityType, rowId) {
        const event = new CustomEvent('tableAction', {
            detail: { action: 'edit', entityType, rowId }
        });
        window.dispatchEvent(event);
    }

    /**
     * Handler למחיקה
     */
    function handleDelete(entityType, rowId) {
        if (confirm('האם אתה בטוח שברצונך למחוק רשומה זו?')) {
            const event = new CustomEvent('tableAction', {
                detail: { action: 'delete', entityType, rowId }
            });
            window.dispatchEvent(event);
        }
    }

    /**
     * ניקוי cache
     */
    function clearCache(entityType = null) {
        if (entityType) {
            permissionsCache.delete(entityType);
        } else {
            permissionsCache.clear();
            isLoaded = false;
        }
    }

    /**
     * אתחול - טעינת כל ההרשאות
     */
    async function init() {
        try {
            await load();
            console.log('TablePermissions: Initialized');
            return true;
        } catch (error) {
            console.error('TablePermissions: Init error', error);
            return false;
        }
    }

    // Public API
    return {
        load,
        get,
        getAsync,
        can,
        isColumnVisible,
        isColumnEditable,
        filterColumns,
        getAllowedActions,
        createRowActions,
        renderActionButtons,
        handleView,
        handleEdit,
        handleDelete,
        clearCache,
        init,
        getDefaultPermissions
    };
})();

// Export
if (typeof window !== 'undefined') {
    window.TablePermissions = TablePermissions;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = TablePermissions;
}
