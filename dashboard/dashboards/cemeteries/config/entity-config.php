<?php
/*
 * File: dashboard/dashboards/cemeteries/config/entity-config.php
 * Version: 1.0.0
 * Updated: 2025-11-18
 * Author: Malkiel
 * Description: קונפיגורציה מרכזית של כל ה-Entity Types במערכת
 * Change Summary:
 * - v1.0.0: יצירת קונפיג מרכזי עם כל הישויות
 */

/**
 * קונפיגורציה של כל ה-Entity Types במערכת
 * 
 * כל entity מכיל:
 * - nameHe: שם בעברית (יחיד)
 * - namePluralHe: שם בעברית (רבים)
 * - parentType: Entity ההורה (null אם אין)
 * - enabled: האם פעיל במערכת
 * - level: רמה בהיררכיה (1-5)
 * - icon: אייקון emoji
 * - color: צבע ייצוגי
 */

return [
    // ===================================================================
    // רמה 1: בית עלמין (הרמה העליונה)
    // ===================================================================
    'cemetery' => [
        'nameHe' => 'בית עלמין',
        'namePluralHe' => 'בתי עלמין',
        'parentType' => null,
        'enabled' => true,
        'level' => 1,
        'icon' => '🏛️',
        'color' => '#8b5cf6',
        'description' => 'בית עלמין - הרמה העליונה במערכת'
    ],
    
    // ===================================================================
    // רמה 2: גוש
    // ===================================================================
    'block' => [
        'nameHe' => 'גוש',
        'namePluralHe' => 'גושים',
        'parentType' => 'cemetery',
        'enabled' => true,
        'level' => 2,
        'icon' => '📦',
        'color' => '#3b82f6',
        'description' => 'גוש בתוך בית עלמין'
    ],
    
    // ===================================================================
    // רמה 3: חלקה
    // ===================================================================
    'plot' => [
        'nameHe' => 'חלקה',
        'namePluralHe' => 'חלקות',
        'parentType' => 'block',
        'enabled' => true,
        'level' => 3,
        'icon' => '📐',
        'color' => '#10b981',
        'description' => 'חלקה בתוך גוש'
    ],
    
    // ===================================================================
    // רמה 4: אחוזת קבר
    // ===================================================================
    'areaGrave' => [
        'nameHe' => 'אחוזת קבר',
        'namePluralHe' => 'אחוזות קבר',
        'parentType' => 'plot',
        'enabled' => true,
        'level' => 4,
        'icon' => '🏘️',
        'color' => '#f59e0b',
        'description' => 'אחוזת קבר בתוך חלקה'
    ],
    
    // ===================================================================
    // רמה 5: קבר (הרמה התחתונה)
    // ===================================================================
    'grave' => [
        'nameHe' => 'קבר',
        'namePluralHe' => 'קברים',
        'parentType' => 'areaGrave',
        'enabled' => true,
        'level' => 5,
        'icon' => '🪦',
        'color' => '#8e2de2',
        'description' => 'קבר בתוך אחוזת קבר - הרמה האחרונה'
    ],
    
    // ===================================================================
    // ישויות נוספות (לא בהיררכיה)
    // ===================================================================
    
    'customer' => [
        'nameHe' => 'לקוח',
        'namePluralHe' => 'לקוחות',
        'parentType' => null,
        'enabled' => true,
        'level' => 0,
        'icon' => '👤',
        'color' => '#6366f1',
        'description' => 'לקוח במערכת'
    ],
    
    'purchase' => [
        'nameHe' => 'רכישה',
        'namePluralHe' => 'רכישות',
        'parentType' => null,
        'enabled' => true,
        'level' => 0,
        'icon' => '🛒',
        'color' => '#ec4899',
        'description' => 'רכישת קבר'
    ],
    
    'burial' => [
        'nameHe' => 'קבורה',
        'namePluralHe' => 'קבורות',
        'parentType' => null,
        'enabled' => true,
        'level' => 0,
        'icon' => '⚰️',
        'color' => '#64748b',
        'description' => 'קבורה בקבר'
    ],
    
    'payment' => [
        'nameHe' => 'תשלום',
        'namePluralHe' => 'תשלומים',
        'parentType' => null,
        'enabled' => true,
        'level' => 0,
        'icon' => '💰',
        'color' => '#22c55e',
        'description' => 'תשלום עבור רכישה'
    ],

    'residency' => [
        'nameHe' => 'הגדרת תושבות',
        'namePluralHe' => 'הגדרות תושבות',
        'parentType' => null,
        'enabled' => true,
        'level' => 0,
        'icon' => '🏠',
        'color' => '#0ea5e9',
        'description' => 'הגדרות תושבות למחירון'
    ],

    'country' => [
        'nameHe' => 'מדינה',
        'namePluralHe' => 'מדינות',
        'parentType' => null,
        'enabled' => true,
        'level' => 0,
        'icon' => '🌍',
        'color' => '#14b8a6',
        'description' => 'מדינות במערכת'
    ],

    'city' => [
        'nameHe' => 'עיר',
        'namePluralHe' => 'ערים',
        'parentType' => 'country',
        'enabled' => true,
        'level' => 0,
        'icon' => '🏙️',
        'color' => '#8b5cf6',
        'description' => 'ערים במערכת'
    ]
];