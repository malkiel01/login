/**
 * API Connector for PDF/Image Editor
 * Handles communication between the frontend and PHP backend
 */

class PDFEditorAPI {
    constructor(apiEndpoint = '/api/process-document.php') {
        this.apiEndpoint = apiEndpoint;
        this.currentDocument = null;
        this.canvas = null;
    }

    /**
     * Initialize the API connector with canvas instance
     */
    init(canvasInstance) {
        this.canvas = canvasInstance;
    }

    /**
     * Convert canvas data to API format
     */
    async prepareDocumentData(file, canvas) {
        const objects = canvas.getObjects();
        const canvasWidth = canvas.width / canvas.getZoom();
        const canvasHeight = canvas.height / canvas.getZoom();

        // Convert file to base64
        const fileBase64 = await this.fileToBase64(file);

        // Build document structure
        const documentData = {
            document: {
                file: {
                    base64: fileBase64.split(',')[1], // Remove data:image/... prefix
                    name: file.name,
                    type: file.type
                },
                size: {
                    unit: 'px',
                    width: canvasWidth,
                    height: canvasHeight
                },
                content_language: 'he',
                font: {
                    name: 'Arial',
                    fallback: true
                }
            },
            elements: []
        };

        // Process each canvas object
        for (const obj of objects) {
            const element = await this.convertCanvasObject(obj);
            if (element) {
                documentData.elements.push(element);
            }
        }

        return documentData;
    }

    /**
     * Convert canvas object to API element format
     */
    async convertCanvasObject(obj) {
        if (obj.type === 'i-text' || obj.type === 'text') {
            return this.convertTextObject(obj);
        } else if (obj.type === 'image') {
            return await this.convertImageObject(obj);
        }
        return null;
    }

    /**
     * Convert text object to API format
     */
    convertTextObject(obj) {
        // Determine text direction
        const isHebrew = /[\u0590-\u05FF]/.test(obj.text);
        const textDirection = isHebrew ? 'rtl' : 'ltr';
        
        return {
            type: 'text',
            value: obj.text,
            language: isHebrew ? 'he' : 'en',
            position: {
                unit: 'px',
                from_top: obj.top,
                from_left: obj.left,
                from_right: null
            },
            anchor: {
                x_ref: 'left',
                y_ref: 'top',
                measure_from: 'page'
            },
            layout: {
                max_width: obj.width || 0,
                text_direction: textDirection,
                align: this.getTextAlign(obj.textAlign)
            },
            style: {
                font_size_pt: Math.round(obj.fontSize * 0.75), // Convert px to pt
                font_family: obj.fontFamily || 'Arial',
                bold: obj.fontWeight === 'bold',
                italic: obj.fontStyle === 'italic',
                color: obj.fill || '#000000',
                opacity: obj.opacity || 1,
                rotation: obj.angle || 0,
                line_height: obj.lineHeight || 1.2
            }
        };
    }

    /**
     * Convert image object to API format
     */
    async convertImageObject(obj) {
        // Get image data URL
        const imageDataUrl = obj.toDataURL({
            format: 'png',
            multiplier: 1
        });

        return {
            type: 'image',
            source: {
                base64: imageDataUrl.split(',')[1]
            },
            position: {
                unit: 'px',
                from_top: obj.top,
                from_left: obj.left,
                from_right: null
            },
            size: {
                width: obj.width * obj.scaleX,
                height: obj.height * obj.scaleY,
                unit: 'px'
            },
            style: {
                opacity: obj.opacity || 1,
                rotation: obj.angle || 0
            }
        };
    }

    /**
     * Get text alignment value for API
     */
    getTextAlign(align) {
        switch (align) {
            case 'center':
                return 'center';
            case 'right':
                return 'end';
            case 'left':
            default:
                return 'start';
        }
    }

