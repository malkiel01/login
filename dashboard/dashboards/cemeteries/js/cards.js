// dashboard/dashboards/cemeteries/js/cards.js
// ניהול כרטיסי מידע

// יצירת כרטיס מידע לבית עלמין
async function createCemeteryCard(cemeteryId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=cemetery&id=${cemeteryId}`);
        const data = await response.json();
        
        if (!data.success) return '';
        
        const cemetery = data.data;
        const stats = await getCemeteryStats(cemeteryId);
        
        return `
            <div class="info-card" id="cemeteryCard">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">🏛️</span>
                        <div>
                            <h2 class="info-card-name">${cemetery.cemeteryNameHe || cemetery.name || 'בית עלמין'}</h2>
                            ${cemetery.code ? `<div class="info-card-code">קוד: ${cemetery.code}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        <button class="info-card-btn" onclick="editCemetery(${cemetery.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        <button class="info-card-btn" onclick="printCemeteryReport(${cemetery.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-print"></use></svg>
                            הדפסה
                        </button>
                    </div>
                </div>
                
                <div class="info-card-content">
                    <div class="info-card-item">
                        <div class="info-card-label">כתובת</div>
                        <div class="info-card-value">${cemetery.address || 'לא מוגדר'}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">טלפון</div>
                        <div class="info-card-value">${cemetery.phone || 'לא מוגדר'}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">מנהל</div>
                        <div class="info-card-value">${cemetery.manager || 'לא מוגדר'}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">סטטוס</div>
                        <div class="info-card-value">
                            <span class="status-badge-large status-active">פעיל</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-value">${stats.blocks || 0}</div>
                    <div class="stat-label">גושים</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-value">${stats.plots || 0}</div>
                    <div class="stat-label">חלקות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🪦</div>
                    <div class="stat-value">${stats.graves || 0}</div>
                    <div class="stat-label">קברים</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value">${stats.available || 0}</div>
                    <div class="stat-label">פנויים</div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error creating cemetery card:', error);
        return '';
    }
}

// יצירת כרטיס מידע לגוש
async function createBlockCard(blockId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=block&id=${blockId}`);
        const data = await response.json();
        
        if (!data.success) return '';
        
        const block = data.data;
        const stats = await getBlockStats(blockId);
        
        return `
            <div class="info-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">📦</span>
                        <div>
                            <h2 class="info-card-name">${block.blockNameHe || block.name || 'גוש'}</h2>
                            ${block.code ? `<div class="info-card-code">קוד: ${block.code}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        <button class="info-card-btn" onclick="editBlock(${block.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        <button class="info-card-btn" onclick="viewBlockMap(${block.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-map"></use></svg>
                            מפה
                        </button>
                    </div>
                </div>
                
                <div class="info-card-content">
                    <div class="info-card-item">
                        <div class="info-card-label">מיקום</div>
                        <div class="info-card-value">${block.location || 'לא מוגדר'}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">שטח</div>
                        <div class="info-card-value">${block.area || 'לא מוגדר'}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">נוצר בתאריך</div>
                        <div class="info-card-value">${formatDate(block.created_at)}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">סטטוס</div>
                        <div class="info-card-value">
                            <span class="status-badge-large status-active">פעיל</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-value">${stats.plots || 0}</div>
                    <div class="stat-label">חלקות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🪦</div>
                    <div class="stat-value">${stats.graves || 0}</div>
                    <div class="stat-label">קברים</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value">${stats.available || 0}</div>
                    <div class="stat-label">פנויים</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔴</div>
                    <div class="stat-value">${stats.occupied || 0}</div>
                    <div class="stat-label">תפוסים</div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error creating block card:', error);
        return '';
    }
}

// יצירת כרטיס מידע לחלקה (כולל ניהול שורות)
async function createPlotCard(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=plot&id=${plotId}`);
        const data = await response.json();
        
        if (!data.success) return '';
        
        const plot = data.data;
        const rows = await getPlotRows(plotId);
        const stats = await getPlotStats(plotId);
        
        return `
            <div class="info-card" style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%);">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">📋</span>
                        <div>
                            <h2 class="info-card-name">${plot.plotNameHe || plot.name || 'חלקה'}</h2>
                            ${plot.serial_number ? `<div class="info-card-code">מספר סידורי: ${plot.serial_number}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        <button class="info-card-btn" onclick="editPlot(${plot.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        <button class="info-card-btn" onclick="managePlotRows(${plot.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-rows"></use></svg>
                            ניהול שורות
                        </button>
                        <!-- הוסף את הכפתור הזה! -->
                        <button class="info-card-btn" onclick="openAddAreaGrave()">
                            <svg class="icon-sm"><use xlink:href="#icon-plus"></use></svg>
                            הוסף אחוזת קבר
                        </button>
                    </div>
                </div>
                
                <div class="info-card-content">
                    <div class="info-card-item">
                        <div class="info-card-label">מיקום</div>
                        <div class="info-card-value">${plot.location || 'לא מוגדר'}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">סוג חלקה</div>
                        <div class="info-card-value">${getPlotTypeName(plot.plot_type)}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">שטח</div>
                        <div class="info-card-value">${plot.area || 'לא מוגדר'} מ"ר</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">סטטוס</div>
                        <div class="info-card-value">
                            <span class="status-badge-large status-active">פעיל</span>
                        </div>
                    </div>
                </div>
                
                <!-- אזור ניהול שורות -->
                <div class="rows-section">
                    <div class="rows-header">
                        <div class="rows-title">
                            <span>📏</span>
                            שורות בחלקה (${rows.length})
                        </div>
                        <button class="info-card-btn" onclick="addRowToPlot(${plot.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-plus"></use></svg>
                            הוסף שורה
                        </button>
                    </div>
                    <div class="rows-list">
                        ${rows.map(row => `
                            <div class="row-item" onclick="openRow(${row.id}, '${row.name}')">
                                <div style="font-weight: bold;">${row.name}</div>
                                <div style="font-size: 12px; opacity: 0.8;">${row.area_graves_count || 0} אחוזות</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📏</div>
                    <div class="stat-value">${rows.length}</div>
                    <div class="stat-label">שורות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏘️</div>
                    <div class="stat-value">${stats.areaGraves || 0}</div>
                    <div class="stat-label">אחוזות קבר</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🪦</div>
                    <div class="stat-value">${stats.graves || 0}</div>
                    <div class="stat-label">קברים</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value">${stats.available || 0}</div>
                    <div class="stat-label">פנויים</div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error creating plot card:', error);
        return '';
    }
}
function addAreaGraveButtonToPlotCard() {
    return `
        <button class="info-card-btn" onclick="openAddAreaGrave()">
            <svg class="icon-sm"><use xlink:href="#icon-plus"></use></svg>
            הוסף אחוזת קבר
        </button>
    `;
}

