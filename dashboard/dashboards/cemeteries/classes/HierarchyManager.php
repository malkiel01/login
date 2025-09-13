<?php
// dashboard/dashboards/cemeteries/classes/HierarchyManager.php
// מחלקה לניהול היררכיית בתי העלמין על בסיס הקונפיגורציה

class HierarchyManager {
    private $config;
    private $pdo;
    private $userRole;
    
    public function __construct($pdo, $userRole = 'viewer') {
        $this->config = require __DIR__ . '/../config/cemetery-hierarchy-config.php';
        $this->pdo = $pdo;
        $this->userRole = $userRole;
    }
    
    /**
     * קבלת הגדרות לסוג נתון
     */
    public function getConfig($type) {
        return $this->config[$type] ?? null;
    }
    
    /**
     * קבלת שם הטבלה
     */
    public function getTableName($type) {
        $config = $this->getConfig($type);
        return $config['table'] ?? null;
    }
    
    /**
     * קבלת עמודת המפתח הראשי
     */
    public function getPrimaryKey($type) {
        $config = $this->getConfig($type);
        return $config['primaryKey'] ?? 'id';
    }
    
    /**
     * קבלת עמודת ההורה
     */
    public function getParentKey($type) {
        $config = $this->getConfig($type);
        return $config['parentKey'] ?? null;
    }
    
    /**
     * בניית שאילתת SELECT עם השדות המוגדרים
     */
    public function buildSelectQuery($type, $conditions = [], $orderBy = null, $limit = null, $offset = null) {
        $config = $this->getConfig($type);
        if (!$config) {
            throw new Exception("Invalid type: $type");
        }
        
        $table = $config['table'];
        $fields = $config['queryFields'];
        
        // בניית רשימת השדות
        $fieldsList = implode(', ', $fields);
        
        // בניית השאילתה הבסיסית
        $sql = "SELECT $fieldsList FROM $table WHERE isActive = 1";
        
        // הוספת תנאים
        $params = [];
        foreach ($conditions as $field => $value) {
            if ($field === 'search' && !empty($value)) {
                // חיפוש בשדות המוגדרים כ-searchable
                $searchFields = $this->getSearchableFields($type);
                if (!empty($searchFields)) {
                    $searchConditions = [];
                    foreach ($searchFields as $searchField) {
                        $searchConditions[] = "$searchField LIKE :search";
                    }
                    $sql .= " AND (" . implode(' OR ', $searchConditions) . ")";
                    $params['search'] = '%' . $value . '%';
                }
            } else {
                $sql .= " AND $field = :$field";
                $params[$field] = $value;
            }
        }
        
        // מיון
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        } else {
            // מיון ברירת מחדל
            $defaultSort = $this->getDefaultSort($type);
            if ($defaultSort) {
                $sql .= " ORDER BY $defaultSort";
            }
        }
        
        // הגבלה
        if ($limit) {
            $sql .= " LIMIT $limit";
            if ($offset) {
                $sql .= " OFFSET $offset";
            }
        }
        
