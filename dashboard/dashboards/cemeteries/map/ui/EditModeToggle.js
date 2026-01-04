/**
 * EditModeToggle - טוגל מצב עריכה
 * Version: 1.0.0
 *
 * מחלקה לניהול מצב עריכה של המפה (on/off)
 * Usage:
 *   const toggle = new EditModeToggle({
 *     onToggle: (enabled) => {...},
 *     onEnter: () => {...},
 *     onExit: () => {...}
 *   });
 *   toggle.setEnabled(true);
 */

export class EditModeToggle {
    constructor(options = {}) {
        this.options = {
            checkboxId: options.checkboxId || 'editModeToggle',
            containerId: options.containerId || 'mapContainer',
            onToggle: options.onToggle || null,      // Called on any toggle
            onEnter: options.onEnter || null,        // Called when entering edit mode
            onExit: options.onExit || null,          // Called when exiting edit mode
            canvas: options.canvas || null,          // Canvas reference for cleanup
            initialText: options.initialText || 'לחץ על "מצב עריכה" כדי להתחיל'
        };

        this.isEnabled = false;
        this.checkbox = null;
        this.container = null;
    }

    /**
     * אתחול והתחברות ל-DOM
     */
    init() {
        this.checkbox = document.getElementById(this.options.checkboxId);
        this.container = document.getElementById(this.options.containerId);

        if (!this.checkbox || !this.container) {
            console.warn('EditModeToggle: checkbox or container not found');
            return false;
        }

        // Listen to checkbox changes (if not already handled by onchange attribute)
        // This allows programmatic control
        this.checkbox.addEventListener('change', (e) => {
            this.setEnabled(e.target.checked);
        });

        console.log('✅ EditModeToggle initialized');
        return true;
    }

    /**
     * הפעלה/כיבוי של מצב עריכה
     * @param {boolean} enabled - האם להפעיל מצב עריכה
     */
    setEnabled(enabled) {
        this.isEnabled = enabled;

        // Update checkbox if needed
        if (this.checkbox && this.checkbox.checked !== enabled) {
            this.checkbox.checked = enabled;
        }

        // Update container class
        if (this.container) {
            if (enabled) {
                this.container.classList.add('edit-mode');
                this.onEnterEditMode();
            } else {
                this.container.classList.remove('edit-mode');
                this.onExitEditMode();
            }
        }

        // General toggle callback
        if (this.options.onToggle) {
            this.options.onToggle(enabled);
        }

        console.log(enabled ? '✅ Edit mode: ON' : '✅ Edit mode: OFF');
    }

    /**
     * פעולות בכניסה למצב עריכה
     * @private
     */
    onEnterEditMode() {
        // הסרת טקסט התחלתי
        if (this.options.canvas) {
            const objects = this.options.canvas.getObjects('text');
            objects.forEach(obj => {
                if (obj.text === this.options.initialText) {
                    this.options.canvas.remove(obj);
                }
            });
            this.options.canvas.renderAll();
        }

        // Callback
        if (this.options.onEnter) {
            this.options.onEnter();
        }
    }

    /**
     * פעולות ביציאה ממצב עריכה
     * @private
     */
    onExitEditMode() {
        // Callback (e.g., cancel polygon drawing)
        if (this.options.onExit) {
            this.options.onExit();
        }
    }

    /**
     * קבלת מצב נוכחי
     */
    getState() {
        return {
            isEnabled: this.isEnabled
        };
    }

    /**
     * הגדרת Canvas חדש
     */
    setCanvas(canvas) {
        this.options.canvas = canvas;
        console.log('Canvas reference updated in EditModeToggle');
    }

    /**
     * Enable/disable the toggle control
     */
    setControlEnabled(enabled) {
        if (this.checkbox) {
            this.checkbox.disabled = !enabled;
        }
    }

    /**
     * Debug info
     */
    debug() {
        console.group('🎚️ EditModeToggle');
        console.log('Is Enabled:', this.isEnabled);
        console.log('Checkbox:', this.checkbox ? 'found' : 'not found');
        console.log('Container:', this.container ? 'found' : 'not found');
        console.groupEnd();
    }
}
