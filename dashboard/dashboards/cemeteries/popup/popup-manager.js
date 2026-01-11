/**
 * PopupManager - מנהל פופ-אפ גנרי לחלוטין
 * תומך ב-iframe וגם HTML ישיר
 * תקשורת דו-כיוונית עם התוכן
 * @version 1.0.0
 */

class PopupManager {
    static popups = new Map();
    static maxZIndex = 10000;
    static minimizedContainer = null;
    static cssLoaded = false;

    /**
     * טוען את ה-CSS של הפופ-אפ (אם עדיין לא נטען)
     */
    static loadCSS() {
        const targetDoc = this.getTargetDocument();

        // בדוק אם כבר נטען
        if (targetDoc.getElementById('popup-manager-css')) {
            return;
        }

        // צור link element
        const link = targetDoc.createElement('link');
        link.id = 'popup-manager-css';
        link.rel = 'stylesheet';
        link.href = '/dashboard/dashboards/cemeteries/popup/popup.css';
        targetDoc.head.appendChild(link);

        this.cssLoaded = true;
        console.log('✅ Popup CSS loaded');
    }

    /**
     * מחזיר את ה-document הנכון (top window אם בתוך iframe)
     */
    static getTargetDocument() {
        try {
            // אם אנחנו בתוך iframe, השתמש ב-top window
            if (window.self !== window.top && window.top.document) {
                return window.top.document;
            }
        } catch (e) {
            // אם יש בעיית CORS, נשאר עם החלון הנוכחי
            console.warn('Cannot access parent window, using current window');
        }
        return document;
    }

    /**
     * מחזיר את ה-window הנכון (top window אם בתוך iframe)
     */
    static getTargetWindow() {
        try {
            if (window.self !== window.top && window.top) {
                return window.top;
            }
        } catch (e) {
            console.warn('Cannot access parent window, using current window');
        }
        return window;
    }

