<?php
// fix-it-now.php

$working_functions = '<?php
function getPDFEditorDB() {
    return getDBConnection();
}
?>';

file_put_contents(
    $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php',
    $working_functions
);

echo "Done. Try index.php now.";
?>