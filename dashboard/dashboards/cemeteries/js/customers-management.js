// אתחול TableManager
function initCustomersTable(data) {
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (customersTable) {
        customersTable.setData(data);
        return customersTable;
    }
    
    customersTable = new TableManager({
        tableSelector: '#mainTable',
        
        // ⭐⭐⭐ כאן אתה מגדיר את הרוחב! ⭐⭐⭐
        containerWidth: '100%',      // שנה לפי הצורך
        containerPadding: '16px',    // שנה לפי הצורך
        
        // הגדרת עמודות
        columns: [
            {
                field: 'checkbox',
                label: '',
                width: '40px',
                sortable: false,
                render: (customer) => `
                    <input type="checkbox" class="customer-checkbox" value="${customer.unicId}">
                `
            },
            {
                field: 'numId',
                label: 'ת.ז.',
                width: '120px',
                type: 'text',
                sortable: true
            },
            // ... שאר העמודות
        ],
        
        // הנתונים
        data: data,
        
        // הגדרות
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        infiniteScroll: true,
        itemsPerPage: 100,
        scrollThreshold: 300,
        
        // callbacks
        onSort: (field, order) => {
            console.log(`📊 Sorted by ${field} ${order}`);
            showToast(`ממוין לפי ${field} (${order === 'asc' ? 'עולה' : 'יורד'})`, 'info');
        },
        
        onFilter: (filters) => {
            console.log('🔍 Active filters:', filters);
            const count = customersTable.getFilteredData().length;
            showToast(`נמצאו ${count} תוצאות`, 'info');
        }
    });
    
    return customersTable;
}