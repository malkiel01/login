<?php
/**
 * דף בדיקה למערכת UniversalSearch בדשבורד לקוחות
 */
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת מערכת חיפוש - לקוחות</title>
    
    <!-- Your existing CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- UniversalSearch CSS -->
    <link rel="stylesheet" href="css/universal-search.css">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 10px 0;
        }
        
        .page-subtitle {
            color: #6b7280;
            font-size: 16px;
        }
        
        .search-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .table-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f9fafb;
            padding: 14px;
            text-align: right;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            position: sticky;
            top: 0;
            font-size: 14px;
        }
        
        td {
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .btn-edit:hover {
            background: #bfdbfe;
        }
        
        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-delete:hover {
            background: #fecaca;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">🔍 בדיקת מערכת חיפוש מתקדמת</h1>
            <p class="page-subtitle">דשבורד ניהול לקוחות עם חיפוש אוניברסלי</p>
        </div>
        
        <!-- Search Section -->
        <div class="search-section">
            <div id="customerSearch"></div>
        </div>
        
        <!-- Results Table -->
        <div class="table-container">
            <table id="customersTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>ת.ז.</th>
                        <th>שם מלא</th>
                        <th>טלפון</th>
                        <th>אימייל</th>
                        <th>כתובת</th>
                        <th>עיר</th>
                        <th>סטטוס</th>
                        <th>תאריך יצירה</th>
                        <th style="width: 150px;">פעולות</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 60px; color: #9ca3af;">
                            <div style="font-size: 48px; margin-bottom: 16px;">👋</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">התחל לחפש</div>
                            <div>השתמש בשדה החיפוש למעלה למציאת לקוחות</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="js/universal-search.js"></script>
    <script src="js/search-presets.js"></script>
    
    <script>
        // ============================================
        // אתחול מערכת החיפוש ללקוחות
        // ============================================
        
        const customerSearch = createSearchFromPreset('customers', {
            display: {
                containerSelector: '#customerSearch',
                showAdvanced: true,
                placeholder: 'חיפוש לקוחות לפי שם, ת.ז, טלפון, אימייל...',
                layout: 'horizontal'
            },
            
            results: {
                containerSelector: '#customersTableBody',
                itemsPerPage: 50,
                showCounter: true,
                
                // פונקציית רינדור מותאמת אישית
                renderFunction: (data, container) => {
                    console.log('📊 Rendering', data.length, 'customers');
                    
                    if (data.length === 0) {
                        container.innerHTML = `
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 60px; color: #9ca3af;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">לא נמצאו תוצאות</div>
                                    <div>נסה לשנות את מילות החיפוש או הפילטרים</div>
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    container.innerHTML = data.map(customer => {
                        // פורמט סטטוס
                        const statusText = customer.statusCustomer == 1 ? 'פעיל' : 'לא פעיל';
                        const statusClass = customer.statusCustomer == 1 ? 'badge-active' : 'badge-inactive';
                        
                        // פורמט תאריך
                        const date = customer.createDate ? new Date(customer.createDate).toLocaleDateString('he-IL') : '-';
                        
                        return `
                            <tr data-id="${customer.unicId}">
                                <td>
                                    <input type="checkbox" class="customer-checkbox" value="${customer.unicId}">
                                </td>
                                <td>${customer.numId || '-'}</td>
                                <td>
                                    <strong>${customer.firstName || ''} ${customer.lastName || ''}</strong>
                                    ${customer.nomPerson ? '<br><small style="color:#6b7280;">' + customer.nomPerson + '</small>' : ''}
                                </td>
                                <td>
                                    ${customer.phone || '-'}
                                    ${customer.phoneMobile ? '<br><small style="color:#6b7280;">' + customer.phoneMobile + '</small>' : ''}
                                </td>
                                <td>${customer.email || '-'}</td>
                                <td>${customer.streetAddress || '-'}</td>
                                <td>${customer.city_name || '-'}</td>
                                <td>
                                    <span class="badge ${statusClass}">${statusText}</span>
                                </td>
                                <td>${date}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-edit" onclick="editCustomer('${customer.unicId}')">
                                            ✏️ ערוך
                                        </button>
                                        <button class="btn-action btn-delete" onclick="deleteCustomer('${customer.unicId}')">
                                            🗑️ מחק
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');
                }
            },
            
            behavior: {
                realTime: true,
                autoSubmit: true,
                highlightResults: true
            },
            
            callbacks: {
                onInit: () => {
                    console.log('✅ מערכת החיפוש אותחלה בהצלחה');
                },
                
                onSearch: (query, filters) => {
                    console.log('🔍 מחפש:', { query, filters: Array.from(filters.entries()) });
                },
                
                onResults: (data) => {
                    console.log('📦 התקבלו תוצאות:', {
                        total: data.total,
                        returned: data.data.length,
                        page: data.page,
                        pages: data.pages
                    });
                },
                
                onError: (error) => {
                    console.error('❌ שגיאה בחיפוש:', error);
                    alert('שגיאה בחיפוש: ' + error.message);
                },
                
                onEmpty: () => {
                    console.log('📭 אין תוצאות');
                }
            }
        });
        
        // ============================================
        // פונקציות פעולה (לדוגמה)
        // ============================================
        
        function editCustomer(customerId) {
            console.log('✏️ עריכת לקוח:', customerId);
            alert('עריכת לקוח: ' + customerId);
            // כאן תוכלי להוסיף את הלוגיקה של פתיחת מודל עריכה
        }
        
        function deleteCustomer(customerId) {
            if (confirm('האם אתה בטוח שברצונך למחוק לקוח זה?')) {
                console.log('🗑️ מחיקת לקוח:', customerId);
                // כאן תוכלי להוסיף את הלוגיקה של מחיקה
            }
        }
        
        // Select All checkbox
        document.getElementById('selectAll').addEventListener('change', function(e) {
            document.querySelectorAll('.customer-checkbox').forEach(cb => {
                cb.checked = e.target.checked;
            });
        });
        
        console.log('🚀 דף בדיקה נטען בהצלחה!');
        console.log('📝 אובייקט החיפוש זמין ב: customerSearch');
    </script>
</body>
</html>