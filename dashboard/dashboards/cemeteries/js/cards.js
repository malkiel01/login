// dashboard/dashboards/cemeteries/js/cards.js
// ניהול כרטיסי מידע

// יצירת כרטיס מידע לבית עלמין
async function createCemeteryCard(cemeteryId, signal) {
    try {
        const response = await fetch(
            `${API_BASE}cemetery-hierarchy.php?action=get&type=cemetery&id=${cemeteryId}`,
            { signal: signal }
            );
        const data = await response.json();
        
        if (!data.success) return '';
        
        const cemetery = data.data;
        const stats = await getCemeteryStats(cemeteryId, signal);

        // בדיקת הרשאת עריכה
        const hasEditPermission = window.hasPermission ? window.hasPermission('cemeteries', 'edit') : true;

        return `
            <div class="info-card" id="cemeteryCard">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">🏛️</span>
                        <div>
                            <div class="info-card-type">בית עלמין</div>
                            <h2 class="info-card-name">${cemetery.cemeteryNameHe || cemetery.name || 'בית עלמין'}</h2>
                            ${cemetery.code ? `<div class="info-card-code">קוד: ${cemetery.code}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        ${hasEditPermission ? `
                        <button class="info-card-btn" onclick="if(window.tableRenderer) window.tableRenderer.editItem('${cemetery.unicId}')">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        ` : ''}
                        <button class="info-card-btn" onclick="viewCemeteryMap('${cemetery.unicId}')">
                            <svg class="icon-sm"><use xlink:href="#icon-map"></use></svg>
                            מפה
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
        // ⭐ טפל ב-AbortError!
        if (error.name === 'AbortError') {
            return ''; // החזר string ריק
        }
        
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

        // בדיקת הרשאת עריכה
        const hasEditPermission = window.hasPermission ? window.hasPermission('blocks', 'edit') : true;

        return `
            <div class="info-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">📦</span>
                        <div>
                            <div class="info-card-type">גוש</div>
                            <h2 class="info-card-name">${block.blockNameHe || block.name || 'גוש'}</h2>
                            ${block.code ? `<div class="info-card-code">קוד: ${block.code}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        ${hasEditPermission ? `
                        <button class="info-card-btn" onclick="if(window.tableRenderer) window.tableRenderer.editItem('${block.unicId}')">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        ` : ''}
                        <button class="info-card-btn" onclick="viewBlockMap('${block.unicId}')">
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

// יצירת כרטיס מידע לחלקה (כולל ניהול שורות עם ספירת אחוזות)
async function createPlotCard(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=plot&id=${plotId}`);
        const data = await response.json();
        
        if (!data.success) return '';
        
        const plot = data.data;
        const rows = await getPlotRows(plotId);
        const stats = await getPlotStats(plotId);
        
        // ⭐⭐ ספירת אחוזות קבר לכל שורה!
        const rowsWithCounts = await Promise.all(rows.map(async (row) => {
            try {
                // קריאה ל-API לספירת אחוזות קבר
                const areaGravesResponse = await fetch(
                    `${API_BASE}areaGraves-api.php?action=count&lineId=${row.unicId}`
                );
                const areaGravesData = await areaGravesResponse.json();

                return {
                    ...row,
                    area_graves_count: areaGravesData.success ? areaGravesData.count : 0
                };
            } catch (error) {
                console.error(`Error counting area graves for row ${row.unicId}:`, error);
                return {
                    ...row,
                    area_graves_count: 0
                };
            }
        }));

        // בדיקת הרשאת עריכה
        const hasEditPermission = window.hasPermission ? window.hasPermission('plots', 'edit') : true;

        return `
            <div class="info-card" style="background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%);">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">📋</span>
                        <div>
                            <div class="info-card-type">חלקה</div>
                            <h2 class="info-card-name">${plot.plotNameHe || plot.name || 'חלקה'}</h2>
                            ${plot.serial_number ? `<div class="info-card-code">מספר סידורי: ${plot.serial_number}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        ${hasEditPermission ? `
                        <button class="info-card-btn" onclick="if(window.tableRenderer) window.tableRenderer.editItem('${plot.unicId}')">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        ` : ''}
                        <button class="info-card-btn" onclick="managePlotRows('${plot.unicId}')">
                            <svg class="icon-sm"><use xlink:href="#icon-rows"></use></svg>
                            ניהול שורות
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
                            שורות בחלקה (${rowsWithCounts.length})
                        </div>
                        <button class="info-card-btn" onclick="addRowToPlot('${plot.unicId}')">
                            <svg class="icon-sm"><use xlink:href="#icon-plus"></use></svg>
                            הוסף שורה
                        </button>
                    </div>
                    <div class="rows-list">
                        ${rowsWithCounts.map(row => `
                            <div class="row-item" onclick="openRow(${row.id}, '${row.name}')">
                                <div style="font-weight: bold;">${row.lineNameHe || row.name}</div>
                                <div style="font-size: 12px; opacity: 0.8;">${row.area_graves_count || 0} אחוזות</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📏</div>
                    <div class="stat-value">${rowsWithCounts.length}</div>
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

// יצירת כרטיס מידע לאחוזת קבר
async function createAreaGraveCard(areaGraveId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get&type=areaGrave&id=${areaGraveId}`);
        const data = await response.json();
        
        if (!data.success) return '';
        
        const areaGrave = data.data;
        const stats = await getAreaGraveStats(areaGraveId);

        // בדיקת הרשאת עריכה
        const hasEditPermission = window.hasPermission ? window.hasPermission('areaGraves', 'edit') : true;

        return `
            <div class="info-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">🏘️</span>
                        <div>
                            <div class="info-card-type">אחוזת קבר</div>
                            <h2 class="info-card-name">${areaGrave.areaGraveNameHe || areaGrave.name || 'אחוזת קבר'}</h2>
                            ${areaGrave.code ? `<div class="info-card-code">קוד: ${areaGrave.code}</div>` : ''}
                        </div>
                    </div>
                    <div class="info-card-actions">
                        ${hasEditPermission ? `
                        <button class="info-card-btn" onclick="if(window.tableRenderer) window.tableRenderer.editItem('${areaGrave.unicId}')">
                            <svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>
                            עריכה
                        </button>
                        ` : ''}
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

// ===================================================
// יצירת כרטיס מידע ללקוח
// ===================================================
async function createCustomerCard(customerId) {
    try {
        const response = await fetch(`${API_BASE}customers-api.php?action=get&id=${customerId}`);
        const data = await response.json();
        
        if (!data.success) {
            return '';
        }
        
        const customer = data.data;
        
        // פורמט סוג תושבות
        const typeLabels = {
            1: 'תושב העיר',
            2: 'תושב הארץ',
            3: 'תושב חו"ל'
        };
        const residentType = typeLabels[customer.statusResident] || 'לא מוגדר';
        
        // פורמט סטטוס
        const statusBadge = customer.statusCustomer == 1 
            ? '<span class="status-badge-large status-active">פעיל</span>'
            : '<span class="status-badge-large status-inactive">לא פעיל</span>';
        
        // ספירת רכישות
        const purchasesCount = customer.purchases ? customer.purchases.length : 0;

        // בדיקת הרשאת עריכה
        const hasEditPermission = window.hasPermission ? window.hasPermission('customers', 'edit') : true;

        return `
            <div class="info-card" id="customerCard">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <span class="info-card-icon">👤</span>
                        <div>
                            <div class="info-card-type">לקוח</div>
                            <h2 class="info-card-name">${customer.firstName} ${customer.lastName}</h2>
                            <div class="info-card-code">ת.ז: ${customer.numId}</div>
                        </div>
                    </div>
                    <div class="info-card-actions">
                        ${hasEditPermission ? `
                        <button class="btn-secondary" onclick="if(window.tableRenderer) window.tableRenderer.editItem('${customer.unicId}')">
                            <span>✏️</span> עריכה
                        </button>
                        ` : ''}
                        <button class="btn-primary" onclick="printCustomerReport('${customer.unicId}')">
                            <span>🖨️</span> הדפסה
                        </button>
                    </div>
                </div>
                
                <div class="info-card-content">
                    <div class="info-row">
                        <div class="info-group">
                            <div class="info-label">טלפון</div>
                            <div class="info-value">${customer.phone || '-'}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">נייד</div>
                            <div class="info-value">${customer.mobile || '-'}</div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-group full-width">
                            <div class="info-label">אימייל</div>
                            <div class="info-value">${customer.email || '-'}</div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-group full-width">
                            <div class="info-label">כתובת</div>
                            <div class="info-value">${customer.address || '-'}</div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-group">
                            <div class="info-label">סוג תושבות</div>
                            <div class="info-value">${residentType}</div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">סטטוס</div>
                            <div class="info-value">${statusBadge}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            ${purchasesCount > 0 ? `
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-value">${purchasesCount}</div>
                    <div class="stat-label">רכישות</div>
                </div>
            </div>
            ` : ''}
        `;
    } catch (error) {
        console.error('Error creating customer card:', error);
        return '';
    }
}

function printCustomerReport(customerId) {
    // TODO: implement print
}

// פונקציות עזר לקבלת סטטיסטיקות - גרסה מלאה
async function getCemeteryStats(cemeteryId, signal) {
    try {
        const response = await fetch(
            `${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=cemetery&itemId=${cemeteryId}`,
            { signal: signal }
            );
        const data = await response.json();
        return data.success ? data.stats : {};
    } catch (error) {
        console.error('Error getting cemetery stats:', error);
        return {};
    }
}

async function getBlockStats(blockId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=block&itemId=${blockId}`);
        const data = await response.json();
        return data.success ? data.stats : {};
    } catch (error) {
        console.error('Error getting block stats:', error);
        return {};
    }
}

async function getPlotStats(plotId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=plot&itemId=${plotId}`);
        const data = await response.json();
        return data.success ? data.stats : {};
    } catch (error) {
        console.error('Error getting plot stats:', error);
        return {};
    }
}

async function getAreaGraveStats(areaGraveId) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=item_stats&item_type=areaGrave&itemId=${areaGraveId}`);
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
    // שורות מנוהלות דרך מודל ניהול שורות
    if (typeof window.openRowsManagementModal === 'function') {
        window.openRowsManagementModal(plotId, window.selectedItems.plot?.name);
    } else {
        console.warn('Row management modal not available');
        alert('ניהול שורות לא זמין');
    }
}

function managePlotRows(plotId) {
    if (typeof window.openRowsManagementModal === 'function') {
        window.openRowsManagementModal(plotId, window.selectedItems.plot?.name);
    }
}

function openRow(rowId, rowName) {
    // כאן אפשר להוסיף לוגיקה למעבר לשורה
}

function viewBlockMap(blockId) {
    // Open map view for the block
    const url = `map/index.php?type=block&id=${blockId}&mode=view`;
    window.open(url, '_blank');
}

function viewCemeteryMap(cemeteryId) {
    // Open map view for the cemetery
    const url = `map/index.php?type=cemetery&id=${cemeteryId}&mode=view`;
    window.open(url, '_blank');
}

function viewPlotMap(plotId) {
    // Open map view for the plot
    const url = `map/index.php?type=plot&id=${plotId}&mode=view`;
    window.open(url, '_blank');
}

function printCemeteryReport(cemeteryId) {
    // TODO: implement print
}

function printAreaGraveReport(areaGraveId) {
    // TODO: implement print
}

// ייצוא פונקציות גלובליות
window.createCemeteryCard = createCemeteryCard;
window.createBlockCard = createBlockCard;
window.createPlotCard = createPlotCard;
window.createAreaGraveCard = createAreaGraveCard;