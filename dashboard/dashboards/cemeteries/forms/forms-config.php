<?php
// /dashboards/cemeteries/forms/forms-config.php
// הגדרות מרכזיות לכל הטפסים במערכת

require_once dirname(__DIR__) . '/config.php';

// הגדרת השדות לכל סוג טופס
function getFormFields($type, $data = null) {
    $fields = [];
    
    switch($type) {
        case 'cemetery':
            return [
                ['name' => 'name', 'label' => 'שם בית העלמין', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'קוד', 'type' => 'text'],
                ['name' => 'address', 'label' => 'כתובת', 'type' => 'text'],
                ['name' => 'contact_name', 'label' => 'איש קשר', 'type' => 'text'],
                ['name' => 'contact_phone', 'label' => 'טלפון', 'type' => 'tel'],
                ['name' => 'location', 'label' => 'מיקום', 'type' => 'text'],
                ['name' => 'coordinates', 'label' => 'קואורדינטות', 'type' => 'text', 'placeholder' => '31.7683, 35.2137'],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'block':
            return [
                ['name' => 'name', 'label' => 'שם הגוש', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'קוד', 'type' => 'text'],
                ['name' => 'location', 'label' => 'מיקום', 'type' => 'text'],
                ['name' => 'coordinates', 'label' => 'קואורדינטות', 'type' => 'text'],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'plot':
            return [
                ['name' => 'name', 'label' => 'שם החלקה', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'קוד', 'type' => 'text'],
                ['name' => 'plot_type', 'label' => 'סוג חלקה', 'type' => 'select', 
                 'options' => PLOT_TYPES ? array_map(function($type) { 
                     return $type['name']; 
                 }, PLOT_TYPES) : []],
                ['name' => 'location', 'label' => 'מיקום', 'type' => 'text'],
                ['name' => 'coordinates', 'label' => 'קואורדינטות', 'type' => 'text'],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'row':
            return [
                ['name' => 'name', 'label' => 'שם השורה', 'type' => 'text', 'required' => true],
                ['name' => 'serial_number', 'label' => 'מספר סידורי', 'type' => 'number'],
                ['name' => 'location', 'label' => 'מיקום', 'type' => 'text'],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'area_grave':
            return [
                ['name' => 'name', 'label' => 'שם אחוזת הקבר', 'type' => 'text', 'required' => true],
                ['name' => 'grave_type', 'label' => 'סוג קבר', 'type' => 'select',
                 'options' => [
                     '1' => 'פטור',
                     '2' => 'חריג',
                     '3' => 'סגור'
                 ]],
                ['name' => 'coordinates', 'label' => 'קואורדינטות', 'type' => 'text'],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'grave':
            // שים לב: בטבלת graves אין עמודת name!
            return [
                ['name' => 'grave_number', 'label' => 'מספר קבר', 'type' => 'text', 'required' => true],
                ['name' => 'plot_type', 'label' => 'סוג חלקה', 'type' => 'select',
                 'options' => PLOT_TYPES ? array_map(function($type) { 
                     return $type['name']; 
                 }, PLOT_TYPES) : []],
                ['name' => 'grave_status', 'label' => 'סטטוס', 'type' => 'select', 'required' => true,
                 'options' => GRAVE_STATUS ? array_map(function($status) { 
                     return $status['name']; 
                 }, GRAVE_STATUS) : []],
                ['name' => 'grave_location', 'label' => 'מיקום מדויק', 'type' => 'text'],
                ['name' => 'construction_cost', 'label' => 'עלות בנייה', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'is_small_grave', 'label' => 'קבר קטן', 'type' => 'checkbox'],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'customer':
            return [
                ['name' => 'first_name', 'label' => 'שם פרטי', 'type' => 'text', 'required' => true],
                ['name' => 'last_name', 'label' => 'שם משפחה', 'type' => 'text', 'required' => true],
                ['name' => 'id_number', 'label' => 'ת.ז.', 'type' => 'text', 'required' => true],
                ['name' => 'birth_date', 'label' => 'תאריך לידה', 'type' => 'date'],
                ['name' => 'death_date', 'label' => 'תאריך פטירה', 'type' => 'date'],
                ['name' => 'phone', 'label' => 'טלפון', 'type' => 'tel'],
                ['name' => 'mobile', 'label' => 'נייד', 'type' => 'tel'],
                ['name' => 'email', 'label' => 'דוא"ל', 'type' => 'email'],
                ['name' => 'address', 'label' => 'כתובת', 'type' => 'text'],
                ['name' => 'city', 'label' => 'עיר', 'type' => 'text'],
                ['name' => 'customer_status', 'label' => 'סטטוס', 'type' => 'select',
                 'options' => CUSTOMER_STATUS ? array_map(function($status) { 
                     return $status['name']; 
                 }, CUSTOMER_STATUS) : []],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'purchase':
            return [
                ['name' => 'purchase_date', 'label' => 'תאריך רכישה', 'type' => 'date', 'required' => true],
                ['name' => 'customer_id', 'label' => 'לקוח', 'type' => 'select', 'required' => true],
                ['name' => 'grave_id', 'label' => 'קבר', 'type' => 'select', 'required' => true],
                ['name' => 'amount', 'label' => 'סכום', 'type' => 'number', 'step' => '0.01', 'required' => true],
                ['name' => 'payment_method', 'label' => 'אמצעי תשלום', 'type' => 'select',
                 'options' => [
                     'cash' => 'מזומן',
                     'check' => 'צ\'ק',
                     'credit' => 'כרטיס אשראי',
                     'transfer' => 'העברה בנקאית'
                 ]],
                ['name' => 'receipt_number', 'label' => 'מספר קבלה', 'type' => 'text'],
                ['name' => 'purchase_status', 'label' => 'סטטוס', 'type' => 'select',
                 'options' => PURCHASE_STATUS ? array_map(function($status) { 
                     return $status['name']; 
                 }, PURCHASE_STATUS) : []],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        case 'burial':
            return [
                ['name' => 'burial_date', 'label' => 'תאריך קבורה', 'type' => 'date', 'required' => true],
                ['name' => 'burial_time', 'label' => 'שעת קבורה', 'type' => 'time'],
                ['name' => 'deceased_id', 'label' => 'נפטר', 'type' => 'select', 'required' => true],
                ['name' => 'grave_id', 'label' => 'קבר', 'type' => 'select', 'required' => true],
                ['name' => 'purchase_id', 'label' => 'רכישה מקושרת', 'type' => 'select'],
                ['name' => 'rabbi_name', 'label' => 'שם הרב', 'type' => 'text'],
                ['name' => 'burial_certificate', 'label' => 'תעודת קבורה', 'type' => 'text'],
                ['name' => 'comments', 'label' => 'הערות', 'type' => 'textarea']
            ];
            
        default:
            return [];
    }
}

// פונקציה לקבלת נתונים לטופס עריכה
function getFormData($type, $id) {
    $pdo = getDBConnection();
    
    $table = getTableName($type);
    if (!$table) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error loading form data: " . $e->getMessage());
        return null;
    }
}

// פונקציה למיפוי סוג לטבלה
function getTableName($type) {
    $tables = [
        'cemetery' => 'cemeteries',
        'block' => 'blocks',
        'plot' => 'plots',
        'row' => 'rows',
        'area_grave' => 'area_graves',
        'grave' => 'graves',
        'customer' => 'customers',
        'purchase' => 'purchases',
        'burial' => 'burials'
    ];
    return $tables[$type] ?? null;
}
?>