/**
 * Layers Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/layers-manager.js
 */

class LayersManager {
    constructor(canvasManager) {
        this.canvasManager = canvasManager;
        this.canvas = canvasManager.canvas;
        this.layers = [];
        this.currentLayerId = null;
        
        // Initialize
        this.init();
    }

    init() {
        // Create default layers
        this.createDefaultLayers();
        
        // Bind events
        this.bindEvents();
        
        // Update UI
        this.updateLayersPanel();
    }

    createDefaultLayers() {
        const defaultLayers = PDFEditorConfig.layers.defaultLayers;
        
        defaultLayers.forEach((layerConfig, index) => {
            this.addLayer({
                id: `layer_${Date.now()}_${index}`,
                name: layerConfig.name,
                type: layerConfig.type,
                visible: layerConfig.visible,
                locked: layerConfig.locked,
                opacity: 1,
                objects: [],
                order: index
            });
        });
        
        // Set first layer as active
        if (this.layers.length > 0) {
            this.currentLayerId = this.layers[0].id;
        }
    }

    addLayer(options = {}) {
        const layer = {
            id: options.id || `layer_${Date.now()}`,
            name: options.name || `${t('layers.newLayer')} ${this.layers.length + 1}`,
            type: options.type || 'custom',
            visible: options.visible !== undefined ? options.visible : true,
            locked: options.locked !== undefined ? options.locked : false,
            opacity: options.opacity !== undefined ? options.opacity : 1,
            objects: options.objects || [],
            order: options.order !== undefined ? options.order : this.layers.length
        };
        
        this.layers.push(layer);
        this.sortLayers();
        this.updateLayersPanel();
        
        return layer;
    }

    removeLayer(layerId) {
        // Don't remove if it's the only layer
        if (this.layers.length <= 1) {
            this.showNotification(t('layers.cannotDeleteLastLayer'));
            return;
        }
        
        const layerIndex = this.layers.findIndex(l => l.id === layerId);
        if (layerIndex === -1) return;
        
        const layer = this.layers[layerIndex];
        
        // Remove all objects in this layer from canvas
        layer.objects.forEach(objId => {
            const obj = this.canvas.getObjects().find(o => o.id === objId);
            if (obj) {
                this.canvas.remove(obj);
            }
        });
        
        // Remove layer
        this.layers.splice(layerIndex, 1);
        
        // If current layer was removed, select another
        if (this.currentLayerId === layerId) {
            this.currentLayerId = this.layers[0].id;
        }
        
        this.canvas.renderAll();
        this.updateLayersPanel();
    }

    duplicateLayer(layerId) {
        const layer = this.getLayer(layerId);
        if (!layer) return;
        
        const newLayer = this.addLayer({
            name: `${layer.name} ${t('layers.copy')}`,
            type: layer.type,
            visible: layer.visible,
            locked: false,
            opacity: layer.opacity
        });
        
        // Duplicate objects in the layer
        layer.objects.forEach(objId => {
            const obj = this.canvas.getObjects().find(o => o.id === objId);
            if (obj) {
                obj.clone((cloned) => {
                    cloned.set({
                        id: `obj_${Date.now()}_${Math.random()}`,
                        left: cloned.left + 10,
                        top: cloned.top + 10
                    });
                    
                    this.canvas.add(cloned);
                    this.assignObjectToLayer(cloned, newLayer.id);
                });
            }
        });
        
        this.canvas.renderAll();
    }

    renameLayer(layerId, newName) {
        const layer = this.getLayer(layerId);
        if (!layer) return;
        
        layer.name = newName;
        this.updateLayersPanel();
    }

    toggleLayerVisibility(layerId) {
        const layer = this.getLayer(layerId);
        if (!layer) return;
        
        layer.visible = !layer.visible;
        
        // Update object visibility
        layer.objects.forEach(objId => {
            const obj = this.canvas.getObjects().find(o => o.id === objId);
            if (obj) {
                obj.set('visible', layer.visible);
            }
        });
        
        this.canvas.renderAll();
        this.updateLayersPanel();
    }

