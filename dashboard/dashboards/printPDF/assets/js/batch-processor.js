/**
 * Batch Processor for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/batch-processor.js
 */

class BatchProcessor {
    constructor(canvasManager, apiConnector) {
        this.canvasManager = canvasManager;
        this.apiConnector = apiConnector;
        this.queue = [];
        this.processing = false;
        this.currentIndex = 0;
        this.processedFiles = [];
        this.failedFiles = [];
        this.currentElements = [];
        
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Batch file input
        const batchFileInput = document.getElementById('batchFileInput');
        if (batchFileInput) {
            batchFileInput.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                this.addFilesToQueue(files);
            });
        }

        // Batch browse button
        document.getElementById('btnBatchBrowse')?.addEventListener('click', () => {
            document.getElementById('batchFileInput')?.click();
        });

        // Start batch button
        document.getElementById('btnStartBatch')?.addEventListener('click', () => {
            this.startProcessing();
        });

        // Clear batch button
        document.getElementById('btnClearBatch')?.addEventListener('click', () => {
            this.clearQueue();
        });

        // Download all button
        document.getElementById('btnDownloadAll')?.addEventListener('click', () => {
            this.downloadAll();
        });

        // Drag and drop for batch area
        const batchUploadArea = document.querySelector('.batch-upload-area');
        if (batchUploadArea) {
            batchUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                batchUploadArea.classList.add('drag-over');
            });

            batchUploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                batchUploadArea.classList.remove('drag-over');
            });

            batchUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                batchUploadArea.classList.remove('drag-over');
                
                const files = Array.from(e.dataTransfer.files);
                this.addFilesToQueue(files);
            });
        }
    }

    addFilesToQueue(files) {
        // Validate files
        const validFiles = files.filter(file => this.validateFile(file));
        
        if (validFiles.length === 0) {
            this.showError('לא נבחרו קבצים תקינים');
            return;
        }

        // Check max files limit
        const remainingSlots = PDFEditorConfig.batch.maxFiles - this.queue.length;
        if (remainingSlots <= 0) {
            this.showError(`ניתן לעבד עד ${PDFEditorConfig.batch.maxFiles} קבצים`);
            return;
        }

        // Add files to queue
        const filesToAdd = validFiles.slice(0, remainingSlots);
        filesToAdd.forEach(file => {
            this.queue.push({
                id: `file_${Date.now()}_${Math.random()}`,
                file: file,
                status: 'waiting',
                progress: 0,
                result: null,
                error: null
            });
        });

        // Update display
        this.updateQueueDisplay();

        // Show notification if some files were skipped
        if (validFiles.length > filesToAdd.length) {
            this.showWarning(`${validFiles.length - filesToAdd.length} קבצים דולגו עקב מגבלת הכמות`);
        }
    }

    validateFile(file) {
        // Check file size
        if (file.size > PDFEditorConfig.file.maxSize) {
            console.warn(`File ${file.name} is too large`);
            return false;
        }

        // Check file type
        const extension = file.name.split('.').pop().toLowerCase();
        if (!PDFEditorConfig.file.allowedExtensions.includes(extension)) {
            console.warn(`File ${file.name} has unsupported format`);
            return false;
        }

        return true;
    }

    updateQueueDisplay() {
        const queueContainer = document.getElementById('batchQueue');
        if (!queueContainer) return;

        if (this.queue.length === 0) {
            queueContainer.innerHTML = `
                <div style="text-align: center; padding: 20px; color: #6b7280;">
                    <p>אין קבצים בתור</p>
                </div>
            `;
            return;
        }

        queueContainer.innerHTML = '';
        
        this.queue.forEach((item, index) => {
            const itemElement = document.createElement('div');
            itemElement.className = 'batch-item';
            itemElement.style = `
                padding: 10px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            itemElement.innerHTML = `
                <span class="batch-index" style="min-width: 30px; text-align: center; font-weight: 500;">${index + 1}</span>
                <span class="batch-name" style="flex: 1;">${item.file.name}</span>
                <span class="batch-size" style="color: #6b7280; font-size: 14px;">${this.formatFileSize(item.file.size)}</span>
                <span class="batch-status" data-status="${item.status}" style="min-width: 100px; text-align: center;">
                    ${this.getStatusDisplay(item.status)}
                </span>
                <button class="batch-remove" data-index="${index}" style="padding: 4px 8px; background: #f3f4f6; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            `;

            // Add remove event
            itemElement.querySelector('.batch-remove').addEventListener('click', () => {
                this.removeFromQueue(index);
            });

            queueContainer.appendChild(itemElement);
        });
    }

    getStatusDisplay(status) {
        const statusMap = {
            'waiting': '<i class="fas fa-clock"></i> ממתין',
            'processing': '<i class="fas fa-spinner fa-spin"></i> מעבד...',
            'completed': '<i class="fas fa-check" style="color: #10b981;"></i> הושלם',
            'failed': '<i class="fas fa-times" style="color: #ef4444;"></i> נכשל'
        };
        
        return statusMap[status] || status;
    }

    removeFromQueue(index) {
        if (this.processing) {
            this.showError('לא ניתן להסיר קבצים במהלך עיבוד');
            return;
        }

        this.queue.splice(index, 1);
        this.updateQueueDisplay();
    }

    clearQueue() {
        if (this.processing) {
            this.showError('לא ניתן לנקות את התור במהלך עיבוד');
            return;
        }

        if (this.queue.length > 0 && !confirm('האם לנקות את כל הקבצים בתור?')) {
            return;
        }

        this.queue = [];
        this.processedFiles = [];
        this.failedFiles = [];
        this.currentIndex = 0;
        this.updateQueueDisplay();
        
        // Hide download button
        const downloadBtn = document.getElementById('btnDownloadAll');
        if (downloadBtn) {
            downloadBtn.style.display = 'none';
        }
    }

    async startProcessing() {
        if (this.processing) {
            this.showError('עיבוד כבר בתהליך');
            return;
        }

        if (this.queue.length === 0) {
            this.showError('אין קבצים לעיבוד');
            return;
        }

        // Get current canvas elements to apply to all files
        this.currentElements = this.getCanvasElements();
        
        if (this.currentElements.length === 0) {
            if (!confirm('לא נוספו אלמנטים. האם להמשיך?')) {
                return;
            }
        }

        this.processing = true;
        this.currentIndex = 0;
        this.processedFiles = [];
        this.failedFiles = [];

        // Disable buttons
        this.setButtonsState(false);

        // Start processing
        await this.processNext();
    }

    getCanvasElements() {
        // Get all objects from canvas except background
        const objects = this.canvasManager.canvas.getObjects();
        const elements = [];

        objects.forEach(obj => {
            if (obj.type === 'i-text' || obj.type === 'text') {
                elements.push({
                    type: 'text',
                    value: obj.text,
                    position: {
                        from_left: obj.left,
                        from_top: obj.top,
                        unit: 'px'
                    },
                    style: {
                        font_family: obj.fontFamily,
                        font_size_pt: obj.fontSize,
                        color: obj.fill,
                        bold: obj.fontWeight === 'bold',
                        italic: obj.fontStyle === 'italic',
                        opacity: obj.opacity,
                        rotation: obj.angle
                    },
                    layout: {
                        text_direction: obj.direction || 'ltr',
                        align: obj.textAlign
                    }
                });
            } else if (obj.type === 'image') {
                elements.push({
                    type: 'image',
                    value: obj.getSrc(),
                    position: {
                        from_left: obj.left,
                        from_top: obj.top,
                        unit: 'px'
                    },
                    width: obj.width * obj.scaleX,
                    height: obj.height * obj.scaleY,
                    style: {
                        opacity: obj.opacity,
                        rotation: obj.angle
                    }
                });
            }
        });

        return elements;
    }

    async processNext() {
        if (this.currentIndex >= this.queue.length) {
            // Processing complete
            this.onProcessingComplete();
            return;
        }

        const item = this.queue[this.currentIndex];
        
        // Update status
        item.status = 'processing';
        this.updateItemStatus(this.currentIndex, 'processing');

        try {
            // Upload and process file
            const result = await this.processFile(item.file);
            
            item.status = 'completed';
            item.result = result;
            this.processedFiles.push({
                name: item.file.name,
                url: result.download_url,
                data: result
            });
            
            this.updateItemStatus(this.currentIndex, 'completed');
            
        } catch (error) {
            console.error('Failed to process file:', error);
            item.status = 'failed';
            item.error = error.message;
            this.failedFiles.push(item.file.name);
            
            this.updateItemStatus(this.currentIndex, 'failed');
        }

        // Process next file
        this.currentIndex++;
        await this.processNext();
    }

    async processFile(file) {
        // First upload the file
        const uploadResponse = await this.apiConnector.uploadFile(file, (progress) => {
            console.log(`Uploading ${file.name}: ${progress}%`);
        });

        if (!uploadResponse.success) {
            throw new Error(uploadResponse.message || 'Upload failed');
        }

        // Then process with elements
        const processResponse = await this.apiConnector.processDocument(
            {
                file: {
                    url: uploadResponse.data.url
                },
                size: uploadResponse.data.dimensions
            },
            this.currentElements
        );

        if (!processResponse.success) {
            throw new Error(processResponse.message || 'Processing failed');
        }

        return processResponse.data;
    }

    updateItemStatus(index, status) {
        const statusElements = document.querySelectorAll('.batch-status');
        if (statusElements[index]) {
            statusElements[index].setAttribute('data-status', status);
            statusElements[index].innerHTML = this.getStatusDisplay(status);
        }
    }

    onProcessingComplete() {
        this.processing = false;
        this.setButtonsState(true);

        // Show summary
        const summary = `
            עיבוד הושלם!
            הצליחו: ${this.processedFiles.length}
            נכשלו: ${this.failedFiles.length}
        `;
        
        this.showSuccess(summary);

        // Show download button if there are processed files
        if (this.processedFiles.length > 0) {
            const downloadBtn = document.getElementById('btnDownloadAll');
            if (downloadBtn) {
                downloadBtn.style.display = 'inline-block';
            }
        }
    }

    setButtonsState(enabled) {
        const buttons = ['btnStartBatch', 'btnClearBatch', 'btnBatchBrowse'];
        buttons.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.disabled = !enabled;
            }
        });
    }

    async downloadAll() {
        if (this.processedFiles.length === 0) {
            this.showError('אין קבצים מעובדים להורדה');
            return;
        }

        // Download each file
        this.processedFiles.forEach((file, index) => {
            setTimeout(() => {
                this.downloadFile(file.url, file.name);
            }, index * 500); // Delay to prevent browser blocking
        });
    }

    downloadFile(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = `processed_${filename}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Helper methods
    showError(message) {
        alert(message);
    }

    showWarning(message) {
        console.warn(message);
    }

    showSuccess(message) {
        alert(message);
    }
}

// Export for global use
window.BatchProcessor = BatchProcessor;