<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$userid = $_SESSION['userid'];

// Get user's projects (created and joined) - excluding soft-deleted
$my_projects = [];
$joined_projects = [];

// Projects created by user (where user is supervisor) - excluding soft-deleted
$created_sql = "SELECT p.*, COUNT(pm.user_id) as member_count 
                FROM projects p 
                LEFT JOIN project_members pm ON p.id = pm.project_id 
                WHERE p.supervisor_id = '$userid' AND p.is_deleted = 0
                GROUP BY p.id 
                ORDER BY p.created_at DESC";
$created_result = mysqli_query($conn, $created_sql);
if ($created_result) {
    while ($row = mysqli_fetch_assoc($created_result)) {
        $my_projects[] = $row;
    }
}

// Projects joined by user (where user is participant) - excluding soft-deleted
$joined_sql = "SELECT p.*, pm.role as member_role 
               FROM projects p 
               JOIN project_members pm ON p.id = pm.project_id 
               WHERE pm.user_id = '$userid' AND p.supervisor_id != '$userid' AND p.is_deleted = 0
               ORDER BY p.created_at DESC";
$joined_result = mysqli_query($conn, $joined_sql);
if ($joined_result) {
    while ($row = mysqli_fetch_assoc($joined_result)) {
        $joined_projects[] = $row;
    }
}

// Combine all projects for the main grid
$all_projects = array_merge($my_projects, $joined_projects);

// Check for success message from project deletion
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
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
        /* Google Classroom Style */
        .classroom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            padding: 20px 0;
        }

        .class-card {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .class-header {
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            padding: 20px;
            color: white;
        }

        .class-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            color: white;
        }

        .class-description {
            font-size: 14px;
            opacity: 0.9;
            color: white;
        }

        .class-body {
            padding: 20px;
        }

        .class-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .class-role {
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .class-role.participant {
            background: #667eea;
        }

        .class-code {
            background: #48bb78;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .class-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            display: block;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }

        .class-actions {
            display: flex;
            gap: 10px;
        }

        .class-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            text-decoration: none;
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            text-decoration: none;
            color: #4a5568;
        }

        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            font-size: 16px;
            color: #718096;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #4a5568;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 25px;
        }

        .create-first-btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .create-first-btn:hover {
            background: #5a67d8;
            text-decoration: none;
            color: white;
            transform: translateY(-2px);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            text-align: center;
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
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <!-- Dashboard Content -->
   <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-section">
                <div class="dropdown-header" onclick="toggleDropdown('created-projects')">
                    <h3>Projects I Created</h3>
                    <span class="dropdown-arrow" id="created-arrow">▼</span>
                </div>
                <ul class="project-list dropdown-content" id="created-projects">
                    <?php foreach($my_projects as $project): ?>
                        <li>
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['title']); ?>
                                <span class="supervisor-badge">Supervisor</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($my_projects)): ?>
                        <li class="no-projects">No projects created</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <div class="dropdown-header" onclick="toggleDropdown('joined-projects')">
                    <h3>Projects I Joined</h3>
                    <span class="dropdown-arrow" id="joined-arrow">▼</span>
                </div>
                <ul class="project-list dropdown-content" id="joined-projects">
                    <?php foreach($joined_projects as $project): ?>
                        <li>
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['title']); ?>
                                <span class="participant-badge">Participant</span>
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
            <!-- Welcome Section -->
            <?php if(isset($success_message)): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
 
            <?php if(!empty($all_projects)): ?>
                <div class="classroom-grid">
                    <?php foreach($all_projects as $project): 
                        $is_supervisor = ($project['supervisor_id'] == $userid);
                        
                        // Get task counts for this project (excluding soft-deleted)
                        $tasks_sql = "SELECT status, COUNT(*) as count FROM tasks WHERE project_id = '{$project['id']}' AND is_deleted = 0 GROUP BY status";
                        $tasks_result = mysqli_query($conn, $tasks_sql);
                        $task_counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
                        
                        if ($tasks_result) {
                            while ($row = mysqli_fetch_assoc($tasks_result)) {
                                $task_counts[$row['status']] = $row['count'];
                            }
                        }
                        
                        $total_tasks = array_sum($task_counts);
                        $completed_percentage = $total_tasks > 0 ? round(($task_counts['completed'] / $total_tasks) * 100) : 0;
                    ?>
                        <div class="class-card">
                            <div class="class-header">
                                <h3 class="class-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                                <p class="class-description"><?php echo htmlspecialchars(substr($project['description'], 0, 60)) . '...'; ?></p>
                            </div>
                            
                            <div class="class-body">
                                <div class="class-meta">
                                    <span class="class-role <?php echo $is_supervisor ? '' : 'participant'; ?>">
                                        <?php echo $is_supervisor ? 'Supervisor' : 'Participant'; ?>
                                    </span>
                                    <?php if($is_supervisor): ?>
                                        <span class="class-code"><?php echo $project['join_code']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="class-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $total_tasks; ?></span>
                                        <span class="stat-label">Total Tasks</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $completed_percentage; ?>%</span>
                                        <span class="stat-label">Completed</span>
                                    </div>
                                </div>
                                
                                <div class="class-actions">
                                    <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="class-btn btn-primary">
                                        <i class="fas fa-eye"></i> View Project
                                    </a>
                                    <?php if($is_supervisor): ?>
                                        <a href="project_detail.php?id=<?php echo $project['id']; ?>&manage=true" class="class-btn btn-secondary">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Add New Project Card -->
                    <div class="class-card" style="border: 2px dashed #cbd5e0; background: #f7fafc;">
                        <div class="class-body" style="text-align: center; padding: 40px 20px;">
                            <i class="fas fa-plus" style="font-size: 48px; color: #667eea; margin-bottom: 15px;"></i>
                            <h3 style="color: #4a5568; margin-bottom: 10px;">Create New Project</h3>
                            <p style="color: #718096; margin-bottom: 20px; font-size: 14px;">Start a new project and invite team members</p>
                            <a href="create_project.php" class="create-first-btn">
                                <i class="fas fa-plus"></i> Create Project
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-tasks" style="font-size: 64px; color: #cbd5e0; margin-bottom: 20px;"></i>
                    <h3>No Projects Yet</h3>
                    <p>Create your first project or join an existing one to get started!</p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <a href="create_project.php" class="create-first-btn">
                            <i class="fas fa-plus"></i> Create Project
                        </a>
                        <a href="join_project.php" class="create-first-btn" style="background: #48bb78;">
                            <i class="fas fa-users"></i> Join Project
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    
    <script>
    function toggleDropdown(dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        const arrow = document.getElementById(dropdownId.replace('projects', 'arrow'));
        
        dropdown.classList.toggle('active');
        arrow.style.transform = dropdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    // Initialize dropdowns as open by default
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('created-projects').classList.add('active');
        document.getElementById('joined-projects').classList.add('active');
    });
    </script>
    
</body>
</html>
