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
        this.selectedItem = null;
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
        this.render();
        this.loadFiles();
        this.setupDragDrop();
    }

    render() {
        this.container.innerHTML = `
            <div class="file-explorer">
                <div class="explorer-toolbar">
                    <div class="explorer-breadcrumb">
                        <span class="breadcrumb-home" onclick="window.explorer.goToRoot()"><i class="fas fa-home"></i></span>
                        <span class="breadcrumb-path"></span>
                    </div>
                    <div class="explorer-actions">
                        <button type="button" class="explorer-btn" onclick="window.explorer.refresh()" title="רענון">
                            <i class="fas fa-sync-alt"></i>
                        </button>

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
    }

    renderItem(item) {
        const icon = this.getIcon(item);
        const thumb = item.isImage ? `<img src="${item.thumbUrl}" alt="${item.name}">` : `<i class="${icon}"></i>`;

        return `
            <div class="explorer-item"
                 data-path="${item.path}"
                 data-is-dir="${item.isDir}"
                 onclick="window.explorer.selectItem(this)"
                 ondblclick="window.explorer.openItem('${item.path}', ${item.isDir})">
                <button class="explorer-item-delete" onclick="event.stopPropagation(); window.explorer.deleteItem('${item.path}')" title="מחיקה">
                    <i class="fas fa-times"></i>
                </button>
                <div class="explorer-item-icon">
                    ${thumb}
                </div>
                <div class="explorer-item-name">${item.name}</div>
            </div>
        `;
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
            this.breadcrumbEl.innerHTML = '<span class="breadcrumb-current">שורש</span>';
            return;
        }

        let html = '';
        let path = '';

        // קישור לשורש
        html += `<span class="breadcrumb-separator">/</span>`;
        html += `<a href="javascript:void(0)" class="breadcrumb-link" onclick="window.explorer.goToRoot()">שורש</a>`;

        parts.forEach((part, index) => {
            path += (index > 0 ? '/' : '') + part;
            html += `<span class="breadcrumb-separator">/</span>`;

            if (index === parts.length - 1) {
                // האחרון - לא לחיץ
                html += `<span class="breadcrumb-current">${part}</span>`;
            } else {
                // לחיץ
                html += `<a href="javascript:void(0)" class="breadcrumb-link" onclick="window.explorer.loadFiles('${path}')">${part}</a>`;
            }
        });

        this.breadcrumbEl.innerHTML = html;
    }

    selectItem(el) {
        // הסר בחירה קודמת
        this.container.querySelectorAll('.explorer-item.selected').forEach(item => {
            item.classList.remove('selected');
        });

        el.classList.add('selected');
        this.selectedItem = el.dataset.path;
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
}

// ייצוא גלובלי
window.FileExplorer = FileExplorer;
