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

// Handle task creation (only for supervisors)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_task']) && $is_supervisor) {
    $task_title = mysqli_real_escape_string($conn, $_POST['task_title']);
    $task_description = mysqli_real_escape_string($conn, $_POST['task_description']);
    $task_priority = $_POST['task_priority'];
    
    // Handle assigned_to properly
    $assigned_to = $_POST['assigned_to'];
    if (empty($assigned_to)) {
        $assigned_to = "NULL";
    } else {
        $assigned_to = "'" . intval($assigned_to) . "'";
    }
    
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
            $task_sql = "INSERT INTO tasks (project_id, title, description, assigned_to, priority, due_date, created_by) 
                         VALUES ('$project_id', '$task_title', '$task_description', $assigned_to, '$task_priority', $task_due_date, '$user_id')";
            
            if (mysqli_query($conn, $task_sql)) {
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

// Get project members
$members_sql = "SELECT u.id, u.username, pm.role 
                FROM project_members pm 
                JOIN users u ON pm.user_id = u.id 
                WHERE pm.project_id = '$project_id' 
                ORDER BY pm.role, u.username";
$members_result = mysqli_query($conn, $members_sql);
$project_members = [];
while ($row = mysqli_fetch_assoc($members_result)) {
    $project_members[] = $row;
}

// Get tasks for this project (excluding soft-deleted)
$tasks_sql = "SELECT t.*, u.username as assigned_username, creator.username as creator_name
              FROM tasks t 
              LEFT JOIN users u ON t.assigned_to = u.id 
              JOIN users creator ON t.created_by = creator.id
              WHERE t.project_id = '$project_id' AND t.is_deleted = 0
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
    $tasks[] = $row;
}

// Categorize tasks by status
$pending_tasks = array_filter($tasks, function($task) { return $task['status'] == 'pending'; });
$in_progress_tasks = array_filter($tasks, function($task) { return $task['status'] == 'in_progress'; });
$completed_tasks = array_filter($tasks, function($task) { return $task['status'] == 'completed'; });

// Calculate project progress
$total_tasks = count($tasks);
$completed_count = count($completed_tasks);
$progress_percentage = $total_tasks > 0 ? round(($completed_count / $total_tasks) * 100) : 0;
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
        
        .task-card:hover .task-delete-btn {
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
            
            .task-delete-btn {
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
                        <li><a href="#tasks">Tasks</a></li>
                    <?php else: ?>
                        <li><a href="#manage">Manage Project</a></li>
                    <?php endif; ?>
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
            <span><?php echo $completed_count; ?> of <?php echo $total_tasks; ?> tasks completed</span>
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
                                <span class="member-role <?php echo $member['id'] == $project['supervisor_id'] ? 'supervisor-badge' : ''; ?>">
                                    <?php echo $member['id'] == $project['supervisor_id'] ? 'Supervisor' : ucfirst($member['role']); ?>
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
                                            <?php if ($task['due_date']): ?>
                                                <span class="task-due">Due <?php echo date('M j', strtotime($task['due_date'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($task['assigned_username']): ?>
                                            <div class="task-assignee">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assigned_username']); ?></span>
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
                                            <?php if ($task['due_date']): ?>
                                                <span class="task-due">Due <?php echo date('M j', strtotime($task['due_date'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($task['assigned_username']): ?>
                                            <div class="task-assignee">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assigned_username']); ?></span>
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
                                        <?php if ($task['assigned_username']): ?>
                                            <div class="task-assignee">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assigned_username']); ?></span>
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
                    <form method="POST" action="" onsubmit="return validateTaskDueDate()">
                        <div class="form-grid">
                            <div class="form-full">
                                <input type="text" name="task_title" class="form-input" placeholder="Task title" required>
                            </div>
                            <div class="form-full">
                                <textarea name="task_description" class="form-input" placeholder="Task description" rows="3"></textarea>
                            </div>
                            <div>
                                <select name="assigned_to" class="form-input">
                                    <option value="">Assign to (optional)</option>
                                    <?php foreach ($project_members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>">
                                            <?php echo htmlspecialchars($member['username']); ?> 
                                            (<?php echo $member['role']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <select name="task_priority" class="form-input">
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                            </div>
                            <div class="form-full">
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
                <div class="members-list">
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
                                    <?php if ($task['assigned_username']): ?>
                                        <span style="color: #667eea; font-size: 12px;">
                                            (Assigned to: <?php echo htmlspecialchars($task['assigned_username']); ?>)
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" name="delete_task" class="btn-manage btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
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

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteConfirmation();
            }
        });
        
        // Confirm task deletion
        function confirmTaskDelete(form) {
            return confirm('Are you sure you want to delete this task?');
        }
        
        // Task due date validation
        function validateTaskDueDate() {
            const taskDueDateInput = document.getElementById('task_due_date');
            const today = new Date().toISOString().split('T')[0];
            
            if (taskDueDateInput.value && taskDueDateInput.value < today) {
                alert('Task due date cannot be in the past! Please select today or a future date.');
                taskDueDateInput.focus();
                return false;
            }
            
            return true;
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
        });
    </script>
</body>
</html>
