<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header("Location: login.html");
    exit();
}

// If user doesn't have a role yet, redirect to choose role
if (!isset($_SESSION['role']) || empty($_SESSION['role'])) {
    header("Location: choose_role.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
?>


<a href="create_project.php">for creating project</a>
