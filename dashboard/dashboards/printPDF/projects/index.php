<?php
// Prevent direct access to projects directory
// Location: /dashboard/dashboards/printPDF/projects/index.php

header("HTTP/1.0 403 Forbidden");
die("Access denied");
?>