/*
 * File: dashboards/dashboard/cemeteries/assets/js/entities-framework/entity-loader.js
 * Version: 1.0.0
 * Updated: 2025-11-20
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: 🆕 מנהל טעינת נתונים גנרי
 *   ✅ loadBrowseData() - טעינה ראשונית
 *   ✅ appendMoreData() - Infinite Scroll
 *   ✅ loadStats() - טעינת סטטיסטיקות
 *   ✅ תמיכה מלאה ב-AbortController
 *   ✅ לוגים מפורטים ומובנים
 */


// ===================================================================
// מנהל טעינת נתונים גנרי
// ===================================================================
class EntityLoader {
    
    /**
     * טעינת נתונים ראשונית (Browse Mode)
     * @param {string} entityType - סוג היישות
     * @param {AbortSignal} signal - signal לביטול
     * @param {string|null} parentId - מזהה הורה (אופציונלי)
     * @returns {Promise<Object>} תוצאת הטעינה
     */
    static async loadBrowseData(entityType, signal = null, parentId = null) {
        const config = ENTITY_CONFIG[entityType];
        
        if (!config) {
            throw new Error(`❌ Unknown entity type: ${entityType}`);
        }
        
        
        // איפוס state
        entityState.setState(entityType, {
            currentPage: 1,
            currentData: []
        });
        
        try {
            // בניית URL עם קידוד נכון למניעת XSS
            let apiUrl = `${config.apiEndpoint}?action=list&limit=${encodeURIComponent(config.defaultLimit)}&page=1`;
            apiUrl += `&orderBy=${encodeURIComponent(config.defaultOrderBy)}&sortDirection=${encodeURIComponent(config.defaultSortDirection)}`;

            // הוספת parent ID אם קיים - עם קידוד URL
            if (parentId && config.parentParam) {
                apiUrl += `&${encodeURIComponent(config.parentParam)}=${encodeURIComponent(parentId)}`;
            }
            
            // שליחת בקשה
            const response = await fetch(apiUrl, { signal });

            // 403 = אין הרשאה - לא מציגים שגיאה, פשוט לא טוענים
            if (response.status === 403) {
                console.log(`⚠️ No permission to view ${entityType}`);
                return { success: false, noPermission: true };
            }

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success && result.data) {
                // עדכון state
                entityState.setState(entityType, {
                    currentData: result.data,
                    currentPage: result.pagination?.page || 1,
                    totalPages: result.pagination?.pages || 1,
                    lastUpdated: new Date().toISOString()
                });
                
                
                return {
                    success: true,
                    data: result.data,
                    pagination: result.pagination
                };
            } else {
                throw new Error(result.error || `Failed to load ${config.plural}`);
            }
            
        } catch (error) {
            if (error.name === 'AbortError') {
                return { success: false, aborted: true };
            }
            
            console.error(`❌ Error loading ${entityType} browse data:`, error);
            
            if (typeof showToast === 'function') {
                showToast(`שגיאה בטעינת ${config.plural}`, 'error');
            }
            
            return { success: false, error: error.message };
        }
    }

    /**
     * טעינת עוד נתונים (Infinite Scroll)
     * @param {string} entityType - סוג היישות
     * @param {string|null} parentId - מזהה הורה (אופציונלי)
     * @returns {Promise<boolean>} האם הטעינה הצליחה
     */
    static async appendMoreData(entityType, parentId = null) {
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);
        
        // בדיקות בסיסיות
        if (state.isLoadingMore) {
            return false;
        }
        
        if (state.currentPage >= state.totalPages) {
            return false;
        }
        
        // התחל טעינה
        entityState.setLoading(entityType, true);
        const nextPage = state.currentPage + 1;
        
        // עדכון מונה טעינות
        const loadCounter = entityState.incrementLoadCounter(entityType);
        
        try {
            // בניית URL לעמוד הבא - עם קידוד נכון למניעת XSS
            let apiUrl = `${config.apiEndpoint}?action=list&limit=${encodeURIComponent(config.defaultLimit)}&page=${encodeURIComponent(nextPage)}`;
            apiUrl += `&orderBy=${encodeURIComponent(config.defaultOrderBy)}&sortDirection=${encodeURIComponent(config.defaultSortDirection)}`;

            // הוספת parent ID אם קיים - עם קידוד URL
            if (parentId && config.parentParam) {
                apiUrl += `&${encodeURIComponent(config.parentParam)}=${encodeURIComponent(parentId)}`;
            }
            
            // שליחת בקשה
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success && result.data && result.data.length > 0) {
                // שמור את הגודל הקודם
                const previousTotal = state.currentData.length;
                
                // הוסף נתונים חדשים
                const updatedData = entityState.appendData(entityType, result.data);
                
                // עדכון pagination
                entityState.updatePagination(entityType, nextPage, state.totalPages);

                // עדכן את הטבלה אם קיימת
                if (state.tableInstance) {
                    state.tableInstance.setData(updatedData);
                }
                
                entityState.setLoading(entityType, false);
                return true;
                
            } else {
                entityState.setLoading(entityType, false);
                return false;
            }
            
        } catch (error) {
            console.error(`❌ Error loading more ${config.plural}:`, error);
            entityState.setLoading(entityType, false);
            return false;
        }
    }

    // /**
    //  * טעינת סטטיסטיקות
    //  * @param {string} entityType - סוג היישות
    //  * @param {AbortSignal} signal - signal לביטול
    //  * @param {string|null} parentId - מזהה הורה (אופציונלי)
    //  * @returns {Promise<Object>} הסטטיסטיקות
    //  */
    // static async loadStats(entityType, signal = null, parentId = null) {
    //     const config = ENTITY_CONFIG[entityType];
    //     const statsConfig = config.statsConfig;
        
    //     if (!statsConfig || !statsConfig.elements) {
    //         console.warn(`⚠️ No stats config for ${entityType}`);
    //         return { success: false };
    //     }
        
    //     console.log(`📊 Loading stats for ${entityType}...`);
        
    //     try {
    //         // בניית URL
    //         let apiUrl = `${config.apiEndpoint}?action=stats`;
            
    //         // הוספת parent ID אם נדרש
    //         if (parentId && statsConfig.parentParam) {
    //             apiUrl += `&${statsConfig.parentParam}=${parentId}`;
    //         }
            
    //         // שליחת בקשה
    //         const response = await fetch(apiUrl, { signal });
            
    //         if (!response.ok) {
    //             throw new Error(`HTTP error! status: ${response.status}`);
    //         }
            
    //         const result = await response.json();
            
    //         if (result.success && result.stats) {
    //             // עדכון ה-DOM
    //             Object.entries(statsConfig.elements).forEach(([elementId, statKey]) => {
    //                 const element = document.getElementById(elementId);
    //                 if (element && result.stats[statKey] !== undefined) {
    //                     element.textContent = result.stats[statKey];
    //                 }
    //             });
                
    //             console.log(`✅ Stats loaded for ${entityType}:`, result.stats);
    //             return { success: true, stats: result.stats };
                
    //         } else {
    //             throw new Error(result.error || 'Failed to load stats');
    //         }
            
    //     } catch (error) {
    //         if (error.name === 'AbortError') {
    //             console.log(`⚠️ ${entityType} stats loading aborted`);
    //             return { success: false, aborted: true };
    //         }
            
    //         console.error(`❌ Error loading ${entityType} stats:`, error);
    //         return { success: false, error: error.message };
    //     }
    // }


    /**
     * טעינת סטטיסטיקות
     * @param {string} entityType - סוג היישות
     * @param {AbortSignal} signal - signal לביטול
     * @param {string|null} parentId - מזהה הורה (אופציונלי)
     * @returns {Promise<Object>} הסטטיסטיקות
     */
    static async loadStats(entityType, signal = null, parentId = null) {
        const config = ENTITY_CONFIG[entityType];
        const statsConfig = config.statsConfig;
        
        if (!statsConfig || !statsConfig.elements) {
            return { success: false };
        }
        
        
        try {
            // בניית URL
            let apiUrl = `${config.apiEndpoint}?action=stats`;
            
            // הוספת parent ID אם נדרש
            if (parentId && statsConfig.parentParam) {
                apiUrl += `&${statsConfig.parentParam}=${parentId}`;
            }
            
            
            // שליחת בקשה
            const response = await fetch(apiUrl, { signal });
            
            
            if (!response.ok) {
                return { success: false, error: `HTTP ${response.status}` };
            }
            
            const result = await response.json();
            
            if (result.success && result.stats) {
                // עדכון ה-DOM
                Object.entries(statsConfig.elements).forEach(([elementId, statKey]) => {
                    const element = document.getElementById(elementId);
                    if (element && result.stats[statKey] !== undefined) {
                        element.textContent = result.stats[statKey];
                    }
                });
                
                return { success: true, stats: result.stats };
                
            } else {
                // לא שגיאה קריטית - אולי ה-API לא תומך בסטטיסטיקות
                return { success: false, error: result.error || 'Stats not available' };
            }
            
        } catch (error) {
            if (error.name === 'AbortError') {
                return { success: false, aborted: true };
            }
            
            // לא שגיאה קריטית - רק warning
            return { success: false, error: error.message };
        }
    }

    /**
     * רענון נתונים (טעינה מחדש)
     * @param {string} entityType - סוג היישות
     * @param {string|null} parentId - מזהה הורה (אופציונלי)
     * @returns {Promise<void>}
     */
    static async refresh(entityType, parentId = null) {
        
        const config = ENTITY_CONFIG[entityType];
        const state = entityState.getState(entityType);
        
        // אם יש instance של חיפוש - השתמש ב-refresh שלו
        if (state.searchInstance && typeof state.searchInstance.refresh === 'function') {
            state.searchInstance.refresh();
            return;
        }
        
        // אחרת - טען מחדש באמצעות loadBrowseData
        const result = await this.loadBrowseData(entityType, null, parentId);
        
        if (result.success && result.data) {
            // רנדר את הנתונים
            const tableBody = document.getElementById('tableBody');
            if (tableBody && window.EntityRenderer) {
                await window.EntityRenderer.render(entityType, result.data, tableBody, result.pagination);
            }
        }
    }

    /**
     * מחיקת רשומה
     * @param {string} entityType - סוג היישות
     * @param {string} entityId - מזהה הרשומה
     * @returns {Promise<boolean>} האם המחיקה הצליחה
     */
    static async deleteEntity(entityType, entityId) {
        const config = ENTITY_CONFIG[entityType];
        
        // אישור מחיקה
        if (!confirm(`האם אתה בטוח שברצונך למחוק ${config.singularArticle}?`)) {
            return false;
        }
        
        try {
            
            // שליחת בקשת DELETE
            const response = await fetch(
                `${config.apiEndpoint}?action=delete&id=${entityId}`, 
                { method: 'DELETE' }
            );
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || `שגיאה במחיקת ה${config.singular}`);
            }
            
            // הודעת הצלחה
            if (typeof showToast === 'function') {
                showToast(`ה${config.singular} נמחקה בהצלחה`, 'success');
            }
            
            // רענון הנתונים
            await this.refresh(entityType);
            
            return true;
            
        } catch (error) {
            console.error(`❌ Error deleting ${entityType}:`, error);
            
            if (typeof showToast === 'function') {
                showToast(error.message, 'error');
            }
            
            return false;
        }
    }
}

// ===================================================================
// הפוך לגלובלי
// ===================================================================
window.EntityLoader = EntityLoader;

// פונקציות backward compatibility
window.genericLoadBrowseData = async (entityType, signal, parentId) => {
    return await EntityLoader.loadBrowseData(entityType, signal, parentId);
};

