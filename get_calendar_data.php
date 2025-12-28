<?php
session_start();
header('Content-Type: application/json');
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode(['error' => 'Not logged in. Please login first.']);
    exit();
}

$userId = $_SESSION['userid'];
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Initialize arrays
$projects = [];
$tasks = [];

// Get projects where user is supervisor or member
$projectsQuery = "
    SELECT DISTINCT p.* 
    FROM projects p
    LEFT JOIN project_members pm ON p.id = pm.project_id
    WHERE (p.supervisor_id = ? OR pm.user_id = ?)
    AND p.is_deleted = 0
    AND p.due_date IS NOT NULL
    ORDER BY p.due_date, 
    CASE p.priority 
        WHEN 'high' THEN 1 
        WHEN 'medium' THEN 2 
        WHEN 'low' THEN 3 
    END
";

$stmt = mysqli_prepare($conn, $projectsQuery);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $projects[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['error' => 'Query preparation failed: ' . mysqli_error($conn)]);
    exit();
}

// Get tasks assigned to user or created by user
$tasksQuery = "
    SELECT t.*, p.title as project_title
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE (t.assigned_to = ? OR t.created_by = ?)
    AND t.is_deleted = 0
    AND t.due_date IS NOT NULL
    AND (p.is_deleted = 0 OR p.id IS NULL)
    ORDER BY t.due_date, 
    CASE t.priority 
        WHEN 'high' THEN 1 
        WHEN 'medium' THEN 2 
        WHEN 'low' THEN 3 
    END
";

$stmt = mysqli_prepare($conn, $tasksQuery);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['error' => 'Query preparation failed: ' . mysqli_error($conn)]);
    exit();
}

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'month' => $month,
    'year' => $year,
    'projects' => $projects,
    'tasks' => $tasks
]);

?>