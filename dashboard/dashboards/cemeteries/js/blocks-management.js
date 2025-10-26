/*
 * File: dashboards/dashboard/cemeteries/assets/blocks-management.js
 * Version: 1.0.0
 * Updated: 2025-10-26
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירה ראשונית - זהה למבנה cemeteries-management.js
 * - שימוש ב-unified-table-renderer + table-manager
 * - תמיכה בסינון לפי בית עלמין
 */

import { TableManager } from './table-manager.js';
import { UnifiedTableRenderer } from './unified-table-renderer.js';

class BlocksManagement {
    constructor() {
        this.tableManager = new TableManager();
        this.currentCemeteryId = null; // לסינון גושים לפי בית עלמין
        this.currentCemeteryName = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadBlocks();
    }

    setupEventListeners() {
        // כפתורים ראשיים
        document.getElementById('addBlockBtn')?.addEventListener('click', () => this.showBlockForm());
        document.getElementById('refreshBtn')?.addEventListener('click', () => this.loadBlocks());
        
        // חיפוש
        document.getElementById('searchInput')?.addEventListener('input', (e) => {
            this.tableManager.search(e.target.value);
        });
        
        // סינון לפי בית עלמין
        document.getElementById('cemeteryFilterSelect')?.addEventListener('change', (e) => {
            this.currentCemeteryId = e.target.value || null;
            this.loadBlocks();
        });
        
        // כפתור חזרה לכל הגושים
        document.getElementById('clearCemeteryFilterBtn')?.addEventListener('click', () => {
            this.currentCemeteryId = null;
            this.currentCemeteryName = null;
            document.getElementById('cemeteryFilterSelect').value = '';
            this.updateFilterDisplay();
            this.loadBlocks();
        });
    }

    updateFilterDisplay() {
        const filterInfo = document.getElementById('filterInfo');
        if (this.currentCemeteryName) {
            filterInfo.textContent = `מוצג: גושים של ${this.currentCemeteryName}`;
            filterInfo.classList.remove('hidden');
        } else {
            filterInfo.textContent = '';
            filterInfo.classList.add('hidden');
        }
    }

    // ========== טעינת נתונים ==========
    
    async loadBlocks(page = 1) {
        try {
            const searchQuery = document.getElementById('searchInput')?.value || '';
            let url = `/api/blocks-api.php?action=list&page=${page}&limit=50&search=${encodeURIComponent(searchQuery)}`;
            
            if (this.currentCemeteryId) {
                url += `&cemeteryId=${this.currentCemeteryId}`;
            }
            
            const response = await fetch(url);
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'שגיאה בטעינת נתונים');
            }
            
