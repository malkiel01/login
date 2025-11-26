// /*
//  * File: dashboards/dashboard/cemeteries/js/grave-card-modal.js
//  * Version: 1.0.0
//  * Updated: 2025-11-24
//  * Author: Malkiel
//  * Change Summary:
//  * - v1.0.0: יצירה ראשונית - לוגיקת כרטיס קבר פופאפ
//  */

// // ========================================
// // מודול כרטיס קבר - Grave Card Modal
// // ========================================

// const GraveCardModal = {
//     // מצב נוכחי
//     isOpen: false,
//     currentGraveId: null,
    
//     // אלמנטים
//     overlay: null,
//     modal: null,
    
//     // ========================================
//     // אתחול
//     // ========================================
//     init: function() {
//         console.log('🎴 GraveCardModal: אתחול מודול כרטיס קבר');
//         this.createModalStructure();
//         this.bindEvents();
//     },
    
//     // ========================================
//     // יצירת מבנה ה-DOM
//     // ========================================
//     createModalStructure: function() {
//         // בדוק אם כבר קיים
//         if (document.getElementById('graveCardOverlay')) {
//             this.overlay = document.getElementById('graveCardOverlay');
//             this.modal = document.getElementById('graveCardModal');
//             return;
//         }
        
//         // יצירת שכבת רקע
//         this.overlay = document.createElement('div');
//         this.overlay.id = 'graveCardOverlay';
//         this.overlay.className = 'grave-card-overlay';
        
//         // יצירת המודל
//         this.modal = document.createElement('div');
//         this.modal.id = 'graveCardModal';
//         this.modal.className = 'grave-card-modal';
//         this.modal.innerHTML = this.getLoadingHTML();
        
//         // הוספה ל-DOM
//         document.body.appendChild(this.overlay);
//         document.body.appendChild(this.modal);
//     },
    
//     // ========================================
//     // קישור אירועים
//     // ========================================
//     bindEvents: function() {
//         // סגירה בלחיצה על הרקע
//         this.overlay.addEventListener('click', () => this.close());
        
//         // סגירה ב-Escape
//         document.addEventListener('keydown', (e) => {
//             if (e.key === 'Escape' && this.isOpen) {
//                 this.close();
//             }
//         });
//     },
    
//     // ========================================
//     // פתיחת הכרטיס
//     // ========================================
//     open: async function(graveId) {
//         console.log('📖 GraveCardModal: פתיחת כרטיס לקבר', graveId);
        
//         this.currentGraveId = graveId;
//         this.isOpen = true;
        
//         // הצגת מצב טעינה
//         this.modal.innerHTML = this.getLoadingHTML();
//         this.overlay.classList.add('active');
//         this.modal.classList.add('active');
        
//         // מניעת גלילת הדף
//         document.body.style.overflow = 'hidden';
        
//         try {
//             // טעינת נתוני הקבר מה-API
//             const graveData = await this.fetchGraveData(graveId);
            
//             // עדכון התוכן
//             this.modal.innerHTML = this.renderGraveCard(graveData);
            
//             // קישור אירועי כפתורים
//             this.bindCardEvents();
            
//         } catch (error) {
//             console.error('❌ GraveCardModal: שגיאה בטעינת נתונים', error);
//             this.modal.innerHTML = this.getErrorHTML(error.message);
//         }
//     },
    
//     // ========================================
//     // סגירת הכרטיס
//     // ========================================
//     close: function() {
//         console.log('🚪 GraveCardModal: סגירת כרטיס');
        
//         this.isOpen = false;
//         this.currentGraveId = null;
        
//         this.overlay.classList.remove('active');
//         this.modal.classList.remove('active');
        
//         // החזרת גלילת הדף
//         document.body.style.overflow = '';
//     },
    
//     // ========================================
//     // טעינת נתונים מה-API
//     // ========================================
//     fetchGraveData: async function(graveId) {
//         const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=getDetails&id=${encodeURIComponent(graveId)}`);
        
//         if (!response.ok) {
//             throw new Error('שגיאה בתקשורת עם השרת');
//         }
        
//         const result = await response.json();
        
//         if (!result.success) {
//             throw new Error(result.error || 'הקבר לא נמצא');
//         }
        
//         return result.data;
//     },
    
