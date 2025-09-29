/**
 * Undo/Redo Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/undo-redo-manager.js
 */

class UndoRedoManager {
    constructor(canvasManager, maxStates = 50) {
        this.canvasManager = canvasManager;
        this.canvas = canvasManager.canvas;
        this.maxStates = maxStates;
        this.history = [];
        this.currentIndex = -1;
        this.isExecuting = false;
        this.saveTimeout = null;
        
        // Initialize
        this.init();
    }

    init() {
        // Save initial state
        this.saveState();
        
        // Bind canvas events
        this.bindEvents();
        
        // Update UI
        this.updateButtons();
    }

    bindEvents() {
        const self = this;
        const saveDelay = PDFEditorConfig.history.saveDelay || 500;

        // Listen to canvas changes
        const events = [
            'object:added',
            'object:removed',
            'object:modified',
            'object:rotated',
            'object:scaled',
            'object:moved',
            'object:skewed',
            'text:changed',
            'path:created'
        ];

        events.forEach(eventName => {
            this.canvas.on(eventName, function(e) {
                if (!self.isExecuting) {
                    // Debounce state saving
                    clearTimeout(self.saveTimeout);
                    self.saveTimeout = setTimeout(() => {
                        self.saveState();
                    }, saveDelay);
                }
            });
        });
    }

    saveState() {
        if (this.isExecuting) return;

        // Get current canvas state
        const state = JSON.stringify(this.canvas.toJSON([
            'selectable',
            'evented',
            'id',
            'direction',
            'textAlign',
            'lockMovementX',
            'lockMovementY',
            'lockRotation',
            'lockScalingX',
            'lockScalingY'
        ]));

        // Remove any states after current index
        if (this.currentIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.currentIndex + 1);
        }

        // Add new state
        this.history.push({
            state: state,
            timestamp: Date.now(),
            description: this.getActionDescription()
        });

        // Limit history size
        if (this.history.length > this.maxStates) {
            this.history.shift();
        } else {
            this.currentIndex++;
        }

        // Update UI
        this.updateButtons();
        this.updateHistoryPanel();

