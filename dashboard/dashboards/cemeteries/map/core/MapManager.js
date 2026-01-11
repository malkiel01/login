/**
 * Map Manager - מנהל המפה המרכזי
 * מחבר את כל המודולים ומנהל את המפה
 */

import { EntityConfig, DEFAULT_MAP_SETTINGS } from '../config/EntityConfigV2.js';
import { MapAPI, EntityAPI } from '../api/MapAPI.js';
import { HistoryManager } from './HistoryManager.js';
import { BoundaryManager } from './BoundaryManager.js';
import { BackgroundManager } from './BackgroundManager.js';

export class MapManager {
    constructor(options = {}) {
        // הגדרות בסיס
        this.options = {
            entityType: options.entityType || 'cemetery',
            entityId: options.entityId || null,
            mode: options.mode || 'view', // 'view' או 'edit'
            canvasId: options.canvasId || 'mapCanvas',
            ...options
        };

        // קונפיגורציה
        this.entityConfig = options.entityConfig || new EntityConfig();

        // API
        this.mapAPI = options.mapAPI || new MapAPI();
        this.entityAPI = options.entityAPI || new EntityAPI();

        // State
        this.state = {
            entityType: this.options.entityType,
            entityId: this.options.entityId,
            mode: this.options.mode,
            isLoading: false,
            isEditMode: false,
            currentZoom: 1,
            entity: null,
            children: []
        };

        // Canvas
        this.canvas = null;

        // מנהלים
        this.history = null;
        this.boundary = null;
        this.background = null;

        // Event handlers
        this.eventHandlers = new Map();
    }

    /**
     * אתחול המפה
     */
    async init() {
        try {
            this.state.isLoading = true;
            this.trigger('loading:start');

            // יצירת Canvas
            this.createCanvas();

            // יצירת מנהלים
            this.initManagers();

            // טעינת נתונים
            await this.load();

            // הגדרת מצב
            if (this.state.mode === 'edit') {
                this.enableEditMode();
            }

            this.state.isLoading = false;
            this.trigger('init:complete');

            return true;
        } catch (error) {
            console.error('Error initializing map:', error);
            this.state.isLoading = false;
            this.trigger('init:error', { error });
            return false;
        }
    }

    /**
     * יצירת Canvas
     */
    createCanvas() {
        const canvasElement = document.getElementById(this.options.canvasId);

        if (!canvasElement) {
            throw new Error(`Canvas element with id '${this.options.canvasId}' not found`);
        }

        const container = canvasElement.parentElement;
        const containerRect = container.getBoundingClientRect();

        this.canvas = new fabric.Canvas(canvasElement, {
            width: containerRect.width,
            height: containerRect.height,
            backgroundColor: DEFAULT_MAP_SETTINGS.backgroundColor,
            selection: this.state.mode === 'edit',
            preserveObjectStacking: true,
            enableRetinaScaling: true
        });

        // Event listeners
        this.setupCanvasEvents();

        // Resize handler
        window.addEventListener('resize', () => this.handleResize());
    }

    /**
     * אתחול מנהלים
     */
    initManagers() {
        this.history = new HistoryManager(this.canvas);
        this.boundary = new BoundaryManager(this.canvas, this.mapAPI);
        this.background = new BackgroundManager(this.canvas, this.mapAPI);

        // חיבור אירועים
        this.canvas.on('state:saved', () => this.trigger('history:saved'));
        this.canvas.on('state:restored', () => this.trigger('history:restored'));
    }

    /**
     * טעינת נתוני המפה
     */
    async load() {
        try {
            const data = await this.mapAPI.loadMap(
                this.state.entityType,
                this.state.entityId,
                true // includeChildren
            );

            if (!data || !data.entity) {
                throw new Error('Entity not found');
            }

            this.state.entity = data.entity;
            this.state.children = data.children || [];

            // טעינת תמונת רקע
            if (data.entity.mapBackgroundImage) {
                await this.background.loadBackground(data.entity.mapBackgroundImage);
            }

            // טעינת גבול הורה (אם זו לא ישות שורש)
            const parentType = this.entityConfig.getParentType(this.state.entityType);
            if (parentType && data.entity[this.entityConfig.get(this.state.entityType).parentField]) {
                const parentId = data.entity[this.entityConfig.get(this.state.entityType).parentField];
                await this.boundary.loadParentBoundary(parentType, parentId);
            }

            // טעינת פוליגון הישות
            if (data.entity.mapPolygon) {
                this.loadEntityPolygon(data.entity.mapPolygon);
            }

            // טעינת ישויות בנות
            if (this.state.children.length > 0) {
                this.loadChildrenPolygons(this.state.children);
            }

            // שמירת מצב התחלתי
            this.history.save();

            this.trigger('data:loaded', { entity: data.entity, children: data.children });

            return data;
        } catch (error) {
            console.error('Error loading map data:', error);
            this.trigger('data:error', { error });
            throw error;
        }
    }

