/**
 * hierarchy-cards.js
 * מערכת כרטיסי תצוגה לכל רמות ההיררכיה
 * 
 * קובץ זה מכיל את כל הפונקציות החסרות ליצירת כרטיסי תצוגה
 */

// ============================================
// 1. כרטיס בית עלמין
// ============================================
function createCemeteryCard(cemetery) {
    if (!cemetery) {
        cemetery = window.selectedCemetery || {
            id: window.currentCemeteryId,
            name: window.currentCemeteryName || 'בית עלמין'
        };
    }
    
    return `
        <div class="hierarchy-card cemetery-card" data-level="cemetery" data-id="${cemetery.id}">
            <div class="hierarchy-card-header">
                <div class="card-title-section">
                    <span class="card-icon">🏛️</span>
                    <h3 class="card-title">${cemetery.name || 'בית עלמין'}</h3>
                    ${cemetery.code ? `<span class="badge badge-info">${cemetery.code}</span>` : ''}
                </div>
                <button class="btn-close-card" onclick="closeHierarchyCard('cemetery')" title="סגור">✕</button>
            </div>
            ${cemetery.address || cemetery.city ? `
                <div class="hierarchy-card-body">
                    <div class="card-info-row">
                        ${cemetery.address ? `<span class="info-item"><strong>כתובת:</strong> ${cemetery.address}</span>` : ''}
                        ${cemetery.city ? `<span class="info-item"><strong>עיר:</strong> ${cemetery.city}</span>` : ''}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}

// ============================================
// 2. כרטיס גוש
// ============================================
function createBlockCard(block) {
    if (!block) {
        block = window.selectedBlock || {
            id: window.currentBlockId,
            name: window.currentBlockName || 'גוש',
            block_code: window.currentBlockCode
        };
    }
    
    return `
        <div class="hierarchy-card block-card" data-level="block" data-id="${block.id}">
            <div class="hierarchy-card-header">
                <div class="card-title-section">
                    <span class="card-icon">📦</span>
                    <h3 class="card-title">${block.name || block.block_name || 'גוש ' + (block.block_code || block.id)}</h3>
                    ${block.block_code ? `<span class="badge badge-info">קוד: ${block.block_code}</span>` : ''}
                </div>
                <button class="btn-close-card" onclick="closeHierarchyCard('block')" title="סגור">✕</button>
            </div>
            ${block.plots_count !== undefined ? `
                <div class="hierarchy-card-body">
                    <span class="info-item"><strong>מספר חלקות:</strong> ${block.plots_count}</span>
                </div>
            ` : ''}
        </div>
    `;
}

// ============================================
// 3. כרטיס חלקה
// ============================================
function createPlotCard(plot) {
    if (!plot) {
        plot = window.selectedPlot || {
            id: window.currentPlotId,
            name: window.currentPlotName || 'חלקה',
            plot_code: window.currentPlotCode
        };
    }
    
    return `
        <div class="hierarchy-card plot-card" data-level="plot" data-id="${plot.id}">
            <div class="hierarchy-card-header">
                <div class="card-title-section">
                    <span class="card-icon">📋</span>
                    <h3 class="card-title">${plot.name || plot.plot_name || 'חלקה ' + (plot.plot_code || plot.id)}</h3>
                    ${plot.plot_code ? `<span class="badge badge-info">קוד: ${plot.plot_code}</span>` : ''}
                </div>
                <button class="btn-close-card" onclick="closeHierarchyCard('plot')" title="סגור">✕</button>
            </div>
            ${plot.area_graves_count !== undefined || plot.rows || plot.columns ? `
                <div class="hierarchy-card-body">
                    <div class="card-info-row">
                        ${plot.area_graves_count !== undefined ? `<span class="info-item"><strong>אחוזות קבר:</strong> ${plot.area_graves_count}</span>` : ''}
                        ${plot.rows ? `<span class="info-item"><strong>שורות:</strong> ${plot.rows}</span>` : ''}
                        ${plot.columns ? `<span class="info-item"><strong>עמודות:</strong> ${plot.columns}</span>` : ''}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}

