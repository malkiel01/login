<?php
// dashboard/dashboards/cemeteries/test-api.php

// require_once 'config.php';
require_once 'dashboards/cemeteries/config.php';

echo "<h2>בדיקת חיבור לבסיס נתונים</h2>";

try {
    $pdo = getDBConnection();
    echo "✓ חיבור לבסיס נתונים הצליח<br>";
    
    // בדיקת טבלאות
    $tables = ['cemeteries', 'blocks', 'plots', 'rows', 'area_graves', 'graves'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✓ טבלת $table: $count רשומות<br>";
    }
    
} catch (Exception $e) {
    echo "✗ שגיאה: " . $e->getMessage();
}

echo "<h2>בדיקת API</h2>";
echo '<a href="/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=stats" target="_blank">בדוק API Stats</a><br>';
echo '<a href="/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=cemetery" target="_blank">בדוק רשימת בתי עלמין</a>';
?>