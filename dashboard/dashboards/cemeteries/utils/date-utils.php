<?php
/*
 * File: utils/date-utils.php
 * Version: 1.0.0
 * Updated: 2025-01-21
 * Author: Malkiel
 * Description: פונקציות עזר לעבודה עם תאריכים בעברית
 */

/**
 * המרת תאריך לפורמט עברי קריא
 */
function formatHebrewDate($date) {
    if (empty($date)) return '';
    
    $timestamp = strtotime($date);
    $hebrewMonths = [
        1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
        5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
        9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
    ];
    
    $day = date('d', $timestamp);
    $month = (int)date('m', $timestamp);
    $year = date('Y', $timestamp);
    
    return "{$day} ב{$hebrewMonths[$month]} {$year}";
}

/**
 * המרת תאריך לפורמט קצר
 */
function formatShortDate($date) {
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}