<?php
// Enable errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$host = "localhost";
$username = "root";
$password = "";  // put your MySQL password here
$db = "taskboard_db";
$port = 3306; // change if your MySQL port is different

$conn = mysqli_connect($host, $username, $password, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
