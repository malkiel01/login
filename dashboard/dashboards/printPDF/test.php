<?php
session_start();
require_once '../../../config.php';

// בדיקה אם המשתמש מחובר (אופציונלי - הסר אם מפריע)
// if (!isset($_SESSION['user_id'])) {
//     die('יש להתחבר למערכת');
// }

$pdo = getDBConnection();

echo "<!DOCTYPE html>
<html dir='rtl' lang='he'>
<head>
    <meta charset='UTF-8'>
    <title>נתוני טסט למפת בית עלמין</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; direction: ltr; text-align: left; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
<h1>🗺️ נתוני טסט למפת בית עלמין</h1>";

// 1. חלקה לדוגמה
echo "<div class='section'>";
echo "<h2>1. חלקה לדוגמה</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM plots WHERE isActive = 1 LIMIT 1");
    $plot = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($plot, true) . "</pre>";
    $plotId = $plot['unicId'] ?? null;
} catch (Exception $e) {
    echo "<p class='error'>שגיאה: " . $e->getMessage() . "</p>";
    $plotId = null;
}
echo "</div>";

// 2. שורות של החלקה
if ($plotId) {
    echo "<div class='section'>";
    echo "<h2>2. שורות של החלקה (3 ראשונות)</h2>";
    try {
        $stmt = $pdo->prepare("SELECT * FROM rows WHERE plotId = ? AND isActive = 1 LIMIT 3");
        $stmt->execute([$plotId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($rows, true) . "</pre>";
        $lineId = $rows[0]['unicId'] ?? null;
    } catch (Exception $e) {
        echo "<p class='error'>שגיאה: " . $e->getMessage() . "</p>";
        $lineId = null;
    }
    echo "</div>";
}

// 3. אחוזות קבר
if (isset($lineId) && $lineId) {
    echo "<div class='section'>";
    echo "<h2>3. אחוזות קבר (5 ראשונות)</h2>";
    try {
        $stmt = $pdo->prepare("SELECT * FROM areaGraves WHERE lineId = ? AND isActive = 1 LIMIT 5");
        $stmt->execute([$lineId]);
        $areaGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($areaGraves, true) . "</pre>";
        $areaGraveId = $areaGraves[0]['unicId'] ?? null;
    } catch (Exception $e) {
        echo "<p class='error'>שגיאה: " . $e->getMessage() . "</p>";
        $areaGraveId = null;
    }
    echo "</div>";
}

// 4. קברים
if (isset($areaGraveId) && $areaGraveId) {
    echo "<div class='section'>";
    echo "<h2>4. קברים (10 ראשונים)</h2>";
    try {
        $stmt = $pdo->prepare("SELECT * FROM graves WHERE areaGraveId = ? AND isActive = 1 LIMIT 10");
        $stmt->execute([$areaGraveId]);
        $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($graves, true) . "</pre>";
        $graveId = $graves[0]['unicId'] ?? null;
    } catch (Exception $e) {
        echo "<p class='error'>שגיאה: " . $e->getMessage() . "</p>";
        $graveId = null;
    }
    echo "</div>";
}

// 5. רכישה לדוגמה
if (isset($graveId) && $graveId) {
    echo "<div class='section'>";
    echo "<h2>5. רכישה מקושרת לקבר</h2>";
    try {
        $stmt = $pdo->prepare("SELECT * FROM purchases WHERE graveId = ? AND isActive = 1 LIMIT 1");
        $stmt->execute([$graveId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($purchase, true) . "</pre>";
        $clientId = $purchase['clientId'] ?? null;
    } catch (Exception $e) {
        echo "<p class='error'>שגיאה: " . $e->getMessage() . "</p>";
        $clientId = null;
    }
    echo "</div>";
}

// 6. קבורה לדוגמה
if (isset($graveId) && $graveId) {
    echo "<div class='section'>";
    echo "<h2>6. קבורה מקושרת לקבר</h2>";
    try {
        $stmt = $pdo->prepare("SELECT * FROM burials WHERE graveId = ? AND isActive = 1 LIMIT 1");
        $stmt->execute([$graveId]);
        $burial = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($burial, true) . "</pre>";
        if (!$clientId && $burial) {
            $clientId = $burial['clientId'] ?? null;
        }
    } catch (Exception $e) {
        echo "<p class='error'>שגיאה: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

// 7. לקוח
if (isset($clientId) && $clientId) {
    echo "<div class='section'>";
    echo "<h2>7. לקוח מקושר</h2>";
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE unicId = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($customer, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p class='error'>שגיאה: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

echo "<div class='section' style='background: #d4edda; border: 1px solid #c3e6cb;'>";
echo "<h2 class='success'>✅ הטסט הושלם</h2>";
echo "<p>כעת תוכל להעתיק את הפלט ולשלוח לי.</p>";
echo "</div>";

echo "</body></html>";
?>