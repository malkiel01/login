
// ============================================
// 5. פונקציות עזר
// ============================================

/**
 * סגירת כרטיס היררכיה וחזרה לרמה הקודמת
 */
function closeHierarchyCard(level) {
    
    // הסרת הכרטיס מה-DOM
    const card = document.querySelector(`.hierarchy-card[data-level="${level}"]`);
    if (card) {
        card.remove();
    }
    
    // חזרה לרמה הקודמת בהיררכיה
    switch(level) {
        case 'cemetery':
            if (typeof loadCemeteries === 'function') {
                loadCemeteries();
            }
            break;
            
        case 'block':
            if (window.currentCemeteryId && typeof loadBlocksForCemetery === 'function') {
                // טען שוב את הגושים של בית העלמין הנוכחי
                loadBlocksForCemetery(window.currentCemeteryId, window.currentCemeteryName);
            } else if (typeof loadBlocks === 'function') {
                loadBlocks();
            }
            break;
            
        case 'plot':
            if (window.currentBlockId && typeof loadPlotsForBlock === 'function') {
                // טען שוב את החלקות של הגוש הנוכחי
                loadPlotsForBlock(window.currentBlockId, window.currentBlockName);
            } else if (typeof loadPlots === 'function') {
                loadPlots();
            }
            break;
            
        case 'areaGrave':
            if (window.currentPlotId && typeof loadAreaGravesForPlot === 'function') {
                // טען שוב את אחוזות הקבר של החלקה הנוכחית
                loadAreaGravesForPlot(window.currentPlotId, window.currentPlotName);
            } else if (typeof loadAreaGraves === 'function') {
                loadAreaGraves();
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
    
    // נקה את כל הכרטיסים הקיימים - כולל info-card ו-stats-row
    const existingInfoCards = mainContent.querySelectorAll('.info-card');
    existingInfoCards.forEach(card => card.remove());
    
    const existingStatsRows = mainContent.querySelectorAll('.stats-row');
    existingStatsRows.forEach(row => row.remove());
    
    const existingHierarchyCards = mainContent.querySelectorAll('.hierarchy-card');
    existingHierarchyCards.forEach(card => card.remove());
    
    // הוסף את הכרטיס החדש לפני הטבלה
    const tableContainer = mainContent.querySelector('.table-container');
    if (tableContainer) {
        tableContainer.insertAdjacentHTML('beforebegin', cardHtml);
    } else {
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
    const infoCards = document.querySelectorAll('.info-card');
    infoCards.forEach(card => card.remove());
    
    const statsRows = document.querySelectorAll('.stats-row');
    statsRows.forEach(row => row.remove());
    
    const hierarchyCards = document.querySelectorAll('.hierarchy-card');
    hierarchyCards.forEach(card => card.remove());
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
window.closeHierarchyCard = closeHierarchyCard;
window.displayHierarchyCard = displayHierarchyCard;
window.clearAllHierarchyCards = clearAllHierarchyCards;

