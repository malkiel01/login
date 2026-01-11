// EntityConfig - Minimal version
console.log('EntityConfigSimple: Starting to parse...');

export const CEMETERY_ENTITIES = {
    cemetery: {
        table: 'cemeteries',
        nameField: 'cemeteryNameHe',
        parentField: null,
        color: '#1976D2',
        hasChildren: true,
        childType: 'block'
    },
    block: {
        table: 'blocks',
        nameField: 'blockNameHe',
        parentField: 'cemeteryId',
        color: '#388E3C',
        hasChildren: true,
        childType: 'plot'
    },
    plot: {
        table: 'plots',
        nameField: 'plotNameHe',
        parentField: 'blockId',
        color: '#F57C00',
        hasChildren: true,
        childType: 'row'
    },
    row: {
        table: 'rows',
        nameField: 'lineNameHe',
        parentField: 'plotId',
        color: '#7B1FA2',
        hasChildren: true,
        childType: 'areaGrave'
    },
    areaGrave: {
        table: 'areaGraves',
        nameField: 'areaGraveNameHe',
        parentField: 'lineId',
        color: '#C2185B',
        hasChildren: false,
        childType: null
    }
};

console.log('EntityConfigSimple: CEMETERY_ENTITIES defined');

export const DEFAULT_MAP_SETTINGS = {
    canvasWidth: 2000,
    canvasHeight: 1500,
    initialZoom: 1,
    minZoom: 0.1,
    maxZoom: 5
};

console.log('EntityConfigSimple: DEFAULT_MAP_SETTINGS defined');

export class EntityConfig {
    constructor(entities = CEMETERY_ENTITIES) {
        console.log('EntityConfig: constructor called');
        this.entities = entities;
        this._hierarchy = null;
    }

    get hierarchy() {
        if (!this._hierarchy) {
            this._hierarchy = this.buildHierarchy();
        }
        return this._hierarchy;
    }

    buildHierarchy() {
        console.log('EntityConfig: buildHierarchy called');
        const hierarchy = new Map();
        for (const [type, config] of Object.entries(this.entities)) {
            hierarchy.set(type, { ...config, type });
        }
        return hierarchy;
    }

    getParentType(entityType) {
        const entity = this.entities[entityType];
        if (!entity || !entity.parentField) return null;
        for (const [type, config] of Object.entries(this.entities)) {
            if (config.childType === entityType) return type;
        }
        return null;
    }

    getChildrenTypes(entityType) {
        const entity = this.entities[entityType];
        if (!entity || !entity.hasChildren) return [];
        const children = [];
        let current = entity.childType;
        while (current && this.entities[current]) {
            children.push(current);
            current = this.entities[current].childType;
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
        return this.hierarchy.get(entityType) || this.entities[entityType];
    }

    has(entityType) {
        return entityType in this.entities;
    }

    getAllTypes() {
        return Object.keys(this.entities);
    }

    getRootType() {
        return Object.keys(this.entities).find(type => !this.entities[type].parentField);
    }

    getLeafTypes() {
        return Object.keys(this.entities).filter(type => !this.entities[type].hasChildren);
    }

    isParentOf(parentType, childType) {
        return this.getParentTypes(childType).includes(parentType);
    }

    isChildOf(childType, parentType) {
        return this.isParentOf(parentType, childType);
    }

    getMaxDepth() {
        return Object.keys(this.entities).length - 1;
    }
}

console.log('EntityConfigSimple: EntityConfig class defined');

export const defaultEntityConfig = new EntityConfig(CEMETERY_ENTITIES);

console.log('EntityConfigSimple: Module fully loaded!');
