// Simplified EntityConfig for testing
export const CEMETERY_ENTITIES = {
    cemetery: {
        table: 'cemeteries',
        nameField: 'cemeteryNameHe',
        parentField: null,
        color: '#1976D2'
    }
};

export class EntityConfig {
    constructor(entities = CEMETERY_ENTITIES) {
        this.entities = entities;
    }

    get(entityType) {
        return this.entities[entityType];
    }
}

export const defaultEntityConfig = new EntityConfig(CEMETERY_ENTITIES);

// Default map settings
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

console.log('EntityConfigSimple loaded!');
