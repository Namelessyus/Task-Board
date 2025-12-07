<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$username = $_SESSION['username'];

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$manage_mode = isset($_GET['manage']) ? true : false;

if ($project_id == 0) {
    header("Location: dashboard.php");
    exit();
}

// Get project details (excluding soft-deleted)
$project_sql = "SELECT p.*, u.username as supervisor_name 
                FROM projects p 
                JOIN users u ON p.supervisor_id = u.id 
                WHERE p.id = '$project_id' AND p.is_deleted = 0";
$project_result = mysqli_query($conn, $project_sql);

if (!$project_result || mysqli_num_rows($project_result) == 0) {
    header("Location: dashboard.php");
    exit();
}

$project = mysqli_fetch_assoc($project_result);

// Check if user has access to this project
$access_sql = "SELECT pm.role FROM project_members pm 
               WHERE pm.project_id = '$project_id' AND pm.user_id = '$user_id'";
$access_result = mysqli_query($conn, $access_sql);
$is_member = mysqli_num_rows($access_result) > 0;
$user_role = $is_member ? mysqli_fetch_assoc($access_result)['role'] : null;

// Check if user is supervisor
$is_supervisor = ($project['supervisor_id'] == $user_id);

// If not member and not supervisor, redirect
if (!$is_member && !$is_supervisor) {
    header("Location: dashboard.php");
    exit();
}

// If in manage mode but not supervisor, redirect to normal view
if ($manage_mode && !$is_supervisor) {
    header("Location: project_detail.php?id=$project_id");
    exit();
}

// Handle task creation (only for supervisors) - WITH MULTIPLE ASSIGNMENTS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_task']) && $is_supervisor) {
    $task_title = mysqli_real_escape_string($conn, $_POST['task_title']);
    $task_description = mysqli_real_escape_string($conn, $_POST['task_description']);
    $task_priority = $_POST['task_priority'];
    
    // Get selected assignees (array of user IDs)
    $assignees = isset($_POST['assignees']) ? $_POST['assignees'] : array();
    
    // Handle due_date properly with validation
    $task_due_date = $_POST['task_due_date'];
    if (empty($task_due_date)) {
        $task_due_date = "NULL";
    } else {
        // Validate due date - must be today or in the future
        $today = date('Y-m-d');
        if ($task_due_date < $today) {
            $error = "Task due date cannot be in the past! Please select today or a future date.";
        } else {
            $task_due_date = "'$task_due_date'";
        }
    }
    
    if (empty($error)) {
        if (!empty($task_title)) {
            // Insert the task (assigned_to is NULL since we're using multiple assignments)
            $task_sql = "INSERT INTO tasks (project_id, title, description, assigned_to, priority, due_date, created_by) 
                         VALUES ('$project_id', '$task_title', '$task_description', NULL, '$task_priority', $task_due_date, '$user_id')";
            
            if (mysqli_query($conn, $task_sql)) {
                $task_id = mysqli_insert_id($conn); // Get the ID of the newly created task
                
                // Insert assignments for each selected user
                if (!empty($assignees) && is_array($assignees)) {
                    foreach ($assignees as $assignee_id) {
                        $assignee_id = intval($assignee_id);
                        if ($assignee_id > 0) {
                            $assign_sql = "INSERT INTO task_assignments (task_id, user_id) 
                                          VALUES ('$task_id', '$assignee_id')";
                            mysqli_query($conn, $assign_sql);
                        }
                    }
                }
                
                $success = "Task created successfully!";
                // Refresh to show the new task
                header("Location: project_detail.php?id=$project_id&manage=true");
                exit();
            } else {
                $error = "Error creating task: " . mysqli_error($conn);
            }
        } else {
            $error = "Task title is required!";
        }
    }
}

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $task_id = intval($_POST['task_id']);
    $new_status = $_POST['status'];
    
    // Verify user has access to this task
    $verify_sql = "SELECT t.* FROM tasks t 
                   JOIN project_members pm ON t.project_id = pm.project_id 
                   WHERE t.id = '$task_id' AND pm.user_id = '$user_id' AND t.project_id = '$project_id' AND t.is_deleted = 0";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if (mysqli_num_rows($verify_result) > 0) {
        $update_sql = "UPDATE tasks SET status = '$new_status' WHERE id = '$task_id' AND is_deleted = 0";
        if (mysqli_query($conn, $update_sql)) {
            $success = "Task status updated!";
        } else {
            $error = "Error updating task: " . mysqli_error($conn);
        }
    } else {
        $error = "You don't have permission to update this task.";
    }
}

