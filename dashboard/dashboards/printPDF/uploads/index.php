<?php
// Prevent direct access to uploads directory
// Location: /dashboard/dashboards/printPDF/uploads/index.php

header("HTTP/1.0 403 Forbidden");
die("Access denied");
?>