    toggleLayerLock(layerId) {
        const layer = this.getLayer(layerId);
        if (!layer) return;
        
        layer.locked = !layer.locked;
        
        // Update object selectability
        layer.objects.forEach(objId => {
            const obj = this.canvas.getObjects().find(o => o.id === objId);
            if (obj) {
                obj.set({
                    selectable: !layer.locked,
                    evented: !layer.locked,
                    lockMovementX: layer.locked,
                    lockMovementY: layer.locked,
                    lockRotation: layer.locked,
                    lockScalingX: layer.locked,
                    lockScalingY: layer.locked
                });
            }
        });
        
        this.canvas.renderAll();
        this.updateLayersPanel();
    }

    setLayerOpacity(layerId, opacity) {
        const layer = this.getLayer(layerId);
        if (!layer) return;
        
        layer.opacity = opacity;
        
        // Update object opacity
        layer.objects.forEach(objId => {
            const obj = this.canvas.getObjects().find(o => o.id === objId);
            if (obj) {
                obj.set('opacity', opacity);
            }
        });
        
        this.canvas.renderAll();
    }

    moveLayerUp(layerId) {
        const layerIndex = this.layers.findIndex(l => l.id === layerId);
        if (layerIndex === -1 || layerIndex === this.layers.length - 1) return;
        
        // Swap with layer above
        [this.layers[layerIndex], this.layers[layerIndex + 1]] = 
        [this.layers[layerIndex + 1], this.layers[layerIndex]];
        
        this.reorderCanvasObjects();
        this.updateLayersPanel();
    }

    moveLayerDown(layerId) {
        const layerIndex = this.layers.findIndex(l => l.id === layerId);
        if (layerIndex === -1 || layerIndex === 0) return;
        
        // Swap with layer below
        [this.layers[layerIndex], this.layers[layerIndex - 1]] = 
        [this.layers[layerIndex - 1], this.layers[layerIndex]];
        
        this.reorderCanvasObjects();
        this.updateLayersPanel();
    }

    setCurrentLayer(layerId) {
        const layer = this.getLayer(layerId);
        if (!layer || layer.locked) return;
        
        this.currentLayerId = layerId;
        this.updateLayersPanel();
    }

    assignObjectToLayer(object, layerId = null) {
        // Use current layer if not specified
        layerId = layerId || this.currentLayerId;
        
        const layer = this.getLayer(layerId);
        if (!layer) return;
        
        // Ensure object has an ID
        if (!object.id) {
            object.id = `obj_${Date.now()}_${Math.random()}`;
        }
        
        // Remove from other layers
        this.layers.forEach(l => {
            const index = l.objects.indexOf(object.id);
            if (index > -1) {
                l.objects.splice(index, 1);
            }
        });
        
        // Add to new layer
        layer.objects.push(object.id);
        
        // Apply layer properties
        if (layer.locked) {
            object.set({
                selectable: false,
                evented: false
            });
        }
        
        if (!layer.visible) {
            object.set('visible', false);
        }
        
        object.set('opacity', layer.opacity);
    }

    getObjectLayer(objectId) {
        return this.layers.find(layer => layer.objects.includes(objectId));
    }

    getLayer(layerId) {
        return this.layers.find(layer => layer.id === layerId);
    }

    getCurrentLayer() {
        return this.getLayer(this.currentLayerId);
    }

    sortLayers() {
        this.layers.sort((a, b) => a.order - b.order);
    }

    reorderCanvasObjects() {
        // Reorder objects on canvas based on layer order
        const orderedObjects = [];
        
        this.layers.forEach(layer => {
            layer.objects.forEach(objId => {
                const obj = this.canvas.getObjects().find(o => o.id === objId);
                if (obj) {
                    orderedObjects.push(obj);
                }
            });
        });
        
        // Clear and re-add objects in correct order
        orderedObjects.forEach(obj => {
            this.canvas.bringToFront(obj);
        });
        
        this.canvas.renderAll();
    }