    /**
     * טעינת פוליגון הישות הנוכחית
     */
    loadEntityPolygon(polygonData) {
        if (!polygonData || !polygonData.points) return;

        const entityDef = this.entityConfig.get(this.state.entityType);

        const polygon = new fabric.Polygon(polygonData.points, {
            fill: polygonData.style?.fillColor || entityDef.color,
            fillOpacity: polygonData.style?.fillOpacity || entityDef.fillOpacity,
            stroke: polygonData.style?.strokeColor || entityDef.strokeColor,
            strokeWidth: polygonData.style?.strokeWidth || entityDef.strokeWidth,
            selectable: this.state.isEditMode,
            evented: this.state.isEditMode,
            objectCaching: false,
            entityType: this.state.entityType,
            entityId: this.state.entityId,
            isEntityPolygon: true
        });

        this.canvas.add(polygon);
    }

    /**
     * טעינת פוליגונים של ישויות בנות
     */
    loadChildrenPolygons(children) {
        const childType = this.entityConfig.get(this.state.entityType).childType;
        if (!childType) return;

        const childDef = this.entityConfig.get(childType);

        children.forEach(child => {
            if (!child.mapPolygon || !child.mapPolygon.points) return;

            const polygon = new fabric.Polygon(child.mapPolygon.points, {
                fill: child.mapPolygon.style?.fillColor || childDef.color,
                fillOpacity: child.mapPolygon.style?.fillOpacity || childDef.fillOpacity,
                stroke: child.mapPolygon.style?.strokeColor || childDef.strokeColor,
                strokeWidth: child.mapPolygon.style?.strokeWidth || childDef.strokeWidth,
                selectable: false,
                evented: true,
                objectCaching: false,
                entityType: childType,
                entityId: child.unicId,
                isChildPolygon: true,
                hoverCursor: 'pointer'
            });

            // אירוע click - drill down
            polygon.on('mousedown', () => {
                this.trigger('entity:click', { entityType: childType, entityId: child.unicId });
            });

            this.canvas.add(polygon);
        });
    }

    /**
     * שמירת המפה
     */
    async save() {
        try {
            const mapData = {
                polygon: this.getEntityPolygonData(),
                background: this.background.getBackgroundData(),
                settings: this.getMapSettings(),
                canvasData: this.canvas.toJSON()
            };

            const result = await this.mapAPI.saveMap(
                this.state.entityType,
                this.state.entityId,
                mapData
            );

            this.trigger('map:saved', { data: mapData, result });
            return result;
        } catch (error) {
            console.error('Error saving map:', error);
            this.trigger('map:save-error', { error });
            throw error;
        }
    }

    /**
     * קבלת נתוני פוליגון הישות
     */
    getEntityPolygonData() {
        const polygon = this.canvas.getObjects().find(obj => obj.isEntityPolygon);

        if (!polygon) return null;

        return {
            points: polygon.points.map(p => ({ x: p.x, y: p.y })),
            style: {
                fillColor: polygon.fill,
                fillOpacity: polygon.fillOpacity,
                strokeColor: polygon.stroke,
                strokeWidth: polygon.strokeWidth
            }
        };
    }

    /**
     * קבלת הגדרות המפה
     */
    getMapSettings() {
        return {
            canvasWidth: this.canvas.width,
            canvasHeight: this.canvas.height,
            zoom: this.state.currentZoom,
            ...DEFAULT_MAP_SETTINGS
        };
    }

    /**
     * הפעלת מצב עריכה
     */
    enableEditMode() {
        this.state.isEditMode = true;
        this.state.mode = 'edit';
        this.canvas.selection = true;

        // הפעלת עריכה לאובייקטים
        this.canvas.getObjects().forEach(obj => {
            if (obj.isEntityPolygon) {
                obj.set({ selectable: true, evented: true });
            }
        });

        this.canvas.renderAll();
        this.trigger('mode:edit');
    }

