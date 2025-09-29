/**
 * Templates Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/templates-manager.js
 */

class TemplatesManager {
    constructor(canvasManager, apiConnector) {
        this.canvasManager = canvasManager;
        this.apiConnector = apiConnector;
        this.templates = [];
        this.categories = PDFEditorConfig.templates.categories;
        this.currentCategory = 'all';
        
        this.init();
    }

    init() {
        // Load default templates from config
        this.templates = PDFEditorConfig.templates.defaultTemplates || [];
        
        // Bind events
        this.bindEvents();
        
        // Load templates from server
        this.loadTemplates();
    }

    bindEvents() {
        // Category buttons
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const category = btn.getAttribute('data-category');
                this.filterByCategory(category);
                
                // Update active state
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    async loadTemplates() {
        try {
            const response = await this.apiConnector.getTemplates();
            if (response.success) {
                this.templates = [...this.templates, ...response.data.templates];
                this.displayTemplates();
            }
        } catch (error) {
            console.error('Failed to load templates:', error);
            // Display default templates anyway
            this.displayTemplates();
        }
    }

    displayTemplates() {
        const grid = document.getElementById('templatesGrid');
        if (!grid) return;
        
        grid.innerHTML = '';
        
        const filteredTemplates = this.currentCategory === 'all' 
            ? this.templates 
            : this.templates.filter(t => t.category === this.currentCategory);
        
        if (filteredTemplates.length === 0) {
            grid.innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6b7280;">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px;"></i>
                    <p>אין תבניות בקטגוריה זו</p>
                </div>
            `;
            return;
        }
        
        filteredTemplates.forEach(template => {
            const item = document.createElement('div');
            item.className = 'template-item';
            item.innerHTML = `
                <div class="template-preview">
                    ${template.thumbnail ? 
                        `<img src="${template.thumbnail}" alt="${template.name}">` :
                        this.getTemplateIcon(template.category)
                    }
                </div>
                <div class="template-name">${template.name}</div>
            `;
            
            item.addEventListener('click', () => {
                this.useTemplate(template);
            });
            
            grid.appendChild(item);
        });
    }

    getTemplateIcon(category) {
        const icons = {
            'business': '<i class="fas fa-briefcase"></i>',
            'certificates': '<i class="fas fa-certificate"></i>',
            'presentations': '<i class="fas fa-presentation-screen"></i>',
            'receipts': '<i class="fas fa-receipt"></i>',
            'invoices': '<i class="fas fa-file-invoice"></i>',
            'letterhead': '<i class="fas fa-envelope"></i>',
            'cards': '<i class="fas fa-id-card"></i>',
            'custom': '<i class="fas fa-file"></i>'
        };
        
        return icons[category] || '<i class="fas fa-file-alt"></i>';
    }

    filterByCategory(category) {
        this.currentCategory = category;
        this.displayTemplates();
    }

    async useTemplate(template) {
        try {
            // Show loading
            this.showLoading();
            
            let templateData = null;
            
            if (template.data) {
                // Template data is already loaded
                templateData = template.data;
            } else if (template.id) {
                // Need to load template from server
                const response = await this.apiConnector.loadTemplate(template.id);
                if (response.success) {
                    templateData = response.data.template;
                }
            }
            
            if (templateData) {
                // Apply template to canvas
                this.applyTemplate(templateData);
                
                // Close modal
                this.closeTemplatesModal();
                
                // Show canvas
                if (window.app) {
                    window.app.showCanvas();
                }
            }
            
        } catch (error) {
            console.error('Failed to use template:', error);
            this.showError('שגיאה בטעינת התבנית');
        } finally {
            this.hideLoading();
        }
    }

    applyTemplate(templateData) {
        // Clear canvas
        this.canvasManager.clear();
        
        // Apply template based on type
        if (typeof templateData === 'string') {
            // JSON string
            this.canvasManager.loadFromJSON(JSON.parse(templateData));
        } else if (templateData.canvas) {
            // Canvas data
            this.canvasManager.loadFromJSON(templateData.canvas);
        } else if (templateData.elements) {
            // Elements array
            this.applyElements(templateData.elements);
        } else {
            // Direct canvas JSON
            this.canvasManager.loadFromJSON(templateData);
        }
    }

    applyElements(elements) {
        elements.forEach(element => {
            switch (element.type) {
                case 'text':
                    this.canvasManager.addText(element.value || element.text, {
                        left: element.left || element.x || 100,
                        top: element.top || element.y || 100,
                        fontSize: element.fontSize || 20,
                        fontFamily: element.fontFamily || 'Arial',
                        fill: element.fill || element.color || '#000000'
                    });
                    break;
                    
                case 'image':
                    if (element.src || element.url) {
                        this.canvasManager.addImage(element.src || element.url, {
                            left: element.left || element.x || 100,
                            top: element.top || element.y || 100,
                            scaleX: element.scaleX || 1,
                            scaleY: element.scaleY || 1
                        });
                    }
                    break;
                    
                case 'rect':
                case 'rectangle':
                    const rect = new fabric.Rect({
                        left: element.left || element.x || 100,
                        top: element.top || element.y || 100,
                        width: element.width || 100,
                        height: element.height || 100,
                        fill: element.fill || 'transparent',
                        stroke: element.stroke || '#000000',
                        strokeWidth: element.strokeWidth || 1
                    });
                    this.canvasManager.canvas.add(rect);
                    break;
            }
        });
        
        this.canvasManager.canvas.renderAll();
    }

    async saveAsTemplate() {
        const name = prompt('שם התבנית:');
        if (!name) return;
        
        const category = prompt('קטגוריה (business/certificates/custom):', 'custom');
        
        try {
            // Get canvas data
            const canvasData = this.canvasManager.getCanvasJSON();
            
            // Create template object
            const template = {
                name: name,
                category: category || 'custom',
                data: canvasData,
                thumbnail: this.generateThumbnail(),
                created: new Date()
            };
            
            // Save to server
            const response = await this.apiConnector.saveTemplate(template);
            if (response.success) {
                // Add to local templates
                this.templates.push({
                    ...template,
                    id: response.data.templateId
                });
                
                // Refresh display
                this.displayTemplates();
                
                this.showSuccess('התבנית נשמרה בהצלחה');
            }
        } catch (error) {
            console.error('Failed to save template:', error);
            this.showError('שגיאה בשמירת התבנית');
        }
    }

    generateThumbnail() {
        // Generate thumbnail from canvas
        return this.canvasManager.canvas.toDataURL({
            format: 'png',
            quality: 0.3,
            multiplier: 0.25 // Scale down to 25%
        });
    }

    async deleteTemplate(templateId) {
        if (!confirm('האם למחוק את התבנית?')) return;
        
        try {
            const response = await this.apiConnector.deleteTemplate(templateId);
            if (response.success) {
                // Remove from local templates
                this.templates = this.templates.filter(t => t.id !== templateId);
                
                // Refresh display
                this.displayTemplates();
                
                this.showSuccess('התבנית נמחקה');
            }
        } catch (error) {
            console.error('Failed to delete template:', error);
            this.showError('שגיאה במחיקת התבנית');
        }
    }

    closeTemplatesModal() {
        const modal = document.getElementById('templatesModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Helper methods
    showLoading() {
        // Could implement loading indicator
        console.log('Loading template...');
    }

    hideLoading() {
        // Hide loading indicator
        console.log('Loading complete');
    }

    showSuccess(message) {
        // Could implement notification system
        console.log('Success:', message);
    }

    showError(message) {
        alert(message);
    }

    // Create default templates
    getDefaultTemplates() {
        return [
            {
                id: 'blank',
                name: 'דף ריק',
                category: 'business',
                data: {
                    version: '5.3.0',
                    objects: []
                }
            },
            {
                id: 'invoice',
                name: 'חשבונית',
                category: 'invoices',
                data: {
                    version: '5.3.0',
                    objects: [
                        {
                            type: 'text',
                            version: '5.3.0',
                            originX: 'left',
                            originY: 'top',
                            left: 50,
                            top: 50,
                            width: 200,
                            height: 45,
                            fill: '#000000',
                            text: 'חשבונית מס',
                            fontSize: 30,
                            fontWeight: 'bold',
                            fontFamily: 'Arial',
                            textAlign: 'right',
                            direction: 'rtl'
                        }
                    ]
                }
            },
            {
                id: 'certificate',
                name: 'תעודת הוקרה',
                category: 'certificates',
                data: {
                    version: '5.3.0',
                    objects: [
                        {
                            type: 'text',
                            version: '5.3.0',
                            originX: 'center',
                            originY: 'top',
                            left: 297,
                            top: 100,
                            width: 300,
                            height: 45,
                            fill: '#000000',
                            text: 'תעודת הוקרה',
                            fontSize: 36,
                            fontWeight: 'bold',
                            fontFamily: 'Arial',
                            textAlign: 'center'
                        }
                    ]
                }
            }
        ];
    }
}

// Export for global use
window.TemplatesManager = TemplatesManager;