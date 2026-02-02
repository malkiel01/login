/**
 * Central Entity Labels Configuration
 * Shared across all approval-related JavaScript
 * @version 1.0.0
 */

const EntityLabels = {
    // Entity type labels (must match PHP ENTITY_LABELS in config.php)
    entities: {
        purchases: 'רכישה',
        burials: 'קבורה',
        customers: 'לקוח',
        cemeteries: 'בית עלמין',
        blocks: 'גוש',
        plots: 'חלקה',
        graves: 'קבר',
        payments: 'תשלום',
        areaGraves: 'אחוזת קבר',
        countries: 'מדינה',
        cities: 'עיר',
        residencies: 'תושבות'
    },

    // Action labels (must match PHP ACTION_LABELS in config.php)
    actions: {
        create: 'יצירה',
        edit: 'עריכה',
        delete: 'מחיקה'
    },

    /**
     * Get entity label
     * @param {string} entityType
     * @returns {string}
     */
    getEntity(entityType) {
        return this.entities[entityType] || entityType;
    },

    /**
     * Get action label
     * @param {string} action
     * @returns {string}
     */
    getAction(action) {
        return this.actions[action] || action;
    }
};

// Make globally available
window.EntityLabels = EntityLabels;
