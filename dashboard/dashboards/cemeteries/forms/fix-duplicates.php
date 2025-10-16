<?php
// תיקון כפילויות
$files = glob('*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // מחק כפילות של parentId
    $content = preg_replace('/\$parentId = \$_GET\[\'parent_id\'\] \?\? null;\n/', '', 
$content);
    
    // וודא שיש require_once של FormUtils
    if (strpos($content, 'FormUtils::') !== false && 
        strpos($content, "require_once __DIR__ . '/FormUtils.php'") === false) {
        $content = str_replace(
            "require_once __DIR__ . '/FormBuilder.php';",
            "require_once __DIR__ . '/FormBuilder.php';\nrequire_once __DIR__ . 
'/FormUtils.php';",
            $content
        );
    }
    
    file_put_contents($file, $content);
    echo "Fixed: $file\n";
}
?>