//     // ========================================
//     // רינדור תוכן הכרטיס
//     // ========================================
//     renderGraveCard: function(grave) {
//         const statusInfo = this.getStatusInfo(grave.graveStatus);
//         const plotTypeName = this.getPlotTypeName(grave.plotType);
        
//         return `
//             <!-- כותרת -->
//             <div class="grave-card-header">
//                 <div class="grave-card-header-content">
//                     <h2 class="grave-card-title">
//                         <span class="grave-card-title-icon">⚰️</span>
//                         קבר ${grave.graveNameHe || 'ללא שם'}
//                     </h2>
//                     <div class="grave-card-subtitle">
//                         <span>🏛️ ${grave.cemeteryNameHe || '-'}</span>
//                         <span>📦 ${grave.blockNameHe || '-'}</span>
//                         <span>📋 ${grave.plotNameHe || '-'}</span>
//                         <span>📏 ${grave.lineNameHe || '-'}</span>
//                         <span>🏘️ ${grave.areaGraveNameHe || '-'}</span>
//                     </div>
//                 </div>
//                 <button class="grave-card-close" onclick="GraveCardModal.close()" title="סגור">
//                     ✕
//                 </button>
//             </div>
            
//             <!-- גוף הכרטיס -->
//             <div class="grave-card-body">
//                 <!-- סטטוס -->
//                 <div class="grave-status-badge ${statusInfo.class}">
//                     <span>${statusInfo.icon}</span>
//                     <span>${statusInfo.label}</span>
//                 </div>
                
//                 <!-- פרטי קבר -->
//                 <div class="grave-card-section">
//                     <h3 class="grave-card-section-title">
//                         <span class="icon">📋</span>
//                         פרטי הקבר
//                     </h3>
//                     <div class="grave-card-grid">
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">מזהה ייחודי</span>
//                             <span class="grave-card-value">${grave.unicId || '-'}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">שם הקבר</span>
//                             <span class="grave-card-value">${grave.graveNameHe || '-'}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">סוג חלקה</span>
//                             <span class="grave-card-value">${plotTypeName}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">מיקום</span>
//                             <span class="grave-card-value">${this.getLocationName(grave.graveLocation)}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">גודל</span>
//                             <span class="grave-card-value">${grave.isSmallGrave == 1 ? '📏 קבר קטן' : '📐 קבר רגיל'}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">עלות בנייה</span>
//                             <span class="grave-card-value">${grave.constructionCost ? '₪' + grave.constructionCost : '-'}</span>
//                         </div>
//                     </div>
//                 </div>
                
//                 <!-- מיקום היררכי -->
//                 <div class="grave-card-section">
//                     <h3 class="grave-card-section-title">
//                         <span class="icon">📍</span>
//                         מיקום היררכי
//                     </h3>
//                     <div class="grave-card-grid">
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">בית עלמין</span>
//                             <span class="grave-card-value">${grave.cemeteryNameHe || '-'}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">גוש</span>
//                             <span class="grave-card-value">${grave.blockNameHe || '-'}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">חלקה</span>
//                             <span class="grave-card-value">${grave.plotNameHe || '-'}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">שורה</span>
//                             <span class="grave-card-value">${grave.lineNameHe || '-'}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">אחוזת קבר</span>
//                             <span class="grave-card-value">${grave.areaGraveNameHe || '-'}</span>
//                         </div>
//                     </div>
//                 </div>
                
//                 ${this.renderPurchaseSection(grave)}
                
//                 ${this.renderBurialSection(grave)}
                
//                 <!-- הערות -->
//                 ${grave.comments ? `
//                 <div class="grave-card-section">
//                     <h3 class="grave-card-section-title">
//                         <span class="icon">📝</span>
//                         הערות
//                     </h3>
//                     <div class="grave-card-value">${grave.comments}</div>
//                 </div>
//                 ` : ''}
                
//                 <!-- תאריכים -->
//                 <div class="grave-card-section">
//                     <h3 class="grave-card-section-title">
//                         <span class="icon">📅</span>
//                         תאריכים
//                     </h3>
//                     <div class="grave-card-grid">
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">תאריך יצירה</span>
//                             <span class="grave-card-value">${this.formatDate(grave.createDate)}</span>
//                         </div>
//                         <div class="grave-card-field">
//                             <span class="grave-card-label">עדכון אחרון</span>
//                             <span class="grave-card-value">${this.formatDate(grave.updateDate)}</span>
//                         </div>
//                     </div>
//                 </div>
//             </div>
            
