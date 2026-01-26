/**
 * Web Share API - שיתוף תוכן החוצה מהאפליקציה
 * תומך בשיתוף טקסט, קישורים וקבצים
 *
 * @version 1.0.0
 */

class WebShare {
    constructor() {
        this.isSupported = this.checkSupport();
        this.isFileShareSupported = this.checkFileShareSupport();
    }

    /**
     * בדיקת תמיכה ב-Web Share API
     */
    checkSupport() {
        return 'share' in navigator;
    }

    /**
     * בדיקת תמיכה בשיתוף קבצים
     */
    checkFileShareSupport() {
        return 'canShare' in navigator;
    }

    /**
     * שיתוף טקסט/קישור
     *
     * @param {Object} data - נתוני השיתוף
     * @param {string} data.title - כותרת
     * @param {string} data.text - טקסט
     * @param {string} data.url - קישור
     * @returns {Promise<boolean>}
     */
    async shareText({ title = '', text = '', url = '' }) {
        if (!this.isSupported) {
            console.warn('[WebShare] Web Share API not supported');
            return this.fallbackShare({ title, text, url });
        }

        try {
            await navigator.share({
                title,
                text,
                url
            });
            console.log('[WebShare] Shared successfully');
            return true;
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('[WebShare] Share cancelled by user');
                return false;
            }
            console.error('[WebShare] Share error:', error);
            return this.fallbackShare({ title, text, url });
        }
    }

    /**
     * שיתוף קובץ/קבצים
     *
     * @param {Object} data - נתוני השיתוף
     * @param {File[]} data.files - מערך קבצים
     * @param {string} data.title - כותרת (אופציונלי)
     * @param {string} data.text - טקסט (אופציונלי)
     * @returns {Promise<boolean>}
     */
    async shareFiles({ files, title = '', text = '' }) {
        if (!this.isSupported || !files || files.length === 0) {
            console.warn('[WebShare] Cannot share files');
            return false;
        }

        // בדוק אם ניתן לשתף את הקבצים
        const shareData = { files };
        if (title) shareData.title = title;
        if (text) shareData.text = text;

        if (navigator.canShare && !navigator.canShare(shareData)) {
            console.warn('[WebShare] Cannot share these files');
            return false;
        }

        try {
            await navigator.share(shareData);
            console.log('[WebShare] Files shared successfully');
            return true;
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('[WebShare] Share cancelled by user');
                return false;
            }
            console.error('[WebShare] File share error:', error);
            return false;
        }
    }

    /**
     * שיתוף תמונה מ-URL
     *
     * @param {string} imageUrl - כתובת התמונה
     * @param {string} filename - שם הקובץ
     * @param {string} title - כותרת
     * @param {string} text - טקסט
     * @returns {Promise<boolean>}
     */
    async shareImage(imageUrl, filename = 'image.jpg', title = '', text = '') {
        try {
            // הורד את התמונה והמר ל-File
            const response = await fetch(imageUrl);
            const blob = await response.blob();
            const file = new File([blob], filename, { type: blob.type });

            return await this.shareFiles({ files: [file], title, text });
        } catch (error) {
            console.error('[WebShare] Image share error:', error);
            return false;
        }
    }

    /**
     * שיתוף מסמך PDF
     *
     * @param {string|Blob} pdfSource - URL או Blob של ה-PDF
     * @param {string} filename - שם הקובץ
     * @param {string} title - כותרת
     * @returns {Promise<boolean>}
     */
    async sharePDF(pdfSource, filename = 'document.pdf', title = '') {
        try {
            let file;

            if (typeof pdfSource === 'string') {
                const response = await fetch(pdfSource);
                const blob = await response.blob();
                file = new File([blob], filename, { type: 'application/pdf' });
            } else if (pdfSource instanceof Blob) {
                file = new File([pdfSource], filename, { type: 'application/pdf' });
            } else {
                throw new Error('Invalid PDF source');
            }

            return await this.shareFiles({ files: [file], title });
        } catch (error) {
            console.error('[WebShare] PDF share error:', error);
            return false;
        }
    }

    /**
     * שיתוף כרטיס איש קשר (vCard)
     *
     * @param {Object} contact - פרטי איש קשר
     * @returns {Promise<boolean>}
     */
    async shareContact(contact) {
        const vCard = this.generateVCard(contact);
        const blob = new Blob([vCard], { type: 'text/vcard' });
        const file = new File([blob], `${contact.name || 'contact'}.vcf`, { type: 'text/vcard' });

        return await this.shareFiles({
            files: [file],
            title: contact.name || 'איש קשר',
            text: `פרטי קשר: ${contact.name}`
        });
    }

    /**
     * יצירת vCard מפרטי קשר
     */
    generateVCard(contact) {
        const lines = [
            'BEGIN:VCARD',
            'VERSION:3.0'
        ];

        if (contact.name) {
            lines.push(`FN:${contact.name}`);
            const nameParts = contact.name.split(' ');
            lines.push(`N:${nameParts.slice(1).join(' ')};${nameParts[0]};;;`);
        }

        if (contact.phone) {
            lines.push(`TEL;TYPE=CELL:${contact.phone}`);
        }

        if (contact.email) {
            lines.push(`EMAIL:${contact.email}`);
        }

        if (contact.address) {
            lines.push(`ADR;TYPE=HOME:;;${contact.address};;;;`);
        }

        if (contact.organization) {
            lines.push(`ORG:${contact.organization}`);
        }

        if (contact.title) {
            lines.push(`TITLE:${contact.title}`);
        }

        if (contact.note) {
            lines.push(`NOTE:${contact.note}`);
        }

        lines.push('END:VCARD');
        return lines.join('\r\n');
    }

    /**
     * שיתוף מיקום
     *
     * @param {number} lat - קו רוחב
     * @param {number} lng - קו אורך
     * @param {string} name - שם המיקום
     * @returns {Promise<boolean>}
     */
    async shareLocation(lat, lng, name = '') {
        const url = `https://www.google.com/maps?q=${lat},${lng}`;
        const text = name ? `מיקום: ${name}` : 'מיקום';

        return await this.shareText({
            title: name || 'מיקום',
            text,
            url
        });
    }

    /**
     * שיתוף קבר (מותאם למערכת)
     *
     * @param {Object} grave - פרטי הקבר
     * @returns {Promise<boolean>}
     */
    async shareGrave(grave) {
        const title = `${grave.deceased_name || 'נפטר'}`;
        const text = this.formatGraveText(grave);
        const url = grave.url || window.location.href;

        // אם יש תמונה, שתף עם קובץ
        if (grave.photo && this.isFileShareSupported) {
            try {
                const response = await fetch(grave.photo);
                const blob = await response.blob();
                const file = new File([blob], `${grave.deceased_name || 'grave'}.jpg`, { type: blob.type });

                return await this.shareFiles({ files: [file], title, text });
            } catch (e) {
                // fallback לשיתוף טקסט
            }
        }

        return await this.shareText({ title, text, url });
    }

    /**
     * פורמט טקסט קבר
     */
    formatGraveText(grave) {
        const lines = [];

        if (grave.deceased_name) {
            lines.push(`שם: ${grave.deceased_name}`);
        }

        if (grave.hebrew_date_of_death || grave.date_of_death) {
            lines.push(`תאריך פטירה: ${grave.hebrew_date_of_death || grave.date_of_death}`);
        }

        if (grave.cemetery_name) {
            lines.push(`בית עלמין: ${grave.cemetery_name}`);
        }

        if (grave.section && grave.row && grave.grave_number) {
            lines.push(`מיקום: חלקה ${grave.section}, שורה ${grave.row}, קבר ${grave.grave_number}`);
        }

        return lines.join('\n');
    }

    /**
     * fallback לדפדפנים שלא תומכים
     */
    fallbackShare({ title, text, url }) {
        // נסה להעתיק ללוח
        const shareText = [title, text, url].filter(Boolean).join('\n');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shareText)
                .then(() => {
                    this.showToast('הקישור הועתק ללוח');
                })
                .catch(() => {
                    this.showFallbackDialog(shareText);
                });
            return true;
        }

        this.showFallbackDialog(shareText);
        return true;
    }

    /**
     * הצגת דיאלוג fallback
     */
    showFallbackDialog(text) {
        const dialog = document.createElement('div');
        dialog.className = 'web-share-fallback-dialog';
        dialog.innerHTML = `
            <div class="share-fallback-content">
                <h3>שיתוף</h3>
                <p>העתק את הטקסט הבא:</p>
                <textarea readonly>${text}</textarea>
                <div class="share-fallback-buttons">
                    <button onclick="this.closest('.web-share-fallback-dialog').remove()">סגור</button>
                </div>
            </div>
        `;

        // סגנון
        dialog.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;

        const content = dialog.querySelector('.share-fallback-content');
        content.style.cssText = `
            background: white;
            padding: 20px;
            border-radius: 12px;
            max-width: 90%;
            width: 320px;
            text-align: center;
        `;

        const textarea = dialog.querySelector('textarea');
        textarea.style.cssText = `
            width: 100%;
            height: 100px;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: none;
        `;

        const btn = dialog.querySelector('button');
        btn.style.cssText = `
            background: #007AFF;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            cursor: pointer;
        `;

        document.body.appendChild(dialog);

        // בחר את הטקסט
        textarea.select();
    }

    /**
     * הצגת הודעת toast
     */
    showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'web-share-toast';
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            z-index: 10000;
            animation: fadeInUp 0.3s ease;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }

    /**
     * בדיקה אם ניתן לשתף סוג קובץ מסוים
     *
     * @param {string} mimeType - סוג הקובץ
     * @returns {boolean}
     */
    canShareFileType(mimeType) {
        if (!this.isFileShareSupported) return false;

        const testFile = new File(['test'], 'test', { type: mimeType });
        return navigator.canShare({ files: [testFile] });
    }

    /**
     * קבלת רשימת סוגי קבצים נתמכים
     */
    getSupportedFileTypes() {
        const types = [
            { type: 'image/jpeg', name: 'JPEG Images' },
            { type: 'image/png', name: 'PNG Images' },
            { type: 'image/gif', name: 'GIF Images' },
            { type: 'image/webp', name: 'WebP Images' },
            { type: 'application/pdf', name: 'PDF Documents' },
            { type: 'text/plain', name: 'Text Files' },
            { type: 'text/vcard', name: 'vCard Contacts' },
            { type: 'video/mp4', name: 'MP4 Videos' },
            { type: 'audio/mpeg', name: 'MP3 Audio' }
        ];

        return types.filter(t => this.canShareFileType(t.type));
    }
}

// יצירת instance גלובלי
window.webShare = new WebShare();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebShare;
}
