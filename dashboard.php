<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$userid = $_SESSION['userid'];

// Handle task status update
if (isset($_GET['update_task'])) {
    $task_id = mysqli_real_escape_string($conn, $_GET['task_id']);
    $new_status = mysqli_real_escape_string($conn, $_GET['new_status']);
    
    // Verify user has access to this task
    $check_sql = "SELECT t.* FROM tasks t 
                  JOIN project_members pm ON t.project_id = pm.project_id 
                  WHERE t.id = '$task_id' AND pm.user_id = '$userid'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $update_sql = "UPDATE tasks SET status = '$new_status', updated_at = NOW() WHERE id = '$task_id'";
        mysqli_query($conn, $update_sql);
    }
    header("Location: dashboard.php");
    exit();
}

// Handle new task creation
if (isset($_POST['create_task'])) {
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    
    // Verify user has access to this project
    $check_sql = "SELECT * FROM project_members WHERE project_id = '$project_id' AND user_id = '$userid'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $insert_sql = "INSERT INTO tasks (project_id, title, description, status, priority, due_date, created_by, created_at, updated_at) 
                      VALUES ('$project_id', '$title', '$description', '$status', '$priority', '$due_date', '$userid', NOW(), NOW())";
        mysqli_query($conn, $insert_sql);
    }
    header("Location: dashboard.php");
    exit();
}

// Get user's projects (created and joined)
$my_projects = [];
$joined_projects = [];

// Projects created by user
$created_sql = "SELECT p.*, COUNT(pm.user_id) as member_count 
                FROM projects p 
                LEFT JOIN project_members pm ON p.id = pm.project_id 
                WHERE p.supervisor_id = '$userid' 
                GROUP BY p.id";
$created_result = mysqli_query($conn, $created_sql);
if ($created_result) {
    while ($row = mysqli_fetch_assoc($created_result)) {
        $my_projects[] = $row;
    }
}

// Projects joined by user
$joined_sql = "SELECT p.*, pm.role as member_role 
               FROM projects p 
               JOIN project_members pm ON p.id = pm.project_id 
               WHERE pm.user_id = '$userid' AND p.supervisor_id != '$userid'";
$joined_result = mysqli_query($conn, $joined_sql);
if ($joined_result) {
    while ($row = mysqli_fetch_assoc($joined_result)) {
        $joined_projects[] = $row;
    }
}

// Get all projects for main content (both created and joined)
$all_projects_sql = "SELECT DISTINCT p.* 
                     FROM projects p 
                     JOIN project_members pm ON p.id = pm.project_id 
                     WHERE pm.user_id = '$userid' 
                     ORDER BY p.created_at DESC";
$all_projects_result = mysqli_query($conn, $all_projects_sql);
$all_projects = [];
if ($all_projects_result) {
    while ($row = mysqli_fetch_assoc($all_projects_result)) {
        $all_projects[] = $row;
    }
}