    /**
     * כיבוי מצב עריכה
     */
    disableEditMode() {
        this.state.isEditMode = false;
        this.state.mode = 'view';
        this.canvas.selection = false;

        this.canvas.getObjects().forEach(obj => {
            if (obj.isEntityPolygon) {
                obj.set({ selectable: false, evented: false });
            }
        });

        this.canvas.discardActiveObject();
        this.canvas.renderAll();
        this.trigger('mode:view');
    }

    /**
     * זום
     */
    zoom(delta) {
        const newZoom = Math.max(
            DEFAULT_MAP_SETTINGS.minZoom,
            Math.min(DEFAULT_MAP_SETTINGS.maxZoom, this.state.currentZoom + delta)
        );

        this.setZoom(newZoom);
    }

    /**
     * הגדרת זום
     */
    setZoom(zoom) {
        this.state.currentZoom = zoom;
        this.canvas.setZoom(zoom);
        this.boundary.updateMaskPosition();
        this.canvas.renderAll();
        this.trigger('zoom:changed', { zoom });
    }

    /**
     * התאמה ל-viewport
     */
    zoomToFit() {
        const objects = this.canvas.getObjects().filter(obj => obj.isEntityPolygon || obj.isChildPolygon);

        if (objects.length === 0) return;

        const group = new fabric.Group(objects, { originX: 'center', originY: 'center' });
        const bounds = group.getBoundingRect();

        const zoomX = this.canvas.width / bounds.width;
        const zoomY = this.canvas.height / bounds.height;
        const zoom = Math.min(zoomX, zoomY) * 0.9;

        this.setZoom(zoom);
        this.canvas.viewportCenterObject(group);

        group.destroy(); // פירוק הקבוצה
        this.canvas.renderAll();
    }

    /**
     * הגדרת event listeners ל-Canvas
     */
    setupCanvasEvents() {
        // Mouse wheel zoom
        this.canvas.on('mouse:wheel', (opt) => {
            const delta = opt.e.deltaY;
            const zoomDelta = delta > 0 ? -0.1 : 0.1;
            this.zoom(zoomDelta);
            opt.e.preventDefault();
            opt.e.stopPropagation();
        });

        // Object moving
        this.canvas.on('object:moving', (opt) => {
            if (this.boundary) {
                this.boundary.saveValidState(opt.target);
            }
        });

        // Object moved
        this.canvas.on('object:moved', (opt) => {
            if (this.boundary && !this.boundary.constrainObjectToBoundary(opt.target)) {
                this.boundary.restoreValidState(opt.target);
            }
        });
    }

    /**
     * טיפול ב-resize
     */
    handleResize() {
        const container = this.canvas.wrapperEl.parentElement;
        const rect = container.getBoundingClientRect();

        this.canvas.setDimensions({
            width: rect.width,
            height: rect.height
        });

        this.canvas.renderAll();
        this.trigger('canvas:resized', { width: rect.width, height: rect.height });
    }

    /**
     * רישום event handler
     */
    on(eventName, handler) {
        if (!this.eventHandlers.has(eventName)) {
            this.eventHandlers.set(eventName, []);
        }
        this.eventHandlers.get(eventName).push(handler);
    }

    /**
     * הסרת event handler
     */
    off(eventName, handler) {
        if (!this.eventHandlers.has(eventName)) return;

        const handlers = this.eventHandlers.get(eventName);
        const index = handlers.indexOf(handler);

        if (index > -1) {
            handlers.splice(index, 1);
        }
    }

    /**
     * הפעלת event
     */
    trigger(eventName, data = {}) {
        if (!this.eventHandlers.has(eventName)) return;

        this.eventHandlers.get(eventName).forEach(handler => {
            try {
                handler({ ...data, manager: this });
            } catch (error) {
                console.error(`Error in event handler for '${eventName}':`, error);
            }
        });
    }

    /**
     * קבלת מצב המפה
     */
    getState() {
        return { ...this.state };
    }

    /**
     * השמדת המנהל
     */
    destroy() {
        if (this.history) this.history.destroy();
        if (this.boundary) this.boundary.destroy();
        if (this.background) this.background.destroy();

        if (this.canvas) {
            this.canvas.dispose();
            this.canvas = null;
        }

        this.eventHandlers.clear();
    }
}
