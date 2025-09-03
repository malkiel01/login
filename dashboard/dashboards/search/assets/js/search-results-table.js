/**
 * Search Results Table Module
 * assets/js/search-results-table.js
 */

class ResultsTable {
    constructor(containerId = 'results-container') {
        this.container = document.getElementById(containerId);
        this.currentResults = [];
        this.currentConfig = null;
    }

    /**
     * הצגת תוצאות בטבלה
     */
    display(results, config, searchType) {
        this.currentResults = results;
        this.currentConfig = config;
        
        if (!results || results.length === 0) {
            this.showNoResults();
            return;
        }

        // יצירת הטבלה
        const table = this.createTable(results, config);
        
        // הצגה בקונטיינר
        this.container.innerHTML = '';
        this.container.appendChild(table);
        
        // הוספת אירועים
        this.attachEvents(table);
    }

    /**
     * יצירת טבלת התוצאות
     */
    createTable(results, config) {
        const table = document.createElement('table');
        table.className = 'result-table';
        table.id = 'results-table';
        
        // יצירת כותרות
        const thead = this.createTableHeader(config);
        table.appendChild(thead);
        
        // יצירת גוף הטבלה
        const tbody = this.createTableBody(results, config);
        table.appendChild(tbody);
        
        return table;
    }

    /**
     * יצירת כותרות הטבלה
     */
    createTableHeader(config) {
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        
        // הוספת עמודת מספר שורה
        const thNumber = document.createElement('th');
        thNumber.textContent = '#';
        thNumber.style.width = '50px';
        headerRow.appendChild(thNumber);
        
        // הוספת כותרות לפי returnFields
        const displayLabels = config.displayFields || {};
        
        config.returnFields.forEach(field => {
            const th = document.createElement('th');
            th.textContent = displayLabels[field] || this.formatFieldName(field);
            th.dataset.field = field;
            th.className = 'sortable';
            th.onclick = () => this.sortTable(field);
            
            // הוספת אייקון מיון
            const sortIcon = document.createElement('span');
            sortIcon.className = 'sort-icon';
            sortIcon.innerHTML = ' ↕';
            th.appendChild(sortIcon);
            
            headerRow.appendChild(th);
        });
        
        // הוספת עמודת פעולות
        const thActions = document.createElement('th');
        thActions.textContent = 'פעולות';
        thActions.style.width = '100px';
        headerRow.appendChild(thActions);
        
        thead.appendChild(headerRow);
        return thead;
    }

    /**
     * יצירת גוף הטבלה
     */
    createTableBody(results, config) {
        const tbody = document.createElement('tbody');
        
        results.forEach((record, index) => {
            const row = this.createTableRow(record, index + 1, config);
            tbody.appendChild(row);
        });
        
        return tbody;
    }

    /**
     * יצירת שורה בטבלה
     */
    createTableRow(record, rowNumber, config) {
        const row = document.createElement('tr');
        row.dataset.recordId = record.graveId || record.c_unicId || rowNumber;
        
        // מספר שורה
        const tdNumber = document.createElement('td');
        tdNumber.textContent = rowNumber;
        tdNumber.className = 'row-number';
        row.appendChild(tdNumber);
        
        // נתוני השורה
        config.returnFields.forEach(field => {
            const td = document.createElement('td');
            const value = record[field];
            
            // עיצוב מיוחד לפי סוג השדה
            if (field.includes('Date') && value) {
                td.textContent = this.formatDate(value);
                td.className = 'date-field';
            } else if (field.includes('price') || field.includes('Price')) {
                td.textContent = this.formatCurrency(value);
                td.className = 'currency-field';
            } else if (field.includes('Status')) {
                td.innerHTML = this.formatStatus(value, field);
                td.className = 'status-field';
            } else {
                td.textContent = value || '-';
            }
            
            td.title = value || '';
            row.appendChild(td);
        });
        
        // פעולות
        const tdActions = document.createElement('td');
        tdActions.className = 'actions-cell';
        tdActions.innerHTML = this.createActionButtons(record);
        row.appendChild(tdActions);
        
        return row;
    }

    /**
     * יצירת כפתורי פעולות
     */
    createActionButtons(record) {
        return `
            <div class="action-buttons">
                <button class="btn-view" onclick="resultsTable.viewDetails('${record.graveId || ''}')">
                    👁️
                </button>
                <button class="btn-edit" onclick="resultsTable.editRecord('${record.graveId || ''}')">
                    ✏️
                </button>
            </div>
        `;
    }

    /**
     * עיצוב תאריך
     */
    formatDate(dateString) {
        if (!dateString) return '-';
        
        try {
            const date = new Date(dateString);
            if (isNaN(date)) return dateString;
            
            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear();
            
            return `${day}/${month}/${year}`;
        } catch (e) {
            return dateString;
        }
    }

    /**
     * עיצוב מטבע
     */
    formatCurrency(value) {
        if (!value || value === null) return '-';
        
        try {
            const num = parseFloat(value);
            if (isNaN(num)) return value;
            
            return new Intl.NumberFormat('he-IL', {
                style: 'currency',
                currency: 'ILS'
            }).format(num);
        } catch (e) {
            return value;
        }
    }

