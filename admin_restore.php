<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin (you can modify this based on your admin system)
// For now, let's assume only user with ID 1 is admin
if ($_SESSION['userid'] != 1) {
    header("Location: dashboard.php");
    exit();
}

// Handle restoration of projects
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_project'])) {
    $project_id = intval($_POST['project_id']);
    $restore_sql = "UPDATE projects SET is_deleted = 0, deleted_at = NULL WHERE id = '$project_id'";
    
    if (mysqli_query($conn, $restore_sql)) {
        $success = "Project restored successfully!";
    } else {
        $error = "Error restoring project: " . mysqli_error($conn);
    }
}

// Handle restoration of tasks
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_task'])) {
    $task_id = intval($_POST['task_id']);
    $restore_sql = "UPDATE tasks SET is_deleted = 0, deleted_at = NULL WHERE id = '$task_id'";
    
    if (mysqli_query($conn, $restore_sql)) {
        $success = "Task restored successfully!";
    } else {
        $error = "Error restoring task: " . mysqli_error($conn);
    }
}

// Get soft-deleted projects
$deleted_projects_sql = "SELECT p.*, u.username as supervisor_name 
                         FROM projects p 
                         JOIN users u ON p.supervisor_id = u.id 
                         WHERE p.is_deleted = 1 
                         ORDER BY p.deleted_at DESC";
$deleted_projects_result = mysqli_query($conn, $deleted_projects_sql);
$deleted_projects = [];
while ($row = mysqli_fetch_assoc($deleted_projects_result)) {
    $deleted_projects[] = $row;
}

// Get soft-deleted tasks
$deleted_tasks_sql = "SELECT t.*, p.title as project_title, u.username as assigned_username 
                      FROM tasks t 
                      LEFT JOIN projects p ON t.project_id = p.id 
                      LEFT JOIN users u ON t.assigned_to = u.id 
                      WHERE t.is_deleted = 1 
                      ORDER BY t.deleted_at DESC";
$deleted_tasks_result = mysqli_query($conn, $deleted_tasks_sql);
$deleted_tasks = [];
while ($row = mysqli_fetch_assoc($deleted_tasks_result)) {
    $deleted_tasks[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Restore Deleted Items</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .admin-table th, .admin-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .admin-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        
        .admin-table tr:hover {
            background: #f7fafc;
        }
        
        .btn-restore {
            background: #48bb78;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-restore:hover {
            background: #38a169;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
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
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <a href="dashboard.php"><i class="fas fa-tasks"></i> Task Board - Admin</a>
        </div>
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="admin_restore.php" class="active">Restore Items</a>
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>
    
    <div class="admin-container">
        <h1>Restore Deleted Items</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Deleted Projects Section -->
        <div class="admin-section">
            <h2>Deleted Projects (<?php echo count($deleted_projects); ?>)</h2>
            
            <?php if (!empty($deleted_projects)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Project Title</th>
                            <th>Supervisor</th>
                            <th>Deleted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deleted_projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><?php echo htmlspecialchars($project['supervisor_name']); ?></td>
                                <td><?php echo $project['deleted_at'] ? date('M j, Y H:i', strtotime($project['deleted_at'])) : 'N/A'; ?></td>
                                <td>
                                    <form method="POST" action="" onsubmit="return confirm('Restore this project?');">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" name="restore_project" class="btn-restore">
                                            <i class="fas fa-undo"></i> Restore
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No deleted projects found.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Deleted Tasks Section -->
        <div class="admin-section">
            <h2>Deleted Tasks (<?php echo count($deleted_tasks); ?>)</h2>
            
            <?php if (!empty($deleted_tasks)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Task Title</th>
                            <th>Project</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Deleted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deleted_tasks as $task): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                <td><?php echo htmlspecialchars($task['project_title']); ?></td>
                                <td><?php echo $task['assigned_username'] ? htmlspecialchars($task['assigned_username']) : 'Not assigned'; ?></td>
                                <td><?php echo ucfirst($task['status']); ?></td>
                                <td><?php echo $task['deleted_at'] ? date('M j, Y H:i', strtotime($task['deleted_at'])) : 'N/A'; ?></td>
                                <td>
                                    <form method="POST" action="" onsubmit="return confirm('Restore this task?');">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" name="restore_task" class="btn-restore">
                                            <i class="fas fa-undo"></i> Restore
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No deleted tasks found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>