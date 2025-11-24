/*
 * File: dashboards/dashboard/cemeteries/js/reports/graves-inventory-report.js
 * Version: 1.0.0
 * Updated: 2025-01-21
 * Author: Malkiel
 * Description: מודול JavaScript להצגת דוח ניהול יתרות קברים
 * Change Summary:
 * - יצירה ראשונית של מודול הדוח
 * - תמיכה בדוח מצומצם ומורחב
 * - חלון מודאלי עם עיצוב מותאם
 */

const GravesInventoryReport = (() => {
    // ========== קונפיגורציה ==========
    const CONFIG = {
        apiUrl: '/api/reports/graves-inventory-report-api.php',
        configUrl: '/config/reports-config.php',
        defaultDateRange: 30 // ימים
    };

    let reportConfig = null;

    // ========== פונקציות עזר ==========

    /**
     * טעינת קונפיגורציה מהשרת
     */
    async function loadConfig() {
        try {
            const response = await fetch(CONFIG.configUrl);
            const config = await response.json();
            reportConfig = config.gravesInventory;
            return reportConfig;
        } catch (error) {
            console.error('שגיאה בטעינת קונפיגורציה:', error);
            // קונפיגורציה ברירת מחדל
            reportConfig = getDefaultConfig();
            return reportConfig;
        }
    }

    /**
     * קונפיגורציה ברירת מחדל
     */
    function getDefaultConfig() {
        return {
            title: 'דוח ניהול יתרות קברים פנויים',
            modal: {
                width: '95%',
                maxWidth: '1400px',
                height: '90vh'
            },
            colors: {
                primary: '#2c3e50',
                success: '#27ae60',
                danger: '#e74c3c'
            }
        };
    }

    /**
     * יצירת HTML למודאל הדוח
     */
    function createReportModal() {
        const modalHTML = `
            <div id="gravesInventoryReportModal" class="graves-report-modal" style="display: none;">
                <div class="graves-report-overlay" onclick="GravesInventoryReport.close()"></div>
                <div class="graves-report-container">
                    <!-- כותרת וכפתור סגירה -->
                    <div class="graves-report-header">
                        <h2 class="graves-report-title">${reportConfig.title}</h2>
                        <button class="graves-report-close-btn" onclick="GravesInventoryReport.close()" title="סגור">
                            ✕
                        </button>
                    </div>

                    <!-- פילטרים -->
                    <div class="graves-report-filters">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="reportStartDate">תאריך התחלה:</label>
                                <input type="date" id="reportStartDate" class="filter-input">
                            </div>

                            <div class="filter-group">
                                <label for="reportEndDate">תאריך סיום:</label>
                                <input type="date" id="reportEndDate" class="filter-input">
                            </div>

                            <div class="filter-group">
                                <label for="reportType">סוג דוח:</label>
                                <select id="reportType" class="filter-input">
                                    <option value="summary">מצומצם (לפי חלקות)</option>
                                    <option value="detailed">מורחב (כל קבר)</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <button onclick="GravesInventoryReport.generate()" class="btn-generate">
                                    📊 הפק דוח
                                </button>
                                <button onclick="GravesInventoryReport.exportToExcel()" class="btn-export" style="display: none;">
                                    📥 ייצא ל-Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- תוכן הדוח -->
                    <div id="reportContent" class="graves-report-content">
                        <div class="report-placeholder">
                            <p>בחר תאריכים ולחץ על "הפק דוח" כדי להציג את התוצאות</p>
                        </div>
                    </div>

                    <!-- לואדר -->
                    <div id="reportLoader" class="report-loader" style="display: none;">
                        <div class="loader-spinner"></div>
                        <p>מפיק דוח...</p>
                    </div>
                </div>
            </div>
        `;

        // הוספה ל-DOM
        const container = document.createElement('div');
        container.innerHTML = modalHTML;
        document.body.appendChild(container);

        // הגדרת תאריכים ברירת מחדל
        setDefaultDates();
    }

    /**
     * הגדרת תאריכים ברירת מחדל
     */
    function setDefaultDates() {
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - CONFIG.defaultDateRange);

        document.getElementById('reportEndDate').valueAsDate = endDate;
        document.getElementById('reportStartDate').valueAsDate = startDate;
    }

    /**
     * הצגת המודאל
     */
    async function open() {
        // טעינת קונפיגורציה אם עדיין לא נטענה
        if (!reportConfig) {
            await loadConfig();
        }

        // יצירת המודאל אם עדיין לא קיים
        if (!document.getElementById('gravesInventoryReportModal')) {
            createReportModal();
            applyStyling();
        }

        // הצגת המודאל
        document.getElementById('gravesInventoryReportModal').style.display = 'flex';
        document.body.style.overflow = 'hidden'; // מניעת גלילה ברקע
    }

    /**
     * סגירת המודאל
     */
    function close() {
        document.getElementById('gravesInventoryReportModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    /**
     * הפקת הדוח
     */
    async function generate() {
        const startDate = document.getElementById('reportStartDate').value;
        const endDate = document.getElementById('reportEndDate').value;
        const reportType = document.getElementById('reportType').value;

        // ולידציה
        if (!startDate || !endDate) {
            alert('יש לבחור תאריך התחלה ותאריך סיום');
            return;
        }

        if (new Date(startDate) > new Date(endDate)) {
            alert('תאריך הסיום חייב להיות אחרי תאריך ההתחלה');
            return;
        }

        // הצגת לואדר
        showLoader(true);

        try {
            const response = await fetch(CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    startDate,
                    endDate,
                    reportType
                })
            });

            const data = await response.json();

            if (data.success) {
                displayReport(data);
                document.querySelector('.btn-export').style.display = 'inline-block';
            } else {
                throw new Error(data.error || 'שגיאה לא ידועה');
            }

        } catch (error) {
            console.error('שגיאה בהפקת הדוח:', error);
            showError('אירעה שגיאה בהפקת הדוח: ' + error.message);
        } finally {
            showLoader(false);
        }
    }

    /**
     * הצגת הדוח
     */
    function displayReport(data) {
        const contentDiv = document.getElementById('reportContent');

        let html = `
            <div class="report-summary">
                <div class="summary-header">
                    <h3>תקופת הדוח: ${data.dateRange.startDateFormatted} - ${data.dateRange.endDateFormatted}</h3>
                </div>

                <div class="summary-cards">
                    <div class="summary-card opening">
                        <h4>יתרת פתיחה</h4>
                        <div class="card-value">${data.summary.openingBalance.total}</div>
                        <div class="card-breakdown">
                            <span>פטור: ${data.summary.openingBalance.byType.exempt}</span>
                            <span>יוצא דופן: ${data.summary.openingBalance.byType.unusual}</span>
                            <span>סמוך: ${data.summary.openingBalance.byType.close}</span>
                        </div>
                    </div>

                    <div class="summary-card movements">
                        <h4>תנועות בתקופה</h4>
                        <div class="card-value">${data.summary.totalMovements}</div>
                    </div>

                    <div class="summary-card closing">
                        <h4>יתרת סגירה</h4>
                        <div class="card-value">${data.summary.closingBalance.total}</div>
                        <div class="card-breakdown">
                            <span>פטור: ${data.summary.closingBalance.byType.exempt}</span>
                            <span>יוצא דופן: ${data.summary.closingBalance.byType.unusual}</span>
                            <span>סמוך: ${data.summary.closingBalance.byType.close}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="report-table-container">
                ${data.reportType === 'detailed' ? renderDetailedTable(data.movements) : renderSummaryTable(data.movements)}
            </div>
        `;

        contentDiv.innerHTML = html;

        // שמירת הנתונים לייצוא
        window.currentReportData = data;
    }

    /**
     * טבלה מפורטת
     */
    function renderDetailedTable(movements) {
        let html = `
            <table class="report-table">
                <thead>
                    <tr>
                        <th>תאריך</th>
                        <th>סוג תנועה</th>
                        <th>בית עלמין</th>
                        <th>גוש</th>
                        <th>חלקה</th>
                        <th>שורה</th>
                        <th>אזור</th>
                        <th>קבר</th>
                        <th>סוג קבר</th>
                        <th>לקוח/פרטים</th>
                        <th>כמות</th>
                    </tr>
                </thead>
                <tbody>
        `;

        movements.forEach(movement => {
            const movementTypeConfig = reportConfig.movementTypes[movement.movementType] || {};
            const plotTypeName = reportConfig.plotTypes[movement.plotType] || '';

            html += `
                <tr class="movement-row ${movement.movementType}">
                    <td>${formatDate(movement.date)}</td>
                    <td>
                        <span class="movement-badge" style="background-color: ${movementTypeConfig.color}">
                            ${movementTypeConfig.icon} ${movementTypeConfig.label}
                        </span>
                    </td>
                    <td>${movement.cemeteryNameHe || '-'}</td>
                    <td>${movement.blockNameHe || '-'}</td>
                    <td>${movement.plotNameHe || '-'}</td>
                    <td>${movement.lineNameHe || '-'}</td>
                    <td>${movement.areaGraveNameHe || '-'}</td>
                    <td>${movement.graveNameHe || '-'}</td>
                    <td>${plotTypeName}</td>
                    <td>${movement.customerName || movement.serialPurchaseId || movement.serialBurialId || '-'}</td>
                    <td class="quantity ${movement.quantity > 0 ? 'positive' : 'negative'}">
                        ${movement.quantity}
                    </td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        return html;
    }

    /**
     * טבלה מסוכמת
     */
    function renderSummaryTable(movements) {
        let html = `
            <table class="report-table summary-table">
                <thead>
                    <tr>
                        <th>בית עלמין</th>
                        <th>גוש</th>
                        <th>חלקה</th>
                        <th>קברים חדשים</th>
                        <th>רכישות</th>
                        <th>קבורות</th>
                        <th>ביטולי רכישה</th>
                        <th>ביטולי קבורה</th>
                        <th>שינוי נטו</th>
                    </tr>
                </thead>
                <tbody>
        `;

        movements.forEach(plot => {
            html += `
                <tr>
                    <td>${plot.cemeteryName || '-'}</td>
                    <td>${plot.blockName || '-'}</td>
                    <td>${plot.plotName || '-'}</td>
                    <td class="positive">${plot.movements.קבר_חדש || 0}</td>
                    <td class="negative">${plot.movements.רכישה || 0}</td>
                    <td class="negative">${plot.movements.קבורה || 0}</td>
                    <td class="positive">${plot.movements.ביטול_רכישה || 0}</td>
                    <td class="positive">${plot.movements.ביטול_קבורה || 0}</td>
                    <td class="net-change ${plot.netChange >= 0 ? 'positive' : 'negative'}">
                        ${plot.netChange}
                    </td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        return html;
    }

    /**
     * ייצוא ל-Excel
     */
    function exportToExcel() {
        if (!window.currentReportData) {
            alert('אין נתוני דוח לייצוא');
            return;
        }

        // TODO: מימוש ייצוא ל-Excel
        alert('פיצ׳ר ייצוא ל-Excel יפותח בקרוב');
    }

    /**
     * הצגת/הסתרת לואדר
     */
    function showLoader(show) {
        document.getElementById('reportLoader').style.display = show ? 'flex' : 'none';
    }

    /**
     * הצגת שגיאה
     */
    function showError(message) {
        const contentDiv = document.getElementById('reportContent');
        contentDiv.innerHTML = `
            <div class="report-error">
                <span class="error-icon">⚠️</span>
                <p>${message}</p>
            </div>
        `;
    }

    /**
     * פורמט תאריך
     */
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('he-IL');
    }

    /**
     * החלת עיצוב דינמי
     */
    function applyStyling() {
        if (!reportConfig) return;

        const modal = document.querySelector('.graves-report-container');
        if (modal) {
            modal.style.width = reportConfig.modal.width;
            modal.style.maxWidth = reportConfig.modal.maxWidth;
            modal.style.height = reportConfig.modal.height;
        }
    }

    // ========== API ציבורי ==========
    return {
        open,
        close,
        generate,
        exportToExcel
    };
})();

// הוספה ל-window לגישה גלובלית
window.GravesInventoryReport = GravesInventoryReport;