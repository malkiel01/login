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
console.log('EntityConfigSimple loaded!');