    /**
     * יצירת פופ-אפ חדש
     * @param {Object} config - קונפיגורציה
     * @returns {Popup} instance
     */
    static create(config) {
        // טען CSS אם עדיין לא נטען
        this.loadCSS();

        // יצירת ID ייחודי אם לא סופק
        const id = config.id || `popup-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

        // בדיקה אם קיים popup עם אותו ID
        if (this.popups.has(id)) {
            console.warn(`Popup with ID "${id}" already exists`);
            return this.popups.get(id);
        }

        // יצירת instance חדש
        const popup = new Popup(id, config);
        this.popups.set(id, popup);

        // יצירת ה-DOM ופתיחת הפופ-אפ
        popup.render();
        popup.show();

        return popup;
    }

    /**
     * קבלת popup לפי ID
     */
    static get(id) {
        return this.popups.get(id);
    }

    /**
     * סגירת popup
     */
    static close(id) {
        const popup = this.get(id);
        if (popup) {
            popup.close();
            this.popups.delete(id);
        }
    }

    /**
     * סגירת כל הפופ-אפים
     */
    static closeAll() {
        this.popups.forEach(popup => popup.close());
        this.popups.clear();
    }

    /**
     * קבלת Z-index חדש (להעלות popup למעלה)
     */
    static getNextZIndex() {
        return ++this.maxZIndex;
    }

    /**
     * יצירת container ל-popups ממוזערים
     */
    static getMinimizedContainer() {
        if (!this.minimizedContainer) {
            const targetDoc = this.getTargetDocument();
            this.minimizedContainer = targetDoc.createElement('div');
            this.minimizedContainer.className = 'popup-minimized-container';
            targetDoc.body.appendChild(this.minimizedContainer);
        }
        return this.minimizedContainer;
    }
}

/**
 * מחלקת Popup - מייצגת פופ-אפ בודד
 */
class Popup {
    constructor(id, config) {
        this.id = id;
        this.config = {
            // ברירות מחדל
            type: 'iframe', // iframe | html | ajax
            src: null,
            content: '',
            url: null,
            title: 'Popup',
            width: 800,
            height: 600,
            minWidth: 400,
            minHeight: 300,
            maxWidth: null,
            maxHeight: null,
            position: { x: 'center', y: 'center' },
            draggable: true,
            resizable: true,
            controls: {
                minimize: true,
                maximize: true,
                detach: true,
                close: true
            },
            onMinimize: null,
            onMaximize: null,
            onRestore: null,
            onDetach: null,
            onClose: null,
            onLoad: null,
            ...config
        };

        this.state = {
            mode: 'normal', // normal | minimized | maximized | detached
            lastMode: 'normal',
            position: { x: 0, y: 0 },
            size: { width: this.config.width, height: this.config.height },
            lastPosition: null,
            lastSize: null,
            zIndex: PopupManager.getNextZIndex()
        };

        this.elements = {
            container: null,
            header: null,
            content: null,
            iframe: null,
            resizeHandle: null
        };

        this.dragData = null;
        this.resizeData = null;
    }

    /**
     * יצירת ה-DOM
     */
    render() {
        const container = document.createElement('div');
        container.className = 'popup-container';
        container.id = this.id;
        container.style.cssText = `
            width: ${this.config.width}px;
            height: ${this.config.height}px;
            z-index: ${this.state.zIndex};
        `;

        // Header
        const header = this.createHeader();

        // Content
        const content = this.createContent();

        // Resize handle
        const resizeHandle = this.createResizeHandle();

        container.appendChild(header);
        container.appendChild(content);
        if (this.config.resizable) {
            container.appendChild(resizeHandle);
        }

        this.elements.container = container;
        this.elements.header = header;
        this.elements.content = content;
        this.elements.resizeHandle = resizeHandle;

        // הוסף לחלון הנכון (top window אם בתוך iframe)
        const targetDoc = PopupManager.getTargetDocument();
        targetDoc.body.appendChild(container);

        // אתחול אירועים
        this.initEvents();

        // מיקום
        this.position(this.config.position.x, this.config.position.y);
    }

    /**
     * יצירת Header
     */
    createHeader() {
        const header = document.createElement('div');
        header.className = 'popup-header';

        const title = document.createElement('span');
        title.className = 'popup-title';
        title.textContent = this.config.title;

        const controls = document.createElement('div');
        controls.className = 'popup-controls';

        // כפתורי בקרה
        if (this.config.controls.minimize) {
            const btnMin = this.createControlButton('minimize', '−', () => this.minimize());
            controls.appendChild(btnMin);
        }

        if (this.config.controls.maximize) {
            const btnMax = this.createControlButton('maximize', '□', () => this.toggleMaximize());
            controls.appendChild(btnMax);
        }

        if (this.config.controls.detach) {
            const btnDetach = this.createControlButton('detach', '↗', () => this.detach());
            controls.appendChild(btnDetach);
        }

        if (this.config.controls.close) {
            const btnClose = this.createControlButton('close', '×', () => this.close());
            controls.appendChild(btnClose);
        }

        header.appendChild(title);
        header.appendChild(controls);

        return header;
    }

    /**
     * יצירת כפתור בקרה
     */
    createControlButton(name, icon, onClick) {
        const btn = document.createElement('button');
        btn.className = `popup-control-btn popup-${name}`;
        btn.textContent = icon;
        btn.onclick = (e) => {
            e.stopPropagation();
            onClick();
        };
        return btn;
    }

    /**
     * יצירת Content
     */
    createContent() {
        const content = document.createElement('div');
        content.className = 'popup-content';

        if (this.config.type === 'iframe') {
            const iframe = document.createElement('iframe');
            iframe.className = 'popup-iframe';

            // העברת popup ID דרך URL
            const separator = this.config.src.includes('?') ? '&' : '?';
            iframe.src = `${this.config.src}${separator}popupId=${this.id}`;

            iframe.onload = () => {
                this.onContentLoad();
            };

            content.appendChild(iframe);
            this.elements.iframe = iframe;

        } else if (this.config.type === 'html') {
            content.innerHTML = this.config.content;
            setTimeout(() => this.onContentLoad(), 0);

        } else if (this.config.type === 'ajax') {
            this.loadAjaxContent(content);
        }

        return content;
    }

    /**
     * טעינת תוכן AJAX
     */
    async loadAjaxContent(content) {
        try {
            const response = await fetch(this.config.url);
            const html = await response.text();
            content.innerHTML = html;
            this.onContentLoad();
        } catch (error) {
            content.innerHTML = `<div class="popup-error">שגיאה בטעינת התוכן: ${error.message}</div>`;
        }
    }

    /**
     * callback לאחר טעינת תוכן
     */
    onContentLoad() {
        if (this.config.onLoad) {
            this.config.onLoad(this);
        }
        this.notifyContent('loaded', { popupId: this.id });
    }

    /**
     * יצירת resize handle
     */
    createResizeHandle() {
        const handle = document.createElement('div');
        handle.className = 'popup-resize-handle';
        return handle;
    }

    /**
     * אתחול אירועים
     */
    initEvents() {
        // Dragging
        if (this.config.draggable) {
            this.elements.header.addEventListener('mousedown', (e) => this.startDrag(e));
        }

        // Resizing
        if (this.config.resizable) {
            this.elements.resizeHandle.addEventListener('mousedown', (e) => this.startResize(e));
        }

        // Focus (העלאת z-index)
        this.elements.container.addEventListener('mousedown', () => this.focus());

        // האזנה להודעות מהתוכן (אם iframe)
        if (this.config.type === 'iframe') {
            window.addEventListener('message', (e) => this.handleMessage(e));
        }
    }

    /**
     * התחלת גרירה
     */
    startDrag(e) {
        if (this.state.mode === 'maximized') return;

        e.preventDefault();
        this.focus();

        const rect = this.elements.container.getBoundingClientRect();
        this.dragData = {
            startX: e.clientX,
            startY: e.clientY,
            offsetX: e.clientX - rect.left,
            offsetY: e.clientY - rect.top
        };

        const targetDoc = PopupManager.getTargetDocument();
        targetDoc.addEventListener('mousemove', this.onDrag);
        targetDoc.addEventListener('mouseup', this.stopDrag);

        this.elements.container.classList.add('popup-dragging');
    }

    onDrag = (e) => {
        if (!this.dragData) return;

        const x = e.clientX - this.dragData.offsetX;
        const y = e.clientY - this.dragData.offsetY;

        this.elements.container.style.left = `${x}px`;
        this.elements.container.style.top = `${y}px`;

        this.state.position = { x, y };
    }

    stopDrag = () => {
        this.dragData = null;
        const targetDoc = PopupManager.getTargetDocument();
        targetDoc.removeEventListener('mousemove', this.onDrag);
        targetDoc.removeEventListener('mouseup', this.stopDrag);
        this.elements.container.classList.remove('popup-dragging');
    }

    /**
     * התחלת שינוי גודל
     */
    startResize(e) {
        if (this.state.mode === 'maximized') return;

        e.preventDefault();
        e.stopPropagation();
        this.focus();

        const rect = this.elements.container.getBoundingClientRect();
        this.resizeData = {
            startX: e.clientX,
            startY: e.clientY,
            startWidth: rect.width,
            startHeight: rect.height
        };

        const targetDoc = PopupManager.getTargetDocument();
        targetDoc.addEventListener('mousemove', this.onResize);
        targetDoc.addEventListener('mouseup', this.stopResize);

        this.elements.container.classList.add('popup-resizing');
    }

    onResize = (e) => {
        if (!this.resizeData) return;

        const deltaX = e.clientX - this.resizeData.startX;
        const deltaY = e.clientY - this.resizeData.startY;

        let newWidth = this.resizeData.startWidth + deltaX;
        let newHeight = this.resizeData.startHeight + deltaY;

        // אילוצים
        newWidth = Math.max(this.config.minWidth, newWidth);
        newHeight = Math.max(this.config.minHeight, newHeight);

        if (this.config.maxWidth) newWidth = Math.min(this.config.maxWidth, newWidth);
        if (this.config.maxHeight) newHeight = Math.min(this.config.maxHeight, newHeight);

        this.elements.container.style.width = `${newWidth}px`;
        this.elements.container.style.height = `${newHeight}px`;

        this.state.size = { width: newWidth, height: newHeight };
    }

    stopResize = () => {
        this.resizeData = null;
        const targetDoc = PopupManager.getTargetDocument();
        targetDoc.removeEventListener('mousemove', this.onResize);
        targetDoc.removeEventListener('mouseup', this.stopResize);
        this.elements.container.classList.remove('popup-resizing');
    }

    /**
     * מיקום הפופ-אפ
     */
    position(x, y) {
        let left, top;
        const targetWindow = PopupManager.getTargetWindow();

        if (x === 'center') {
            left = (targetWindow.innerWidth - this.state.size.width) / 2;
        } else {
            left = x;
        }

        if (y === 'center') {
            top = (targetWindow.innerHeight - this.state.size.height) / 2;
        } else {
            top = y;
        }

        this.elements.container.style.left = `${left}px`;
        this.elements.container.style.top = `${top}px`;

        this.state.position = { x: left, y: top };
    }

    /**
     * הבאת הפופ-אפ לחזית
     */
    focus() {
        this.state.zIndex = PopupManager.getNextZIndex();
        this.elements.container.style.zIndex = this.state.zIndex;
    }

    /**
     * הצגת פופ-אפ
     */
    show() {
        this.elements.container.classList.add('popup-visible');
    }

    /**
     * מזעור
     */
    minimize() {
        if (this.state.mode === 'minimized') return;

        this.state.lastMode = this.state.mode;
        this.state.mode = 'minimized';

        // שמירת מצב נוכחי
        if (this.state.lastMode === 'normal') {
            this.state.lastPosition = { ...this.state.position };
            this.state.lastSize = { ...this.state.size };
        }

        // העברה ל-minimized container
        const minimizedContainer = PopupManager.getMinimizedContainer();
        this.elements.container.classList.add('popup-minimized');
        this.elements.container.classList.remove('popup-maximized');

        // יצירת כרטיס ממוזער
        const minimizedCard = document.createElement('div');
        minimizedCard.className = 'popup-minimized-card';
        minimizedCard.innerHTML = `
            <span class="popup-minimized-title">${this.config.title}</span>
            <button class="popup-minimized-restore" title="שחזר">↑</button>
        `;
        minimizedCard.querySelector('.popup-minimized-restore').onclick = () => this.restore();
        minimizedCard.dataset.popupId = this.id;

        this.elements.minimizedCard = minimizedCard;
        minimizedContainer.appendChild(minimizedCard);

        this.elements.container.style.display = 'none';

        this.notifyContent('minimized');
        if (this.config.onMinimize) this.config.onMinimize(this);
    }

    /**
     * מסך מלא
     */
    maximize() {
        if (this.state.mode === 'maximized') return;

        this.state.lastMode = this.state.mode;
        this.state.mode = 'maximized';

        // שמירת מצב נוכחי
        if (this.state.lastMode === 'normal') {
            this.state.lastPosition = { ...this.state.position };
            this.state.lastSize = { ...this.state.size };
        }

        this.elements.container.classList.add('popup-maximized');
        this.elements.container.style.left = '0';
        this.elements.container.style.top = '0';
        this.elements.container.style.width = '100vw';
        this.elements.container.style.height = '100vh';

        this.notifyContent('maximized');
        if (this.config.onMaximize) this.config.onMaximize(this);
    }

    /**
     * שחזור ממוזער/ממקסם
     */
    restore() {
        const wasMinimized = this.state.mode === 'minimized';

        this.state.mode = 'normal';

        if (wasMinimized) {
            // הסרת כרטיס ממוזער
            if (this.elements.minimizedCard) {
                this.elements.minimizedCard.remove();
                this.elements.minimizedCard = null;
            }
            this.elements.container.style.display = '';
        }

        this.elements.container.classList.remove('popup-minimized', 'popup-maximized');

        // שחזור מצב קודם
        if (this.state.lastPosition) {
            this.elements.container.style.left = `${this.state.lastPosition.x}px`;
            this.elements.container.style.top = `${this.state.lastPosition.y}px`;
            this.state.position = this.state.lastPosition;
        }

        if (this.state.lastSize) {
            this.elements.container.style.width = `${this.state.lastSize.width}px`;
            this.elements.container.style.height = `${this.state.lastSize.height}px`;
            this.state.size = this.state.lastSize;
        }

        this.focus();
        this.notifyContent('restored');
        if (this.config.onRestore) this.config.onRestore(this);
    }

    /**
     * toggle בין רגיל למקסימום
     */
    toggleMaximize() {
        if (this.state.mode === 'maximized') {
            this.restore();
        } else {
            this.maximize();
        }
    }

    /**
     * ניתוק לחלון נפרד
     */
    detach() {
        // שמירת מצב
        const state = {
            popupId: this.id,
            config: this.config,
            state: {
                scrollPosition: this.getScrollPosition(),
                formData: this.getFormData()
            }
        };

        // שמירה ב-localStorage
        localStorage.setItem(`popup-detached-${this.id}`, JSON.stringify(state));

        // פתיחת חלון חדש
        const features = `width=${this.state.size.width},height=${this.state.size.height},menubar=no,toolbar=no,location=no,status=no`;
        const newWindow = window.open(
            `/dashboard/dashboards/cemeteries/popup/popup-detached.php?id=${this.id}`,
            this.id,
            features
        );

        if (!newWindow) {
            alert('חוסם פופ-אפ מונע פתיחת חלון חדש. אנא אפשר פופ-אפים לאתר זה.');
            return;
        }

        // סגירת הפופ-אפ המקורי
        this.state.mode = 'detached';
        this.notifyContent('detached', { windowRef: newWindow });
        if (this.config.onDetach) this.config.onDetach(this, newWindow);

        this.close(false); // סגור בלי callback
    }

    /**
     * קבלת מיקום גלילה
     */
    getScrollPosition() {
        if (this.config.type === 'iframe' && this.elements.iframe) {
            try {
                return {
                    x: this.elements.iframe.contentWindow.scrollX,
                    y: this.elements.iframe.contentWindow.scrollY
                };
            } catch (e) {
                return { x: 0, y: 0 };
            }
        }
        return { x: 0, y: 0 };
    }

    /**
     * קבלת נתוני טפסים
     */
    getFormData() {
        // TODO: אימפלמנטציה לשמירת נתוני טפסים
        return {};
    }

    /**
     * סגירה
     */
    close(triggerCallback = true) {
        // שאל את התוכן אם ניתן לסגור
        const canClose = this.notifyContent('closing');
        if (canClose === false) return;

        if (triggerCallback && this.config.onClose) {
            const result = this.config.onClose(this);
            if (result === false) return; // ביטול סגירה
        }

        // ניקוי
        if (this.elements.minimizedCard) {
            this.elements.minimizedCard.remove();
        }

        this.elements.container.remove();
        PopupManager.popups.delete(this.id);
    }

    /**
     * עדכון כותרת
     */
    setTitle(title) {
        this.config.title = title;
        const titleElement = this.elements.header.querySelector('.popup-title');
        if (titleElement) {
            titleElement.textContent = title;
        }
    }

    /**
     * עדכון תוכן (רק ל-HTML)
     */
    setContent(content) {
        if (this.config.type === 'html') {
            this.elements.content.innerHTML = content;
        }
    }

    /**
     * שינוי גודל
     */
    resize(width, height) {
        if (this.state.mode === 'maximized') return;

        this.elements.container.style.width = `${width}px`;
        this.elements.container.style.height = `${height}px`;
        this.state.size = { width, height };
    }

    /**
     * שליחת הודעה לתוכן
     */
    notifyContent(event, data = {}) {
        const message = {
            type: 'popup-event',
            popupId: this.id,
            event,
            data
        };

        if (this.config.type === 'iframe' && this.elements.iframe) {
            try {
                this.elements.iframe.contentWindow.postMessage(message, '*');
            } catch (e) {
                console.warn('Failed to send message to iframe:', e);
            }
        } else {
            // HTML ישיר - שלח custom event
            const customEvent = new CustomEvent(`popup-${event}`, { detail: message });
            this.elements.content.dispatchEvent(customEvent);
        }
    }

    /**
     * קבלת הודעות מהתוכן
     */
    handleMessage(e) {
        const data = e.data;

        if (data.type !== 'popup-api' || data.popupId !== this.id) return;

        const { action, payload } = data;

        switch (action) {
            case 'setTitle':
                this.setTitle(payload.title);
                break;
            case 'resize':
                this.resize(payload.width, payload.height);
                break;
            case 'minimize':
                this.minimize();
                break;
            case 'maximize':
                this.maximize();
                break;
            case 'restore':
                this.restore();
                break;
            case 'close':
                this.close();
                break;
            case 'detach':
                this.detach();
                break;
            default:
                console.warn(`Unknown popup action: ${action}`);
        }
    }
}

// Export
window.PopupManager = PopupManager;
window.Popup = Popup;
