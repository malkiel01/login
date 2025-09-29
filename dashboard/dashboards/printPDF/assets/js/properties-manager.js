/**
 * Properties Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/properties-manager.js
 */

class PropertiesManager {
    constructor(canvasManager) {
        this.canvasManager = canvasManager;
        this.canvas = canvasManager.canvas;
        this.currentObject = null;
        this.currentType = null;
        
        this.init();
    }

    init() {
        // Bind canvas selection events
        this.bindCanvasEvents();
        
        // Bind property input events
        this.bindPropertyInputs();
    }

    bindCanvasEvents() {
        // Object selection
        this.canvas.on('selection:created', (e) => {
            this.update(e.selected);
        });

        this.canvas.on('selection:updated', (e) => {
            this.update(e.selected);
        });

        this.canvas.on('selection:cleared', () => {
            this.clear();
        });

        // Object modification
        this.canvas.on('object:modified', (e) => {
            this.updateFromObject(e.target);
        });

        // Text editing
        this.canvas.on('text:changed', (e) => {
            this.updateFromObject(e.target);
        });
    }

    bindPropertyInputs() {
        // Text properties
        this.bindInput('fontFamily', 'change', (value) => {
            this.setProperty('fontFamily', value);
        });

        this.bindInput('fontSize', 'input', (value) => {
            this.setProperty('fontSize', parseInt(value));
        });

        this.bindInput('fontColor', 'change', (value) => {
            this.setProperty('fill', value);
        });

        this.bindInput('textBold', 'change', (value) => {
            this.setProperty('fontWeight', value ? 'bold' : 'normal');
        });

        this.bindInput('textItalic', 'change', (value) => {
            this.setProperty('fontStyle', value ? 'italic' : 'normal');
        });

        this.bindInput('textUnderline', 'change', (value) => {
            this.setProperty('underline', value);
        });

        this.bindInput('textAlign', 'change', (value) => {
            this.setProperty('textAlign', value);
        });

        this.bindInput('lineHeight', 'input', (value) => {
            this.setProperty('lineHeight', parseFloat(value));
        });

        // General properties
        this.bindInput('objectX', 'input', (value) => {
            this.setProperty('left', parseFloat(value));
        });

        this.bindInput('objectY', 'input', (value) => {
            this.setProperty('top', parseFloat(value));
        });

        this.bindInput('objectWidth', 'input', (value) => {
            if (this.currentObject) {
                const scaleX = parseFloat(value) / this.currentObject.width;
                this.setProperty('scaleX', scaleX);
            }
        });

        this.bindInput('objectHeight', 'input', (value) => {
            if (this.currentObject) {
                const scaleY = parseFloat(value) / this.currentObject.height;
                this.setProperty('scaleY', scaleY);
            }
        });

        this.bindInput('objectRotation', 'input', (value) => {
            this.setProperty('angle', parseFloat(value));
        });

        this.bindInput('objectOpacity', 'input', (value) => {
            this.setProperty('opacity', parseFloat(value) / 100);
        });
    }

