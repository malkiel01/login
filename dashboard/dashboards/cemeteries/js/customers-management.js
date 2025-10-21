/**
 * דוגמה: שימוש ב-TableManager עם טבלת לקוחות
 * להוסיף ל-customers-management.js
 */

let customersTable = null;

// במקום renderCustomersRows, השתמש ב-TableManager
function initCustomersTable(data) {
    customersTable = new TableManager({
        tableSelector: '#mainTable',
        
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
                type: 'text',
                sortable: true,
                render: (customer) => formatCustomerStatus(customer.statusCustomer)
            },
            {
                field: 'statusResident',
                label: 'סוג',
                width: '100px',
                type: 'text',
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
        
        // הגדרות
        sortable: true,
        resizable: true,
        reorderable: true,
        filterable: true,
        
        // callbacks
        onSort: (field, order) => {
            console.log(`Sorted by ${field} ${order}`);
        },
        
        onFilter: (filters) => {
            console.log('Active filters:', filters);
        }
    });
    
    return customersTable;
}

// עדכון ב-loadCustomers
async function loadCustomers() {
    console.log('Loading customers...');
    
    // ... הקוד הקיים שלך ...
    
    // אתחל את UniversalSearch
    if (!customerSearch) {
        await initUniversalSearch();
        customerSearch.search();
    } else {
        customerSearch.refresh();
    }
    
    // טען סטטיסטיקות
    await loadCustomerStats();
}

// עדכון renderCustomersRows להשתמש ב-TableManager
function renderCustomersRows(data, container) {
    if (data.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 60px;">
                    <div style="color: #9ca3af;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                        <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // אם הטבלה כבר קיימת, עדכן את הנתונים
    if (customersTable) {
        customersTable.setData(data);
    } else {
        // אתחל טבלה חדשה
        initCustomersTable(data);
    }
}