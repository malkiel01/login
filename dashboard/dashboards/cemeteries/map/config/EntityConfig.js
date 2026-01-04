/**
 * Entity Configuration - הגדרות ישויות גנריות
 * מאפשר הגדרה של כל היררכיית ישויות באופן דינמי
 */

/**
 * הגדרת ישות בודדת
 * @typedef {Object} EntityDefinition
 * @property {string} table - שם הטבלה במסד הנתונים
 * @property {string} nameField - שדה השם בטבלה
 * @property {string|null} parentField - שדה המפתח להורה (null לרמה העליונה)
 * @property {string} color - צבע הישות במפה (hex)
 * @property {number} fillOpacity - שקיפות המילוי (0-1)
 * @property {string} strokeColor - צבע קו המתאר
 * @property {number} strokeWidth - עובי קו המתאר
 * @property {string} icon - אייקון לתצוגה
 * @property {string} labelHe - תווית בעברית
 * @property {string} labelEn - תווית באנגלית
 * @property {number} minZoom - רמת זום מינימלית להצגה
 * @property {boolean} hasChildren - האם לישות יש ישויות בנות
 * @property {string|null} childType - סוג הישות הבת
 */

/**
 * הגדרות ישויות בתי עלמין
 * ניתן להשתמש באותה מבנה לכל היררכיית ישויות אחרת
 */
export const CEMETERY_ENTITIES = {
    cemetery: {
        table: 'cemeteries',
        nameField: 'cemeteryNameHe',
        parentField: null,
        color: '#1976D2',
        fillOpacity: 0.3,
        strokeColor: '#0D47A1',
        strokeWidth: 2,
        icon: '🏛️',
        labelHe: 'בית עלמין',
        labelEn: 'Cemetery',
        minZoom: 0,
        hasChildren: true,
        childType: 'block'
    },

    block: {
        table: 'blocks',
        nameField: 'blockNameHe',
        parentField: 'cemeteryId',
        color: '#388E3C',
        fillOpacity: 0.3,
        strokeColor: '#1B5E20',
        strokeWidth: 2,
        icon: '📦',
        labelHe: 'גוש',
        labelEn: 'Block',
        minZoom: 0.3,
        hasChildren: true,
        childType: 'plot'
    },

    plot: {
        table: 'plots',
        nameField: 'plotNameHe',
        parentField: 'blockId',
        color: '#F57C00',
        fillOpacity: 0.3,
        strokeColor: '#E65100',
        strokeWidth: 2,
        icon: '📐',
        labelHe: 'חלקה',
        labelEn: 'Plot',
        minZoom: 0.6,
        hasChildren: true,
        childType: 'row'
    },

    row: {
        table: 'rows',
        nameField: 'lineNameHe',
        parentField: 'plotId',
        color: '#7B1FA2',
        fillOpacity: 0.3,
        strokeColor: '#4A148C',
        strokeWidth: 2,
        icon: '➖',
        labelHe: 'שורה',
        labelEn: 'Row',
        minZoom: 1.2,
        hasChildren: true,
        childType: 'areaGrave'
    },

    areaGrave: {
        table: 'areaGraves',
        nameField: 'areaGraveNameHe',
        parentField: 'lineId',
        color: '#C2185B',
        fillOpacity: 0.3,
        strokeColor: '#880E4F',
        strokeWidth: 2,
        icon: '🏘️',
        labelHe: 'אחוזת קבר',
        labelEn: 'Area Grave',
        minZoom: 1.5,
        hasChildren: false,
        childType: null
    }
};

/**
 * שדות JSON במסד נתונים
 */
export const MAP_FIELDS = {
    polygon: 'mapPolygon',           // נתוני הפוליגון (points + style)
    background: 'mapBackgroundImage', // תמונת רקע
    settings: 'mapSettings',          // הגדרות כלליות
    canvasData: 'mapCanvasData'       // נתוני Canvas מלאים (Fabric.js)
};

/**
 * הגדרות ברירת מחדל למפה
 */
