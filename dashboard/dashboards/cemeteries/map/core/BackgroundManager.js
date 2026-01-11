/**
 * Background Manager - ניהול תמונות רקע ו-PDF
 * מטפל בהעלאה, תצוגה ועריכה של תמונות רקע
 */

export class BackgroundManager {
    constructor(canvas, mapAPI) {
        this.canvas = canvas;
        this.mapAPI = mapAPI;
        this.backgroundImage = null;
        this.isEditMode = false;
        this.pdfDoc = null;
        this.currentPdfPage = 1;
    }

    /**
     * טעינת תמונת רקע מנתונים
     * @param {Object} backgroundData - נתוני תמונת הרקע
     */
    async loadBackground(backgroundData) {
        if (!backgroundData || !backgroundData.path) {
            this.clearBackground();
            return null;
        }

        try {
            const image = await this.loadImageFromPath(backgroundData.path);

            if (image) {
                this.setBackground(image, {
                    width: backgroundData.width,
                    height: backgroundData.height,
                    offsetX: backgroundData.offsetX || 0,
                    offsetY: backgroundData.offsetY || 0,
                    scale: backgroundData.scale || 1
                });
            }

            return image;
        } catch (error) {
            console.error('Error loading background:', error);
            return null;
        }
    }

    /**
     * טעינת תמונה מנתיב
     * @param {string} path - נתיב התמונה
     * @returns {Promise<fabric.Image>}
     */
    loadImageFromPath(path) {
        return new Promise((resolve, reject) => {
            fabric.Image.fromURL(path, (img) => {
                if (!img) {
                    reject(new Error('Failed to load image'));
                    return;
                }
                resolve(img);
            }, { crossOrigin: 'anonymous' });
        });
    }

    /**
     * הגדרת תמונת רקע
     * @param {fabric.Image} image - התמונה
     * @param {Object} options - אפשרויות
     */
    setBackground(image, options = {}) {
        this.clearBackground();

        const {
            width = null,
            height = null,
            offsetX = 0,
            offsetY = 0,
            scale = 1
        } = options;

        // הגדרות בסיסיות
        image.set({
            left: offsetX,
            top: offsetY,
            selectable: false,
            evented: false,
            objectCaching: false,
            isBackground: true
        });

        // התאמת גודל
        if (width && height) {
            image.scaleToWidth(width);
            image.scaleToHeight(height);
        }

        // התאמת סקייל
        if (scale !== 1) {
            image.scale(scale);
        }

        this.backgroundImage = image;
        this.canvas.add(image);
        this.sendToBack();

        this.canvas.renderAll();
    }

    /**
     * העלאת תמונה חדשה מקובץ
     * @param {File} file - קובץ התמונה
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @returns {Promise<Object>}
     */
    async uploadImage(file, entityType, entityId) {
        try {
            // בדיקת סוג הקובץ
            if (!file.type.startsWith('image/') && file.type !== 'application/pdf') {
                throw new Error('יש להעלות קובץ תמונה או PDF בלבד');
            }

            // טיפול ב-PDF
            if (file.type === 'application/pdf') {
                return await this.uploadPDF(file, entityType, entityId);
            }

            // העלאה לשרת
            const result = await this.mapAPI.uploadBackground(entityType, entityId, file);

            // טעינת התמונה שהועלתה
            if (result.path) {
                const image = await this.loadImageFromPath(result.path);
                this.setBackground(image, result);
            }

            return result;
        } catch (error) {
            console.error('Error uploading image:', error);
            throw error;
        }
    }