    bindEvents() {
        const self = this;
        
        // When object is added to canvas
        this.canvas.on('object:added', function(e) {
            if (!e.target.id) {
                e.target.id = `obj_${Date.now()}_${Math.random()}`;
            }
            
            // Check if object is already in a layer
            const existingLayer = self.getObjectLayer(e.target.id);
            if (!existingLayer) {
                self.assignObjectToLayer(e.target);
            }
        });
        
        // When object is removed from canvas
        this.canvas.on('object:removed', function(e) {
            // Remove from all layers
            self.layers.forEach(layer => {
                const index = layer.objects.indexOf(e.target.id);
                if (index > -1) {
                    layer.objects.splice(index, 1);
                }
            });
        });
        
        // Layer panel button events
        document.getElementById('btnAddLayer')?.addEventListener('click', () => {
            this.addLayer();
        });
        
        document.getElementById('btnDeleteLayer')?.addEventListener('click', () => {
            if (this.currentLayerId) {
                if (confirm(t('dialogs.confirmDelete'))) {
                    this.removeLayer(this.currentLayerId);
                }
            }
        });
    }

    updateLayersPanel() {
        const layersList = document.getElementById('layersList');
        if (!layersList) return;
        
        // Clear current list
        layersList.innerHTML = '';
        
        // Add layers in reverse order (top layer first)
        [...this.layers].reverse().forEach(layer => {
            const layerItem = document.createElement('div');
            layerItem.className = 'layer-item';
            if (layer.id === this.currentLayerId) {
                layerItem.classList.add('active');
            }
            
            layerItem.innerHTML = `
                <button class="layer-visibility" data-layer-id="${layer.id}" title="${t('layers.showHide')}">
                    <i class="fas fa-${layer.visible ? 'eye' : 'eye-slash'}"></i>
                </button>
                <span class="layer-name" data-layer-id="${layer.id}">${layer.name}</span>
                <button class="layer-lock" data-layer-id="${layer.id}" title="${layer.locked ? t('layers.unlock') : t('layers.lock')}">
                    <i class="fas fa-${layer.locked ? 'lock' : 'lock-open'}"></i>
                </button>
            `;
            
            // Add event listeners
            layerItem.addEventListener('click', (e) => {
                if (!e.target.closest('button')) {
                    this.setCurrentLayer(layer.id);
                }
            });
            
            // Visibility toggle
            const visBtn = layerItem.querySelector('.layer-visibility');
            visBtn.addEventListener('click', () => {
                this.toggleLayerVisibility(layer.id);
            });
            
            // Lock toggle
            const lockBtn = layerItem.querySelector('.layer-lock');
            lockBtn.addEventListener('click', () => {
                this.toggleLayerLock(layer.id);
            });
            
            // Double-click to rename
            const nameSpan = layerItem.querySelector('.layer-name');
            nameSpan.addEventListener('dblclick', () => {
                const newName = prompt(t('layers.renameLayer'), layer.name);
                if (newName && newName.trim()) {
                    this.renameLayer(layer.id, newName.trim());
                }
            });
            
            layersList.appendChild(layerItem);
        });
    }

    exportLayers() {
        return {
            layers: this.layers,
            currentLayerId: this.currentLayerId
        };
    }

    importLayers(data) {
        if (data && data.layers) {
            this.layers = data.layers;
            this.currentLayerId = data.currentLayerId || this.layers[0]?.id;
            this.updateLayersPanel();
        }
    }

    showNotification(message) {
        if (window.notificationManager) {
            window.notificationManager.show(message);
        } else {
            alert(message);
        }
    }
}

// Export for global use
window.LayersManager = LayersManager;