// יצירת כרטיס מידע לאחוזת קבר
async function createAreaGraveCard(areaGraveId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=area_grave&id=${areaGraveId}`);
        const data = await response.json();
        
        if (!data.success) return '';
        
        const areaGrave = data.data;
        const stats = await getAreaGraveStats(areaGraveId);
        
        return `
            <div class="info-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">🏘️</span>
                        <div>
                            <h2 class="info-card-name">${areaGrave.areaGraveNameHe || areaGrave.name || 'אחוזת קבר'}</h2>
                            ${areaGrave.code ? `<div class="info-card-code">קוד: ${areaGrave.code}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        <button class="info-card-btn" onclick="editAreaGrave(${areaGrave.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        <button class="info-card-btn" onclick="printAreaGraveReport(${areaGrave.id})">
                            <svg class="icon-sm"><use xlink:href="#icon-print"></use></svg>
                            דוח
                        </button>
                    </div>
                </div>
                
                <div class="info-card-content">
                    <div class="info-card-item">
                        <div class="info-card-label">סוג קבר</div>
                        <div class="info-card-value">${getGraveTypeName(areaGrave.grave_type)}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">קואורדינטות</div>
                        <div class="info-card-value">${areaGrave.coordinates || 'לא מוגדר'}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">נוצר בתאריך</div>
                        <div class="info-card-value">${formatDate(areaGrave.created_at)}</div>
                    </div>
                    <div class="info-card-item">
                        <div class="info-card-label">סטטוס</div>
                        <div class="info-card-value">
                            <span class="status-badge-large status-active">פעיל</span>
                        </div>
                    </div>
                </div>
                
                ${areaGrave.notes ? `
                    <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 10px;">
                        <div style="font-weight: bold; margin-bottom: 10px;">הערות:</div>
                        <div>${areaGrave.notes}</div>
                    </div>
                ` : ''}
            </div>
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">🪦</div>
                    <div class="stat-value">${stats.total || 0}</div>
                    <div class="stat-label">סה"כ קברים</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value">${stats.available || 0}</div>
                    <div class="stat-label">פנויים</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🟠</div>
                    <div class="stat-value">${stats.purchased || 0}</div>
                    <div class="stat-label">נרכשו</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔴</div>
                    <div class="stat-value">${stats.occupied || 0}</div>
                    <div class="stat-label">תפוסים</div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error creating area grave card:', error);
        return '';
    }
}

// פונקציות עזר לקבלת סטטיסטיקות - גרסה מלאה
async function getCemeteryStats(cemeteryId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=cemetery&item_id=${cemeteryId}`);
        const data = await response.json();
        return data.success ? data.stats : {};
    } catch (error) {
        console.error('Error getting cemetery stats:', error);
        return {};
    }
}

