// dashboard/dashboards/cemeteries/js/unified-table-renderer-fixed.js
// תיקון המערכת האחידה - ללא כפילויות ועם ניווט תקין

class UnifiedTableRenderer {
    constructor() {
        this.config = null;
        this.currentType = 'cemetery';
        this.currentData = [];
    }
    
    /**
     * טעינת הקונפיג מהשרת
     */
    async loadConfig(type) {
        try {
            const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=get_config&type=${type}`);
            const data = await response.json();
            
            if (data.success) {
                this.config = data.config;
                this.currentType = type;
                return true;
            }
        } catch (error) {
            console.error('Error loading config:', error);
        }
        return false;
    }
    
    /**
     * טעינת נתונים וציור טבלה
     */
    async loadAndDisplay(type, parentId = null) {
        // טען קונפיג אם צריך
        if (!this.config || this.currentType !== type) {
            await this.loadConfig(type);
        }
        
        // טען נתונים
        const data = await this.loadData(type, parentId);
        if (data) {
            this.displayTable(data);
        }
    }
    
    /**
     * טעינת נתונים מהשרת
     */
    async loadData(type, parentId = null) {
        try {
            let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=${type}`;
            if (parentId) {
                url += `&parent_id=${parentId}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.currentData = data.data;
                return data.data;
            }
        } catch (error) {
            console.error('Error loading data:', error);
            showError('שגיאה בטעינת נתונים');
        }
        return null;
    }
    
    /**
     * ציור הטבלה על פי הקונפיג
     */
    displayTable(data) {
        const tbody = document.getElementById('tableBody');
        const thead = document.getElementById('tableHeaders');
        
        if (!tbody || !thead || !this.config) return;
        
        // ציור כותרות
        this.renderHeaders(thead);
        
        // ציור שורות
        this.renderRows(tbody, data);
        
        // הסרנו את updateBreadcrumb מכאן! זה כבר נקרא במקום אחר
        
        // עדכון כפתור הוספה
        if (typeof updateAddButtonText === 'function') {
            updateAddButtonText();
        }
    }
    
    /**
     * פתיחת פריט - כניסה לרמה הבאה
     */
    openItem(itemId, itemName) {
        const nextType = this.getChildType();
        if (!nextType) return;
        
        // עדכן את הבחירה הגלובלית
        window.selectedItems[this.currentType] = { 
            id: itemId, 
            name: itemName 
        };
        
        // עדכן משתנים גלובליים
        window.currentType = nextType;
        window.currentParentId = itemId;
        
        // שמור ID ספציפי לכל רמה (לתאימות אחורה)
        const idMapping = {
            'cemetery': 'currentCemeteryId',
            'block': 'currentBlockId',
            'plot': 'currentPlotId',
            'area_grave': 'currentAreaGraveId'
        };
        
        if (idMapping[this.currentType]) {
            window[idMapping[this.currentType]] = itemId;
        }
        
        // עדכן את ה-Breadcrumb פעם אחת
        if (window.BreadcrumbManager) {
            window.BreadcrumbManager.update(window.selectedItems);
        }
        
        // טען את הנתונים הבאים
        this.loadAndDisplay(nextType, itemId);
    }
    
    /**
     * קבלת סוג הילד
     */
    getChildType() {
        const hierarchy = {
            'cemetery': 'block',
            'block': 'plot',
            'plot': 'area_grave',
            'area_grave': 'grave',
            'grave': null
        };
        
        return hierarchy[this.currentType];
    }
    
    // ... שאר הפונקציות נשארות אותו דבר ...
}

// יצירת מופע גלובלי
window.tableRenderer = new UnifiedTableRenderer();

// ==========================================
// פונקציות גלובליות - טעינת כל הפריטים
// ==========================================

window.loadAllCemeteries = async function() {
    console.log('📍 Loading all cemeteries');
    window.currentType = 'cemetery';
    window.currentParentId = null;
    window.selectedItems = {};
    
    DashboardCleaner.clear({ targetLevel: 'cemetery' });
    BreadcrumbManager.update({}, 'cemetery');
    await tableRenderer.loadAndDisplay('cemetery');
};

window.loadAllBlocks = async function() {
    console.log('📍 Loading all blocks');
    window.currentType = 'block';
    window.currentParentId = null;
    
    // שמור רק את בית העלמין אם קיים
    const temp = window.selectedItems?.cemetery;
    window.selectedItems = {};
    if (temp) window.selectedItems.cemetery = temp;
    
    DashboardCleaner.clear({ targetLevel: 'block' });
    BreadcrumbManager.update({}, 'block');
    await tableRenderer.loadAndDisplay('block');
};

window.loadAllPlots = async function() {
    console.log('📍 Loading all plots');
    window.currentType = 'plot';
    window.currentParentId = null;
    
    // שמור רק עד גוש
    const tempCemetery = window.selectedItems?.cemetery;
    const tempBlock = window.selectedItems?.block;
    window.selectedItems = {};
    if (tempCemetery) window.selectedItems.cemetery = tempCemetery;
    if (tempBlock) window.selectedItems.block = tempBlock;
    
    DashboardCleaner.clear({ targetLevel: 'plot' });
    BreadcrumbManager.update({}, 'plot');
    await tableRenderer.loadAndDisplay('plot');
};

window.loadAllAreaGraves = async function() {
    console.log('📍 Loading all area graves');
    window.currentType = 'area_grave';
    window.currentParentId = null;
    
    // שמור עד חלקה
    const temp = { ...window.selectedItems };
    window.selectedItems = {};
    if (temp.cemetery) window.selectedItems.cemetery = temp.cemetery;
    if (temp.block) window.selectedItems.block = temp.block;
    if (temp.plot) window.selectedItems.plot = temp.plot;
    
    DashboardCleaner.clear({ targetLevel: 'area_grave' });
    BreadcrumbManager.update({}, 'area_grave');
    await tableRenderer.loadAndDisplay('area_grave');
};

window.loadAllGraves = async function() {
    console.log('📍 Loading all graves');
    window.currentType = 'grave';
    window.currentParentId = null;
    
    // שמור עד אחוזת קבר
    const temp = { ...window.selectedItems };
    window.selectedItems = {};
    if (temp.cemetery) window.selectedItems.cemetery = temp.cemetery;
    if (temp.block) window.selectedItems.block = temp.block;
    if (temp.plot) window.selectedItems.plot = temp.plot;
    if (temp.areaGrave) window.selectedItems.areaGrave = temp.areaGrave;
    
    DashboardCleaner.clear({ targetLevel: 'grave' });
    BreadcrumbManager.update({}, 'grave');
    await tableRenderer.loadAndDisplay('grave');
};

// ==========================================
// פונקציות גלובליות - פתיחת פריט ספציפי
// ==========================================

window.openCemetery = function(cemeteryId, cemeteryName) {
    console.log('🏛️ Opening cemetery:', cemeteryId, cemeteryName);
    
    window.selectedItems.cemetery = { id: cemeteryId, name: cemeteryName };
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    window.currentCemeteryId = cemeteryId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען גושים
    tableRenderer.loadAndDisplay('block', cemeteryId);
};

window.openBlock = function(blockId, blockName) {
    console.log('📦 Opening block:', blockId, blockName);
    
    window.selectedItems.block = { id: blockId, name: blockName };
    window.currentType = 'plot';
    window.currentParentId = blockId;
    window.currentBlockId = blockId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען חלקות
    tableRenderer.loadAndDisplay('plot', blockId);
};

window.openPlot = function(plotId, plotName) {
    console.log('📋 Opening plot:', plotId, plotName);
    
    window.selectedItems.plot = { id: plotId, name: plotName };
    window.currentType = 'area_grave';
    window.currentParentId = plotId;
    window.currentPlotId = plotId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען אחוזות קבר
    tableRenderer.loadAndDisplay('area_grave', plotId);
};

window.openAreaGrave = function(areaGraveId, areaGraveName) {
    console.log('🏘️ Opening area grave:', areaGraveId, areaGraveName);
    
    window.selectedItems.areaGrave = { id: areaGraveId, name: areaGraveName };
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;
    window.currentAreaGraveId = areaGraveId;
    
    // עדכן Breadcrumb
    BreadcrumbManager.update(window.selectedItems);
    
    // טען קברים
    tableRenderer.loadAndDisplay('grave', areaGraveId);
};

window.viewGraveDetails = function(graveId) {
    console.log('⚰️ Viewing grave details:', graveId);
    // כאן אפשר להציג מודל או כרטיס עם פרטי הקבר
    alert('פרטי קבר: ' + graveId);
};

// ==========================================
// פונקציות גלובליות - טעינה עם הורה (לתאימות אחורה)
// ==========================================

window.loadBlocksForCemetery = async function(cemeteryId) {
    console.log('📦 Loading blocks for cemetery:', cemeteryId);
    window.currentCemeteryId = cemeteryId;
    window.currentType = 'block';
    window.currentParentId = cemeteryId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.cemetery) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('block', cemeteryId);
};

window.loadPlotsForBlock = async function(blockId) {
    console.log('📋 Loading plots for block:', blockId);
    window.currentBlockId = blockId;
    window.currentType = 'plot';
    window.currentParentId = blockId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.block) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('plot', blockId);
};

window.loadAreaGravesForPlot = async function(plotId) {
    console.log('🏘️ Loading area graves for plot:', plotId);
    window.currentPlotId = plotId;
    window.currentType = 'area_grave';
    window.currentParentId = plotId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.plot) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('area_grave', plotId);
};

window.loadGravesForAreaGrave = async function(areaGraveId) {
    console.log('⚰️ Loading graves for area grave:', areaGraveId);
    window.currentAreaGraveId = areaGraveId;
    window.currentType = 'grave';
    window.currentParentId = areaGraveId;
    
    // עדכן Breadcrumb אם צריך
    if (window.selectedItems?.areaGrave) {
        BreadcrumbManager.update(window.selectedItems);
    }
    
    await tableRenderer.loadAndDisplay('grave', areaGraveId);
};