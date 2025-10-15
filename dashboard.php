<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.html");
    exit();
}

$username = $_SESSION['username'];
$userid = $_SESSION['userid'];

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
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 140px);
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
            <a href="#" class="active">Home</a>
            <a href="#" onclick="switchToProjects()">Projects</a>
            <a href="#" onclick="switchToTasks()">Tasks</a>
            <a href="#" onclick="switchToTeam()">Team</a>
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <!-- Dashboard Content -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-section">
                <h3>Projects I Created</h3>
                <ul class="project-list" id="created-projects">
                    <?php foreach($my_projects as $project): ?>
                        <li onclick="filterProjects('created', <?php echo $project['id']; ?>)">
                            <?php echo htmlspecialchars($project['title']); ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($my_projects)): ?>
                        <li style="color: #718096; font-style: italic;">No projects created</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>Projects I Joined</h3>
                <ul class="project-list" id="joined-projects">
                    <?php foreach($joined_projects as $project): ?>
                        <li onclick="filterProjects('joined', <?php echo $project['id']; ?>)">
                            <?php echo htmlspecialchars($project['title']); ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($joined_projects)): ?>
                        <li style="color: #718096; font-style: italic;">No projects joined</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="filters">
                <h3>Filters</h3>
                <div class="filter-option">
                    <input type="checkbox" id="assigned-to-me" onchange="applyFilters()">
                    <label for="assigned-to-me">Assigned to me</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="high-priority" onchange="applyFilters()">
                    <label for="high-priority">High Priority</label>
                </div>
                <div class="filter-option">
                    <input type="checkbox" id="due-soon" onchange="applyFilters()">
                    <label for="due-soon">Due Soon</label>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <div class="search-bar">
                    <span class="search-icon">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="search-input" placeholder="Search tasks, projects, or team members..." onkeyup="searchContent(this.value)">
                </div>
                <div class="action-buttons">
                    <a href="create_project.php" class="btn btn-primary">Create Project</a>
                    <button class="btn btn-warning" onclick="addTask()">Add Task</button>
                </div>
            </div>

            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p style="color: #666; margin-bottom: 30px;">Here are all your projects</p>

            <!-- Projects Grid -->
            <div class="projects-grid" id="projects-container">
                <?php foreach($all_projects as $project): 
                    $tasks = $project_tasks[$project['id']];
                    $total_tasks = count($tasks['pending']) + count($tasks['in_progress']) + count($tasks['completed']);
                ?>
                    <div class="project-card" data-project-id="<?php echo $project['id']; ?>">
                        <div class="project-header">
                            <div>
                                <h2 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h2>
                                <div class="project-meta">
                                    <span class="priority-<?php echo $project['priority']; ?>">
                                        <?php echo ucfirst($project['priority']); ?> Priority
                                    </span>
                                    • Due: <?php echo $project['due_date'] ? date('M d, Y', strtotime($project['due_date'])) : 'No due date'; ?>
                                    • <?php echo $total_tasks; ?> tasks
                                </div>
                            </div>
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="view-project-btn">
                                View Project
                            </a>
                        </div>
                        
                        <p style="color: #718096; margin-bottom: 20px;"><?php echo htmlspecialchars($project['description']); ?></p>
                        
                        <!-- Task Board -->
                        <div class="task-board">
                            <!-- Pending Tasks -->
                            <div class="task-column">
                                <div class="column-header">
                                    <div class="column-title">Pending</div>
                                    <span class="task-count"><?php echo count($tasks['pending']); ?></span>
                                </div>
                                <?php foreach($tasks['pending'] as $task): ?>
                                    <div class="task-card" draggable="true" ondragstart="dragStart(event)" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?></div>
                                        <div class="task-meta">
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                            <?php if($task['assignee_name']): ?>
                                                <span><?php echo $task['assignee_name']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <button class="add-task-btn" onclick="addNewTask(<?php echo $project['id']; ?>, 'pending')">
                                    <i class="fa fa-plus"></i> Add Task
                                </button>
                            </div>
                            
                            <!-- In Progress Tasks -->
                            <div class="task-column">
                                <div class="column-header">
                                    <div class="column-title">In Progress</div>
                                    <span class="task-count"><?php echo count($tasks['in_progress']); ?></span>
                                </div>
                                <?php foreach($tasks['in_progress'] as $task): ?>
                                    <div class="task-card" draggable="true" ondragstart="dragStart(event)" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?></div>
                                        <div class="task-meta">
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                            <?php if($task['assignee_name']): ?>
                                                <span><?php echo $task['assignee_name']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <button class="add-task-btn" onclick="addNewTask(<?php echo $project['id']; ?>, 'in_progress')">
                                    <i class="fa fa-plus"></i> Add Task
                                </button>
                            </div>
                            
                            <!-- Completed Tasks -->
                            <div class="task-column">
                                <div class="column-header">
                                    <div class="column-title">Completed</div>
                                    <span class="task-count"><?php echo count($tasks['completed']); ?></span>
                                </div>
                                <?php foreach($tasks['completed'] as $task): ?>
                                    <div class="task-card" draggable="true" ondragstart="dragStart(event)" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?></div>
                                        <div class="task-meta">
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                            <?php if($task['assignee_name']): ?>
                                                <span><?php echo $task['assignee_name']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <button class="add-task-btn" onclick="addNewTask(<?php echo $project['id']; ?>, 'completed')">
                                    <i class="fa fa-plus"></i> Add Task
                                </button>
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
                    <a href="Index.html"><i class="fas fa-tasks"></i> Task Board</a>
                </div>
                <p>A modern solution for project management and task tracking designed for academic teams and beyond.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#" onclick="switchToHome()">Home</a></li>
                    <li><a href="#" onclick="switchToProjects()">Projects</a></li>
                    <li><a href="#" onclick="switchToTasks()">Tasks</a></li>
                    <li><a href="#" onclick="switchToTeam()">Team</a></li>
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

    <script>
        // Simple filtering functionality
        function filterProjects(type, projectId) {
            // Remove active class from all items
            document.querySelectorAll('.project-list li').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to clicked item
            event.target.classList.add('active');
            
            // For now, just scroll to the project in main content
            const projectElement = document.querySelector(`[data-project-id="${projectId}"]`);
            if (projectElement) {
                projectElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                projectElement.style.background = '#f0fff4';
                setTimeout(() => {
                    projectElement.style.background = 'white';
                }, 2000);
            }
        }
        
        function searchContent(query) {
            const projects = document.querySelectorAll('.project-card');
            projects.forEach(project => {
                const text = project.textContent.toLowerCase();
                if (text.includes(query.toLowerCase())) {
                    project.style.display = 'block';
                } else {
                    project.style.display = 'none';
                }
            });
        }
        
        function applyFilters() {
            // Basic filter implementation - can be enhanced
            console.log('Filters applied');
        }
        
        // Drag and drop functionality
        function dragStart(e) {
            e.dataTransfer.setData('text/plain', e.target.dataset.taskId);
        }
        
        // Make task columns drop targets
        document.addEventListener('DOMContentLoaded', function() {
            const columns = document.querySelectorAll('.task-column');
            columns.forEach(column => {
                column.addEventListener('dragover', e => e.preventDefault());
                column.addEventListener('drop', handleDrop);
            });
        });
        
        function handleDrop(e) {
            e.preventDefault();
            const taskId = e.dataTransfer.getData('text/plain');
            const newStatus = e.target.closest('.task-column').querySelector('.column-title').textContent.toLowerCase().replace(' ', '_');
            
            // Update task status in database
            updateTaskStatus(taskId, newStatus);
        }
        
        function updateTaskStatus(taskId, newStatus) {
            // AJAX call to update task status
            fetch('update_task_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `task_id=${taskId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Simple refresh for now
                }
            });
        }
        
        function addNewTask(projectId, status) {
            // Redirect to task creation page or show modal
            window.location.href = `create_task.php?project_id=${projectId}&status=${status}`;
        }
        
        // Placeholder functions for navigation
        function switchToHome() { window.location.href = 'dashboard.php'; }
        function switchToProjects() { }
        function switchToTasks() { }
        function switchToTeam() { }
        function addTask() {  }
    </script>
</body>
</html>
