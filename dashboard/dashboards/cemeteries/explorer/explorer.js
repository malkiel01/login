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
                        <a onclick="window.explorer.goToRoot()"><i class="fas fa-home"></i></a>
                        <span class="breadcrumb-path"></span>
                    </div>
                    <div class="explorer-actions">
                        <button class="explorer-btn" onclick="window.explorer.refresh()" title="רענון">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button class="explorer-btn" onclick="window.explorer.createFolder()" title="תיקייה חדשה">
                            <i class="fas fa-folder-plus"></i>
                        </button>
                        <button class="explorer-btn primary" onclick="document.getElementById('explorerFileInput').click()" title="העלאת קובץ">
                            <i class="fas fa-upload"></i> העלאה
                        </button>
                        <input type="file" id="explorerFileInput" class="explorer-file-input" multiple onchange="window.explorer.handleFileSelect(event)">
                    </div>
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
                this.renderItems();
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
            this.breadcrumbEl.innerHTML = '<span class="separator">/</span> שורש';
            return;
        }

        let html = '';
        let path = '';

        parts.forEach((part, index) => {
            path += (index > 0 ? '/' : '') + part;
            html += `<span class="separator">/</span>`;
            html += `<a onclick="window.explorer.loadFiles('${path}')">${part}</a>`;
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
        const files = event.target.files;
        if (!files.length) return;

        for (const file of files) {
            await this.uploadFile(file);
        }

        event.target.value = '';
        this.refresh();
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
            const files = e.dataTransfer.files;
            if (!files.length) return;

            for (const file of files) {
                await this.uploadFile(file);
            }

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