//             <!-- Footer -->
//             <div class="grave-card-footer">
//                 <button class="grave-card-btn grave-card-btn-secondary" onclick="GraveCardModal.close()">
//                     סגור
//                 </button>
//                 <button class="grave-card-btn grave-card-btn-primary" onclick="GraveCardModal.editGrave('${grave.unicId}')">
//                     <span>✏️</span>
//                     עריכה
//                 </button>
//             </div>
//         `;
//     },
    
//     // ========================================
//     // רינדור סקציית רכישה
//     // ========================================
//     renderPurchaseSection: function(grave) {
//         if (!grave.purchase) return '';
        
//         const p = grave.purchase;
//         return `
//             <div class="grave-card-section grave-card-customer-info">
//                 <h3 class="grave-card-section-title">
//                     <span class="icon">💰</span>
//                     פרטי רכישה
//                 </h3>
//                 <div class="grave-card-grid">
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">מספר רכישה</span>
//                         <span class="grave-card-value">${p.serialPurchaseId || '-'}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">שם הרוכש</span>
//                         <span class="grave-card-value">
//                             ${p.clientFullNameHe ? 
//                                 `<a href="#" class="grave-card-customer-link" onclick="GraveCardModal.viewCustomer('${p.clientId}')">${p.clientFullNameHe}</a>` : 
//                                 '-'}
//                         </span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">ת.ז. רוכש</span>
//                         <span class="grave-card-value">${p.clientNumId || '-'}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">תאריך רכישה</span>
//                         <span class="grave-card-value">${this.formatDate(p.dateOpening)}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">מחיר</span>
//                         <span class="grave-card-value">${p.price ? '₪' + Number(p.price).toLocaleString() : '-'}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">סטטוס רכישה</span>
//                         <span class="grave-card-value">${this.getPurchaseStatusName(p.purchaseStatus)}</span>
//                     </div>
//                 </div>
//             </div>
//         `;
//     },
    
//     // ========================================
//     // רינדור סקציית קבורה
//     // ========================================
//     renderBurialSection: function(grave) {
//         if (!grave.burial) return '';
        
//         const b = grave.burial;
//         return `
//             <div class="grave-card-section grave-card-customer-info">
//                 <h3 class="grave-card-section-title">
//                     <span class="icon">🕯️</span>
//                     פרטי קבורה
//                 </h3>
//                 <div class="grave-card-grid">
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">מספר קבורה</span>
//                         <span class="grave-card-value">${b.serialBurialId || '-'}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">שם הנפטר</span>
//                         <span class="grave-card-value">
//                             ${b.clientFullNameHe ? 
//                                 `<a href="#" class="grave-card-customer-link" onclick="GraveCardModal.viewCustomer('${b.clientId}')">${b.clientFullNameHe}</a>` : 
//                                 '-'}
//                         </span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">ת.ז. נפטר</span>
//                         <span class="grave-card-value">${b.clientNumId || '-'}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">תאריך פטירה</span>
//                         <span class="grave-card-value">${this.formatDate(b.dateDeath)}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">תאריך קבורה</span>
//                         <span class="grave-card-value">${this.formatDate(b.dateBurial)}</span>
//                     </div>
//                     <div class="grave-card-field">
//                         <span class="grave-card-label">מקום פטירה</span>
//                         <span class="grave-card-value">${b.placeDeath || '-'}</span>
//                     </div>
//                 </div>
//             </div>
//         `;
//     },
    
//     // ========================================
//     // עזרים
//     // ========================================
    
//     getStatusInfo: function(status) {
//         const statuses = {
//             1: { label: 'פנוי', class: 'available', icon: '✅' },
//             2: { label: 'נרכש', class: 'purchased', icon: '💰' },
//             3: { label: 'תפוס', class: 'buried', icon: '🕯️' },
//             4: { label: 'שמור', class: 'saved', icon: '📌' }
//         };
//         return statuses[status] || { label: 'לא ידוע', class: 'available', icon: '❓' };
//     },
    