// ============================================
// 4. כרטיס אחוזת קבר
// ============================================
function createAreaGraveCard(areaGrave) {
    if (!areaGrave) {
        areaGrave = window.selectedAreaGrave || {
            id: window.currentAreaGraveId,
            name: window.currentAreaGraveName || 'אחוזת קבר'
        };
    }
    
    return `
        <div class="hierarchy-card area-grave-card" data-level="areaGrave" data-id="${areaGrave.id}">
            <div class="hierarchy-card-header">
                <div class="card-title-section">
                    <span class="card-icon">🏘️</span>
                    <h3 class="card-title">${areaGrave.name || areaGrave.area_name || 'אחוזת קבר ' + areaGrave.id}</h3>
                    ${areaGrave.area_code ? `<span class="badge badge-info">קוד: ${areaGrave.area_code}</span>` : ''}
                </div>
                <button class="btn-close-card" onclick="closeHierarchyCard('areaGrave')" title="סגור">✕</button>
            </div>
            ${areaGrave.graves_count !== undefined || areaGrave.type ? `
                <div class="hierarchy-card-body">
                    <div class="card-info-row">
                        ${areaGrave.graves_count !== undefined ? `<span class="info-item"><strong>מספר קברים:</strong> ${areaGrave.graves_count}</span>` : ''}
                        ${areaGrave.type ? `<span class="info-item"><strong>סוג:</strong> ${areaGrave.type}</span>` : ''}
                        ${areaGrave.status ? `
                            <span class="info-item">
                                <span class="badge ${areaGrave.status === 'available' ? 'badge-success' : 'badge-warning'}">
                                    ${areaGrave.status === 'available' ? 'פנוי' : 'תפוס'}
                                </span>
                            </span>
                        ` : ''}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}

// ============================================
// 5. פונקציות עזר
// ============================================

/**
 * סגירת כרטיס היררכיה וחזרה לרמה הקודמת
 */
function closeHierarchyCard(level) {
    console.log(`Closing hierarchy card: ${level}`);
    
    // הסרת הכרטיס מה-DOM
    const card = document.querySelector(`.hierarchy-card[data-level="${level}"]`);
    if (card) {
        card.remove();
    }
    
    // חזרה לרמה הקודמת בהיררכיה
    switch(level) {
        case 'cemetery':
            if (typeof loadAllCemeteries === 'function') {
                loadAllCemeteries();
            }
            break;
            
        case 'block':
            if (window.currentCemeteryId && typeof loadBlocksForCemetery === 'function') {
                // טען שוב את הגושים של בית העלמין הנוכחי
                loadBlocksForCemetery(window.currentCemeteryId, window.currentCemeteryName);
            } else if (typeof loadAllBlocks === 'function') {
                loadAllBlocks();
            }
            break;
            
        case 'plot':
            if (window.currentBlockId && typeof loadPlotsForBlock === 'function') {
                // טען שוב את החלקות של הגוש הנוכחי
                loadPlotsForBlock(window.currentBlockId, window.currentBlockName);
            } else if (typeof loadAllPlots === 'function') {
                loadAllPlots();
            }
            break;
            
        case 'areaGrave':
            if (window.currentPlotId && typeof loadAreaGravesForPlot === 'function') {
                // טען שוב את אחוזות הקבר של החלקה הנוכחית
                loadAreaGravesForPlot(window.currentPlotId, window.currentPlotName);
            } else if (typeof loadAllAreaGraves === 'function') {
                loadAllAreaGraves();
            }
            break;
    }
}

/**
 * הצגת כרטיס היררכיה בממשק
 */
