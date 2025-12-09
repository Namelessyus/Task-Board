<?php
// includes/session_check.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['userid']) || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Set cache control headers for all protected pages
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies
?>