    bindInput(id, event, handler) {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener(event, (e) => {
                const value = element.type === 'checkbox' ? element.checked : element.value;
                handler(value);
            });
        }
    }

    update(objects) {
        if (!objects || objects.length === 0) {
            this.clear();
            return;
        }

        // Handle single or multiple selection
        if (objects.length === 1) {
            this.currentObject = objects[0];
            this.showPropertiesForObject(objects[0]);
        } else {
            this.currentObject = this.canvas.getActiveObject();
            this.showPropertiesForGroup(objects);
        }
    }

    showPropertiesForObject(object) {
        const container = document.getElementById('propertiesContent');
        if (!container) return;

        this.currentType = object.type;

        let html = '';

        // Common properties
        html += this.getCommonPropertiesHTML(object);

        // Type-specific properties
        if (object.type === 'i-text' || object.type === 'text' || object.type === 'textbox') {
            html += this.getTextPropertiesHTML(object);
        } else if (object.type === 'image') {
            html += this.getImagePropertiesHTML(object);
        } else if (object.type === 'rect' || object.type === 'circle' || object.type === 'triangle') {
            html += this.getShapePropertiesHTML(object);
        }

        container.innerHTML = html;

        // Update values
        this.updatePropertyValues(object);

        // Re-bind events for new elements
        this.bindPropertyInputs();
    }

    getCommonPropertiesHTML(object) {
        return `
            <div class="property-group">
                <label class="property-label">מיקום וגודל</label>
                <div class="property-row">
                    <div class="property-group">
                        <label class="property-label">X</label>
                        <input type="number" id="objectX" class="property-input" step="1">
                    </div>
                    <div class="property-group">
                        <label class="property-label">Y</label>
                        <input type="number" id="objectY" class="property-input" step="1">
                    </div>
                </div>
                <div class="property-row">
                    <div class="property-group">
                        <label class="property-label">רוחב</label>
                        <input type="number" id="objectWidth" class="property-input" step="1">
                    </div>
                    <div class="property-group">
                        <label class="property-label">גובה</label>
                        <input type="number" id="objectHeight" class="property-input" step="1">
                    </div>
                </div>
            </div>
            <div class="property-group">
                <label class="property-label">סיבוב (מעלות)</label>
                <input type="range" id="objectRotation" class="property-input" min="0" max="360" step="1">
                <span id="rotationValue">0°</span>
            </div>
            <div class="property-group">
                <label class="property-label">שקיפות</label>
                <input type="range" id="objectOpacity" class="property-input" min="0" max="100" step="1">
                <span id="opacityValue">100%</span>
            </div>
        `;
    }

    getTextPropertiesHTML(object) {
        const fonts = this.getFontOptions();
        const isRTL = languageManager.isRTL();
        
        return `
            <div class="property-group">
                <label class="property-label">גופן</label>
                <select id="fontFamily" class="property-select">
                    ${fonts.map(font => `<option value="${font.value}">${font.name}</option>`).join('')}
                </select>
            </div>
            <div class="property-group">
                <label class="property-label">גודל גופן</label>
                <input type="number" id="fontSize" class="property-input" min="8" max="72" step="1">
            </div>
            <div class="property-group">
                <label class="property-label">צבע טקסט</label>
                <input type="color" id="fontColor" class="property-input">
            </div>
            <div class="property-group">
                <label class="property-label">סגנון</label>
                <div style="display: flex; gap: 10px;">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="textBold" style="margin-left: 5px;">
                        מודגש
                    </label>
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="textItalic" style="margin-left: 5px;">
                        נטוי
                    </label>
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="textUnderline" style="margin-left: 5px;">
                        קו תחתון
                    </label>
                </div>
            </div>
            <div class="property-group">
                <label class="property-label">יישור</label>
                <select id="textAlign" class="property-select">
                    <option value="right">ימין</option>
                    <option value="center">מרכז</option>
                    <option value="left">שמאל</option>
                    <option value="justify">מיושר</option>
                </select>
            </div>
            <div class="property-group">
                <label class="property-label">גובה שורה</label>
                <input type="number" id="lineHeight" class="property-input" min="0.5" max="3" step="0.1">
            </div>
        `;
    }

    getImagePropertiesHTML(object) {
        return `
            <div class="property-group">
                <label class="property-label">התאמת תמונה</label>
                <button class="btn btn-secondary" onclick="propertiesManager.fitImage('width')">
                    התאם לרוחב
                </button>
                <button class="btn btn-secondary" onclick="propertiesManager.fitImage('height')">
                    התאם לגובה
                </button>
                <button class="btn btn-secondary" onclick="propertiesManager.fitImage('both')">
                    התאם למסגרת
                </button>
            </div>
            <div class="property-group">
                <label class="property-label">אפקטים</label>
                <button class="btn btn-secondary" onclick="propertiesManager.applyFilter('grayscale')">
                    שחור לבן
                </button>
                <button class="btn btn-secondary" onclick="propertiesManager.applyFilter('sepia')">
                    ספיה
                </button>
                <button class="btn btn-secondary" onclick="propertiesManager.removeFilters()">
                    הסר אפקטים
                </button>
            </div>
        `;
    }

    getShapePropertiesHTML(object) {
        return `
            <div class="property-group">
                <label class="property-label">צבע מילוי</label>
                <input type="color" id="shapeFill" class="property-input" value="${object.fill || '#000000'}">
            </div>
            <div class="property-group">
                <label class="property-label">צבע קו</label>
                <input type="color" id="shapeStroke" class="property-input" value="${object.stroke || '#000000'}">
            </div>
            <div class="property-group">
                <label class="property-label">עובי קו</label>
                <input type="number" id="shapeStrokeWidth" class="property-input" min="0" max="20" step="1" value="${object.strokeWidth || 1}">
            </div>
        `;
    }

    updatePropertyValues(object) {
        // Common properties
        this.setInputValue('objectX', Math.round(object.left));
        this.setInputValue('objectY', Math.round(object.top));
        this.setInputValue('objectWidth', Math.round(object.width * object.scaleX));
        this.setInputValue('objectHeight', Math.round(object.height * object.scaleY));
        this.setInputValue('objectRotation', Math.round(object.angle || 0));
        this.setInputValue('objectOpacity', Math.round((object.opacity || 1) * 100));

        // Update display values
        this.updateDisplayValue('rotationValue', Math.round(object.angle || 0) + '°');
        this.updateDisplayValue('opacityValue', Math.round((object.opacity || 1) * 100) + '%');

        // Text properties
        if (object.type === 'i-text' || object.type === 'text' || object.type === 'textbox') {
            this.setInputValue('fontFamily', object.fontFamily);
            this.setInputValue('fontSize', object.fontSize);
            this.setInputValue('fontColor', object.fill);
            this.setInputValue('textBold', object.fontWeight === 'bold');
            this.setInputValue('textItalic', object.fontStyle === 'italic');
            this.setInputValue('textUnderline', object.underline);
            this.setInputValue('textAlign', object.textAlign);
            this.setInputValue('lineHeight', object.lineHeight || 1);
        }
    }

    setInputValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            if (element.type === 'checkbox') {
                element.checked = value;
            } else {
                element.value = value;
            }
        }
    }

    updateDisplayValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    setProperty(property, value) {
        if (!this.currentObject) return;

        this.currentObject.set(property, value);
        this.canvas.renderAll();
        
        // Fire modified event
        this.canvas.fire('object:modified', { target: this.currentObject });
    }

    updateFromObject(object) {
        if (object === this.currentObject) {
            this.updatePropertyValues(object);
        }
    }

    clear() {
        this.currentObject = null;
        this.currentType = null;
        
        const container = document.getElementById('propertiesContent');
        if (container) {
            container.innerHTML = `
                <div style="text-align: center; padding: 20px; color: #6b7280;">
                    <i class="fas fa-mouse-pointer" style="font-size: 32px; margin-bottom: 10px;"></i>
                    <p>בחר אובייקט לעריכת מאפיינים</p>
                </div>
            `;
        }
    }

    showPropertiesForGroup(objects) {
        const container = document.getElementById('propertiesContent');
        if (!container) return;

        container.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <p style="margin-bottom: 10px;">נבחרו ${objects.length} אובייקטים</p>
                <button class="btn btn-primary" onclick="propertiesManager.groupObjects()">
                    <i class="fas fa-object-group"></i>
                    קבץ אובייקטים
                </button>
                <button class="btn btn-secondary" onclick="propertiesManager.alignObjects('left')">
                    יישר לשמאל
                </button>
                <button class="btn btn-secondary" onclick="propertiesManager.alignObjects('center')">
                    מרכז
                </button>
                <button class="btn btn-secondary" onclick="propertiesManager.alignObjects('right')">
                    יישר לימין
                </button>
            </div>
        `;
    }

    getFontOptions() {
        const isRTL = languageManager.isRTL();
        
        if (isRTL) {
            return [
                { value: 'Rubik', name: 'רוביק' },
                { value: 'Heebo', name: 'היבו' },
                { value: 'Assistant', name: 'אסיסטנט' },
                { value: 'Arial', name: 'אריאל' },
                { value: 'David', name: 'דוד' },
                { value: 'Miriam', name: 'מרים' }
            ];
        } else {
            return [
                { value: 'Arial', name: 'Arial' },
                { value: 'Helvetica', name: 'Helvetica' },
                { value: 'Times New Roman', name: 'Times New Roman' },
                { value: 'Georgia', name: 'Georgia' },
                { value: 'Verdana', name: 'Verdana' },
                { value: 'Roboto', name: 'Roboto' }
            ];
        }
    }

    // Helper methods for buttons
    fitImage(type) {
        if (!this.currentObject || this.currentObject.type !== 'image') return;

        const canvasWidth = this.canvas.width;
        const canvasHeight = this.canvas.height;
        const imgWidth = this.currentObject.width;
        const imgHeight = this.currentObject.height;

        let scale = 1;

        switch (type) {
            case 'width':
                scale = canvasWidth / imgWidth;
                break;
            case 'height':
                scale = canvasHeight / imgHeight;
                break;
            case 'both':
                scale = Math.min(canvasWidth / imgWidth, canvasHeight / imgHeight);
                break;
        }

        this.currentObject.scale(scale * 0.9); // 90% to leave some margin
        this.currentObject.center();
        this.canvas.renderAll();
    }

    applyFilter(filterType) {
        if (!this.currentObject || this.currentObject.type !== 'image') return;

        let filter = null;

        switch (filterType) {
            case 'grayscale':
                filter = new fabric.Image.filters.Grayscale();
                break;
            case 'sepia':
                filter = new fabric.Image.filters.Sepia();
                break;
        }

        if (filter) {
            this.currentObject.filters = [filter];
            this.currentObject.applyFilters();
            this.canvas.renderAll();
        }
    }

    removeFilters() {
        if (!this.currentObject || this.currentObject.type !== 'image') return;

        this.currentObject.filters = [];
        this.currentObject.applyFilters();
        this.canvas.renderAll();
    }

    groupObjects() {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject || activeObject.type !== 'activeSelection') return;

        activeObject.toGroup();
        this.canvas.renderAll();
    }

    alignObjects(alignment) {
        const activeObject = this.canvas.getActiveObject();
        if (!activeObject) return;

        if (activeObject.type === 'activeSelection') {
            // Align multiple objects
            const objects = activeObject.getObjects();
            let targetX = 0;

            switch (alignment) {
                case 'left':
                    targetX = Math.min(...objects.map(obj => obj.left));
                    objects.forEach(obj => obj.set('left', targetX));
                    break;
                case 'center':
                    const center = this.canvas.width / 2;
                    objects.forEach(obj => obj.set('left', center - obj.width / 2));
                    break;
                case 'right':
                    targetX = Math.max(...objects.map(obj => obj.left + obj.width));
                    objects.forEach(obj => obj.set('left', targetX - obj.width));
                    break;
            }
        } else {
            // Align single object to canvas
            switch (alignment) {
                case 'left':
                    activeObject.set('left', 0);
                    break;
                case 'center':
                    activeObject.centerH();
                    break;
                case 'right':
                    activeObject.set('left', this.canvas.width - activeObject.width);
                    break;
            }
        }

        this.canvas.renderAll();
    }
}

// Export for global use
window.PropertiesManager = PropertiesManager;