        return ['sql' => $sql, 'params' => $params];
    }
    
    /**
     * קבלת שדות הניתנים לחיפוש
     */
    public function getSearchableFields($type) {
        $config = $this->getConfig($type);
        if (!$config) return [];
        
        $searchableFields = [];
        foreach ($config['table_columns'] as $column) {
            if (isset($column['searchable']) && $column['searchable']) {
                $searchableFields[] = $column['field'];
            }
        }
        
        return $searchableFields;
    }
    
    /**
     * קבלת מיון ברירת מחדל
     */
    public function getDefaultSort($type) {
        $config = $this->getConfig($type);
        
        // חפש עמודה עם sortable = true
        foreach ($config['table_columns'] as $column) {
            if (isset($column['sortable']) && $column['sortable']) {
                return $column['field'] . ' ASC';
            }
        }
        
        // ברירת מחדל - לפי תאריך יצירה
        return 'createDate DESC';
    }
    
    /**
     * קבלת הגדרות עמודות לטבלה
     */
    public function getTableColumns($type) {
        $config = $this->getConfig($type);
        if (!$config) return [];
        
        // סנן עמודות לפי הרשאות
        $columns = [];
        foreach ($config['table_columns'] as $column) {
            if ($this->hasPermissionForField($column)) {
                $columns[] = $column;
            }
        }
        
        return $columns;
    }
    
    /**
     * קבלת שדות לטופס
     */
    public function getFormFields($type, $mode = 'create') {
        $config = $this->getConfig($type);
        if (!$config) return [];
        
        // סנן שדות לפי הרשאות
        $fields = [];
        foreach ($config['form_fields'] as $field) {
            if ($this->hasPermissionForField($field)) {
                // בעדכון, אל תכלול שדות שאסור לערוך
                if ($mode === 'edit' && $this->isFieldReadOnly($field)) {
                    continue;
                }
                $fields[] = $field;
            }
        }
        
        return $fields;
    }
    
    /**
     * בדיקת הרשאה לשדה
     */
    private function hasPermissionForField($field) {
        // אם אין הגדרת הרשאות, השדה פתוח לכולם
        if (!isset($field['permissions'])) {
            return true;
        }
        
        // בדוק אם למשתמש יש את ההרשאה הנדרשת
        return in_array($this->userRole, $field['permissions']);
    }
    
    /**
     * בדיקה אם שדה הוא לקריאה בלבד
     */
    private function isFieldReadOnly($field) {
        // שדות מערכת שתמיד לקריאה בלבד
        $systemFields = ['id', 'unicId', 'createDate'];
        if (in_array($field['name'], $systemFields)) {
            return true;
        }
        
        // בדוק הגדרות הרשאה ספציפיות
        $roleConfig = $this->config['permissions']['roles'][$this->userRole] ?? null;
        if ($roleConfig && isset($roleConfig['restricted_fields'])) {
            return in_array($field['name'], $roleConfig['restricted_fields']);
        }
        
        return false;
    }
    
    /**
     * עיבוד ערך לתצוגה
     */
    public function formatFieldValue($value, $fieldConfig) {
        switch ($fieldConfig['type']) {
            case 'date':
                return $this->formatDate($value);
                
            case 'datetime':
                return $this->formatDateTime($value);
                
            case 'currency':
                return $this->formatCurrency($value);
                
            case 'status':
                $badges = $fieldConfig['badges'] ?? [];
                if (isset($badges[$value])) {
                    return '<span class="badge ' . $badges[$value]['class'] . '">' . 
                           $badges[$value]['text'] . '</span>';
                }
                return $value;
                
            case 'boolean':
                $icons = $fieldConfig['icons'] ?? [1 => '✓', 0 => '✗'];
                return $icons[$value] ?? '-';
                
            case 'select':
                $options = $fieldConfig['options'] ?? [];
                return $options[$value] ?? $value;
                
            default:
                return htmlspecialchars($value ?? '');
        }
    }
    
    /**
     * פונקציות עזר לפורמט
     */
    private function formatDate($date) {
        if (!$date) return '-';
        $format = $this->config['general']['date_format'] ?? 'd/m/Y';
        return date($format, strtotime($date));
    }
    
    private function formatDateTime($datetime) {
        if (!$datetime) return '-';
        $format = $this->config['general']['datetime_format'] ?? 'd/m/Y H:i';
        return date($format, strtotime($datetime));
    }
    
    private function formatCurrency($amount) {
        if (!$amount) return '-';
        $symbol = $this->config['general']['currency_symbol'] ?? '₪';
        return $symbol . ' ' . number_format($amount, 2);
    }
    
    /**
     * יצירת HTML לטבלה
     */
    public function renderTableHeaders($type) {
        $columns = $this->getTableColumns($type);
        $html = '<tr>';
        
        foreach ($columns as $column) {
            $width = isset($column['width']) ? 'style="width: ' . $column['width'] . '"' : '';
            $sortable = isset($column['sortable']) && $column['sortable'] ? 'data-sortable="true"' : '';
            $html .= "<th $width $sortable>{$column['title']}</th>";
        }
        
        $html .= '</tr>';
        return $html;
    }
    
    /**
     * יצירת HTML לשורת טבלה
     */
    public function renderTableRow($type, $data, $index = null) {
        $columns = $this->getTableColumns($type);
        $config = $this->getConfig($type);
        $html = '<tr>';
        
        foreach ($columns as $column) {
            $html .= '<td>';
            
            if ($column['type'] === 'index') {
                $html .= $index ?? '';
            } elseif ($column['type'] === 'actions') {
                $html .= $this->renderActions($type, $data, $column['actions'] ?? []);
            } else {
                $value = $data[$column['field']] ?? '';
                $html .= $this->formatFieldValue($value, $column);
                
                // הצג שדה משני אם מוגדר
                if (isset($column['show_secondary']) && isset($data[$column['show_secondary']])) {
                    $secondary = $data[$column['show_secondary']];
                    if ($secondary) {
                        $icon = $column['icon_secondary'] ?? '';
                        $html .= "<br><small class='text-muted'>$icon $secondary</small>";
                    }
                }
            }
            
            $html .= '</td>';
        }
        
        $html .= '</tr>';
        return $html;
    }
    
    /**
     * יצירת כפתורי פעולות
     */
    private function renderActions($type, $data, $actions) {
        $html = '<div class="btn-group">';
        $unicId = $data['unicId'] ?? $data['id'];
        $name = $data[$this->getConfig($type)['displayFields']['name']] ?? '';
        
        foreach ($actions as $action) {
            switch ($action) {
                case 'edit':
                    if ($this->canEdit()) {
                        $html .= "<button class='btn btn-sm btn-secondary' onclick='edit$type(\"$unicId\")'>";
                        $html .= '<svg class="icon-sm"><use xlink:href="#icon-edit"></use></svg>';
                        $html .= '</button>';
                    }
                    break;
                    
                case 'delete':
                    if ($this->canDelete()) {
                        $html .= "<button class='btn btn-sm btn-danger' onclick='delete$type(\"$unicId\")'>";
                        $html .= '<svg class="icon-sm"><use xlink:href="#icon-delete"></use></svg>';
                        $html .= '</button>';
                    }
                    break;
                    
                case 'enter':
                    $html .= "<button class='btn btn-sm btn-primary' onclick='open$type(\"$unicId\", \"$name\")'>";
                    $html .= '<svg class="icon-sm"><use xlink:href="#icon-enter"></use></svg> כניסה';
                    $html .= '</button>';
                    break;
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * בדיקות הרשאה
     */
    public function canView() {
        $roleConfig = $this->config['permissions']['roles'][$this->userRole] ?? null;
        return $roleConfig && $roleConfig['can_view_all'];
    }
    
    public function canEdit() {
        $roleConfig = $this->config['permissions']['roles'][$this->userRole] ?? null;
        return $roleConfig && $roleConfig['can_edit_all'];
    }
    
    public function canDelete() {
        $roleConfig = $this->config['permissions']['roles'][$this->userRole] ?? null;
        return $roleConfig && $roleConfig['can_delete_all'];
    }
    
    public function canCreate() {
        $roleConfig = $this->config['permissions']['roles'][$this->userRole] ?? null;
        return $roleConfig && $roleConfig['can_create_all'];
    }
}
?>