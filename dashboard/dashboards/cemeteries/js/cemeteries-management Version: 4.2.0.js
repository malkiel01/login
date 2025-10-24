/*
 * File: dashboards/dashboard/cemeteries/assets/js/cemeteries-management.js
 * Version: 4.2.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - v4.0.0: תיקון API - שימוש ב-cemeteries-api.php במקום cemetery-hierarchy.php
 * - v4.2.0: תיקון backward compatibility - הוספת alias ל-loadAllCemeteries
 */

// ===================================================================
// משתנים גלובליים
// ===================================================================
let cemeteriesLiveSearch = null;
let currentCemeteries = [];
const CEMETERIES_API_ENDPOINT = '/dashboard/dashboards/cemeteries/api/cemeteries-api.php'; // ✅ תוקן!

// ===================================================================
// טעינת בתי עלמין
// ===================================================================
async function loadCemeteries() {
    console.log('📋 Loading cemeteries - v4.0.0 (Correct API)...');

    try {
        // ניקוי הדשבורד
        if (typeof clearDashboard === 'function') {
            clearDashboard({
                targetLevel: 'cemetery',
                keepBreadcrumb: false,
                keepSidebar: false,
                keepCard: false,
                fullReset: false
            });
        }

        // עדכון breadcrumb
        if (typeof updateBreadcrumb === 'function') {
            updateBreadcrumb({ cemetery: { name: 'בתי עלמין' } });
        }

        // בניית קונטיינר בתי עלמין
        buildCemeteriesContainer();

        // אתחול LiveSearch
        if (!cemeteriesLiveSearch) {
            initCemeteriesLiveSearch();
        } else {
            cemeteriesLiveSearch.refresh();
        }

        console.log('✅ Cemeteries loaded successfully (v4.0.0)');

    } catch (error) {
        console.error('❌ Error loading cemeteries:', error);
        alert('שגיאה בטעינת בתי עלמין. נסה שוב.');
    }
}

// ===================================================================
// בניית קונטיינר בתי עלמין
// ===================================================================
function buildCemeteriesContainer() {
    console.log('🏗️ Building cemeteries container - v4.0.0...');

    // מציאת או יצירת main-container
    let mainContainer = document.getElementById('main-container');
    if (!mainContainer) {
        console.log('⚠️ main-container not found, creating one...');
        mainContainer = document.createElement('div');
        mainContainer.id = 'main-container';
        mainContainer.className = 'main-content';
        
        const dashboardElement = document.querySelector('.dashboard, #dashboard');
        if (dashboardElement) {
            dashboardElement.appendChild(mainContainer);
        } else {
            document.body.appendChild(mainContainer);
        }
    }

    // יצירת מבנה HTML - זהה ל-customers
    mainContainer.innerHTML = `
        <div id="cemeterySearchSection" class="search-section">
            <div class="search-container">
                <input 
                    type="text" 
                    id="cemeterySearchInput" 
                    class="search-input" 
                    placeholder="חיפוש בתי עלמין..."
                />
                <svg class="search-icon"><use xlink:href="#icon-search"></use></svg>
            </div>
            <div id="cemeteryCounter" class="search-counter"></div>
        </div>
        
        <div class="table-container">
            <table id="mainTable" class="data-table">
                <thead>
                    <tr id="tableHeaders">
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <th>שם בית העלמין</th>
                        <th>קוד</th>
                        <th>כתובת</th>
                        <th>איש קשר</th>
                        <th>טלפון</th>
                        <th>מספר גושים</th>
                        <th style="width: 120px;">פעולות</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
        
        <div id="paginationContainer"></div>
    `;

    console.log('✅ Cemeteries container built (v4.0.0)');
}

// ===================================================================
// אתחול LiveSearch
// ===================================================================
function initCemeteriesLiveSearch() {
    console.log('🔍 Initializing LiveSearch for cemeteries - v4.0.0...');

    cemeteriesLiveSearch = new LiveSearch({
        searchInputId: 'cemeterySearchInput',
        counterElementId: 'cemeteryCounter',
        resultContainerId: 'tableBody',
        paginationContainerId: 'paginationContainer',
        apiEndpoint: CEMETERIES_API_ENDPOINT + '?action=list', // ✅ cemeteries-api.php
        instanceName: 'cemeteriesLiveSearch',
        debounceDelay: 300,
        itemsPerPage: 50,
        minSearchLength: 0,
        renderFunction: renderCemeteriesRows
    });

    console.log('✅ LiveSearch initialized for cemeteries (v4.0.0)');
}

