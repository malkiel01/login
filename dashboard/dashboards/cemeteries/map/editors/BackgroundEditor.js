/**
 * BackgroundEditor - ניהול תמונת רקע/PDF
 * Version: 1.0.0
 *
 * מחלקה לניהול תמונות רקע (העלאה, עריכה, מחיקה)
 * Usage:
 *   const editor = new BackgroundEditor(canvas, {
 *     onUpload: (img) => {...},
 *     onDelete: () => {...}
 *   });
 *   editor.upload(file);
 */

export class BackgroundEditor {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.options = {
            onUpload: options.onUpload || null,
            onDelete: options.onDelete || null,
            onEditModeChange: options.onEditModeChange || null,
            maxWidthPercent: options.maxWidthPercent || 0.9,
            maxHeightPercent: options.maxHeightPercent || 0.9
        };

        this.isEditMode = false;
        this.backgroundImage = null;
    }

    /**
     * העלאת תמונת רקע
     * @param {File} file - קובץ התמונה
     * @returns {Promise<fabric.Image>}
     */
    upload(file) {
        return new Promise((resolve, reject) => {
            if (!file) {
                reject(new Error('No file provided'));
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                fabric.Image.fromURL(e.target.result, (img) => {
                    // הסרת תמונת רקע קודמת
                    if (this.backgroundImage) {
                        this.canvas.remove(this.backgroundImage);
                    }

                    // התאמת גודל התמונה ל-canvas
                    const scale = Math.min(
                        (this.canvas.width * this.options.maxWidthPercent) / img.width,
                        (this.canvas.height * this.options.maxHeightPercent) / img.height
                    );

                    img.set({
                        left: this.canvas.width / 2,
                        top: this.canvas.height / 2,
                        originX: 'center',
                        originY: 'center',
                        scaleX: scale,
                        scaleY: scale,
                        selectable: true,
                        evented: true,
                        hasControls: true,
                        hasBorders: true,
                        lockRotation: false,
                        objectType: 'backgroundLayer'
                    });

                    this.canvas.add(img);
                    this.backgroundImage = img;

                    // הפעל מצב עריכה אוטומטית
                    this.enableEditMode();

                    // בחר את התמונה
                    this.canvas.setActiveObject(img);
                    this.canvas.renderAll();

                    console.log('✅ Background image uploaded');

                    // Callback
                    if (this.options.onUpload) {
                        this.options.onUpload(img);
                    }

                    resolve(img);
                }, {
                    crossOrigin: 'anonymous'
                });
            };

            reader.onerror = (error) => {
                console.error('Error reading file:', error);
                reject(error);
            };

            reader.readAsDataURL(file);
        });
    }

    /**
     * הפעלת מצב עריכה
     */
    enableEditMode() {
        if (!this.backgroundImage) {
            console.warn('No background image to edit');
            return false;
        }

        this.isEditMode = true;

        this.backgroundImage.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true
        });

        this.canvas.setActiveObject(this.backgroundImage);
        this.canvas.renderAll();

        console.log('✅ Background edit mode: ON');

        if (this.options.onEditModeChange) {
            this.options.onEditModeChange(true);
        }

        return true;
    }

    /**
     * כיבוי מצב עריכה
     */
    disableEditMode() {
        if (!this.backgroundImage) {
            return false;
        }

        this.isEditMode = false;

        this.backgroundImage.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        this.canvas.discardActiveObject();
        this.canvas.renderAll();

        console.log('✅ Background edit mode: OFF');

        if (this.options.onEditModeChange) {
            this.options.onEditModeChange(false);
        }

        return true;
    }

    /**
     * מחיקת תמונת רקע
     */
    delete() {
        if (!this.canvas || !this.backgroundImage) {
            return false;
        }

        // כבה מצב עריכה אם פעיל
        if (this.isEditMode) {
            this.disableEditMode();
        }

        this.canvas.remove(this.backgroundImage);
        this.backgroundImage = null;

        this.canvas.renderAll();

        console.log('✅ Background deleted');

        // Callback
        if (this.options.onDelete) {
            this.options.onDelete();
        }

        return true;
    }

    /**
     * קבלת תמונת הרקע הנוכחית
     */
    getImage() {
        return this.backgroundImage;
    }

    /**
     * קבלת state נוכחי
     */
    getState() {
        return {
            hasBackground: !!this.backgroundImage,
            isEditMode: this.isEditMode
        };
    }

    /**
     * הגדרת תמונת רקע קיימת (למשל בעת טעינה מהשרת)
     */
    setImage(img) {
        if (this.backgroundImage) {
            this.canvas.remove(this.backgroundImage);
        }
        this.backgroundImage = img;
    }

    /**
     * וידוא שהמסכה האפורה נעולה תמיד
     * Helper function to ensure gray mask is always locked
     */
    ensureMaskLocked(grayMask) {
        if (grayMask) {
            grayMask.set({
                selectable: false,
                evented: false,
                hasControls: false,
                hasBorders: false
            });
        }
    }

    /**
     * Debug info
     */
    debug() {
        console.group('🖼️ BackgroundEditor');
        console.log('Has Background:', !!this.backgroundImage);
        console.log('Edit Mode:', this.isEditMode);
        if (this.backgroundImage) {
            console.log('Image Size:', {
                width: this.backgroundImage.width,
                height: this.backgroundImage.height,
                scaleX: this.backgroundImage.scaleX,
                scaleY: this.backgroundImage.scaleY
            });
        }
        console.groupEnd();
    }
}