    /**
     * העלאת PDF
     * @param {File} file - קובץ ה-PDF
     * @param {string} entityType - סוג הישות
     * @param {string} entityId - מזהה הישות
     * @returns {Promise<Object>}
     */
    async uploadPDF(file, entityType, entityId) {
        try {
            // טעינת PDF.js (אם לא נטען)
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js library not loaded');
            }

            const fileReader = new FileReader();

            return new Promise((resolve, reject) => {
                fileReader.onload = async (e) => {
                    try {
                        const typedArray = new Uint8Array(e.target.result);
                        const pdf = await pdfjsLib.getDocument({ data: typedArray }).promise;

                        this.pdfDoc = pdf;
                        this.currentPdfPage = 1;

                        // בקשה מהמשתמש לבחור עמוד
                        const pageNumber = await this.selectPDFPage(pdf.numPages);

                        if (pageNumber) {
                            const imageData = await this.renderPDFPage(pdf, pageNumber);
                            const result = await this.mapAPI.uploadBackground(
                                entityType,
                                entityId,
                                this.dataURLtoFile(imageData, 'pdf-page.png')
                            );

                            if (result.path) {
                                const image = await this.loadImageFromPath(result.path);
                                this.setBackground(image, result);
                            }

                            resolve(result);
                        } else {
                            reject(new Error('לא נבחר עמוד'));
                        }
                    } catch (error) {
                        reject(error);
                    }
                };

                fileReader.onerror = (error) => reject(error);
                fileReader.readAsArrayBuffer(file);
            });
        } catch (error) {
            console.error('Error uploading PDF:', error);
            throw error;
        }
    }

    /**
     * רינדור עמוד PDF לתמונה
     * @param {PDFDocument} pdf - מסמך PDF
     * @param {number} pageNumber - מספר העמוד
     * @returns {Promise<string>} - Data URL של התמונה
     */
    async renderPDFPage(pdf, pageNumber) {
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale: 2.0 });

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        await page.render({
            canvasContext: context,
            viewport: viewport
        }).promise;

        return canvas.toDataURL('image/png');
    }

    /**
     * בחירת עמוד מ-PDF
     * @param {number} numPages - מספר העמודים
     * @returns {Promise<number>} - מספר העמוד שנבחר
     */
    async selectPDFPage(numPages) {
        return new Promise((resolve) => {
            const pageNumber = prompt(`הקובץ מכיל ${numPages} עמודים.\nאיזה עמוד לטעון? (1-${numPages})`, '1');

            if (pageNumber && !isNaN(pageNumber)) {
                const page = parseInt(pageNumber);
                if (page >= 1 && page <= numPages) {
                    resolve(page);
                    return;
                }
            }

            resolve(null);
        });
    }

    /**
     * המרת Data URL לקובץ
     */
    dataURLtoFile(dataURL, filename) {
        const arr = dataURL.split(',');
        const mime = arr[0].match(/:(.*?);/)[1];
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);

        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }

        return new File([u8arr], filename, { type: mime });
    }

    /**
     * התחלת מצב עריכת רקע
     */
    enterEditMode() {
        if (!this.backgroundImage) return;

        this.isEditMode = true;
        this.backgroundImage.set({
            selectable: true,
            evented: true,
            hasControls: true,
            hasBorders: true,
            lockRotation: true
        });

        this.canvas.setActiveObject(this.backgroundImage);
        this.canvas.renderAll();
    }

    /**
     * יציאה ממצב עריכת רקע
     */
    exitEditMode() {
        if (!this.backgroundImage) return;

        this.isEditMode = false;
        this.backgroundImage.set({
            selectable: false,
            evented: false,
            hasControls: false,
            hasBorders: false
        });

        this.canvas.discardActiveObject();
        this.canvas.renderAll();
    }

    /**
     * שליחת תמונת הרקע לאחור (מאחורי כל השכבות)
     */
    sendToBack() {
        if (!this.backgroundImage) return;

        this.canvas.sendToBack(this.backgroundImage);
        this.canvas.renderAll();
    }

    /**
     * קבלת נתוני תמונת הרקע
     */
    getBackgroundData() {
        if (!this.backgroundImage) return null;

        return {
            path: this.backgroundImage.getSrc(),
            width: this.backgroundImage.getScaledWidth(),
            height: this.backgroundImage.getScaledHeight(),
            offsetX: this.backgroundImage.left,
            offsetY: this.backgroundImage.top,
            scale: this.backgroundImage.scaleX,
            originalWidth: this.backgroundImage.width,
            originalHeight: this.backgroundImage.height
        };
    }

    /**
     * מחיקת תמונת רקע
     */
    async deleteBackground(entityType, entityId) {
        try {
            await this.mapAPI.deleteBackground(entityType, entityId);
            this.clearBackground();
            return true;
        } catch (error) {
            console.error('Error deleting background:', error);
            return false;
        }
    }

    /**
     * ניקוי תמונת רקע מה-Canvas
     */
    clearBackground() {
        if (this.backgroundImage) {
            this.canvas.remove(this.backgroundImage);
            this.backgroundImage = null;
        }

        this.pdfDoc = null;
        this.currentPdfPage = 1;
    }

    /**
     * התאמת גודל תמונת רקע ל-Canvas
     */
    fitToCanvas() {
        if (!this.backgroundImage) return;

        const canvasWidth = this.canvas.width;
        const canvasHeight = this.canvas.height;

        this.backgroundImage.scaleToWidth(canvasWidth);
        this.backgroundImage.scaleToHeight(canvasHeight);
        this.backgroundImage.center();

        this.canvas.renderAll();
    }

    /**
     * הצגה/הסתרה של תמונת הרקע
     */
    setVisible(visible) {
        if (this.backgroundImage) {
            this.backgroundImage.set({ visible });
            this.canvas.renderAll();
        }
    }

    /**
     * האם יש תמונת רקע
     */
    hasBackground() {
        return this.backgroundImage !== null;
    }

    /**
     * השמדת המנהל
     */
    destroy() {
        this.clearBackground();
        this.canvas = null;
        this.mapAPI = null;
    }
}
