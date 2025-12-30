/*
 * File: dashboard/dashboards/cemeteries/js/reports/graves-inventory-report.js
 * Version: 1.1.0
 * Updated: 2025-01-21
 * Author: Malkiel
 * Description: מודול JavaScript להצגת דוח ניהול יתרות קברים
 * Change Summary:
 * - v1.1.0: תיקון נתיבי API לפי מבנה הפרויקט
 */

const GravesInventoryReport = (() => {
    // ========== קונפיגורציה - נתיבים מתוקנים! ==========
    const CONFIG = {
        apiUrl: '/dashboard/dashboards/cemeteries/api/reports/graves-inventory-report-api.php',
        configUrl: '/dashboard/dashboards/cemeteries/config/reports-config.php',
        defaultDateRange: 30 // ימים
    };

    let reportConfig = null;

    // ========== פונקציות עזר ==========

    /**
     * טעינת קונפיגורציה - עם ברירת מחדל אם לא קיים
     */
    async function loadConfig() {
        try {
            const response = await fetch(CONFIG.configUrl);
            if (!response.ok) {
                throw new Error('Config file not found');
            }
            const config = await response.json();
            reportConfig = config.gravesInventory;
            return reportConfig;
        } catch (error) {
            // קונפיגורציה ברירת מחדל - לא צריך קובץ חיצוני
            reportConfig = getDefaultConfig();
            return reportConfig;
        }
    }

    /**
     * קונפיגורציה ברירת מחדל - מלאה!
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
                secondary: '#34495e',
                success: '#27ae60',
                danger: '#e74c3c',
                warning: '#f39c12',
                info: '#3498db'
            },
            movementTypes: {
                'קבר_חדש': {
                    label: 'קבר חדש',
                    color: '#27ae60',
                    icon: '➕'
                },
                'רכישה': {
                    label: 'רכישה',
                    color: '#e74c3c',
                    icon: '➖'
                },
                'קבורה': {
                    label: 'קבורה',
                    color: '#c0392b',
                    icon: '⚰️'
                },
                'ביטול_רכישה': {
                    label: 'ביטול רכישה',
                    color: '#3498db',
                    icon: '↩️'
                },
                'ביטול_קבורה': {
                    label: 'ביטול קבורה',
                    color: '#9b59b6',
                    icon: '🔄'
                }
            },
            plotTypes: {
                1: 'פטור',
                2: 'יוצא דופן',
                3: 'סמוך'
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

                            <div class="filter-group filter-buttons">
                                <button onclick="GravesInventoryReport.generate()" class="btn-generate">
                                    📊 הפק דוח
                                </button>
                                <button onclick="GravesInventoryReport.exportToExcel()" class="btn-export" id="btnExport" style="display: none;">
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
            injectStyles();
        }

        // הצגת המודאל
        document.getElementById('gravesInventoryReportModal').style.display = 'flex';
        document.body.style.overflow = 'hidden'; // מניעת גלילה ברקע
    }

    /**
     * סגירת המודאל
     */
    function close() {
        const modal = document.getElementById('gravesInventoryReportModal');
        if (modal) {
            modal.style.display = 'none';
        }
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

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                displayReport(data);
                document.getElementById('btnExport').style.display = 'inline-block';
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
        if (!movements || movements.length === 0) {
            return '<p class="no-data">אין תנועות בתקופה זו</p>';
        }

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
                        <span class="movement-badge" style="background-color: ${movementTypeConfig.color || '#999'}">
                            ${movementTypeConfig.icon || ''} ${movementTypeConfig.label || movement.movementType}
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
                    <td class="quantity ${parseInt(movement.quantity) > 0 ? 'positive' : 'negative'}">
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
        if (!movements || movements.length === 0) {
            return '<p class="no-data">אין תנועות בתקופה זו</p>';
        }

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
                    <td class="positive">${plot.movements['קבר_חדש'] || 0}</td>
                    <td class="negative">${plot.movements['רכישה'] || 0}</td>
                    <td class="negative">${plot.movements['קבורה'] || 0}</td>
                    <td class="positive">${plot.movements['ביטול_רכישה'] || 0}</td>
                    <td class="positive">${plot.movements['ביטול_קבורה'] || 0}</td>
                    <td class="net-change ${plot.netChange >= 0 ? 'positive' : 'negative'}">
                        ${plot.netChange > 0 ? '+' : ''}${plot.netChange}
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
        const loader = document.getElementById('reportLoader');
        if (loader) {
            loader.style.display = show ? 'flex' : 'none';
        }
    }

    /**
     * הצגת שגיאה
     */
    function showError(message) {
        const contentDiv = document.getElementById('reportContent');
        if (contentDiv) {
            contentDiv.innerHTML = `
                <div class="report-error">
                    <span class="error-icon">⚠️</span>
                    <p>${message}</p>
                </div>
            `;
        }
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
     * הזרקת סגנונות CSS לתוך הדף
     */
    function injectStyles() {
        if (document.getElementById('gravesReportStyles')) return;

        const styles = `
            /* מודאל ראשי */
            .graves-report-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Heebo', Arial, sans-serif;
            }

            .graves-report-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
            }

            .graves-report-container {
                position: relative;
                width: 95%;
                max-width: 1400px;
                height: 90vh;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            /* כותרת */
            .graves-report-header {
                background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
                color: #ffffff;
                padding: 20px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 3px solid #1a252f;
            }

            .graves-report-title {
                margin: 0;
                font-size: 24px;
                font-weight: 700;
            }

            .graves-report-close-btn {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: #ffffff;
                font-size: 24px;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .graves-report-close-btn:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: rotate(90deg);
            }

            /* פילטרים */
            .graves-report-filters {
                padding: 20px 30px;
                background-color: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }

            .filter-row {
                display: flex;
                gap: 15px;
                align-items: flex-end;
                flex-wrap: wrap;
            }

            .filter-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .filter-group label {
                font-size: 14px;
                font-weight: 600;
                color: #2c3e50;
            }

            .filter-input {
                padding: 8px 12px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                font-size: 14px;
                min-width: 150px;
            }

            .filter-input:focus {
                outline: none;
                border-color: #3498db;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            }

            .filter-buttons {
                flex-direction: row !important;
                gap: 10px !important;
            }

            .btn-generate, .btn-export {
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .btn-generate {
                background-color: #3498db;
                color: #ffffff;
            }

            .btn-generate:hover {
                background-color: #2980b9;
            }

            .btn-export {
                background-color: #27ae60;
                color: #ffffff;
            }

            .btn-export:hover {
                background-color: #229954;
            }

            /* תוכן הדוח */
            .graves-report-content {
                flex: 1;
                overflow-y: auto;
                padding: 30px;
            }

            .report-placeholder, .no-data {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                color: #95a5a6;
                font-size: 16px;
                text-align: center;
            }

            /* סיכום */
            .report-summary { margin-bottom: 30px; }
            .summary-header h3 {
                margin: 0 0 20px 0;
                color: #2c3e50;
                font-size: 20px;
            }

            .summary-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .summary-card {
                background: #ffffff;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border-right: 4px solid;
            }

            .summary-card.opening { border-color: #3498db; }
            .summary-card.movements { border-color: #f39c12; }
            .summary-card.closing { border-color: #27ae60; }

            .summary-card h4 {
                margin: 0 0 10px 0;
                color: #7f8c8d;
                font-size: 14px;
            }

            .summary-card .card-value {
                font-size: 32px;
                font-weight: 700;
                color: #2c3e50;
            }

            .summary-card .card-breakdown {
                display: flex;
                flex-direction: column;
                gap: 5px;
                font-size: 13px;
                color: #7f8c8d;
                margin-top: 10px;
            }

            /* טבלה */
            .report-table-container {
                background: #ffffff;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }

            .report-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 14px;
            }

            .report-table thead {
                background-color: #34495e;
                color: #ffffff;
                position: sticky;
                top: 0;
            }

            .report-table th {
                padding: 12px 15px;
                text-align: right;
                font-weight: 600;
            }

            .report-table tbody tr {
                border-bottom: 1px solid #dee2e6;
            }

            .report-table tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }

            .report-table tbody tr:hover {
                background-color: #e9ecef;
            }

            .report-table td {
                padding: 10px 15px;
                text-align: right;
            }

            /* תגי תנועה */
            .movement-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 20px;
                color: #ffffff;
                font-size: 12px;
                font-weight: 600;
            }

            /* צבעים */
            .positive { color: #27ae60; font-weight: 600; }
            .negative { color: #e74c3c; font-weight: 600; }

            /* לואדר */
            .report-loader {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 255, 255, 0.95);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 100;
            }

            .loader-spinner {
                width: 50px;
                height: 50px;
                border: 4px solid #ecf0f1;
                border-top-color: #3498db;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .report-loader p {
                margin-top: 20px;
                font-size: 16px;
                color: #7f8c8d;
            }

            /* שגיאות */
            .report-error {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                color: #e74c3c;
            }

            .report-error .error-icon {
                font-size: 48px;
                margin-bottom: 15px;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .graves-report-container {
                    width: 100%;
                    height: 100vh;
                    border-radius: 0;
                }

                .filter-row {
                    flex-direction: column;
                    align-items: stretch;
                }

                .filter-input {
                    width: 100%;
                }

                .summary-cards {
                    grid-template-columns: 1fr;
                }

                .report-table {
                    font-size: 12px;
                }
            }
        `;

        const styleElement = document.createElement('style');
        styleElement.id = 'gravesReportStyles';
        styleElement.textContent = styles;
        document.head.appendChild(styleElement);
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