    /**
     * עיצוב סטטוס
     */
    formatStatus(value, field) {
        const statusMappings = {
            graveStatus: {
                '1': '<span class="status-badge status-available">פנוי</span>',
                '2': '<span class="status-badge status-occupied">תפוס</span>',
                '3': '<span class="status-badge status-reserved">שמור</span>',
                '4': '<span class="status-badge status-purchased">נרכש</span>'
            },
            p_purchaseStatus: {
                1: '<span class="status-badge status-active">פעיל</span>',
                2: '<span class="status-badge status-pending">ממתין</span>',
                3: '<span class="status-badge status-complete">הושלם</span>',
                4: '<span class="status-badge status-cancelled">בוטל</span>'
            }
        };
        
        if (statusMappings[field] && statusMappings[field][value]) {
            return statusMappings[field][value];
        }
        
        return value || '-';
    }

    /**
     * עיצוב שם שדה
     */
    formatFieldName(field) {
        // המרת snake_case או camelCase לטקסט קריא
        return field
            .replace(/_/g, ' ')
            .replace(/([A-Z])/g, ' $1')
            .trim()
            .replace(/^./, str => str.toUpperCase());
    }

    /**
     * מיון טבלה
     */
    sortTable(field) {
        const tbody = document.querySelector('#results-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // קביעת כיוון המיון
        const currentSort = tbody.dataset.sortField === field && tbody.dataset.sortOrder === 'asc' ? 'desc' : 'asc';
        tbody.dataset.sortField = field;
        tbody.dataset.sortOrder = currentSort;
        
        // מיון השורות
        rows.sort((a, b) => {
            const aValue = this.currentResults[a.rowIndex - 1][field] || '';
            const bValue = this.currentResults[b.rowIndex - 1][field] || '';
            
            // השוואה לפי סוג
            let comparison = 0;
            if (!isNaN(aValue) && !isNaN(bValue)) {
                comparison = parseFloat(aValue) - parseFloat(bValue);
            } else {
                comparison = aValue.toString().localeCompare(bValue.toString(), 'he');
            }
            
            return currentSort === 'asc' ? comparison : -comparison;
        });
        
        // עדכון הטבלה
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
        
        // עדכון אייקוני המיון
        this.updateSortIcons(field, currentSort);
    }

    /**
     * עדכון אייקוני מיון
     */
    updateSortIcons(field, order) {
        document.querySelectorAll('.sort-icon').forEach(icon => {
            icon.innerHTML = ' ↕';
        });
        
        const currentHeader = document.querySelector(`th[data-field="${field}"] .sort-icon`);
        if (currentHeader) {
            currentHeader.innerHTML = order === 'asc' ? ' ↑' : ' ↓';
        }
    }

    /**
     * הצגת הודעת "אין תוצאות"
     */
    showNoResults() {
        this.container.innerHTML = `
            <div class="no-results">
                <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                    <path d="M8 11h6" stroke-linecap="round"></path>
                </svg>
                <h3>לא נמצאו תוצאות</h3>
                <p>נסה לשנות את פרמטרי החיפוש</p>
            </div>
        `;
    }

    /**
     * צפייה בפרטים
     */
    viewDetails(recordId) {
        console.log('View details for:', recordId);
        // כאן תוכל להוסיף מודל או ניווט לדף פרטים
        alert('צפייה בפרטי רשומה: ' + recordId);
    }

    /**
     * עריכת רשומה
     */
    editRecord(recordId) {
        console.log('Edit record:', recordId);
        // כאן תוכל להוסיף מודל עריכה או ניווט לדף עריכה
        alert('עריכת רשומה: ' + recordId);
    }

    /**
     * הוספת אירועים
     */
    attachEvents(table) {
        // הוספת אירוע לחיצה על שורה
        table.querySelectorAll('tbody tr').forEach((row, index) => {
            row.addEventListener('click', (e) => {
                if (!e.target.closest('.action-buttons')) {
                    this.selectRow(row, index);
                }
            });
        });
    }

    /**
     * בחירת שורה
     */
    selectRow(row, index) {
        // הסרת בחירה קודמת
        document.querySelectorAll('.result-table tr.selected').forEach(r => {
            r.classList.remove('selected');
        });
        
        // הוספת בחירה חדשה
        row.classList.add('selected');
        
        // שליחת אירוע
        const event = new CustomEvent('rowSelected', {
            detail: {
                index: index,
                data: this.currentResults[index]
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * ייצוא לאקסל
     */
    exportToExcel() {
        // יצירת CSV
        const csv = this.convertToCSV(this.currentResults, this.currentConfig);
        
        // הורדת הקובץ
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `search_results_${new Date().getTime()}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * המרה ל-CSV
     */
    convertToCSV(data, config) {
        if (!data || data.length === 0) return '';
        
        const displayLabels = config.displayFields || {};
        
        // כותרות
        const headers = config.returnFields.map(field => 
            displayLabels[field] || this.formatFieldName(field)
        );
        
        // נתונים
        const rows = data.map(record => 
            config.returnFields.map(field => {
                const value = record[field] || '';
                // אם הערך מכיל פסיק או מרכאות, עטוף במרכאות
                return value.toString().includes(',') || value.toString().includes('"') 
                    ? `"${value.toString().replace(/"/g, '""')}"` 
                    : value;
            })
        );
        
        // חיבור הכל
        return [
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');
    }

    /**
     * הדפסת התוצאות
     */
    printResults() {
        window.print();
    }
}

// יצירת instance גלובלי
window.resultsTable = new ResultsTable();