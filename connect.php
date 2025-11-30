<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$username = "root";
$password = "1234";
$db = "taskboard_db";
$port = 3306;

$conn = mysqli_connect($host, $username, $password, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Function to generate unique join code
function generateJoinCode($conn) {
    do {
        $code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
        $check = mysqli_query($conn, "SELECT id FROM projects WHERE join_code='$code'");
    } while (mysqli_num_rows($check) > 0);
    return $code;
}
?>