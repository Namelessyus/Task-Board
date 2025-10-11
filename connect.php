<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$username = "root";
$password = "";
$db = "taskboard_db";
$port = 6969;

$conn = mysqli_connect($host, $username, $password, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>