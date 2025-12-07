<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Optional: Check session age (e.g., 24 hours max)
$max_session_age = 24 * 60 * 60; // 24 hours in seconds
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $max_session_age)) {
    // Session expired, logout user
    session_destroy();
    header("Location: login.php");
    exit();
}

// Update session time on each request (optional)
$_SESSION['last_activity'] = time();
?>