    /**
     * Convert file to base64
     */
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    /**
     * Send document to API for processing
     */
    async processDocument(documentData) {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(documentData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Processing failed');
            }

            return result.data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    /**
     * Download processed document
     */
    downloadDocument(base64Data, filename) {
        // Create blob from base64
        const byteCharacters = atob(base64Data);
        const byteNumbers = new Array(byteCharacters.length);
        
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: 'application/pdf' });
        
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        
        // Clean up
        window.URL.revokeObjectURL(url);
    }

    /**
     * Export configuration as JSON
     */
    exportJSON(documentData) {
        const dataStr = JSON.stringify(documentData, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);
        
        const exportName = 'document-config-' + Date.now() + '.json';
        
        const linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportName);
        linkElement.click();
    }

    /**
     * Load configuration from JSON
     */
    async loadJSON(jsonFile) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                try {
                    const data = JSON.parse(e.target.result);
                    resolve(data);
                } catch (error) {
                    reject(error);
                }
            };
            
            reader.onerror = reject;
            reader.readAsText(jsonFile);
        });
    }

    /**
     * Apply JSON configuration to canvas
     */
    async applyJSONToCanvas(jsonData, canvas) {
        // Clear existing objects
        canvas.clear();
        
        // Apply elements
        for (const element of jsonData.elements) {
            if (element.type === 'text') {
                const text = new fabric.IText(element.value, {
                    left: element.position.from_left || 0,
                    top: element.position.from_top || 0,
                    fontFamily: element.style.font_family || 'Arial',
                    fontSize: Math.round(element.style.font_size_pt * 1.33), // Convert pt to px
                    fill: element.style.color || '#000000',
                    opacity: element.style.opacity || 1,
                    angle: element.style.rotation || 0,
                    fontWeight: element.style.bold ? 'bold' : 'normal',
                    fontStyle: element.style.italic ? 'italic' : 'normal'
                });
                
                canvas.add(text);
            } else if (element.type === 'image' && element.source.base64) {
                const imgDataUrl = 'data:image/png;base64,' + element.source.base64;
                
                fabric.Image.fromURL(imgDataUrl, (img) => {
                    img.set({
                        left: element.position.from_left || 0,
                        top: element.position.from_top || 0,
                        opacity: element.style.opacity || 1,
                        angle: element.style.rotation || 0
                    });
                    
                    // Calculate scale to match requested size
                    if (element.size) {
                        const scaleX = element.size.width / img.width;
                        const scaleY = element.size.height / img.height;
                        img.set({ scaleX, scaleY });
                    }
                    
                    canvas.add(img);
                    canvas.renderAll();
                });
            }
        }
        
        canvas.renderAll();
    }

    /**
     * Process and download document with loading indicator
     */
    async processAndDownload(file, canvas, loadingCallback) {
        try {
            // Show loading
            if (loadingCallback) loadingCallback(true);
            
            // Prepare document data
            const documentData = await this.prepareDocumentData(file, canvas);
            
            // Send to API
            const result = await this.processDocument(documentData);
            
            // Download the result
            this.downloadDocument(result.file, result.filename);
            
            // Hide loading
            if (loadingCallback) loadingCallback(false);
            
            return true;
        } catch (error) {
            // Hide loading
            if (loadingCallback) loadingCallback(false);
            
            console.error('Process and download error:', error);
            alert('שגיאה בעיבוד המסמך: ' + error.message);
            return false;
        }
    }

    /**
     * Create a preview of the document
     */
    async createPreview(file, canvas) {
        try {
            // Prepare document data
            const documentData = await this.prepareDocumentData(file, canvas);
            
            // For preview, we can use the canvas itself
            const dataURL = canvas.toDataURL({
                format: 'png',
                quality: 1,
                multiplier: 1
            });
            
            // Open preview window
            const previewWindow = window.open('', '_blank');
            previewWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <title>תצוגה מקדימה</title>
                    <style>
                        body {
                            margin: 0;
                            padding: 20px;
                            background: #f5f5f5;
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            font-family: Arial, sans-serif;
                        }
                        .header {
                            background: white;
                            padding: 15px 30px;
                            border-radius: 8px;
                            margin-bottom: 20px;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                            width: 100%;
                            max-width: 800px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        .header h2 {
                            margin: 0;
                            color: #333;
                        }
                        .actions {
                            display: flex;
                            gap: 10px;
                        }
                        button {
                            padding: 8px 16px;
                            background: #667eea;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 14px;
                            transition: all 0.3s;
                        }
                        button:hover {
                            background: #5a67d8;
                            transform: translateY(-1px);
                        }
                        .preview-container {
                            background: white;
                            padding: 20px;
                            border-radius: 8px;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                        }
                        img {
                            max-width: 100%;
                            height: auto;
                            display: block;
                        }
                        .json-preview {
                            margin-top: 20px;
                            padding: 15px;
                            background: #f8f9fa;
                            border-radius: 6px;
                            font-family: 'Courier New', monospace;
                            font-size: 12px;
                            max-height: 200px;
                            overflow-y: auto;
                            white-space: pre-wrap;
                            display: none;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>תצוגה מקדימה של המסמך</h2>
                        <div class="actions">
                            <button onclick="toggleJSON()">הצג/הסתר JSON</button>
                            <button onclick="window.print()">הדפס</button>
                            <button onclick="window.close()">סגור</button>
                        </div>
                    </div>
                    <div class="preview-container">
                        <img src="${dataURL}" alt="Document Preview">
                    </div>
                    <pre class="json-preview" id="jsonPreview">${JSON.stringify(documentData, null, 2)}</pre>
                    <script>
                        function toggleJSON() {
                            const jsonEl = document.getElementById('jsonPreview');
                            jsonEl.style.display = jsonEl.style.display === 'none' ? 'block' : 'none';
                        }
                    </script>
                </body>
                </html>
            `);
            
            return true;
        } catch (error) {
            console.error('Preview error:', error);
            alert('שגיאה ביצירת תצוגה מקדימה');
            return false;
        }
    }
}

// Export for use in main application
window.PDFEditorAPI = PDFEditorAPI;