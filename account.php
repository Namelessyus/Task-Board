<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];

// Get user details
$user_sql = "SELECT * FROM users WHERE id = '$user_id'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

// Get user statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT p.id) as projects_created,
    COUNT(DISTINCT pm.project_id) as projects_joined,
    COUNT(DISTINCT t.id) as tasks_assigned,
    COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as tasks_completed
    FROM users u
    LEFT JOIN projects p ON u.id = p.supervisor_id AND p.is_deleted = 0
    LEFT JOIN project_members pm ON u.id = pm.user_id
    LEFT JOIN tasks t ON u.id = t.created_by
    WHERE u.id = '$user_id'";
    
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Account - Task Board</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <!-- Add account details content here -->
</body>
</html>