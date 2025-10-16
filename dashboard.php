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
    
    // Verify user has access to this project
    $check_sql = "SELECT * FROM project_members WHERE project_id = '$project_id' AND user_id = '$userid'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $insert_sql = "INSERT INTO tasks (project_id, title, status, created_by, created_at, updated_at) 
                      VALUES ('$project_id', '$title', '$status', '$userid', NOW(), NOW())";
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
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>">
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
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($joined_projects)): ?>
                        <li class="no-projects">No projects joined</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <div class="header-left">
                    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                    <p class="welcome-subtitle">Here are all your projects</p>
                </div>
                <div class="header-right">
                    <div class="action-buttons">
                        <a href="create_project.php" class="btn btn-primary">Create Project</a>
                        <a href="join_project.php" class="btn btn-warning">Join Project</a>
                    </div>
                </div>
            </div>

            <!-- Projects Grid -->
            <div class="projects-grid" id="projects-container">
                <?php foreach($all_projects as $project): 
                    $tasks = $project_tasks[$project['id']];
                    $total_tasks = count($tasks['pending']) + count($tasks['in_progress']) + count($tasks['completed']);
                    $completed_percentage = $total_tasks > 0 ? round((count($tasks['completed']) / $total_tasks) * 100) : 0;
                ?>
                    <div class="project-card">
                        <div class="project-header">
                            <div>
                                <h2 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h2>
                                <div class="project-meta">
                                    <span class="priority-<?php echo $project['priority']; ?>">
                                        <?php echo ucfirst($project['priority']); ?> Priority
                                    </span>
                                    • Due: <?php echo $project['due_date'] ? date('M d, Y', strtotime($project['due_date'])) : 'No due date'; ?>
                                    • <?php echo $total_tasks; ?> tasks
                                    • <?php echo $completed_percentage; ?>% complete
                                </div>
                            </div>
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="view-project-btn">
                                View Project
                            </a>
                        </div>
                        
                        <p style="color: #718096; margin-bottom: 20px;"><?php echo htmlspecialchars($project['description']); ?></p>
                        
                        <!-- Progress Bar -->
                        <div style="background: #e2e8f0; border-radius: 10px; height: 8px; margin-bottom: 20px;">
                            <div style="background: #48bb78; width: <?php echo $completed_percentage; ?>%; height: 100%; border-radius: 10px;"></div>
                        </div>
                        
                        <!-- Task Board -->
                        <div class="task-board">
                            <!-- Pending Tasks -->
                            <div class="task-column">
                                <div class="column-header">
                                    <div class="column-title">Pending</div>
                                    <span class="task-count"><?php echo count($tasks['pending']); ?></span>
                                </div>
                                <div class="column-content">
                                    <?php foreach($tasks['pending'] as $task): ?>
                                        <div class="task-card">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <?php if($task['description']): ?>
                                                <div class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?></div>
                                            <?php endif; ?>
                                            <div class="task-meta">
                                                <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                                <div style="display: flex; gap: 5px;">
                                                    <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=in_progress" 
                                                       class="btn" style="padding: 4px 8px; font-size: 11px; background: #4299e1; color: white; text-decoration: none;">Start</a>
                                                    <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=completed" 
                                                       class="btn" style="padding: 4px 8px; font-size: 11px; background: #48bb78; color: white; text-decoration: none;">Complete</a>
                                                </div>
                                            </div>
                                            <?php if($task['assignee_name']): ?>
                                                <div style="font-size: 12px; color: #718096; margin-top: 8px;">
                                                    Assigned to: <?php echo $task['assignee_name']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Add Task Form -->
                                    <form method="POST" action="dashboard.php" style="margin-top: 10px;">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <input type="hidden" name="status" value="pending">
                                        <div style="display: flex; gap: 5px;">
                                            <input type="text" name="title" placeholder="New task..." required 
                                                   style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                            <button type="submit" name="create_task" class="btn" 
                                                    style="padding: 8px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                Add
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- In Progress Tasks -->
                            <div class="task-column">
                                <div class="column-header">
                                    <div class="column-title">In Progress</div>
                                    <span class="task-count"><?php echo count($tasks['in_progress']); ?></span>
                                </div>
                                <div class="column-content">
                                    <?php foreach($tasks['in_progress'] as $task): ?>
                                        <div class="task-card">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <?php if($task['description']): ?>
                                                <div class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?></div>
                                            <?php endif; ?>
                                            <div class="task-meta">
                                                <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                                <div style="display: flex; gap: 5px;">
                                                    <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=pending" 
                                                       class="btn" style="padding: 4px 8px; font-size: 11px; background: #ed8936; color: white; text-decoration: none;">Back</a>
                                                    <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=completed" 
                                                       class="btn" style="padding: 4px 8px; font-size: 11px; background: #48bb78; color: white; text-decoration: none;">Complete</a>
                                                </div>
                                            </div>
                                            <?php if($task['assignee_name']): ?>
                                                <div style="font-size: 12px; color: #718096; margin-top: 8px;">
                                                    Assigned to: <?php echo $task['assignee_name']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Add Task Form -->
                                    <form method="POST" action="dashboard.php" style="margin-top: 10px;">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <input type="hidden" name="status" value="in_progress">
                                        <div style="display: flex; gap: 5px;">
                                            <input type="text" name="title" placeholder="New task..." required 
                                                   style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                            <button type="submit" name="create_task" class="btn" 
                                                    style="padding: 8px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                Add
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Completed Tasks -->
                            <div class="task-column">
                                <div class="column-header">
                                    <div class="column-title">Completed</div>
                                    <span class="task-count"><?php echo count($tasks['completed']); ?></span>
                                </div>
                                <div class="column-content">
                                    <?php foreach($tasks['completed'] as $task): ?>
                                        <div class="task-card">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <?php if($task['description']): ?>
                                                <div class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?></div>
                                            <?php endif; ?>
                                            <div class="task-meta">
                                                <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                                <a href="dashboard.php?update_task=1&task_id=<?php echo $task['id']; ?>&new_status=in_progress" 
                                                   class="btn" style="padding: 4px 8px; font-size: 11px; background: #ed8936; color: white; text-decoration: none;">Reopen</a>
                                            </div>
                                            <?php if($task['assignee_name']): ?>
                                                <div style="font-size: 12px; color: #718096; margin-top: 8px;">
                                                    Assigned to: <?php echo $task['assignee_name']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Add Task Form -->
                                    <form method="POST" action="dashboard.php" style="margin-top: 10px;">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <div style="display: flex; gap: 5px;">
                                            <input type="text" name="title" placeholder="New task..." required 
                                                   style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                            <button type="submit" name="create_task" class="btn" 
                                                    style="padding: 8px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                Add
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if(empty($all_projects)): ?>
                    <div style="text-align: center; padding: 50px; color: #718096;">
                        <h3>No projects yet</h3>
                        <p>Create your first project or join an existing one to get started!</p>
                        <a href="create_project.php" class="btn btn-primary" style="margin-top: 15px;">Create Project</a>
                    </div>
                <?php endif; ?>
            </div>
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