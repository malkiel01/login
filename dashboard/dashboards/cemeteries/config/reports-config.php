<?php
/*
 * File: config/reports-config.php
 * Version: 1.0.0
 * Updated: 2025-01-21
 * Author: Malkiel
 * Description: הגדרות עיצוב ונגישות לדוחות
 */

return [
    'gravesInventory' => [
        'title' => 'דוח ניהול יתרות קברים פנויים',
        'modal' => [
            'width' => '95%',
            'maxWidth' => '1400px',
            'height' => '90vh',
            'backgroundColor' => '#ffffff',
            'borderRadius' => '8px',
            'boxShadow' => '0 4px 20px rgba(0, 0, 0, 0.15)'
        ],
        'colors' => [
            'primary' => '#2c3e50',
            'secondary' => '#34495e',
            'success' => '#27ae60',
            'danger' => '#e74c3c',
            'warning' => '#f39c12',
            'info' => '#3498db',
            'light' => '#ecf0f1',
            'dark' => '#2c3e50'
        ],
        'typography' => [
            'fontFamily' => "'Heebo', 'Arial', sans-serif",
            'fontSize' => [
                'title' => '24px',
                'subtitle' => '18px',
                'body' => '14px',
                'small' => '12px'
            ],
            'fontWeight' => [
                'bold' => 700,
                'semibold' => 600,
                'normal' => 400
            ]
        ],
        'table' => [
            'headerBackground' => '#34495e',
            'headerColor' => '#ffffff',
            'rowEvenBackground' => '#f8f9fa',
            'rowOddBackground' => '#ffffff',
            'borderColor' => '#dee2e6',
            'hoverBackground' => '#e9ecef'
        ],
        'movementTypes' => [
            'קבר_חדש' => [
                'label' => 'קבר חדש',
                'color' => '#27ae60',
                'icon' => '➕'
            ],
            'רכישה' => [
                'label' => 'רכישה',
                'color' => '#e74c3c',
                'icon' => '➖'
            ],
            'קבורה' => [
                'label' => 'קבורה',
                'color' => '#c0392b',
                'icon' => '⚰️'
            ],
            'ביטול_רכישה' => [
                'label' => 'ביטול רכישה',
                'color' => '#3498db',
                'icon' => '↩️'
            ],
            'ביטול_קבורה' => [
                'label' => 'ביטול קבורה',
                'color' => '#9b59b6',
                'icon' => '🔄'
            ]
        ],
        'plotTypes' => [
            1 => 'פטור',
            2 => 'יוצא דופן',
            3 => 'סמוך'
        ],
        'accessibility' => [
            'highContrast' => false,
            'largeFonts' => false,
            'keyboardNavigation' => true
        ]
    ]
];