// Handle task halt (only for supervisors)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['halt_task']) && $is_supervisor) {
    $task_id = intval($_POST['task_id']);
    
    // Verify supervisor can halt this task
    $verify_sql = "SELECT t.* FROM tasks t 
                   WHERE t.id = '$task_id' AND t.project_id = '$project_id' AND t.is_deleted = 0";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if (mysqli_num_rows($verify_result) > 0) {
        $update_sql = "UPDATE tasks SET status = 'halted' WHERE id = '$task_id' AND project_id = '$project_id' AND is_deleted = 0";
        if (mysqli_query($conn, $update_sql)) {
            $success = "Task has been halted!";
        } else {
            $error = "Error halting task: " . mysqli_error($conn);
        }
    } else {
        $error = "Task not found or you don't have permission to halt this task.";
    }
}

// Handle task resume (only for supervisors)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resume_task']) && $is_supervisor) {
    $task_id = intval($_POST['task_id']);
    $new_status = $_POST['resume_status']; // Determine which status to resume to
    
    // Verify supervisor can resume this task
    $verify_sql = "SELECT t.* FROM tasks t 
                   WHERE t.id = '$task_id' AND t.project_id = '$project_id' AND t.is_deleted = 0 AND t.status = 'halted'";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if (mysqli_num_rows($verify_result) > 0) {
        $update_sql = "UPDATE tasks SET status = '$new_status' WHERE id = '$task_id' AND project_id = '$project_id' AND is_deleted = 0";
        if (mysqli_query($conn, $update_sql)) {
            $success = "Task has been resumed!";
        } else {
            $error = "Error resuming task: " . mysqli_error($conn);
        }
    } else {
        $error = "Task not found or is not halted.";
    }
}

// Handle task deletion (only for supervisors)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_task']) && $is_supervisor) {
    $task_id = intval($_POST['task_id']);
    
    // Double check if user is the supervisor
    if ($project['supervisor_id'] == $user_id) {
        // Soft delete the task
        $delete_sql = "UPDATE tasks SET is_deleted = 1, deleted_at = NOW() WHERE id = '$task_id' AND project_id = '$project_id'";
        
        if (mysqli_query($conn, $delete_sql)) {
            $success = "Task deleted successfully!";
            // Refresh to remove the task from view
            header("Location: project_detail.php?id=$project_id&manage=true");
            exit();
        } else {
            $error = "Error deleting task: " . mysqli_error($conn);
        }
    } else {
        $error = "You don't have permission to delete this task.";
    }
}

// Handle project deletion (only for supervisors) - SOFT DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_project']) && $is_supervisor) {
    // Double check if user is the supervisor
    if ($project['supervisor_id'] == $user_id) {
        // Soft delete the project
        $delete_sql = "UPDATE projects SET is_deleted = 1, deleted_at = NOW() WHERE id = '$project_id' AND supervisor_id = '$user_id'";
        
        if (mysqli_query($conn, $delete_sql)) {
            // Also soft delete all tasks in this project
            $delete_tasks_sql = "UPDATE tasks SET is_deleted = 1, deleted_at = NOW() WHERE project_id = '$project_id'";
            mysqli_query($conn, $delete_tasks_sql);
            
            // Redirect to dashboard after successful deletion
            $_SESSION['success'] = "Project deleted successfully!";
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error deleting project: " . mysqli_error($conn);
        }
    } else {
        $error = "You don't have permission to delete this project.";
    }
}

// Get ALL project members INCLUDING SUPERVISOR for task assignment
$members_sql = "SELECT u.id, u.username, 
                CASE 
                    WHEN u.id = '" . $project['supervisor_id'] . "' THEN 'supervisor'
                    ELSE COALESCE(pm.role, 'supervisor') 
                END as role
                FROM users u
                LEFT JOIN project_members pm ON u.id = pm.user_id AND pm.project_id = '$project_id'
                WHERE u.id = '" . $project['supervisor_id'] . "' 
                OR pm.project_id = '$project_id'
                GROUP BY u.id
                ORDER BY 
                    CASE 
                        WHEN u.id = '" . $project['supervisor_id'] . "' THEN 1
                        ELSE 2
                    END,
                    u.username";
$members_result = mysqli_query($conn, $members_sql);
$project_members = [];
while ($row = mysqli_fetch_assoc($members_result)) {
    $project_members[] = $row;
}

// Get tasks for this project (excluding soft-deleted) with multiple assignees
$tasks_sql = "SELECT t.*, creator.username as creator_name,
              GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') as assigned_usernames
              FROM tasks t 
              JOIN users creator ON t.created_by = creator.id
              LEFT JOIN task_assignments ta ON t.id = ta.task_id
              LEFT JOIN users u ON ta.user_id = u.id
              WHERE t.project_id = '$project_id' AND t.is_deleted = 0
              GROUP BY t.id
              ORDER BY 
                CASE t.priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                t.due_date ASC";