            this.renderBlocksTable(result.data, result.pagination);
            this.updateStats(result.pagination);
            
        } catch (error) {
            console.error('Error loading blocks:', error);
            this.showError('שגיאה בטעינת גושים: ' + error.message);
        }
    }

    renderBlocksTable(data, pagination) {
        const config = {
            columns: [
                { 
                    key: 'blockNameHe', 
                    label: 'שם גוש', 
                    primary: true,
                    render: (value, row) => `
                        <div class="primary-cell">
                            <strong>${value || 'לא צוין'}</strong>
                            ${row.blockCode ? `<span class="badge">${row.blockCode}</span>` : ''}
                            ${row.blockNumber ? `<span class="badge secondary">#${row.blockNumber}</span>` : ''}
                        </div>
                    `
                },
                { 
                    key: 'cemetery_name', 
                    label: 'בית עלמין',
                    render: (value) => value || 'לא צוין'
                },
                { 
                    key: 'description', 
                    label: 'תיאור',
                    render: (value) => {
                        if (!value) return '-';
                        return value.length > 50 ? value.substring(0, 50) + '...' : value;
                    }
                },
                { 
                    key: 'plots_count', 
                    label: 'חלקות',
                    render: (value) => `<span class="count-badge">${value || 0}</span>`
                }
            ],
            actions: [
                { 
                    label: 'צפייה בחלקות', 
                    class: 'btn-secondary', 
                    icon: '📋',
                    onClick: (row) => this.viewBlockPlots(row.unicId, row.blockNameHe) 
                },
                { 
                    label: 'עריכה', 
                    class: 'btn-primary', 
                    icon: '✏️',
                    onClick: (row) => this.editBlock(row.unicId) 
                },
                { 
                    label: 'מחיקה', 
                    class: 'btn-danger', 
                    icon: '🗑️',
                    onClick: (row) => this.deleteBlock(row.unicId) 
                }
            ],
            pagination: pagination,
            onPageChange: (page) => this.loadBlocks(page),
            emptyMessage: 'לא נמצאו גושים'
        };

        const tableHtml = UnifiedTableRenderer.render(data, config);
        document.getElementById('blocksTableContainer').innerHTML = tableHtml;
    }

    updateStats(pagination) {
        // עדכון מונים בממשק
        document.getElementById('totalBlocks')?.textContent = pagination.totalAll || 0;
        document.getElementById('filteredBlocks')?.textContent = pagination.total || 0;
    }

    // ========== פעולות על גושים ==========
    
    viewBlockPlots(blockId, blockName) {
        // מעבר לעמוד חלקות עם סינון לפי הגוש
        window.location.href = `/dashboards/dashboard/cemeteries/plots.php?blockId=${blockId}&blockName=${encodeURIComponent(blockName)}`;
    }

    showBlockForm(blockId = null) {
        // TODO: הצגת modal להוספה/עריכה
        console.log('Show block form:', blockId);
        
        // לדוגמה - פתיחת modal
        const modal = document.getElementById('blockFormModal');
        if (modal) {
            if (blockId) {
                // טעינת נתוני הגוש לעריכה
                this.loadBlockData(blockId);
            } else {
                // איפוס הטופס להוספה חדשה
                this.resetBlockForm();
            }
            modal.classList.add('active');
        }
    }

    async loadBlockData(blockId) {
        try {
            const response = await fetch(`/api/blocks-api.php?action=get&id=${blockId}`);
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            // מילוי שדות הטופס
            const block = result.data;
            document.getElementById('blockId').value = block.unicId;
            document.getElementById('blockNameHe').value = block.blockNameHe || '';
            document.getElementById('blockNameEn').value = block.blockNameEn || '';
            document.getElementById('blockCode').value = block.blockCode || '';
            document.getElementById('blockNumber').value = block.blockNumber || '';
            document.getElementById('cemeteryId').value = block.cemeteryId || '';
            document.getElementById('description').value = block.description || '';
            
        } catch (error) {
            this.showError('שגיאה בטעינת נתוני הגוש: ' + error.message);
        }
    }

    resetBlockForm() {
        document.getElementById('blockForm')?.reset();
        document.getElementById('blockId').value = '';
    }

    async editBlock(id) {
        this.showBlockForm(id);
    }

    async deleteBlock(id) {
        if (!confirm('האם אתה בטוח שברצונך למחוק את הגוש?')) {
            return;
        }

        try {
            const response = await fetch(`/api/blocks-api.php?action=delete&id=${id}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            this.showSuccess('הגוש נמחק בהצלחה');
            this.loadBlocks();
            
        } catch (error) {
            this.showError('שגיאה במחיקת הגוש: ' + error.message);
        }
    }

    async saveBlock(formData) {
        try {
            const blockId = formData.get('blockId');
            const action = blockId ? 'update' : 'create';
            const url = blockId 
                ? `/api/blocks-api.php?action=${action}&id=${blockId}`
                : `/api/blocks-api.php?action=${action}`;
            
            const data = {
                blockNameHe: formData.get('blockNameHe'),
                blockNameEn: formData.get('blockNameEn'),
                blockCode: formData.get('blockCode'),
                blockNumber: formData.get('blockNumber'),
                cemeteryId: formData.get('cemeteryId'),
                description: formData.get('description')
            };
            
            const response = await fetch(url, {
                method: blockId ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error);
            }
            
            this.showSuccess(blockId ? 'הגוש עודכן בהצלחה' : 'הגוש נוסף בהצלחה');
            this.closeBlockForm();
            this.loadBlocks();
            
        } catch (error) {
            this.showError('שגיאה בשמירת הגוש: ' + error.message);
        }
    }

    closeBlockForm() {
        const modal = document.getElementById('blockFormModal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    // ========== הודעות למשתמש ==========
    
    showSuccess(message) {
        // TODO: הצגת הודעת הצלחה מתוחכמת
        this.showToast(message, 'success');
    }

    showError(message) {
        // TODO: הצגת הודעת שגיאה מתוחכמת
        this.showToast(message, 'error');
    }

    showToast(message, type = 'info') {
        // הצגת toast פשוט
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// אתחול
document.addEventListener('DOMContentLoaded', () => {
    window.blocksManagement = new BlocksManagement();
});

export default BlocksManagement;