async function getBlockStats(blockId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=block&item_id=${blockId}`);
        const data = await response.json();
        return data.success ? data.stats : {};
    } catch (error) {
        console.error('Error getting block stats:', error);
        return {};
    }
}

async function getPlotStats(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=plot&item_id=${plotId}`);
        const data = await response.json();
        return data.success ? data.stats : {};
    } catch (error) {
        console.error('Error getting plot stats:', error);
        return {};
    }
}

async function getAreaGraveStats(areaGraveId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=area_grave&item_id=${areaGraveId}`);
        const data = await response.json();
        return data.success ? data.stats : {};
    } catch (error) {
        console.error('Error getting area grave stats:', error);
        return {};
    }
}

async function getPlotRows(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        return data.success ? data.data : [];
    } catch (error) {
        console.error('Error getting plot rows:', error);
        return [];
    }
}

// פונקציות עזר נוספות
function getPlotTypeName(type) {
    const types = {
        1: 'פטור',
        2: 'חריג',
        3: 'סגור'
    };
    return types[type] || 'רגיל';
}

function getGraveTypeName(type) {
    const types = {
        1: 'רגיל',
        2: 'כפול',
        3: 'משפחתי'
    };
    return types[type] || 'לא מוגדר';
}

// פונקציות פעולה
function addRowToPlot(plotId) {
    if (typeof window.openAddRowForm === 'function') {
        window.openAddRowForm(plotId);
    }
}

function managePlotRows(plotId) {
    if (typeof window.openRowsManagementModal === 'function') {
        window.openRowsManagementModal(plotId, window.selectedItems.plot?.name);
    }
}

function openRow(rowId, rowName) {
    console.log('Opening row from card:', rowId, rowName);
    // כאן אפשר להוסיף לוגיקה למעבר לשורה
}

function viewBlockMap(blockId) {
    console.log('Viewing block map:', blockId);
    // TODO: implement map view
}

function printCemeteryReport(cemeteryId) {
    console.log('Printing cemetery report:', cemeteryId);
    // TODO: implement print
}

function printAreaGraveReport(areaGraveId) {
    console.log('Printing area grave report:', areaGraveId);
    // TODO: implement print
}

// ייצוא פונקציות גלובליות
window.createCemeteryCard = createCemeteryCard;
window.createBlockCard = createBlockCard;
window.createPlotCard = createPlotCard;
window.createAreaGraveCard = createAreaGraveCard;