function displayHierarchyCard(cardHtml) {
    if (!cardHtml) return;
    
    const mainContent = document.querySelector('.main-content');
    if (!mainContent) return;
    
    // הסר כרטיסים קודמים מאותה רמה או נמוכה יותר
    const existingCards = mainContent.querySelectorAll('.hierarchy-card');
    existingCards.forEach(card => card.remove());
    
    // הוסף את הכרטיס החדש לפני הטבלה
    const tableContainer = mainContent.querySelector('.table-container');
    if (tableContainer) {
        tableContainer.insertAdjacentHTML('beforebegin', cardHtml);
    } else {
        // אם אין טבלה, הוסף אחרי ה-action-bar
        const actionBar = mainContent.querySelector('.action-bar');
        if (actionBar) {
            actionBar.insertAdjacentHTML('afterend', cardHtml);
        }
    }
}

/**
 * ניקוי כל כרטיסי ההיררכיה
 */
function clearAllHierarchyCards() {
    const cards = document.querySelectorAll('.hierarchy-card');
    cards.forEach(card => card.remove());
}

// ============================================
// 6. CSS עבור הכרטיסים
// ============================================
const hierarchyCardsCSS = `
<style>
/* Hierarchy Cards Styles */
.hierarchy-card {
    background: white;
    border: 2px solid var(--primary-color, #667eea);
    border-radius: 12px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hierarchy-card-header {
    background: linear-gradient(135deg, var(--primary-color, #667eea), var(--primary-dark, #764ba2));
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-title-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-icon {
    font-size: 1.5rem;
}

.card-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
}

.hierarchy-card-header .badge {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
}

.btn-close-card {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.25rem;
    padding: 0;
}

.btn-close-card:hover {
    background: rgba(255,255,255,0.3);
    transform: scale(1.1);
}

.hierarchy-card-body {
    padding: 1rem 1.5rem;
    background: var(--bg-secondary, #f8f9fa);
}

.card-info-row {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.info-item {
    font-size: 0.875rem;
    color: var(--text-secondary, #475569);
}

.info-item strong {
    color: var(--text-primary, #1e293b);
    margin-left: 0.25rem;
}

/* Specific card types */
.cemetery-card .hierarchy-card-header { 
    background: linear-gradient(135deg, #667eea, #764ba2); 
}

.block-card .hierarchy-card-header { 
    background: linear-gradient(135deg, #f093fb, #f5576c); 
}

.plot-card .hierarchy-card-header { 
    background: linear-gradient(135deg, #4facfe, #00f2fe); 
}

.area-grave-card .hierarchy-card-header { 
    background: linear-gradient(135deg, #43e97b, #38f9d7); 
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .hierarchy-card {
        margin-bottom: 0.75rem;
        border-radius: 8px;
    }
    
    .hierarchy-card-header {
        padding: 0.75rem 1rem;
    }
    
    .card-title-section {
        gap: 0.5rem;
    }
    
    .card-icon {
        font-size: 1.25rem;
    }
    
    .card-title {
        font-size: 1rem;
    }
    
    .hierarchy-card-body {
        padding: 0.75rem 1rem;
    }
    
    .card-info-row {
        gap: 1rem;
        flex-direction: column;
    }
}
</style>
`;

// ============================================
// 7. אתחול אוטומטי
// ============================================

// הוסף את ה-CSS לדף אם עוד לא קיים
if (!document.getElementById('hierarchy-cards-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'hierarchy-cards-styles';
    styleElement.innerHTML = hierarchyCardsCSS;
    document.head.appendChild(styleElement.firstChild);
}

// הגדר את הפונקציות ב-window object
// window.createCemeteryCard = createCemeteryCard;
// window.createBlockCard = createBlockCard;
// window.createPlotCard = createPlotCard;
// window.createAreaGraveCard = createAreaGraveCard;
window.closeHierarchyCard = closeHierarchyCard;
window.displayHierarchyCard = displayHierarchyCard;
window.clearAllHierarchyCards = clearAllHierarchyCards;

console.log('Hierarchy Cards System loaded successfully!');