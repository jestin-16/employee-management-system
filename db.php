<?php
// Database connection settings
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'employee-management'; // change if your DB name is different

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