//     getPlotTypeName: function(type) {
//         const types = {
//             1: 'פטור',
//             2: 'חריג',
//             3: 'סמוך'
//         };
//         return types[type] || 'לא ידוע';
//     },
    
//     getLocationName: function(location) {
//         const locations = {
//             1: 'עליון',
//             2: 'תחתון',
//             3: 'אמצעי'
//         };
//         return locations[location] || '-';
//     },
    
//     getPurchaseStatusName: function(status) {
//         const statuses = {
//             1: 'פעיל',
//             2: 'בוטל',
//             3: 'הושלם'
//         };
//         return statuses[status] || 'לא ידוע';
//     },
    
//     formatDate: function(dateStr) {
//         if (!dateStr || dateStr === '0000-00-00' || dateStr === 'null') return '-';
//         try {
//             const date = new Date(dateStr);
//             if (isNaN(date.getTime())) return dateStr;
//             return date.toLocaleDateString('he-IL', {
//                 year: 'numeric',
//                 month: '2-digit',
//                 day: '2-digit'
//             });
//         } catch {
//             return dateStr;
//         }
//     },
    
//     // ========================================
//     // HTML מצבים
//     // ========================================
    
//     getLoadingHTML: function() {
//         return `
//             <div class="grave-card-header">
//                 <div class="grave-card-header-content">
//                     <h2 class="grave-card-title">
//                         <span class="grave-card-title-icon">⚰️</span>
//                         טוען פרטי קבר...
//                     </h2>
//                 </div>
//                 <button class="grave-card-close" onclick="GraveCardModal.close()">✕</button>
//             </div>
//             <div class="grave-card-body">
//                 <div class="grave-card-loading">
//                     <div class="grave-card-spinner"></div>
//                     <span class="grave-card-loading-text">טוען נתונים...</span>
//                 </div>
//             </div>
//         `;
//     },
    
//     getErrorHTML: function(message) {
//         return `
//             <div class="grave-card-header">
//                 <div class="grave-card-header-content">
//                     <h2 class="grave-card-title">
//                         <span class="grave-card-title-icon">⚠️</span>
//                         שגיאה
//                     </h2>
//                 </div>
//                 <button class="grave-card-close" onclick="GraveCardModal.close()">✕</button>
//             </div>
//             <div class="grave-card-body">
//                 <div class="grave-card-error">
//                     <div class="grave-card-error-icon">❌</div>
//                     <p>${message || 'אירעה שגיאה בטעינת הנתונים'}</p>
//                 </div>
//             </div>
//             <div class="grave-card-footer">
//                 <button class="grave-card-btn grave-card-btn-secondary" onclick="GraveCardModal.close()">סגור</button>
//             </div>
//         `;
//     },
    
//     // ========================================
//     // פעולות
//     // ========================================
    
//     editGrave: function(graveId) {
//         console.log('✏️ GraveCardModal: מעבר לעריכת קבר', graveId);
//         this.close();
        
//         // קריאה לפונקציית העריכה הקיימת
//         if (typeof window.tableRenderer !== 'undefined' && typeof window.tableRenderer.editItem === 'function') {
//             window.tableRenderer.editItem(graveId);
//         } else if (typeof editGrave === 'function') {
//             editGrave(graveId);
//         } else {
//             console.warn('פונקציית עריכה לא נמצאה');
//         }
//     },
    
//     viewCustomer: function(customerId) {
//         console.log('👤 GraveCardModal: צפייה בלקוח', customerId);
//         // כאן ניתן להוסיף ניווט לדף הלקוח או פתיחת מודל לקוח
//         alert('צפייה בלקוח: ' + customerId);
//     },
    
//     bindCardEvents: function() {
//         // קישור אירועים נוספים אם נדרש
//     }
// };

// // ========================================
// // פונקציה גלובלית לפתיחת כרטיס קבר
// // ========================================
// window.openGraveCard = function(graveId) {
//     GraveCardModal.open(graveId);
// };

// // ========================================
// // אתחול בטעינת הדף
// // ========================================
// document.addEventListener('DOMContentLoaded', function() {
//     GraveCardModal.init();
// });

// // הגדר גלובלית
// window.GraveCardModal = GraveCardModal;