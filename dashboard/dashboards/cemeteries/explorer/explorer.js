/**
 * FileExplorer - סייר קבצים
 * Version: 1.0.0
 */

class FileExplorer {
    constructor(containerId, unicId, options = {}) {
        this.container = document.getElementById(containerId);
        this.unicId = unicId;
        this.currentPath = '';
        this.items = [];
        this.selectedItems = []; // מערך של פריטים נבחרים
        this.apiBase = options.apiBase || '/dashboard/dashboards/cemeteries/explorer/explorer-api.php';
        this.sortBy = 'name'; // name, date, size
        this.sortOrder = 'asc'; // asc, desc
        this.uploadingCount = 0;

        if (!this.container) {
            console.error('Explorer container not found:', containerId);
            return;
        }

        this.init();
    }

    init() {
        this.clipboard = null; // לוח העתקה/גזירה
        this.viewMode = 'grid'; // grid או list
        this.iconSize = 'medium'; // small, medium, large
        this.render();
        this.loadFiles();
        this.setupDragDrop();
        this.setupBackgroundContextMenu();
    }

    render() {
        this.container.innerHTML = `
            <div class="file-explorer">
                <div class="explorer-toolbar">
                    <div class="explorer-breadcrumb">
                        <span class="breadcrumb-home" onclick="window.explorer.goToRoot()" title="חזרה לשורש"><i class="fas fa-folder-open"></i></span>
                        <span class="breadcrumb-path"></span>
                    </div>
                    <div class="explorer-actions">
                        <button type="button" class="explorer-btn" onclick="window.explorer.refresh()" title="רענון">
                            <i class="fas fa-sync-alt"></i>
                        </button>

                        <!-- תפריט תצוגה -->
                        <div class="explorer-dropdown">
                            <button type="button" class="explorer-btn" onclick="window.explorer.toggleDropdown('viewMenu')" title="תצוגה">
                                <i class="fas fa-th-large"></i> תצוגה <i class="fas fa-caret-down" style="margin-right: 5px;"></i>
                            </button>
                            <div class="explorer-dropdown-menu" id="viewMenu">
                                <div class="menu-section-title">מצב תצוגה</div>
                                <a href="javascript:void(0)" onclick="window.explorer.setViewMode('grid')" class="view-option" data-view="grid">
                                    <i class="fas fa-th-large"></i> תצוגת רשת
                                </a>
                                <a href="javascript:void(0)" onclick="window.explorer.setViewMode('list')" class="view-option" data-view="list">
                                    <i class="fas fa-list"></i> תצוגת רשימה
                                </a>
                                <hr>
                                <div class="menu-section-title">גודל אייקונים</div>
                                <a href="javascript:void(0)" onclick="window.explorer.setIconSize('small')" class="size-option" data-size="small">
                                    <i class="fas fa-compress-alt"></i> קטן
                                </a>
                                <a href="javascript:void(0)" onclick="window.explorer.setIconSize('medium')" class="size-option" data-size="medium">
                                    <i class="fas fa-expand-alt"></i> בינוני
                                </a>
                                <a href="javascript:void(0)" onclick="window.explorer.setIconSize('large')" class="size-option" data-size="large">
                                    <i class="fas fa-expand"></i> גדול
                                </a>
                            </div>
                        </div>

                        <!-- תפריט מיון -->
                        <div class="explorer-dropdown">
                            <button type="button" class="explorer-btn" onclick="window.explorer.toggleDropdown('sortMenu')" title="מיון">
                                <i class="fas fa-sort"></i> מיון <i class="fas fa-caret-down" style="margin-right: 5px;"></i>
                            </button>
                            <div class="explorer-dropdown-menu" id="sortMenu">
                                <a href="javascript:void(0)" onclick="window.explorer.setSort('name', 'asc')">
                                    <i class="fas fa-sort-alpha-down"></i> שם (א-ת)
                                </a>
                                <a href="javascript:void(0)" onclick="window.explorer.setSort('name', 'desc')">
                                    <i class="fas fa-sort-alpha-up"></i> שם (ת-א)
                                </a>
                                <hr>
                                <a href="javascript:void(0)" onclick="window.explorer.setSort('date', 'desc')">
                                    <i class="fas fa-clock"></i> חדש ביותר
                                </a>
                                <a href="javascript:void(0)" onclick="window.explorer.setSort('date', 'asc')">
                                    <i class="fas fa-history"></i> ישן ביותר
                                </a>
                                <hr>
                                <a href="javascript:void(0)" onclick="window.explorer.setSort('size', 'desc')">
                                    <i class="fas fa-weight"></i> גדול ביותר
                                </a>
                                <a href="javascript:void(0)" onclick="window.explorer.setSort('size', 'asc')">
                                    <i class="fas fa-feather"></i> קטן ביותר
                                </a>
                            </div>
                        </div>

                        <!-- תפריט חדש -->
                        <div class="explorer-dropdown">
                            <button type="button" class="explorer-btn" onclick="window.explorer.toggleDropdown('newMenu')" title="יצירה">
                                <i class="fas fa-plus"></i> חדש <i class="fas fa-caret-down" style="margin-right: 5px;"></i>
                            </button>
                            <div class="explorer-dropdown-menu" id="newMenu">
                                <a href="javascript:void(0)" onclick="window.explorer.createFolder(); window.explorer.closeAllDropdowns();">
                                    <i class="fas fa-folder-plus"></i> תיקייה חדשה
                                </a>
                            </div>
                        </div>

                        <button type="button" class="explorer-btn primary" onclick="document.getElementById('explorerFileInput').click()" title="העלאת קובץ">
                            <i class="fas fa-upload"></i> העלאה
                        </button>
                        <input type="file" id="explorerFileInput" class="explorer-file-input" multiple onchange="window.explorer.handleFileSelect(event)">
                    </div>
                </div>

                <!-- אינדיקטור העלאה -->
                <div class="explorer-upload-indicator" id="uploadIndicator" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>מעלה קבצים... <span id="uploadCount">0</span></span>
                </div>

                <div class="explorer-content">
                    <div class="explorer-loading">
                        <i class="fas fa-spinner"></i>
                    </div>
                </div>
            </div>
        `;

        this.contentEl = this.container.querySelector('.explorer-content');
        this.breadcrumbEl = this.container.querySelector('.breadcrumb-path');
        this.uploadIndicator = this.container.querySelector('#uploadIndicator');
        this.uploadCountEl = this.container.querySelector('#uploadCount');

        // סגור dropdowns בלחיצה מחוץ
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.explorer-dropdown')) {
                this.closeAllDropdowns();
            }
        });
    }

    toggleDropdown(menuId) {
        // סגור כל התפריטים האחרים
        this.container.querySelectorAll('.explorer-dropdown-menu').forEach(menu => {
            if (menu.id !== menuId) {
                menu.classList.remove('show');
            }
        });
        // פתח/סגור את התפריט הנוכחי
        const menu = document.getElementById(menuId);
        if (menu) {
            menu.classList.toggle('show');
        }
    }

    closeAllDropdowns() {
        this.container.querySelectorAll('.explorer-dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }

    setSort(sortBy, order) {
        this.sortBy = sortBy;
        this.sortOrder = order;
        this.closeAllDropdowns();
        this.sortAndRenderItems();
    }

    setViewMode(mode) {
        this.viewMode = mode;
        this.closeAllDropdowns();
        this.updateViewClasses();
        this.updateMenuSelection();
    }

    setIconSize(size) {
        this.iconSize = size;
        this.closeAllDropdowns();
        this.updateViewClasses();
        this.updateMenuSelection();
    }

    updateViewClasses() {
        const content = this.contentEl;
        // הסר classes קודמים
        content.classList.remove('view-grid', 'view-list', 'size-small', 'size-medium', 'size-large');
        // הוסף classes חדשים
        content.classList.add(`view-${this.viewMode}`, `size-${this.iconSize}`);
    }

    updateMenuSelection() {
        // עדכון בחירת תצוגה
        this.container.querySelectorAll('.view-option').forEach(opt => {
            opt.classList.toggle('active', opt.dataset.view === this.viewMode);
        });
        // עדכון בחירת גודל
        this.container.querySelectorAll('.size-option').forEach(opt => {
            opt.classList.toggle('active', opt.dataset.size === this.iconSize);
        });
    }

    sortAndRenderItems() {
        // מיון לפי הגדרות
        this.items.sort((a, b) => {
            // תיקיות תמיד קודם
            if (a.isDir && !b.isDir) return -1;
            if (!a.isDir && b.isDir) return 1;

            let result = 0;
            switch (this.sortBy) {
                case 'name':
                    result = a.name.localeCompare(b.name, 'he');
                    break;
                case 'date':
                    result = new Date(a.modified) - new Date(b.modified);
                    break;
                case 'size':
                    result = (a.size || 0) - (b.size || 0);
                    break;
            }

            return this.sortOrder === 'desc' ? -result : result;
        });

        this.renderItems();
    }

    async loadFiles(path = '') {
        this.currentPath = path;
        this.showLoading();

        try {
            const url = `${this.apiBase}?action=list&unicId=${this.unicId}&path=${encodeURIComponent(path)}`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.success) {
                this.items = result.data;
                this.sortAndRenderItems();
                this.updateBreadcrumb(result.breadcrumb);
            } else {
                this.showError(result.error);
            }
        } catch (error) {
            console.error('Error loading files:', error);
            this.showError('שגיאה בטעינת הקבצים');
        }
    }

    renderItems() {
        if (this.items.length === 0) {
            this.contentEl.innerHTML = `
                <div class="explorer-empty">
                    <i class="fas fa-folder-open"></i>
                    <p>התיקייה ריקה</p>
                </div>
            `;
            return;
        }

        const html = `
            <div class="explorer-grid">
                ${this.items.map(item => this.renderItem(item)).join('')}
            </div>
        `;

        this.contentEl.innerHTML = html;
        this.updateViewClasses();
        this.updateMenuSelection();
    }

    renderItem(item) {
        const icon = this.getIcon(item);
        const thumb = item.isImage ? `<img src="${item.thumbUrl}" alt="${item.name}">` : `<i class="${icon}"></i>`;
        const escapedPath = item.path.replace(/'/g, "\\'");
        const escapedName = item.name.replace(/'/g, "\\'");

        // פורמט תאריך
        const date = item.modified ? new Date(item.modified).toLocaleDateString('he-IL') : '';
        // פורמט גודל
        const size = item.isDir ? '' : this.formatSize(item.size || 0);

        return `
            <div class="explorer-item"
                 data-path="${item.path}"
                 data-name="${item.name}"
                 data-is-dir="${item.isDir}"
                 onclick="window.explorer.selectItem(event, this)"
                 ondblclick="window.explorer.openItem('${escapedPath}', ${item.isDir})"
                 oncontextmenu="window.explorer.showContextMenu(event, '${escapedPath}', '${escapedName}', ${item.isDir})">
                <div class="explorer-item-icon">
                    ${thumb}
                </div>
                <div class="explorer-item-name">${item.name}</div>
                <div class="explorer-item-date">${date}</div>
                <div class="explorer-item-size">${size}</div>
            </div>
        `;
    }

    formatSize(bytes) {
        if (bytes === 0) return '';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    getIcon(item) {
        if (item.isDir) return 'fas fa-folder';

        const ext = item.ext?.toLowerCase();
        const icons = {
            'pdf': 'fas fa-file-pdf',
            'doc': 'fas fa-file-word',
            'docx': 'fas fa-file-word',
            'xls': 'fas fa-file-excel',
            'xlsx': 'fas fa-file-excel',
            'jpg': 'fas fa-file-image',
            'jpeg': 'fas fa-file-image',
            'png': 'fas fa-file-image',
            'gif': 'fas fa-file-image',
            'txt': 'fas fa-file-alt'
        };

        return icons[ext] || 'fas fa-file';
    }

    updateBreadcrumb(parts) {
        if (!parts || parts.length === 0) {
            this.breadcrumbEl.innerHTML = '';
            return;
        }

        let html = '';
        let path = '';

        // קישור לשורש
        html += `<span class="breadcrumb-separator">›</span>`;
        html += `<a href="javascript:void(0)" class="breadcrumb-link" onclick="window.explorer.goToRoot()"><i class="fas fa-folder breadcrumb-folder-icon"></i>שורש</a>`;

        parts.forEach((part, index) => {
            path += (index > 0 ? '/' : '') + part;
            html += `<span class="breadcrumb-separator">›</span>`;

            if (index === parts.length - 1) {
                // האחרון - לא לחיץ
                html += `<span class="breadcrumb-current"><i class="fas fa-folder-open breadcrumb-folder-icon"></i>${part}</span>`;
            } else {
                // לחיץ
                html += `<a href="javascript:void(0)" class="breadcrumb-link" onclick="window.explorer.loadFiles('${path}')"><i class="fas fa-folder breadcrumb-folder-icon"></i>${part}</a>`;
            }
        });

        this.breadcrumbEl.innerHTML = html;
    }

    selectItem(event, el) {
        const path = el.dataset.path;
        const isCtrl = event.ctrlKey || event.metaKey; // Ctrl או Cmd (Mac)
        const isShift = event.shiftKey;

        if (isCtrl) {
            // Ctrl+Click - הוסף/הסר מהבחירה
            if (this.selectedItems.includes(path)) {
                // הסר מהבחירה
                this.selectedItems = this.selectedItems.filter(p => p !== path);
                el.classList.remove('selected');
            } else {
                // הוסף לבחירה
                this.selectedItems.push(path);
                el.classList.add('selected');
            }
        } else if (isShift && this.selectedItems.length > 0) {
            // Shift+Click - בחר טווח
            const allItems = Array.from(this.container.querySelectorAll('.explorer-item'));
            const lastSelectedPath = this.selectedItems[this.selectedItems.length - 1];
            const lastIndex = allItems.findIndex(item => item.dataset.path === lastSelectedPath);
            const currentIndex = allItems.findIndex(item => item.dataset.path === path);

            const start = Math.min(lastIndex, currentIndex);
            const end = Math.max(lastIndex, currentIndex);

            // נקה בחירה קודמת
            this.clearSelection();

            // בחר את הטווח
            for (let i = start; i <= end; i++) {
                const itemPath = allItems[i].dataset.path;
                this.selectedItems.push(itemPath);
                allItems[i].classList.add('selected');
            }
        } else {
            // לחיצה רגילה - בחר רק את הפריט הזה
            this.clearSelection();
            this.selectedItems = [path];
            el.classList.add('selected');
        }
    }

    clearSelection() {
        this.container.querySelectorAll('.explorer-item.selected').forEach(item => {
            item.classList.remove('selected');
        });
        this.selectedItems = [];
    }

    openItem(path, isDir) {
        if (isDir) {
            this.loadFiles(path);
        } else {
            this.previewFile(path);
        }
    }

    previewFile(path) {
        const item = this.items.find(i => i.path === path);
        if (!item) return;

        // תמונות - תצוגה מקדימה
        if (item.isImage) {
            const overlay = document.createElement('div');
            overlay.className = 'explorer-preview-overlay';
            overlay.onclick = () => overlay.remove();
            overlay.innerHTML = `
                <div class="explorer-preview-content" onclick="event.stopPropagation()">
                    <button class="explorer-preview-close" onclick="this.closest('.explorer-preview-overlay').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                    <img src="${this.apiBase}?action=thumb&unicId=${this.unicId}&path=${encodeURIComponent(path)}">
                    <div class="explorer-preview-name">${item.name}</div>
                </div>
            `;
            document.body.appendChild(overlay);
        } else {
            // קבצים אחרים - הורדה
            window.open(`${this.apiBase}?action=download&unicId=${this.unicId}&path=${encodeURIComponent(path)}`, '_blank');
        }
    }

    goToRoot() {
        this.loadFiles('');
    }

    refresh() {
        this.loadFiles(this.currentPath);
    }

    async createFolder() {
        const name = prompt('שם התיקייה החדשה:');
        if (!name) return;

        try {
            const response = await fetch(`${this.apiBase}?action=createFolder&unicId=${this.unicId}&path=${encodeURIComponent(this.currentPath)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name })
            });

            const result = await response.json();

            if (result.success) {
                this.refresh();
            } else {
                alert('שגיאה: ' + result.error);
            }
        } catch (error) {
            console.error('Error creating folder:', error);
            alert('שגיאה ביצירת התיקייה');
        }
    }

    async deleteItem(path) {
        if (!confirm('האם למחוק את הפריט?')) return;

        try {
            const response = await fetch(`${this.apiBase}?action=delete&unicId=${this.unicId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ path })
            });

            const result = await response.json();

            if (result.success) {
                this.refresh();
            } else {
                alert('שגיאה: ' + result.error);
            }
        } catch (error) {
            console.error('Error deleting item:', error);
            alert('שגיאה במחיקה');
        }
    }

    async deleteItems() {
        this.hideContextMenu();
        const count = this.selectedItems.length;
        const confirmMsg = count === 1 ? 'האם למחוק את הפריט?' : `האם למחוק ${count} פריטים?`;
        if (!confirm(confirmMsg)) return;

        try {
            // מחק את כל הפריטים אחד אחד
            for (const path of this.selectedItems) {
                const response = await fetch(`${this.apiBase}?action=delete&unicId=${this.unicId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path })
                });
                const result = await response.json();
                if (!result.success) {
                    console.error('Error deleting:', path, result.error);
                }
            }
            this.selectedItems = [];
            this.refresh();
        } catch (error) {
            console.error('Error deleting items:', error);
            alert('שגיאה במחיקה');
        }
    }

    async handleFileSelect(event) {
        const files = Array.from(event.target.files);
        if (!files.length) return;

        // הצג אינדיקטור העלאה
        this.showUploadIndicator(files.length);

        // העלה את כל הקבצים במקביל
        const uploadPromises = files.map(file => this.uploadFile(file));
        await Promise.all(uploadPromises);

        // הסתר אינדיקטור וריענן
        this.hideUploadIndicator();
        event.target.value = '';
        this.refresh();
    }

    showUploadIndicator(count) {
        this.uploadingCount = count;
        if (this.uploadIndicator) {
            this.uploadIndicator.style.display = 'flex';
            this.uploadCountEl.textContent = count;
        }
    }

    hideUploadIndicator() {
        this.uploadingCount = 0;
        if (this.uploadIndicator) {
            this.uploadIndicator.style.display = 'none';
        }
    }

    updateUploadCount() {
        this.uploadingCount--;
        if (this.uploadCountEl) {
            this.uploadCountEl.textContent = this.uploadingCount;
        }
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            const url = `${this.apiBase}?action=upload&unicId=${this.unicId}&path=${encodeURIComponent(this.currentPath)}`;
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                alert('שגיאה בהעלאת ' + file.name + ': ' + result.error);
            }
        } catch (error) {
            console.error('Error uploading file:', error);
            alert('שגיאה בהעלאת ' + file.name);
        } finally {
            this.updateUploadCount();
        }
    }

    setupDragDrop() {
        const content = this.contentEl;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            content.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            content.addEventListener(eventName, () => {
                content.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            content.addEventListener(eventName, () => {
                content.classList.remove('drag-over');
            });
        });

        content.addEventListener('drop', async (e) => {
            const files = Array.from(e.dataTransfer.files);
            if (!files.length) return;

            // הצג אינדיקטור העלאה
            this.showUploadIndicator(files.length);

            // העלה את כל הקבצים במקביל
            const uploadPromises = files.map(file => this.uploadFile(file));
            await Promise.all(uploadPromises);

            // הסתר אינדיקטור וריענן
            this.hideUploadIndicator();
            this.refresh();
        });
    }

    showLoading() {
        this.contentEl.innerHTML = `
            <div class="explorer-loading">
                <i class="fas fa-spinner"></i>
            </div>
        `;
    }

    showError(message) {
        this.contentEl.innerHTML = `
            <div class="explorer-empty">
                <i class="fas fa-exclamation-triangle" style="color: #f44336;"></i>
                <p>${message}</p>
            </div>
        `;
    }

    // ========================================
    // תפריט קונטקסט (לחיצה ימנית)
    // ========================================

    showContextMenu(event, path, name, isDir) {
        event.preventDefault();
        event.stopPropagation();

        // הסר תפריט קודם אם קיים
        this.hideContextMenu();

        // אם הפריט הנלחץ לא נבחר, בחר אותו בלבד
        if (!this.selectedItems.includes(path)) {
            this.clearSelection();
            this.selectedItems = [path];
            const el = this.container.querySelector(`[data-path="${path}"]`);
            if (el) el.classList.add('selected');
        }

        const count = this.selectedItems.length;
        const countText = count > 1 ? ` (${count} פריטים)` : '';
        const isMultiple = count > 1;

        const menu = document.createElement('div');
        menu.className = 'explorer-context-menu';
        menu.id = 'explorerContextMenu';
        menu.innerHTML = `
            <a href="javascript:void(0)" onclick="window.explorer.copyItems()">
                <i class="fas fa-copy"></i> העתק${countText}
            </a>
            <a href="javascript:void(0)" onclick="window.explorer.cutItems()">
                <i class="fas fa-cut"></i> גזור${countText}
            </a>
            <hr>
            <a href="javascript:void(0)" class="${isMultiple ? 'disabled' : ''}" onclick="${isMultiple ? 'void(0)' : `window.explorer.renameItem('${path}', '${name}')`}">
                <i class="fas fa-edit"></i> שנה שם
            </a>
            <hr>
            <a href="javascript:void(0)" class="danger" onclick="window.explorer.deleteItems()">
                <i class="fas fa-trash"></i> מחק${countText}
            </a>
        `;

        // מיקום התפריט
        menu.style.left = event.clientX + 'px';
        menu.style.top = event.clientY + 'px';

        document.body.appendChild(menu);

        // סגור בלחיצה מחוץ לתפריט
        setTimeout(() => {
            document.addEventListener('click', this.hideContextMenu);
            document.addEventListener('contextmenu', this.hideContextMenu);
        }, 10);
    }

    hideContextMenu() {
        const menu = document.getElementById('explorerContextMenu');
        if (menu) {
            menu.remove();
        }
        document.removeEventListener('click', window.explorer?.hideContextMenu);
        document.removeEventListener('contextmenu', window.explorer?.hideContextMenu);
    }

    copyItems() {
        this.clipboard = { paths: [...this.selectedItems], action: 'copy' };
        this.hideContextMenu();
        console.log('📋 הועתקו:', this.clipboard.paths.length, 'פריטים');
    }

    cutItems() {
        this.clipboard = { paths: [...this.selectedItems], action: 'cut' };
        this.hideContextMenu();
        // סמן ויזואלית את הפריטים שנגזרו
        this.container.querySelectorAll('.explorer-item').forEach(item => {
            if (this.clipboard.paths.includes(item.dataset.path)) {
                item.classList.add('cut');
            }
        });
        console.log('✂️ נגזרו:', this.clipboard.paths.length, 'פריטים');
    }

    async renameItem(path, currentName) {
        this.hideContextMenu();

        const newName = prompt('שם חדש:', currentName);
        if (!newName || newName === currentName) return;

        try {
            const response = await fetch(`${this.apiBase}?action=rename&unicId=${this.unicId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ oldPath: path, newName })
            });

            const result = await response.json();

            if (result.success) {
                this.refresh();
            } else {
                alert('שגיאה: ' + result.error);
            }
        } catch (error) {
            console.error('Error renaming item:', error);
            alert('שגיאה בשינוי שם');
        }
    }

    async pasteItems() {
        if (!this.clipboard || !this.clipboard.paths || this.clipboard.paths.length === 0) {
            alert('אין פריטים בלוח');
            return;
        }

        this.hideContextMenu();
        const action = this.clipboard.action === 'cut' ? 'move' : 'copy';
        let successCount = 0;
        let errorCount = 0;

        try {
            // העתק/העבר את כל הפריטים
            for (const sourcePath of this.clipboard.paths) {
                const response = await fetch(`${this.apiBase}?action=${action}&unicId=${this.unicId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sourcePath: sourcePath,
                        destPath: this.currentPath
                    })
                });

                const result = await response.json();
                if (result.success) {
                    successCount++;
                } else {
                    errorCount++;
                    console.error('Error pasting:', sourcePath, result.error);
                }
            }

            if (this.clipboard.action === 'cut') {
                this.clipboard = null;
                // הסר סימון cut מכל הפריטים
                this.container.querySelectorAll('.explorer-item.cut').forEach(item => {
                    item.classList.remove('cut');
                });
            }

            if (errorCount > 0) {
                alert(`הודבקו ${successCount} פריטים, ${errorCount} נכשלו`);
            }
            this.refresh();
        } catch (error) {
            console.error('Error pasting items:', error);
            alert('שגיאה בהדבקה');
        }
    }

    // ========================================
    // תפריט קונטקסט על הרקע (לחיצה ימנית על שטח ריק)
    // ========================================

    setupBackgroundContextMenu() {
        this.contentEl.addEventListener('contextmenu', (e) => {
            // בדוק אם הלחיצה היא על פריט או על הרקע
            const clickedItem = e.target.closest('.explorer-item');
            if (!clickedItem) {
                // לחיצה על הרקע - הצג תפריט רקע
                e.preventDefault();
                e.stopPropagation();
                this.showBackgroundContextMenu(e);
            }
            // אם יש פריט, התפריט הרגיל יטופל על ידי oncontextmenu של הפריט
        });
    }

    showBackgroundContextMenu(event) {
        // הסר תפריט קודם אם קיים
        this.hideContextMenu();

        const hasClipboard = this.clipboard && this.clipboard.paths && this.clipboard.paths.length > 0;
        let clipboardInfo = '';
        if (hasClipboard) {
            const actionText = this.clipboard.action === 'cut' ? 'גזור' : 'העתק';
            const count = this.clipboard.paths.length;
            if (count === 1) {
                const fileName = this.clipboard.paths[0].split('/').pop() || this.clipboard.paths[0];
                clipboardInfo = `(${actionText}: ${fileName})`;
            } else {
                clipboardInfo = `(${actionText}: ${count} פריטים)`;
            }
        }

        const menu = document.createElement('div');
        menu.className = 'explorer-context-menu';
        menu.id = 'explorerContextMenu';
        menu.innerHTML = `
            <a href="javascript:void(0)" class="${!hasClipboard ? 'disabled' : ''}" onclick="${hasClipboard ? 'window.explorer.pasteItems()' : 'void(0)'}">
                <i class="fas fa-paste"></i> הדבק ${clipboardInfo}
            </a>
            <hr>
            <a href="javascript:void(0)" class="disabled" onclick="void(0)">
                <i class="fas fa-folder-tree"></i> העבר לתיק אחר...
                <span class="menu-badge">בקרוב</span>
            </a>
        `;

        // מיקום התפריט
        menu.style.left = event.clientX + 'px';
        menu.style.top = event.clientY + 'px';

        document.body.appendChild(menu);

        // סגור בלחיצה מחוץ לתפריט
        setTimeout(() => {
            document.addEventListener('click', this.hideContextMenu);
            document.addEventListener('contextmenu', this.hideContextMenu);
        }, 10);
    }
}

// ייצוא גלובלי
window.FileExplorer = FileExplorer;