        // Log for debugging
        if (PDFEditorConfig.debug) {
            console.log('State saved:', this.currentIndex, '/', this.history.length - 1);
        }
    }

    undo() {
        if (!this.canUndo()) return;

        this.isExecuting = true;
        this.currentIndex--;

        const state = this.history[this.currentIndex];
        this.loadState(state.state);

        this.isExecuting = false;
        this.updateButtons();
        this.updateHistoryPanel();

        // Show notification
        this.showNotification(t('actions.undone'));

        if (PDFEditorConfig.debug) {
            console.log('Undo:', this.currentIndex, '/', this.history.length - 1);
        }
    }

    redo() {
        if (!this.canRedo()) return;

        this.isExecuting = true;
        this.currentIndex++;

        const state = this.history[this.currentIndex];
        this.loadState(state.state);

        this.isExecuting = false;
        this.updateButtons();
        this.updateHistoryPanel();

        // Show notification
        this.showNotification(t('actions.redone'));

        if (PDFEditorConfig.debug) {
            console.log('Redo:', this.currentIndex, '/', this.history.length - 1);
        }
    }

    loadState(stateJson) {
        const state = JSON.parse(stateJson);
        
        // Clear canvas
        this.canvas.clear();
        
        // Load state
        this.canvas.loadFromJSON(state, () => {
            this.canvas.renderAll();
            
            // Restore background if exists
            if (this.canvasManager.currentDocument) {
                if (this.canvasManager.currentDocument.type === 'pdf') {
                    // Restore PDF background
                    // This would be implemented based on your PDF handling
                } else if (this.canvasManager.currentDocument.type === 'image') {
                    // Restore image background
                }
            }
        });
    }

    canUndo() {
        return this.currentIndex > 0;
    }

    canRedo() {
        return this.currentIndex < this.history.length - 1;
    }

    clear() {
        this.history = [];
        this.currentIndex = -1;
        this.saveState();
    }

    updateButtons() {
        const undoBtn = document.getElementById('btnUndo');
        const redoBtn = document.getElementById('btnRedo');

        if (undoBtn) {
            undoBtn.disabled = !this.canUndo();
            undoBtn.title = this.canUndo() 
                ? `${t('toolbar.undo')} (Ctrl+Z)` 
                : t('toolbar.undo');
        }

        if (redoBtn) {
            redoBtn.disabled = !this.canRedo();
            redoBtn.title = this.canRedo() 
                ? `${t('toolbar.redo')} (Ctrl+Y)` 
                : t('toolbar.redo');
        }
    }

    updateHistoryPanel() {
        const historyList = document.getElementById('historyList');
        if (!historyList) return;

        // Clear current list
        historyList.innerHTML = '';

        // Add history items
        this.history.forEach((item, index) => {
            const historyItem = document.createElement('div');
            historyItem.className = 'history-item';
            
            if (index === this.currentIndex) {
                historyItem.classList.add('active');
            }
            
            // Create timestamp
            const time = new Date(item.timestamp);
            const timeStr = time.toLocaleTimeString(languageManager.getCurrentLanguage() === 'he' ? 'he-IL' : 'en-US');
            
            historyItem.innerHTML = `
                <i class="fas fa-circle" style="font-size: 8px; margin-left: 5px;"></i>
                <span class="history-description">${item.description || t('history.state')} ${index + 1}</span>
                <span class="history-time">${timeStr}</span>
            `;

            // Add click handler
            historyItem.addEventListener('click', () => {
                this.goToState(index);
            });

            historyList.appendChild(historyItem);
        });

        // Scroll to current state
        const activeItem = historyList.querySelector('.history-item.active');
        if (activeItem) {
            activeItem.scrollIntoView({ block: 'nearest' });
        }
    }

    goToState(index) {
        if (index < 0 || index >= this.history.length) return;
        if (index === this.currentIndex) return;

        this.isExecuting = true;
        this.currentIndex = index;

        const state = this.history[this.currentIndex];
        this.loadState(state.state);

        this.isExecuting = false;
        this.updateButtons();
        this.updateHistoryPanel();
    }

    getActionDescription() {
        // Try to determine what action was performed
        const activeObject = this.canvas.getActiveObject();
        
        if (!activeObject) {
            return t('history.change');
        }

        if (activeObject.type === 'i-text' || activeObject.type === 'text') {
            return t('history.textChange');
        } else if (activeObject.type === 'image') {
            return t('history.imageChange');
        } else if (activeObject.type === 'group') {
            return t('history.groupChange');
        } else if (activeObject.type === 'path') {
            return t('history.drawingChange');
        } else {
            return t('history.objectChange');
        }
    }

    showNotification(message) {
        // This could be implemented with a proper notification system
        // For now, we'll use console
        if (window.notificationManager) {
            window.notificationManager.show(message);
        } else {
            console.log(message);
        }
    }

    exportHistory() {
        return {
            history: this.history,
            currentIndex: this.currentIndex
        };
    }

    importHistory(data) {
        if (data && data.history && Array.isArray(data.history)) {
            this.history = data.history;
            this.currentIndex = data.currentIndex || data.history.length - 1;
            
            if (this.currentIndex >= 0 && this.currentIndex < this.history.length) {
                this.loadState(this.history[this.currentIndex].state);
            }
            
            this.updateButtons();
            this.updateHistoryPanel();
        }
    }

    getHistorySize() {
        // Calculate approximate memory size of history
        let size = 0;
        this.history.forEach(item => {
            size += item.state.length;
        });
        return size;
    }

    compressHistory() {
        // Compress history to save memory
        // This could be implemented with LZ compression
        // For now, we'll just remove old states beyond a threshold
        const keepStates = Math.floor(this.maxStates / 2);
        
        if (this.history.length > keepStates) {
            const removeCount = this.history.length - keepStates;
            this.history.splice(0, removeCount);
            this.currentIndex = Math.max(0, this.currentIndex - removeCount);
            
            this.updateButtons();
            this.updateHistoryPanel();
        }
    }
}

// Export for global use
window.UndoRedoManager = UndoRedoManager;