$tasks_result = mysqli_query($conn, $tasks_sql);
$tasks = [];
while ($row = mysqli_fetch_assoc($tasks_result)) {
    // Add overdue flag for each task
    $today = date('Y-m-d');
    $row['is_overdue'] = ($row['due_date'] && $row['due_date'] < $today && $row['status'] != 'completed' && $row['status'] != 'halted');
    $tasks[] = $row;
}

// Categorize tasks by status
$pending_tasks = array_filter($tasks, function($task) { return $task['status'] == 'pending'; });
$in_progress_tasks = array_filter($tasks, function($task) { return $task['status'] == 'in_progress'; });
$completed_tasks = array_filter($tasks, function($task) { return $task['status'] == 'completed'; });
$halted_tasks = array_filter($tasks, function($task) { return $task['status'] == 'halted'; });

// Calculate project progress (excluding halted tasks from progress calculation)
$active_tasks = array_filter($tasks, function($task) { return $task['status'] != 'halted'; });
$total_active_tasks = count($active_tasks);
$completed_count = count($completed_tasks);
$progress_percentage = $total_active_tasks > 0 ? round(($completed_count / $total_active_tasks) * 100) : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title><?php echo htmlspecialchars($project['title']); ?> - Task Board</title>
    <style>
        /* FLEXBOX SOLUTION - Clean and working */
        .project-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .project-title {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.3;
            margin: 0;
            order: 1;
        }

        .project-description {
            color: #4a5568;
            line-height: 1.6;
            word-wrap: break-word;
            overflow-wrap: break-word;
            margin: 0;
            order: 2;
            padding: 10px 0;
        }

        .project-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
            order: 3;
            margin: 5px 0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #718096;
            font-size: 14px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .progress-section {
            order: 4;
            margin-top: 10px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #718096;
        }

        .management-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            order: 5;
        }

        .btn-manage {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-info {
            background: #4299e1;
            color: white;
        }

        .btn-halt {
            background: #ed8936;
            color: white;
        }

        .btn-manage:hover {
            transform: translateY(-2px);
            text-decoration: none;
        }

        .task-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Style for multiple select */
        .multiple-select {
            height: 120px;
            padding: 8px;
        }

        .multiple-select option {
            padding: 8px;
            margin: 2px 0;
            border-radius: 4px;
        }

        .multiple-select option:hover {
            background-color: #667eea;
            color: white;
        }

        .selected-count {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
            display: block;
        }

        .members-list {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .member-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-role {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .supervisor-badge {
            background: #f59e0b;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }

        .manage-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .manage-card {
            background: #f8fafc;
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .manage-card:hover {
            border-color: #667eea;
            background: #edf2f7;
        }

        .manage-icon {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        /* Task card with delete option */
        .task-card {
            position: relative;
        }
        
        .task-delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .task-halt-btn {
            position: absolute;
            top: 10px;
            right: 40px;
            background: #ed8936;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .task-card:hover .task-delete-btn,
        .task-card:hover .task-halt-btn {
            opacity: 1;
        }
        
        .delete-task-form {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        /* For mobile responsiveness */
        @media (max-width: 768px) {
            .project-title {
                font-size: 24px;
            }
            
            .project-meta {
                gap: 10px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .meta-item {
                white-space: normal;
                flex-wrap: wrap;
                font-size: 13px;
            }
            
            .management-actions {
                flex-direction: column;
            }
            
            .btn-manage {
                width: 100%;
                justify-content: center;
            }
            
            .task-delete-btn,
            .task-halt-btn {
                opacity: 1;
            }
        }
        
        /* FLEXBOX SOLUTION - Clean and working */
        .project-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .project-title {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.3;
            margin: 0;
            order: 1;
        }

        .project-description {
            color: #4a5568;
            line-height: 1.6;
            word-wrap: break-word;
            overflow-wrap: break-word;
            margin: 0;
            order: 2;
            padding: 10px 0;
        }

        .project-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
            order: 3;
            margin: 5px 0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #718096;
            font-size: 14px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .progress-section {
            order: 4;
            margin-top: 10px;
        }

        /* Halted Tasks Dropdown */
        .halted-section {
            margin-top: 30px;
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .halted-header {
            padding: 20px 25px;
            background: #fffaf0;
            border-bottom: 1px solid #feebc8;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        
        .halted-header:hover {
            background: #feebc8;
        }
        
        .halted-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #c05621;
        }
        
        .halted-count {
            background: #ed8936;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .halted-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            background: #fffaf0;
        }
        
        .halted-content.expanded {
            max-height: 1000px;
        }
        
        .halted-tasks-container {
            padding: 20px 25px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .halted-task-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #feebc8;
            position: relative;
        }
        
        .halted-badge {
            position: absolute;
            top: -8px;
            left: -8px;
            background: #ed8936;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            z-index: 1;
        }
        
        /* Overdue task styling - SIMPLIFIED */
        .task-overdue {
            border: 2px solid #e53e3e !important;
            background: #fff5f5;
        }
        
        .overdue-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e53e3e;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            z-index: 1;
        }
        
        /* For mobile responsiveness */
        @media (max-width: 768px) {
            .project-title {
                font-size: 24px;
            }
            
            .project-meta {
                gap: 10px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .meta-item {
                white-space: normal;
                flex-wrap: wrap;
                font-size: 13px;
            }
            
            .halted-tasks-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <a href="dashboard.php"><i class="fas fa-tasks"></i> Task Board</a>
        </div>
        <nav class="nav">
            <a href="dashboard.php" class="active">Home</a>
            <a href="create_project.php">Create Project</a>
            <a href="join_project.php">Join Project</a>
            <a href="account.php">Account</a>
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-section">
                <h3>Project Navigation</h3>
                <ul class="project-list">
                    <li><a href="#overview">Overview</a></li>
                    <?php if (!$manage_mode): ?>
                        <li><a href="#members">Team Members</a></li>
                        <li><a href="#tasks">Task Board</a></li>
                        <?php if (count($halted_tasks) > 0): ?>
                            <li><a href="#on-hold">On Hold Tasks</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><a href="#manage">Manage Project</a></li>
                    <?php endif; ?>
                    <!-- Always show All Tasks link -->
                    <li><a href="#all-tasks">All Tasks</a></li>
                </ul>
            </div>
            
            <div class="quick-actions">
                <?php if ($is_supervisor): ?>
                    <?php if ($manage_mode): ?>
                        <a href="project_detail.php?id=<?php echo $project_id; ?>" class="quick-btn btn-primary">
                            <i class="fas fa-eye"></i> View Project
                        </a>
                    <?php else: ?>
                        <a href="project_detail.php?id=<?php echo $project_id; ?>&manage=true" class="quick-btn btn-warning">
                            <i class="fas fa-cog"></i> Manage Project
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="dashboard.php" class="quick-btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Project Header -->
            <div class="project-header" id="overview">
                <!-- TITLE - FIRST LINE -->
                <h1 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h1>
                
                <?php if ($project['description']): ?>
                <!-- DESCRIPTION - SECOND LINE -->
                <div class="project-description">
                    <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                </div>
                <?php endif; ?>

                <!-- META ITEMS - THIRD LINE -->
                <div class="project-meta">
                    <div class="meta-item">
                        <i class="fas fa-user-shield"></i>
                        <span>Supervisor: <?php echo htmlspecialchars($project['supervisor_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-flag"></i>
                        <span>Priority: <?php echo ucfirst($project['priority']); ?></span>
                    </div>
                    <?php if ($project['due_date']): ?>
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Due: <?php echo date('M j, Y', strtotime($project['due_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_supervisor): ?>
                    <div class="meta-item">
                        <i class="fas fa-code"></i>
                        <span>Join Code: <strong><?php echo $project['join_code']; ?></strong></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Progress Bar - FOURTH SECTION -->
                <div class="progress-section">
                    <div class="progress-text">
                        <span>Project Progress</span> 
                        <span><?php echo $progress_percentage; ?>% Complete</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                    </div>
                    <div class="progress-text">
                        <span><?php echo $completed_count; ?> of <?php echo $total_active_tasks; ?> active tasks completed</span>
                        <?php if (count($halted_tasks) > 0): ?>
                            <span style="color: #ed8936;">(<?php echo count($halted_tasks); ?> tasks on hold)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- VIEW MODE: Team Members & Tasks -->
            <?php if (!$manage_mode): ?>
                <!-- Team Members Section -->
                <div class="members-list" id="members">
                    <h3><i class="fas fa-users"></i> Team Members (<?php echo count($project_members); ?>)</h3>
                    <?php foreach ($project_members as $member): ?>
                        <div class="member-item">
                            <div class="member-info">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($member['username']); ?></span>
                                <span class="member-role <?php echo $member['role'] == 'supervisor' ? 'supervisor-badge' : ''; ?>">
                                    <?php echo ucfirst($member['role']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Task Board Section -->
                <div id="tasks">
                    <div class="task-board">
                        <!-- Pending Tasks -->
                        <div class="task-column">
                            <div class="column-header">
                                <div class="column-title">
                                    <div class="status-indicator status-pending"></div>
                                    Pending Tasks
                                </div>
                                <span class="task-count"><?php echo count($pending_tasks); ?></span>
                            </div>
                            <div class="column-content">
                                <?php foreach ($pending_tasks as $task): ?>
                                    <div class="task-card <?php echo $task['is_overdue'] ? 'task-overdue' : ''; ?>">
                                        <?php if ($task['is_overdue']): ?>
                                            <div class="overdue-badge">OVERDUE</div>
                                        <?php endif; ?>
                                        <?php if ($is_supervisor): ?>
                                            <button type="button" onclick="showHaltModal(<?php echo $task['id']; ?>, 'pending')" class="task-halt-btn" title="Halt Task">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                            <form method="POST" action="" class="delete-task-form" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" name="delete_task" class="task-delete-btn" title="Delete Task">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <?php if ($task['description']): ?>
                                            <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="task-meta">
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?> Priority
                                            </span>
                                            <?php if ($task['due_date']): ?>
                                                <span class="task-due">Due <?php echo date('M j', strtotime($task['due_date'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($task['assigned_usernames']): ?>
                                            <div class="task-assignee">
                                                <i class="fas fa-users"></i>
                                                <span>Assigned to: <?php echo htmlspecialchars($task['assigned_usernames']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-actions">
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="status" value="in_progress">
                                                <button type="submit" name="update_status" class="status-btn btn-start">
                                                    <i class="fas fa-play"></i> Start
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($pending_tasks)): ?>
                                    <div class="no-tasks">No pending tasks</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- In Progress Tasks -->
                        <div class="task-column">
                            <div class="column-header">
                                <div class="column-title">
                                    <div class="status-indicator status-progress"></div>
                                    In Progress
                                </div>
                                <span class="task-count"><?php echo count($in_progress_tasks); ?></span>
                            </div>
                            <div class="column-content">
                                <?php foreach ($in_progress_tasks as $task): ?>
                                    <div class="task-card <?php echo $task['is_overdue'] ? 'task-overdue' : ''; ?>">
                                        <?php if ($task['is_overdue']): ?>
                                            <div class="overdue-badge">OVERDUE</div>
                                        <?php endif; ?>
                                        <?php if ($is_supervisor): ?>
                                            <button type="button" onclick="showHaltModal(<?php echo $task['id']; ?>, 'in_progress')" class="task-halt-btn" title="Halt Task">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                            <form method="POST" action="" class="delete-task-form" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" name="delete_task" class="task-delete-btn" title="Delete Task">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <?php if ($task['description']): ?>
                                            <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="task-meta">
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?> Priority
                                            </span>
                                            <?php if ($task['due_date']): ?>
                                                <span class="task-due">Due <?php echo date('M j', strtotime($task['due_date'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($task['assigned_usernames']): ?>
                                            <div class="task-assignee">
                                                <i class="fas fa-users"></i>
                                                <span>Assigned to: <?php echo htmlspecialchars($task['assigned_usernames']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-actions">
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" name="update_status" class="status-btn btn-complete">
                                                    <i class="fas fa-check"></i> Complete
                                                </button>
                                            </form>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="status" value="pending">
                                                <button type="submit" name="update_status" class="status-btn btn-back">
                                                    <i class="fas fa-undo"></i> Back
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($in_progress_tasks)): ?>
                                    <div class="no-tasks">No tasks in progress</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Completed Tasks -->
                        <div class="task-column">
                            <div class="column-header">
                                <div class="column-title">
                                    <div class="status-indicator status-completed"></div>
                                    Completed
                                </div>
                                <span class="task-count"><?php echo count($completed_tasks); ?></span>
                            </div>
                            <div class="column-content">
                                <?php foreach ($completed_tasks as $task): ?>
                                    <div class="task-card">
                                        <?php if ($is_supervisor): ?>
                                            <form method="POST" action="" class="delete-task-form" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" name="delete_task" class="task-delete-btn" title="Delete Task">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <?php if ($task['description']): ?>
                                            <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="task-meta">
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?> Priority
                                            </span>
                                            <span class="task-due">Completed</span>
                                        </div>
                                        <?php if ($task['assigned_usernames']): ?>
                                            <div class="task-assignee">
                                                <i class="fas fa-users"></i>
                                                <span>Assigned to: <?php echo htmlspecialchars($task['assigned_usernames']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-actions">
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="status" value="in_progress">
                                                <button type="submit" name="update_status" class="status-btn btn-reopen">
                                                    <i class="fas fa-redo"></i> Reopen
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($completed_tasks)): ?>
                                    <div class="no-tasks">No completed tasks</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- On Hold Tasks Section (Dropdown) -->
                <?php if (count($halted_tasks) > 0): ?>
                <div class="halted-section" id="on-hold">
                    <div class="halted-header" onclick="toggleHaltedSection()">
                        <h3>
                            <i class="fas fa-pause-circle"></i>
                            On Hold Tasks
                            <span class="halted-count"><?php echo count($halted_tasks); ?></span>
                        </h3>
                        <div>
                            <i class="fas fa-chevron-down" id="halted-chevron"></i>
                        </div>
                    </div>
                    <div class="halted-content" id="halted-content">
                        <div class="halted-tasks-container">
                            <?php foreach ($halted_tasks as $task): ?>
                                <div class="halted-task-card">
                                    <div class="halted-badge">ON HOLD</div>
                                    <?php if ($is_supervisor): ?>
                                        <form method="POST" action="" class="delete-task-form" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <button type="submit" name="delete_task" class="task-delete-btn" title="Delete Task">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <?php if ($task['description']): ?>
                                        <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="task-meta">
                                        <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                            <?php echo ucfirst($task['priority']); ?> Priority
                                        </span>
                                        <?php if ($task['due_date']): ?>
                                            <span class="task-due">Due <?php echo date('M j', strtotime($task['due_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($task['assigned_usernames']): ?>
                                        <div class="task-assignee">
                                            <i class="fas fa-users"></i>
                                            <span>Assigned to: <?php echo htmlspecialchars($task['assigned_usernames']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="status-actions">
                                        <?php if ($is_supervisor): ?>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="resume_status" value="pending">
                                                <button type="submit" name="resume_task" class="status-btn btn-resume">
                                                    <i class="fas fa-play"></i> Resume to Pending
                                                </button>
                                            </form>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="resume_status" value="in_progress">
                                                <button type="submit" name="resume_task" class="status-btn btn-resume">
                                                    <i class="fas fa-forward"></i> Resume to In Progress
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #ed8936; font-size: 12px; font-style: italic;">
                                                <i class="fas fa-info-circle"></i> Task is on hold by supervisor
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- All Tasks List (View Mode) -->
                <div class="members-list" id="all-tasks">
                    <h3><i class="fas fa-list"></i> All Tasks (<?php echo count($tasks); ?>)</h3>
                    <?php foreach ($tasks as $task): ?>
                        <div class="member-item">
                            <div class="member-info">
                                <i class="fas fa-task"></i>
                                <span>
                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong> - 
                                    <span class="task-priority priority-<?php echo $task['priority']; ?>" style="font-size: 11px;">
                                        <?php echo ucfirst($task['priority']); ?> Priority
                                    </span> - 
                                    <span style="color: #718096; font-size: 12px;">
                                        Status: <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                    <?php if ($task['is_overdue'] && $task['status'] != 'completed' && $task['status'] != 'halted'): ?>
                                        <span style="color: #e53e3e; font-size: 12px; font-weight: bold;">
                                            <i class="fas fa-exclamation-triangle"></i> OVERDUE
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($task['assigned_usernames']): ?>
                                        <span style="color: #667eea; font-size: 12px;">
                                            (Assigned to: <?php echo htmlspecialchars($task['assigned_usernames']); ?>)
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($task['due_date']): ?>
                                        <span style="color: <?php echo ($task['is_overdue'] && $task['status'] != 'completed' && $task['status'] != 'halted') ? '#e53e3e' : '#718096'; ?>; font-size: 12px;">
                                            (Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?>)
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div>
                                <span class="member-role" style="background: 
                                    <?php 
                                    if ($task['status'] == 'completed') echo '#10b981';
                                    elseif ($task['status'] == 'in_progress') echo '#f59e0b';
                                    elseif ($task['status'] == 'halted') echo '#ed8936';
                                    else echo '#667eea';
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($tasks)): ?>
                        <div class="no-tasks">No tasks yet.</div>
                    <?php endif; ?>
                </div>

            <!-- MANAGE MODE: Management Features -->
            <?php else: ?>
                <div class="manage-section" id="manage">
                    <h3><i class="fas fa-cog"></i> Manage Project</h3>
                    <p>Use the tools below to manage your project settings and team.</p>
                    
                    <div class="manage-grid">
                        <!-- Create Task Card -->
                        <div class="manage-card" onclick="document.getElementById('taskForm').scrollIntoView()">
                            <div class="manage-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h4>Create New Task</h4>
                            <p>Add a new task to the project</p>
                        </div>

                        <!-- Manage Members Card -->
                        <div class="manage-card">
                            <div class="manage-icon">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <h4>Manage Team</h4>
                            <p>Add or remove team members</p>
                        </div>

                        <!-- Project Settings Card -->
                        <div class="manage-card">
                            <div class="manage-icon">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <h4>Project Settings</h4>
                            <p>Edit project details and settings</p>
                        </div>

                        <!-- Delete Project Card -->
                        <div class="manage-card" onclick="showDeleteConfirmation()">
                            <div class="manage-icon">
                                <i class="fas fa-trash-alt"></i>
                            </div>
                            <h4>Delete Project</h4>
                            <p>Permanently delete this project</p>
                        </div>
                    </div>
                </div>

                <!-- Task Creation Form -->
                <div class="task-form" id="taskForm">
                    <h3><i class="fas fa-plus-circle"></i> Create New Task</h3>
                    <form method="POST" action="" onsubmit="return validateTaskForm()">
                        <div class="form-grid">
                            <div class="form-full">
                                <input type="text" name="task_title" class="form-input" placeholder="Task title" required>
                            </div>
                            <div class="form-full">
                                <textarea name="task_description" class="form-input" placeholder="Task description" rows="3"></textarea>
                            </div>
                            <div class="form-full">
                                <label for="assignees">Assign to team members (optional - hold CTRL/CMD to select multiple):</label>
                                <select name="assignees[]" class="form-input multiple-select" id="assignees" multiple>
                                    <?php foreach ($project_members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>">
                                            <?php echo htmlspecialchars($member['username']); ?> 
                                            (<?php echo ucfirst($member['role']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="selected-count" id="selectedCount">0 members selected</span>
                            </div>
                            <div>
                                <select name="task_priority" class="form-input">
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                            </div>
                            <div>
                                <input type="date" name="task_due_date" class="form-input" id="task_due_date" min="<?php echo date('Y-m-d'); ?>">
                                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Select today or a future date (optional)</small>
                            </div>
                        </div>
                        <button type="submit" name="create_task" class="btn-manage btn-primary">
                            <i class="fas fa-plus"></i> Create Task
                        </button>
                    </form>
                </div>
                
                <!-- Task List for Management -->
                <div class="members-list" id="all-tasks">
                    <h3><i class="fas fa-tasks"></i> All Tasks (<?php echo count($tasks); ?>)</h3>
                    <?php foreach ($tasks as $task): ?>
                        <div class="member-item">
                            <div class="member-info">
                                <i class="fas fa-task"></i>
                                <span>
                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong> - 
                                    <span class="task-priority priority-<?php echo $task['priority']; ?>" style="font-size: 11px;">
                                        <?php echo ucfirst($task['priority']); ?> Priority
                                    </span> - 
                                    <span style="color: #718096; font-size: 12px;">
                                        Status: <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                    <?php if ($task['is_overdue'] && $task['status'] != 'completed' && $task['status'] != 'halted'): ?>
                                        <span style="color: #e53e3e; font-size: 12px; font-weight: bold;">
                                            <i class="fas fa-exclamation-triangle"></i> OVERDUE
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($task['assigned_usernames']): ?>
                                        <span style="color: #667eea; font-size: 12px;">
                                            (Assigned to: <?php echo htmlspecialchars($task['assigned_usernames']); ?>)
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div style="display: flex; gap: 5px;">
                                <?php if ($task['status'] == 'halted'): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="resume_status" value="pending">
                                        <button type="submit" name="resume_task" class="btn-manage btn-info" style="padding: 6px 12px; font-size: 12px;">
                                            <i class="fas fa-play"></i> Resume
                                        </button>
                                    </form>
                                <?php elseif ($task['status'] != 'completed'): ?>
                                    <button onclick="showHaltModal(<?php echo $task['id']; ?>, '<?php echo $task['status']; ?>')" class="btn-manage btn-halt" style="padding: 6px 12px; font-size: 12px;">
                                        <i class="fas fa-pause"></i> Halt
                                    </button>
                                <?php endif; ?>
                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this task?');" style="display: inline;">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <button type="submit" name="delete_task" class="btn-manage btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($tasks)): ?>
                        <div class="no-tasks">No tasks yet. Create your first task above.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; text-align: center;">
            <div style="font-size: 3rem; color: #e53e3e; margin-bottom: 15px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 style="color: #2d3748; margin-bottom: 10px;">Delete Project?</h3>
            <p style="color: #718096; margin-bottom: 20px;">
                Are you sure you want to delete the project "<strong><?php echo htmlspecialchars($project['title']); ?></strong>"? 
                <br><br>
                This will <strong>soft delete</strong> the project and all its tasks. They will be hidden from view but can be restored from the database if needed.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button onclick="hideDeleteConfirmation()" style="padding: 10px 20px; border: 1px solid #cbd5e0; background: white; color: #4a5568; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
                <form method="POST" action="" style="display: inline;">
                    <button type="submit" name="delete_project" style="padding: 10px 20px; background: #e53e3e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                        Yes, Delete Project
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Halt Task Modal -->
    <div id="haltModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; text-align: center;">
            <div style="font-size: 3rem; color: #ed8936; margin-bottom: 15px;">
                <i class="fas fa-pause-circle"></i>
            </div>
            <h3 style="color: #2d3748; margin-bottom: 10px;">Halt Task?</h3>
            <p style="color: #718096; margin-bottom: 20px;">
                Are you sure you want to put this task on hold? 
                <br><br>
                Halted tasks are moved to the "On Hold" section and are excluded from progress calculations until resumed.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button onclick="hideHaltModal()" style="padding: 10px 20px; border: 1px solid #cbd5e0; background: white; color: #4a5568; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
                <form method="POST" action="" id="haltForm" style="display: inline;">
                    <input type="hidden" name="task_id" id="haltTaskId">
                    <button type="submit" name="halt_task" style="padding: 10px 20px; background: #ed8936; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                        Yes, Halt Task
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Smooth scrolling for navigation
        document.querySelectorAll('.project-list a').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Delete confirmation functions
        function showDeleteConfirmation() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function hideDeleteConfirmation() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Halt task functions
        function showHaltModal(taskId, currentStatus) {
            document.getElementById('haltTaskId').value = taskId;
            document.getElementById('haltModal').style.display = 'flex';
        }

        function hideHaltModal() {
            document.getElementById('haltModal').style.display = 'none';
        }

        // Toggle halted section
        let haltedExpanded = false;
        function toggleHaltedSection() {
            const haltedContent = document.getElementById('halted-content');
            const chevron = document.getElementById('halted-chevron');
            
            if (haltedExpanded) {
                haltedContent.classList.remove('expanded');
                chevron.style.transform = 'rotate(0deg)';
            } else {
                haltedContent.classList.add('expanded');
                chevron.style.transform = 'rotate(180deg)';
            }
            haltedExpanded = !haltedExpanded;
            
            // Smooth scroll to section if expanding
            if (haltedExpanded) {
                setTimeout(() => {
                    document.getElementById('on-hold').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }, 100);
            }
        }

        // Close modals when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteConfirmation();
            }
        });
        
        document.getElementById('haltModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideHaltModal();
            }
        });
        
        // Task form validation
        function validateTaskForm() {
            const taskDueDateInput = document.getElementById('task_due_date');
            const today = new Date().toISOString().split('T')[0];
            
            if (taskDueDateInput.value && taskDueDateInput.value < today) {
                alert('Task due date cannot be in the past! Please select today or a future date.');
                taskDueDateInput.focus();
                return false;
            }
            
            return true;
        }
        
        // Update selected count for multiple select
        function updateSelectedCount() {
            const selectElement = document.getElementById('assignees');
            const selectedCount = document.getElementById('selectedCount');
            const selectedOptions = Array.from(selectElement.selectedOptions);
            
            selectedCount.textContent = selectedOptions.length + ' member(s) selected';
            
            // Add visual feedback for selected options
            Array.from(selectElement.options).forEach(option => {
                if (option.selected) {
                    option.style.backgroundColor = '#667eea';
                    option.style.color = 'white';
                } else {
                    option.style.backgroundColor = '';
                    option.style.color = '';
                }
            });
        }
        
        // Set minimum date for task due date
        document.addEventListener('DOMContentLoaded', function() {
            const taskDueDateInput = document.getElementById('task_due_date');
            const today = new Date().toISOString().split('T')[0];
            
            if (taskDueDateInput) {
                taskDueDateInput.min = today;
                
                // If there's a previous value that's in the past, clear it
                if (taskDueDateInput.value && taskDueDateInput.value < today) {
                    taskDueDateInput.value = today;
                }
            }
            
            // Initialize selected count for assignees
            const assigneesSelect = document.getElementById('assignees');
            if (assigneesSelect) {
                assigneesSelect.addEventListener('change', updateSelectedCount);
                updateSelectedCount(); // Initial call
            }
            
            // Keyboard shortcuts for multiple selection
            assigneesSelect.addEventListener('keydown', function(e) {
                // Allow Ctrl+A to select all
                if (e.key === 'a' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    Array.from(this.options).forEach(option => {
                        option.selected = true;
                    });
                    updateSelectedCount();
                }
            });
            
            // Auto-check overdue tasks periodically (every 30 seconds)
            setInterval(checkOverdueTasks, 30000);
            
            // Initial check
            checkOverdueTasks();
        });
        
        // Check for overdue tasks and highlight them
        function checkOverdueTasks() {
            const taskCards = document.querySelectorAll('.task-card');
            const today = new Date();
            
            taskCards.forEach(card => {
                const dueDateText = card.querySelector('.task-due');
                if (dueDateText && dueDateText.textContent.includes('Due')) {
                    // Extract date from text
                    const dueDateMatch = dueDateText.textContent.match(/Due (.+)/);
                    if (dueDateMatch) {
                        const dueDateStr = dueDateMatch[1];
                        const dueDate = new Date(dueDateStr + ', ' + today.getFullYear());
                        
                        // Check if overdue and not completed/halted
                        if (dueDate < today) {
                            const statusBtn = card.querySelector('.status-btn');
                            if (statusBtn && !statusBtn.textContent.includes('Complete')) {
                                card.classList.add('task-overdue');
                                
                                // Add overdue badge if not already present
                                if (!card.querySelector('.overdue-badge')) {
                                    const badge = document.createElement('div');
                                    badge.className = 'overdue-badge';
                                    badge.textContent = 'OVERDUE';
                                    card.prepend(badge);
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Helper function to select/deselect all members
        function toggleSelectAll(selectAll) {
            const selectElement = document.getElementById('assignees');
            Array.from(selectElement.options).forEach(option => {
                option.selected = selectAll;
            });
            updateSelectedCount();
        }
    </script>
</body>
</html>