export const DEFAULT_MAP_SETTINGS = {
    canvasWidth: 2000,
    canvasHeight: 1500,
    initialZoom: 1,
    minZoom: 0.1,
    maxZoom: 5,
    gridEnabled: false,
    gridSize: 50,
    gridColor: '#e0e0e0',
    backgroundColor: '#f8f9fa'
};

/**
 * הגדרות היסטוריה
 */
export const HISTORY_SETTINGS = {
    maxStates: 30,           // מספר מקסימלי של מצבים בהיסטוריה
    saveDebounce: 500        // זמן המתנה (ms) לפני שמירת מצב חדש
};

/**
 * EntityConfig Class - מחלקה לניהול קונפיגורציית ישויות
 */
export class EntityConfig {
    constructor(entities = CEMETERY_ENTITIES) {
        this.entities = entities;
        this.hierarchy = this.buildHierarchy();
    }

    /**
     * בניית מפת היררכיה
     */
    buildHierarchy() {
        const hierarchy = new Map();

        Object.entries(this.entities).forEach(([type, config]) => {
            hierarchy.set(type, {
                ...config,
                type: type,
                level: this.getLevel(type),
                children: this.getChildrenTypes(type),
                parents: this.getParentTypes(type)
            });
        });

        return hierarchy;
    }

    /**
     * קבלת רמה היררכית של ישות
     */
    getLevel(entityType) {
        let level = 0;
        let current = this.entities[entityType];

        while (current && current.parentField) {
            level++;
            const parentType = this.getParentType(Object.keys(this.entities).find(
                type => this.entities[type].childType === entityType
            ));
            current = this.entities[parentType];
        }

        return level;
    }

    /**
     * קבלת סוג ישות הורה
     */
    getParentType(entityType) {
        const entity = this.entities[entityType];
        if (!entity || !entity.parentField) return null;

        return Object.keys(this.entities).find(
            type => this.entities[type].childType === entityType
        );
    }

    /**
     * קבלת כל סוגי הישויות הבנות
     */
    getChildrenTypes(entityType) {
        const entity = this.entities[entityType];
        if (!entity || !entity.hasChildren) return [];

        const children = [];
        let current = entity.childType;

        while (current) {
            children.push(current);
            current = this.entities[current]?.childType;
        }

        return children;
    }

    /**
     * קבלת כל סוגי הישויות האבות
     */
    getParentTypes(entityType) {
        const parents = [];
        let current = this.getParentType(entityType);

        while (current) {
            parents.push(current);
            current = this.getParentType(current);
        }

        return parents;
    }

    /**
     * קבלת הגדרת ישות
     */
    get(entityType) {
        return this.hierarchy.get(entityType);
    }

    /**
     * בדיקה אם ישות קיימת
     */
    has(entityType) {
        return this.hierarchy.has(entityType);
    }

    /**
     * קבלת כל סוגי הישויות
     */
    getAllTypes() {
        return Array.from(this.hierarchy.keys());
    }

    /**
     * קבלת ישות השורש (ללא הורה)
     */
    getRootType() {
        return this.getAllTypes().find(type => {
            const entity = this.get(type);
            return !entity.parentField;
        });
    }

    /**
     * קבלת ישויות עלה (ללא ילדים)
     */
    getLeafTypes() {
        return this.getAllTypes().filter(type => {
            const entity = this.get(type);
            return !entity.hasChildren;
        });
    }

    /**
     * בדיקה אם ישות היא הורה של ישות אחרת
     */
    isParentOf(parentType, childType) {
        const child = this.get(childType);
        if (!child) return false;

        const parentOfChild = this.getParentType(childType);
        if (parentOfChild === parentType) return true;

        return this.getParentTypes(childType).includes(parentType);
    }

    /**
     * בדיקה אם ישות היא ילד של ישות אחרת
     */
    isChildOf(childType, parentType) {
        return this.isParentOf(parentType, childType);
    }

    /**
     * קבלת עומק ההיררכיה
     */
    getMaxDepth() {
        return Math.max(...this.getAllTypes().map(type => this.getLevel(type)));
    }
}

/**
 * אינסטנס ברירת מחדל
 */
export const defaultEntityConfig = new EntityConfig(CEMETERY_ENTITIES);
