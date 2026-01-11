// EntityConfig - Full version without problematic characters

export const CEMETERY_ENTITIES = {
    cemetery: {
        table: 'cemeteries',
        nameField: 'cemeteryNameHe',
        parentField: null,
        color: '#1976D2',
        fillOpacity: 0.3,
        strokeColor: '#0D47A1',
        strokeWidth: 2,
        icon: 'building',
        labelHe: 'Cemetery',
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
        icon: 'box',
        labelHe: 'Block',
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
        icon: 'square',
        labelHe: 'Plot',
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
        icon: 'line',
        labelHe: 'Row',
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
        icon: 'home',
        labelHe: 'Area Grave',
        labelEn: 'Area Grave',
        minZoom: 1.5,
        hasChildren: false,
        childType: null
    }
};

export const MAP_FIELDS = {
    polygon: 'mapPolygon',
    background: 'mapBackgroundImage',
    settings: 'mapSettings',
    canvasData: 'mapCanvasData'
};

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

export const HISTORY_SETTINGS = {
    maxStates: 30,
    saveDebounce: 500
};

export class EntityConfig {
    constructor(entities = CEMETERY_ENTITIES) {
        this.entities = entities;
        this.hierarchy = this.buildHierarchy();
    }

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

    getParentType(entityType) {
        const entity = this.entities[entityType];
        if (!entity || !entity.parentField) return null;
        return Object.keys(this.entities).find(
            type => this.entities[type].childType === entityType
        );
    }

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

    getParentTypes(entityType) {
        const parents = [];
        let current = this.getParentType(entityType);
        while (current) {
            parents.push(current);
            current = this.getParentType(current);
        }
        return parents;
    }

    get(entityType) {
        return this.hierarchy.get(entityType);
    }

    has(entityType) {
        return this.hierarchy.has(entityType);
    }

    getAllTypes() {
        return Array.from(this.hierarchy.keys());
    }

    getRootType() {
        return this.getAllTypes().find(type => {
            const entity = this.get(type);
            return !entity.parentField;
        });
    }

    getLeafTypes() {
        return this.getAllTypes().filter(type => {
            const entity = this.get(type);
            return !entity.hasChildren;
        });
    }

    isParentOf(parentType, childType) {
        const child = this.get(childType);
        if (!child) return false;
        const parentOfChild = this.getParentType(childType);
        if (parentOfChild === parentType) return true;
        return this.getParentTypes(childType).includes(parentType);
    }

    isChildOf(childType, parentType) {
        return this.isParentOf(parentType, childType);
    }

    getMaxDepth() {
        return Math.max(...this.getAllTypes().map(type => this.getLevel(type)));
    }
}

export const defaultEntityConfig = new EntityConfig(CEMETERY_ENTITIES);

console.log('EntityConfigSimple loaded!');