// ===================================================================
// רינדור שורות בתי עלמין
// ===================================================================
function renderCemeteriesRows(data, container) {
    console.log('🎨 renderCemeteriesRows called with', data.length, 'items (v4.0.0)');

    if (!container) {
        console.error('❌ Container not found!');
        return;
    }

    // אם אין תוצאות
    if (data.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                    <div style="color: #999;">
                        <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
                        <div>לא נמצאו בתי עלמין</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    // שמירת הנתונים הנוכחיים
    currentCemeteries = data;

    // בניית השורות - התאמה מלאה למבנה של cemeteries-api.php
    container.innerHTML = data.map(cemetery => `
        <tr data-id="${cemetery.unicId || cemetery.id}" class="clickable-row">
            <td>
                <input type="checkbox" class="cemetery-checkbox" value="${cemetery.unicId || cemetery.id}">
            </td>
            <td>
                <strong>${cemetery.cemeteryNameHe || ''}</strong>
                ${cemetery.cemeteryNameEn ? `<br><small style="color:#666;">${cemetery.cemeteryNameEn}</small>` : ''}
            </td>
            <td>${cemetery.cemeteryCode || '-'}</td>
            <td>
                ${cemetery.address || '-'}
                ${cemetery.coordinates ? `<br><small style="color:#666;">📍 ${cemetery.coordinates}</small>` : ''}
            </td>
            <td>${cemetery.contactName || '-'}</td>
            <td>${cemetery.contactPhoneName || '-'}</td>
            <td>${cemetery.blocks_count || 0}</td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="editCemetery('${cemetery.unicId || cemetery.id}')" title="עריכה">
                    <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteCemetery('${cemetery.unicId || cemetery.id}')" title="מחיקה">
                    <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                </button>
            </td>
        </tr>
    `).join('');

    // הוספת מאזינים לקליקים על שורות
    container.querySelectorAll('tr[data-id]').forEach(row => {
        row.addEventListener('click', (e) => {
            // אל תפעיל אם לחצו על checkbox או כפתור
            if (e.target.closest('input[type="checkbox"]') || e.target.closest('button')) {
                return;
            }
            
            const cemeteryId = row.dataset.id;
            const cemeteryName = row.querySelector('strong')?.textContent || 'בית עלמין';
            loadBlocks(cemeteryId, cemeteryName);
        });
    });

    console.log('✅ Rendered', data.length, 'cemetery rows (v4.0.0)');
}

// ===================================================================
// פונקציות עזר - טעינת גושים
// ===================================================================
function loadBlocks(cemeteryId, cemeteryName) {
    console.log(`📦 Loading blocks for cemetery: ${cemeteryName} (ID: ${cemeteryId})`);
    
    // עדכון breadcrumb
    if (typeof updateBreadcrumb === 'function') {
        updateBreadcrumb({
            cemetery: { id: cemeteryId, name: cemeteryName }
        });
    }

    // טעינת גושים (מימוש קיים במערכת)
    if (typeof loadBlocksData === 'function') {
        loadBlocksData(cemeteryId, cemeteryName);
    } else {
        console.warn('⚠️ loadBlocksData function not found');
    }
}

// ===================================================================
// פונקציות CRUD
// ===================================================================
function editCemetery(cemeteryId) {
    console.log('✏️ Edit cemetery:', cemeteryId);
    
    // פתיחת טופס עריכה
    if (typeof FormHandler !== 'undefined' && FormHandler.openForm) {
        FormHandler.openForm('cemetery', null, cemeteryId);
    } else {
        alert(`עריכת בית עלמין: ${cemeteryId}`);
    }
}

function deleteCemetery(cemeteryId) {
    console.log('🗑️ Delete cemetery:', cemeteryId);
    
    if (!confirm('האם אתה בטוח שברצונך למחוק את בית העלמין?')) {
        return;
    }

    // ביצוע מחיקה דרך cemeteries-api.php
    fetch(`${CEMETERIES_API_ENDPOINT}?action=delete&id=${cemeteryId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('בית העלמין נמחק בהצלחה');
            // רענן את הרשימה
            if (cemeteriesLiveSearch) {
                cemeteriesLiveSearch.refresh();
            }
        } else {
            alert('שגיאה במחיקת בית העלמין: ' + (data.error || 'שגיאה לא ידועה'));
        }
    })
    .catch(error => {
        console.error('Error deleting cemetery:', error);
        alert('שגיאה במחיקת בית העלמין');
    });
}

// ===================================================================
// בחירת הכל
// ===================================================================
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.cemetery-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// ===================================================================
// פונקציות דיבאג
// ===================================================================
window.checkCemeteriesStatus = function() {
    if (!cemeteriesLiveSearch) {
        console.warn('⚠️ LiveSearch not initialized yet');
        return;
    }

    console.log('📊 Cemeteries LiveSearch Status:');
    console.log('  - Current cemeteries:', currentCemeteries.length);
    console.log('  - API Endpoint:', CEMETERIES_API_ENDPOINT);
    console.log('  - Full API URL:', CEMETERIES_API_ENDPOINT + '?action=list');
    
    return {
        initialized: !!cemeteriesLiveSearch,
        count: currentCemeteries.length,
        endpoint: CEMETERIES_API_ENDPOINT,
        fullUrl: CEMETERIES_API_ENDPOINT + '?action=list'
    };
};

// ===================================================================
// Backward Compatibility - Aliases
// ===================================================================
// תמיכה בשמות ישנים שעדיין נמצאים ב-HTML
window.loadAllCemeteries = loadCemeteries; // ✅ Alias לשם הישן

// ===================================================================
// אתחול מודול
// ===================================================================
console.log('✅ Cemeteries Management Module Loaded - v4.2.0: Backward Compatible');
console.log('💡 API: ' + CEMETERIES_API_ENDPOINT);
console.log('💡 Aliases: loadAllCemeteries → loadCemeteries');
console.log('💡 Commands: checkCemeteriesStatus() - בדוק סטטוס המערכת');