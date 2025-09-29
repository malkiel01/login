<?php
// בדיקה 1: האם MySQL זמין
if (function_exists('mysqli_connect')) {
    echo "✅ MySQLi extension is installed\n";
} else {
    echo "❌ MySQLi extension is NOT installed\n";
}

// בדיקה 2: נסה להתחבר (החלף את הפרטים שלך)
$host = 'localhost';  // או כתובת השרת שלך
$username = 'your_username';
$password = 'your_password';
$database = 'your_database_name';

$conn = mysqli_connect($host, $username, $password, $database);
if ($conn) {
    echo "✅ Successfully connected to MySQL\n";
    echo "MySQL Server Version: " . mysqli_get_server_info($conn);
    mysqli_close($conn);
} else {
    echo "❌ Failed to connect: " . mysqli_connect_error();
}
?>