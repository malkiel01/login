// אתחול TableManager עם הגדרות infinite scroll מותאמות
function initCustomersTable(data) {
    // אם הטבלה כבר קיימת, רק עדכן נתונים
    if (customersTable) {
        customersTable.setData(data);
        return customersTable;
    }
    
    customersTable = new TableManager({
        tableSelector: '#mainTable',
        
        // ⭐ הגדרות רוחב
        containerWidth: '100%',      // תופס את כל הרוחב
        containerPadding: '16px',    // padding סביב
        
        // ⭐ הגדרות Infinite Scroll
        infiniteScroll: true,        // הפעלת גלילה אינסופית
        itemsPerPage: 50,           // 🔧 שנה כאן: כמה רשומות לטעון בכל פעם (ברירת מחדל: 100)
        scrollThreshold: 200,        // 🔧 שנה כאן: כמה פיקסלים מהתחתית להתחיל טעינה (ברירת מחדל: 200)
        
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
            {
                field: 'fullName',
                label: 'שם מלא',
                width: '200px',
                type: 'text',
                sortable: true,
                render: (customer) => `
                    <strong>${customer.firstName || ''} ${customer.lastName || ''}</strong>
                    ${customer.nomPerson ? '<br><small style="color:#666;">' + customer.nomPerson + '</small>' : ''}
                `
            },
            {
                field: 'phone',
                label: 'טלפון',
                width: '150px',
                type: 'text',
                sortable: true,
                render: (customer) => `
                    ${customer.phone || '-'}
                    ${customer.phoneMobile ? '<br><small style="color:#666;">' + customer.phoneMobile + '</small>' : ''}
                `
            },
            {
                field: 'streetAddress',
                label: 'כתובת',
                width: '180px',
                type: 'text',
                sortable: true
            },
            {
                field: 'city_name',
                label: 'עיר',
                width: '120px',
                type: 'text',
                sortable: true
            },
            {
                field: 'statusCustomer',
                label: 'סטטוס',
                width: '100px',
                type: 'number',
                sortable: true,
                render: (customer) => formatCustomerStatus(customer.statusCustomer)
            },
            {
                field: 'statusResident',
                label: 'סוג',
                width: '100px',
                type: 'number',
                sortable: true,
                render: (customer) => formatCustomerType(customer.statusResident)
            },
            {
                field: 'createDate',
                label: 'תאריך',
                width: '120px',
                type: 'date',
                sortable: true,
                render: (customer) => formatDate(customer.createDate)
            },
            {
                field: 'actions',
                label: 'פעולות',
                width: '120px',
                sortable: false,
                render: (customer) => `
                    <button class="btn btn-sm btn-secondary" onclick="editCustomer('${customer.unicId}')" title="עריכה">
                        <svg class="icon"><use xlink:href="#icon-edit"></use></svg>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteCustomer('${customer.unicId}')" title="מחיקה">
                        <svg class="icon"><use xlink:href="#icon-delete"></use></svg>
                    </button>
                `
            }
        ],
        
        // הנתונים
        data: data,
        
        // הגדרות נוספות
        sortable: true,
        resizable: true,
        reorderable: false,
        filterable: true,
        
        // Callbacks
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
    
    // הצג מידע על הנתונים
    console.log('📊 Total customers loaded:', data.length);
    console.log('📄 Items per page:', customersTable.config.itemsPerPage);
    console.log('📏 Scroll threshold:', customersTable.config.scrollThreshold + 'px');
    
    return customersTable;
}

// פונקציה לבדיקת סטטוס הטעינה
function checkScrollStatus() {
    if (!customersTable) {
        console.log('❌ Table not initialized');
        return;
    }
    
    const total = customersTable.getFilteredData().length;
    const displayed = customersTable.getDisplayedData().length;
    const remaining = total - displayed;
    
    console.log('📊 Scroll Status:');
    console.log(`   Total items: ${total}`);
    console.log(`   Displayed: ${displayed}`);
    console.log(`   Remaining: ${remaining}`);
    console.log(`   Progress: ${Math.round((displayed / total) * 100)}%`);
    
    if (remaining > 0) {
        console.log(`   🔽 Scroll down to load ${Math.min(customersTable.config.itemsPerPage, remaining)} more items`);
    } else {
        console.log('   ✅ All items loaded');
    }
}

// הפוך לגלובלי
window.checkScrollStatus = checkScrollStatus;

console.log('💡 Commands: checkScrollStatus() - בדוק כמה רשומות נטענו');