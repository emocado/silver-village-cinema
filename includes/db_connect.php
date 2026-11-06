<?php
/**
 * CinéPop - Database Connection
 * Standard MySQLi connection with error handling & UTF-8 charset
 */

date_default_timezone_set('Asia/Singapore');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cinepop';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // If the database does not exist yet, provide a helpful notice
    die("<div style='font-family:sans-serif; padding:20px; background:#123457; color:#ffdad6; border:1px solid #93000a; border-radius:8px; margin:40px auto; max-width:600px;'>
        <h3 style='color:#ffb4ab; margin-top:0;'>Database Connection Error</h3>
        <p>Could not connect to MySQL database <code>$db_name</code>: " . htmlspecialchars($conn->connect_error) . "</p>
        <p>Please ensure MySQL is running in XAMPP and import the database schema using <code>sql/schema.sql</code>.</p>
    </div>");
}

// Set character set to utf8mb4
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");
?>