// Get tasks for each project
$project_tasks = [];
foreach ($all_projects as $project) {
    $project_id = $project['id'];
    $tasks_sql = "SELECT t.*, u.username as assignee_name 
                  FROM tasks t 
                  LEFT JOIN users u ON t.assigned_to = u.id 
                  WHERE t.project_id = '$project_id' 
                  ORDER BY t.priority DESC, t.created_at DESC";
    $tasks_result = mysqli_query($conn, $tasks_sql);
    $tasks = ['pending' => [], 'in_progress' => [], 'completed' => []];
    
    if ($tasks_result) {
        while ($task = mysqli_fetch_assoc($tasks_result)) {
            $tasks[$task['status']][] = $task;
        }
    }
    $project_tasks[$project_id] = $tasks;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Task Board - Dashboard</title>
    <style>
        /* New Styles for the Design */
        .search-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto 30px;
        }
        
        .search-container input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 25px;
            font-size: 16px;
            background: white;
            transition: all 0.3s;
        }
        
        .search-container input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .project-header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .project-title-main {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }
        
        .project-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .join-code {
            background: #f7fafc;
            padding: 8px 16px;
            border-radius: 20px;
            font-family: monospace;
            font-weight: 600;
            color: #667eea;
            border: 1px solid #e2e8f0;
        }
        
        .task-board-main {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .task-column-main {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .column-header-main {
            padding: 20px;
            border-bottom: 2px solid #f1f5f9;
            background: #f8fafc;
        }
        
        .column-title-main {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .task-list {
            padding: 20px;
            min-height: 400px;
        }
        
        .task-card-main {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .task-card-main:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .task-title-main {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .task-description-main {
            font-size: 14px;
            color: #718096;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        
        .task-meta-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .task-priority-main {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .priority-high { 
            background: #fed7d7; 
            color: #c53030; 
        }
        .priority-medium { 
            background: #feebc8; 
            color: #dd6b20; 
        }
        .priority-low { 
            background: #c6f6d5; 
            color: #276749; 
        }
        
        .task-due {
            font-size: 12px;
            color: #718096;
            font-weight: 500;
        }
        
        .task-assignee-main {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #4a5568;
        }
        
        .assignee-avatar-main {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        
        .add-task-btn-main {
            width: 100%;
            padding: 15px;
            border: 2px dashed #cbd5e0;
            background: transparent;
            color: #718096;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .add-task-btn-main:hover {
            border-color: #667eea;
            color: #667eea;
            background: #f7fafc;
        }
        
        .action-buttons-main {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-main {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-create {
            background: #667eea;
            color: white;
        }
        
        .btn-create:hover {
            background: #5a67d8;
        }
        
        .btn-add {
            background: #48bb78;
            color: white;
        }
        
        .btn-add:hover {
            background: #38a169;
        }
        
        .status-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .status-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-start { background: #4299e1; color: white; }
        .btn-complete { background: #48bb78; color: white; }
        .btn-back { background: #ed8936; color: white; }
        .btn-reopen { background: #ed8936; color: white; }
        
        .status-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .task-form-main {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
            border: 1px solid #e2e8f0;
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
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .no-projects-main {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .no-projects-main h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #4a5568;
        }
        
        .no-projects-main p {
            font-size: 16px;
            margin-bottom: 25px;
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
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <!-- Dashboard Content -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-section">
                <label class="dropdown-header" for="created-toggle">
                    <h3>Projects I Created</h3>
                    <span class="dropdown-arrow">▼</span>
                </label>
                <input type="checkbox" id="created-toggle" class="dropdown-toggle" hidden>
                <ul class="project-list dropdown-content" id="created-projects">
                    <?php foreach($my_projects as $project): ?>
                        <li>
                            <a href="dashboard.php?project=<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($my_projects)): ?>
                        <li class="no-projects">No projects created</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <label class="dropdown-header" for="joined-toggle">
                    <h3>Projects I Joined</h3>
                    <span class="dropdown-arrow">▼</span>
                </label>
                <input type="checkbox" id="joined-toggle" class="dropdown-toggle" hidden>
                <ul class="project-list dropdown-content" id="joined-projects">
                    <?php foreach($joined_projects as $project): ?>
                        <li>
                            <a href="dashboard.php?project=<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($joined_projects)): ?>
                        <li class="no-projects">No projects joined</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="quick-actions">
                <a href="create_project.php" class="quick-btn btn-primary">Create Project</a>
                <a href="join_project.php" class="quick-btn btn-warning">Join Project</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Search Bar -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search tasks, projects, or team members...">
            </div>

            <?php if(!empty($all_projects)): ?>
                <?php foreach($all_projects as $project): 
                    $tasks = $project_tasks[$project['id']];
                ?>
                    <!-- Project Header -->
                    <div class="project-header-main">
                        <h1 class="project-title-main"><?php echo htmlspecialchars($project['title']); ?></h1>
                        <div class="project-actions">
                            <span class="join-code">Code: <?php echo $project['join_code']; ?></span>
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="btn-main btn-create">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>

                    <!-- Task Board -->
                    <div class="task-board-main">
                        <!-- Pending Tasks Column -->
                        <div class="task-column-main">
                            <div class="column-header-main">
                                <div class="column-title-main">
                                    <i class="fas fa-clock" style="color: #f59e0b;"></i>
                                    Pending Tasks
                                </div>
                            </div>
                            <div class="task-list">
                                <?php foreach($tasks['pending'] as $task): ?>
                                    <div class="task-card-main">
                                        <div class="task-title-main"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <?php if($task['description']): ?>
                                            <div class="task-description-main"><?php echo htmlspecialchars($task['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="task-meta-main">
                                            <span class="task-priority-main priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?> Priority
                                            </span>
                                            <span class="task-due">
                                                <?php 
                                                if($task['due_date']) {
                                                    $due_date = new DateTime($task['due_date']);
                                                    $today = new DateTime();
                                                    $interval = $today->diff($due_date);
                                                    if($interval->days == 0) {
                                                        echo "Due today";
                                                    } elseif($interval->days == 1) {
                                                        echo "Due tomorrow";
                                                    } elseif($interval->invert) {
                                                        echo "Overdue";
                                                    } else {
                                                        echo "Due in " . $interval->days . " days";
                                                    }
                                                } else {
                                                    echo "No due date";
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php if($task['assignee_name']): ?>
                                            <div class="task-assignee-main">
                                                <div class="assignee-avatar-main">
                                                    <?php echo strtoupper(substr($task['assignee_name'], 0, 2)); ?>
                                                </div>
                                                <span><?php echo $task['assignee_name']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-actions">
                                            <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=in_progress" 
                                               class="status-btn btn-start">Start Task</a>
                                            <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=completed" 
                                               class="status-btn btn-complete">Complete</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- Add Task Form for Pending -->
                                <form method="POST" action="dashboard.php" class="task-form-main">
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                    <input type="hidden" name="status" value="pending">
                                    <div class="form-grid">
                                        <input type="text" name="title" placeholder="Task title" required class="form-input form-full">
                                        <textarea name="description" placeholder="Task description" class="form-input form-full" rows="2"></textarea>
                                        <select name="priority" class="form-input">
                                            <option value="low">Low Priority</option>
                                            <option value="medium" selected>Medium Priority</option>
                                            <option value="high">High Priority</option>
                                        </select>
                                        <input type="date" name="due_date" class="form-input">
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" name="create_task" class="status-btn btn-complete">Add Task</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- In Progress Tasks Column -->
                        <div class="task-column-main">
                            <div class="column-header-main">
                                <div class="column-title-main">
                                    <i class="fas fa-sync-alt" style="color: #3b82f6;"></i>
                                    In Progress
                                </div>
                            </div>
                            <div class="task-list">
                                <?php foreach($tasks['in_progress'] as $task): ?>
                                    <div class="task-card-main">
                                        <div class="task-title-main"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <?php if($task['description']): ?>
                                            <div class="task-description-main"><?php echo htmlspecialchars($task['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="task-meta-main">
                                            <span class="task-priority-main priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?> Priority
                                            </span>
                                            <span class="task-due">
                                                <?php echo $task['due_date'] ? 'Due: ' . date('M d', strtotime($task['due_date'])) : 'No due date'; ?>
                                            </span>
                                        </div>
                                        <?php if($task['assignee_name']): ?>
                                            <div class="task-assignee-main">
                                                <div class="assignee-avatar-main">
                                                    <?php echo strtoupper(substr($task['assignee_name'], 0, 2)); ?>
                                                </div>
                                                <span><?php echo $task['assignee_name']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-actions">
                                            <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=pending" 
                                               class="status-btn btn-back">Move Back</a>
                                            <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=completed" 
                                               class="status-btn btn-complete">Complete</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- Add Task Form for In Progress -->
                                <form method="POST" action="dashboard.php" class="task-form-main">
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <div class="form-grid">
                                        <input type="text" name="title" placeholder="Task title" required class="form-input form-full">
                                        <textarea name="description" placeholder="Task description" class="form-input form-full" rows="2"></textarea>
                                        <select name="priority" class="form-input">
                                            <option value="low">Low Priority</option>
                                            <option value="medium" selected>Medium Priority</option>
                                            <option value="high">High Priority</option>
                                        </select>
                                        <input type="date" name="due_date" class="form-input">
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" name="create_task" class="status-btn btn-complete">Add Task</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Completed Tasks Column -->
                        <div class="task-column-main">
                            <div class="column-header-main">
                                <div class="column-title-main">
                                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                    Completed
                                </div>
                            </div>
                            <div class="task-list">
                                <?php foreach($tasks['completed'] as $task): ?>
                                    <div class="task-card-main">
                                        <div class="task-title-main"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <?php if($task['description']): ?>
                                            <div class="task-description-main"><?php echo htmlspecialchars($task['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="task-meta-main">
                                            <span class="task-priority-main priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?> Priority
                                            </span>
                                            <span class="task-due">
                                                Completed: <?php echo $task['updated_at'] ? date('M d', strtotime($task['updated_at'])) : 'Recently'; ?>
                                            </span>
                                        </div>
                                        <?php if($task['assignee_name']): ?>
                                            <div class="task-assignee-main">
                                                <div class="assignee-avatar-main">
                                                    <?php echo strtoupper(substr($task['assignee_name'], 0, 2)); ?>
                                                </div>
                                                <span><?php echo $task['assignee_name']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="status-actions">
                                            <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=in_progress" 
                                               class="status-btn btn-reopen">Reopen</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- Add Task Form for Completed -->
                                <form method="POST" action="dashboard.php" class="task-form-main">
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                    <input type="hidden" name="status" value="completed">
                                    <div class="form-grid">
                                        <input type="text" name="title" placeholder="Task title" required class="form-input form-full">
                                        <textarea name="description" placeholder="Task description" class="form-input form-full" rows="2"></textarea>
                                        <select name="priority" class="form-input">
                                            <option value="low">Low Priority</option>
                                            <option value="medium" selected>Medium Priority</option>
                                            <option value="high">High Priority</option>
                                        </select>
                                        <input type="date" name="due_date" class="form-input">
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" name="create_task" class="status-btn btn-complete">Add Task</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons-main">
                        <a href="create_project.php" class="btn-main btn-create">
                            <i class="fas fa-plus"></i> Create Project
                        </a>
                        <button class="btn-main btn-add" onclick="document.querySelector('.task-form-main').style.display='block'">
                            <i class="fas fa-tasks"></i> Add Task
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- No Projects State -->
                <div class="no-projects-main">
                    <h3>No Projects Yet</h3>
                    <p>Create your first project or join an existing one to get started!</p>
                    <div class="action-buttons-main">
                        <a href="create_project.php" class="btn-main btn-create">
                            <i class="fas fa-plus"></i> Create Project
                        </a>
                        <a href="join_project.php" class="btn-main btn-add">
                            <i class="fas fa-users"></i> Join Project
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <div class="logo">
                    <a href="index.html"><i class="fas fa-tasks"></i> Task Board</a>
                </div>
                <p>A modern solution for project management and task tracking designed for academic teams and beyond.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="dashboard.php">Home</a></li>
                    <li><a href="create_project.php">Create Project</a></li>
                    <li><a href="join_project.php">Join Project</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><i class="fa fa-envelope" style="font-size:16px"></i> contact@taskboard.com</p>
                <p><i class="fa fa-phone" style="font-size:16px"></i> +977 1234567891</p>
                <div class="social-links">
                    <a href="https://www.x.com/"><i class="fa fa-twitter" style="font-size:20px"></i></a>
                    <a href="https://instagram.com/"><i class="fa fa-instagram" style="font-size:20px"></i></a>
                    <a href="https://www.facebook.com/"><i class="fa fa-facebook-square" style="font-size:20px"></i></a>
                    <a href="https://www.linkedin.com/"><i class="fa fa-linkedin-square" style="font-size:20px"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Task Board. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
