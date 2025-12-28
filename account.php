<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$username = $_SESSION['username'];
$email = $_SESSION['email'] ?? '';
$success = "";
$error = "";
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Get user details
$user_sql = "SELECT * FROM users WHERE id = '$user_id'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_username = mysqli_real_escape_string($conn, $_POST['username']);
    $new_email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if username contains numbers
    if (preg_match('/[0-9]/', $new_username)) {
        $error = "Username should not contain numbers.";
    } else {
        // Check if email already exists (excluding current user)
        $check_email_sql = "SELECT id FROM users WHERE email = '$new_email' AND id != '$user_id'";
        $check_email_result = mysqli_query($conn, $check_email_sql);
        
        if (mysqli_num_rows($check_email_result) > 0) {
            $error = "Email already registered by another user.";
        } else {
            // Update user profile
            $update_sql = "UPDATE users SET username = '$new_username', email = '$new_email' WHERE id = '$user_id'";
            
            if (mysqli_query($conn, $update_sql)) {
                $_SESSION['username'] = $new_username;
                $_SESSION['email'] = $new_email;
                $success = "Profile updated successfully!";
                $user['username'] = $new_username;
                $user['email'] = $new_email;
            } else {
                $error = "Error updating profile: " . mysqli_error($conn);
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_password != $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Hash and update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = '$hashed_password' WHERE id = '$user_id'";
        
        if (mysqli_query($conn, $update_sql)) {
            $success = "Password changed successfully!";
        } else {
            $error = "Error changing password: " . mysqli_error($conn);
        }
    }
}

// Handle account deletion (SOFT DELETE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    $confirm_text = mysqli_real_escape_string($conn, $_POST['confirm_text']);
    
    if ($confirm_text === 'DELETE') {
        // Soft delete the account
        $delete_sql = "UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = '$user_id'";
        
        if (mysqli_query($conn, $delete_sql)) {
            // Logout and redirect
            session_destroy();
            header("Location: login.php?message=account_deleted");
            exit();
        } else {
            $error = "Error deleting account: " . mysqli_error($conn);
        }
    } else {
        $error = "Please type 'DELETE' to confirm account deletion.";
    }
}

// Handle project restoration (for supervisors only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_project'])) {
    $project_id = intval($_POST['project_id']);
    
    // Check if user is the supervisor of this project
    $check_sql = "SELECT id FROM projects WHERE id = '$project_id' AND supervisor_id = '$user_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $restore_sql = "UPDATE projects SET is_deleted = 0, deleted_at = NULL WHERE id = '$project_id'";
        
        if (mysqli_query($conn, $restore_sql)) {
            $success = "Project restored successfully!";
        } else {
            $error = "Error restoring project: " . mysqli_error($conn);
        }
    } else {
        $error = "You are not authorized to restore this project.";
    }
}

// Get user statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT p.id) as projects_created,
    COUNT(DISTINCT pm.project_id) as projects_joined,
    COUNT(DISTINCT t.id) as tasks_assigned,
    COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as tasks_completed
    FROM users u
    LEFT JOIN projects p ON u.id = p.supervisor_id AND p.is_deleted = 0
    LEFT JOIN project_members pm ON u.id = pm.user_id
    LEFT JOIN projects p_active ON pm.project_id = p_active.id AND p_active.is_deleted = 0
    LEFT JOIN tasks t ON (u.id = t.created_by OR u.id = t.assigned_to) 
                      AND t.is_deleted = 0
    LEFT JOIN projects p_task ON t.project_id = p_task.id AND p_task.is_deleted = 0
    WHERE u.id = '$user_id'";
    
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// separates counts for better accuracy
$projects_created_sql = "SELECT COUNT(*) as count FROM projects WHERE supervisor_id = '$user_id' AND is_deleted = 0";
$projects_created_result = mysqli_query($conn, $projects_created_sql);
$projects_created = mysqli_fetch_assoc($projects_created_result)['count'];

$projects_joined_sql = "SELECT COUNT(DISTINCT pm.project_id) as count 
                       FROM project_members pm 
                       JOIN projects p ON pm.project_id = p.id 
                       WHERE pm.user_id = '$user_id' AND p.is_deleted = 0";
$projects_joined_result = mysqli_query($conn, $projects_joined_sql);
$projects_joined = mysqli_fetch_assoc($projects_joined_result)['count'];

$tasks_assigned_sql = "SELECT COUNT(DISTINCT t.id) as count 
                      FROM tasks t 
                      JOIN projects p ON t.project_id = p.id 
                      LEFT JOIN task_assignments ta ON t.id = ta.task_id
                      WHERE (t.created_by = '$user_id' OR ta.user_id = '$user_id') 
                      AND t.is_deleted = 0 AND p.is_deleted = 0";
$tasks_assigned_result = mysqli_query($conn, $tasks_assigned_sql);
$tasks_assigned = mysqli_fetch_assoc($tasks_assigned_result)['count'];

$tasks_completed_sql = "SELECT COUNT(DISTINCT t.id) as count 
                       FROM tasks t 
                       JOIN projects p ON t.project_id = p.id 
                       LEFT JOIN task_assignments ta ON t.id = ta.task_id
                       WHERE (t.created_by = '$user_id' OR ta.user_id = '$user_id') 
                       AND t.status = 'completed' 
                       AND t.is_deleted = 0 AND p.is_deleted = 0";
$tasks_completed_result = mysqli_query($conn, $tasks_completed_sql);
$tasks_completed = mysqli_fetch_assoc($tasks_completed_result)['count'];

// Update stats array with accurate counts
$stats['projects_created'] = $projects_created;
$stats['projects_joined'] = $projects_joined;
$stats['tasks_assigned'] = $tasks_assigned;
$stats['tasks_completed'] = $tasks_completed;

// Get soft-deleted projects for this supervisor
$deleted_projects_sql = "SELECT * FROM projects WHERE supervisor_id = '$user_id' AND is_deleted = 1 ORDER BY deleted_at DESC";
$deleted_projects_result = mysqli_query($conn, $deleted_projects_sql);
$deleted_projects = [];
while ($row = mysqli_fetch_assoc($deleted_projects_result)) {
    $deleted_projects[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Account Settings - Task Board</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Reset for avatar */
        .profile-avatar,
        .profile-avatar * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Header - Match your existing style */
        .header {
            background: #764BA2;
            color: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
        }

        .logo a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
        }

        .nav {
            display: flex;
            gap: 32px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 0;
            border-bottom: 2px solid transparent;
            transition: border-color 0.3s;
        }

        .nav a:hover, .nav a.active {
            border-bottom-color: white;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .account-container {
            display: flex;
            min-height: calc(100vh - 140px);
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .account-sidebar {
            width: 320px;
            background: white;
            padding: 30px;
            border-right: 1px solid #e2e8f0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            height: calc(100vh - 140px);
            position: sticky;
            top: 60px;
            overflow-y: auto;
        }
        
        .account-main {
            flex: 1;
            padding: 40px;
            background: #f7fafc;
            min-height: calc(100vh - 140px);
            overflow-y: auto;
        }
        
        .account-card {
            background: white;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .account-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .profile-avatar {
            width: 90px;
            min-width: 90px;
            height: 90px;
            border-radius: 1000px !important;
            background: #764BA2;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            overflow: hidden;
            aspect-ratio: 1 / 1;
            flex-shrink: 0;
        }
        
        .profile-info h2 {
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 24px;
            font-weight: 700;
        }
        
        .profile-info p {
            color: #718096;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .profile-info .member-since {
            color: #94a3b8;
            font-size: 13px;
            margin-top: 6px;
        }
        
        .tab-navigation {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 40px;
        }
        
        .tab-btn {
            padding: 14px 20px;
            border: none;
            background: transparent;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .tab-btn:hover {
            background: #f1f5f9;
            color: #475569;
            transform: translateX(5px);
        }
        
        .tab-btn.active {
            background: #764BA2;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
        }
        
        .tab-btn.active:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            border-radius: 0 4px 4px 0;
        }
        
        .tab-btn i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #475569;
            font-size: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: #764BA2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            background: white;
        }
        
        .btn-primary {
            background: #764BA2;
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 800;
            color: #764BA2;
            display: block;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 15px;
            color: #64748b;
            font-weight: 500;
        }
        
        .danger-zone {
            border: 2px solid #fecaca;
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
        }
        
        .danger-zone h3 {
            color: #dc2626;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #a7f3d0;
            text-align: center;
            font-weight: 500;
            animation: slideIn 0.5s ease;
        }
        
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #fecaca;
            text-align: center;
            font-weight: 500;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle .toggle-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s;
        }
        
        .password-toggle .toggle-btn:hover {
            color: #764BA2;
        }
        
        .section-title {
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            color: #764BA2;
        }
        
        .section-subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .back-link {
            display: block;
            text-align: center;
            padding: 14px;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            margin-top: 20px;
            border: 2px solid #e2e8f0;
        }
        
        .back-link:hover {
            background: #e2e8f0;
            color: #334155;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .achievement-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 18px;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 12px;
            border-left: 4px solid #764BA2;
            transition: all 0.3s;
        }
        
        .achievement-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }
        
        .achievement-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 1000px !important;
            background: #764BA2;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            overflow: hidden;
            aspect-ratio: 1 / 1;
        }
        
        .achievement-info h4 {
            color: #1e293b;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .achievement-info p {
            color: #64748b;
            font-size: 14px;
            margin: 0;
        }
        
        .login-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            border: 2px solid #e2e8f0;
        }
        
        .login-info p {
            margin-bottom: 10px;
            color: #475569;
            font-size: 14px;
        }
        
        .login-info strong {
            color: #334155;
        }
        
        .reset-password-link {
            text-align: center;
            padding: 20px;
            background: #e0f2fe;
            border-radius: 12px;
            margin-top: 20px;
            border: 2px solid #bae6fd;
        }
        
        /* Restore Section Styles - SIMPLIFIED */
        .restore-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .restore-table th, .restore-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .restore-table th {
            background: #764BA2;
            color: white;
            font-weight: 600;
        }
        
        .restore-table tr:hover {
            background: #f7fafc;
        }
        
        .restore-table td:last-child {
            text-align: center;
        }
        
        .btn-restore {
            background: #48bb78;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-restore:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px dashed #e2e8f0;
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }
        
        .info-card {
            background: #e0f2fe;
            border: 2px solid #bae6fd;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-card h4 {
            color: #0369a1;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-card p {
            color: #0c4a6e;
            margin: 0;
            line-height: 1.6;
        }
        
        @media (max-width: 1024px) {
            .account-container {
                flex-direction: column;
            }
            
            .account-sidebar {
                width: 100%;
                height: auto;
                position: static;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .account-main {
                padding: 30px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .tab-navigation {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .tab-btn {
                flex: 1;
                min-width: 120px;
                text-align: center;
                justify-content: center;
            }
            
            .restore-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 640px) {
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .nav {
                gap: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .account-main {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .account-card {
                padding: 25px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .restore-table th, .restore-table td {
                padding: 10px;
                font-size: 14px;
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
            <a href="dashboard.php">Home</a>
            <a href="create_project.php">Create Project</a>
            <a href="join_project.php">Join Project</a>
            <a href="calendar.php">Calendar</a>
            <a href="account.php" class="active">Account</a>
        </nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <!-- Account Content -->
    <div class="account-container">
        <!-- Sidebar -->
        <div class="account-sidebar">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="member-since">Member since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <div class="tab-navigation">
                <button class="tab-btn <?php echo $tab == 'profile' ? 'active' : ''; ?>" onclick="showTab('profile')">
                    <i class="fas fa-user"></i> Profile
                </button>
                <button class="tab-btn <?php echo $tab == 'security' ? 'active' : ''; ?>" onclick="showTab('security')">
                    <i class="fas fa-lock"></i> Security
                </button>
                <button class="tab-btn <?php echo $tab == 'stats' ? 'active' : ''; ?>" onclick="showTab('stats')">
                    <i class="fas fa-chart-bar"></i> Stats
                </button>
                <button class="tab-btn <?php echo $tab == 'restore' ? 'active' : ''; ?>" onclick="showTab('restore')">
                    <i class="fas fa-trash-restore"></i> Restore Projects
                </button>
            </div>
            
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Main Content -->
        <div class="account-main">
            <!-- Success/Error Messages -->
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Profile Tab -->
            <div class="tab-content <?php echo $tab == 'profile' ? 'active' : ''; ?>" id="profile-tab">
                <div class="account-card">
                    <h2 class="section-title"><i class="fas fa-user-edit"></i> Edit Profile</h2>
                    <p class="section-subtitle">Update your personal information</p>
                    
                    <form method="POST" action="" onsubmit="return validateProfile()">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required
                                   pattern="[A-Za-z\s]+" title="Username should contain only letters and spaces">
                            <small style="color: #94a3b8; font-size: 13px; display: block; margin-top: 5px;">Only letters and spaces allowed (no numbers)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
                
                <!-- Danger Zone -->
                <div class="account-card danger-zone">
                    <h3><i class="fas fa-exclamation-triangle"></i> To Deactivate Account</h3>
                    <p style="color: #7f1d1d; margin-bottom: 20px; line-height: 1.6;">
                        This will deactivate your account. Your data will be kept for 30 days before permanent deletion.
                        You can restore your account during this period by contacting support.
                    </p>
                    
                    <form method="POST" action="" id="deleteForm" onsubmit="return confirmDelete()">
                        <div class="form-group">
                            <label for="confirm_text" style="color: #dc2626;">Type "DELETE" to confirm:</label>
                            <input type="text" id="confirm_text" name="confirm_text" class="form-control" 
                                   placeholder="Type DELETE to confirm" required
                                   style="border-color: #f87171; background: #fff5f5;">
                        </div>
                        
                        <button type="submit" name="delete_account" class="btn-primary" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);">
                            <i class="fas fa-trash-alt"></i> Deactivate Account
                        </button>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-content <?php echo $tab == 'security' ? 'active' : ''; ?>" id="security-tab">
                <div class="account-card">
                    <h2 class="section-title"><i class="fas fa-key"></i> Change Password</h2>
                    <p class="section-subtitle">Update your password to keep your account secure</p>
                    
                    <form method="POST" action="" onsubmit="return validatePassword()">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-toggle">
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                                <button type="button" class="toggle-btn" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-toggle">
                                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                                <button type="button" class="toggle-btn" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small style="color: #94a3b8; font-size: 13px; display: block; margin-top: 5px;">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-toggle">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
                
                <div class="account-card">
                    <h2 class="section-title"><i class="fas fa-history"></i> Login Activity</h2>
                    <p class="section-subtitle">Recent login information</p>
                    
                    <div class="login-info">
                        <p><strong>Current Session:</strong> Active</p>
                        <p><strong>Last Login:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></p>
                        <p><strong>IP Address:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
                    </div>
                </div>
            </div>

             <!-- Stats Tab -->
            <div class="tab-content <?php echo $tab == 'stats' ? 'active' : ''; ?>" id="stats-tab">
                <div class="account-card">
                    <h2><i class="fas fa-chart-line"></i> Your Activity Statistics</h2>
                    <p>Overview of your contributions and activities</p>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-number"><?php echo $stats['projects_created'] ?: 0; ?></span>
                            <span class="stat-label">Projects Created</span>
                        </div>
                        
                        <div class="stat-card">
                            <span class="stat-number"><?php echo $stats['projects_joined'] ?: 0; ?></span>
                            <span class="stat-label">Projects Joined</span>
                        </div>
                        
                        <div class="stat-card">
                            <span class="stat-number"><?php echo $stats['tasks_assigned'] ?: 0; ?></span>
                            <span class="stat-label">Tasks Assigned</span>
                        </div>
                        
                        <div class="stat-card">
                            <span class="stat-number"><?php echo $stats['tasks_completed'] ?: 0; ?></span>
                            <span class="stat-label">Tasks Completed</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Restore Tab (Only Projects) -->
            <div class="tab-content <?php echo $tab == 'restore' ? 'active' : ''; ?>" id="restore-tab">
                
                <!-- Deleted Projects Section -->
                <div class="account-card">
                    <h2 class="section-title"><i class="fas fa-project-diagram"></i> Deleted Projects</h2>
                    <p class="section-subtitle">Projects you supervise that have been deleted</p>
                    
                    <?php if (!empty($deleted_projects)): ?>
                        <table class="restore-table">
                            <thead>
                                <tr>
                                    <th>Project Title</th>
                                    <th>Description</th>
                                    <th>Deleted On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deleted_projects as $project): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($project['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($project['description'] ?? 'No description', 0, 50)) . (strlen($project['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><?php echo $project['deleted_at'] ? date('M j, Y H:i', strtotime($project['deleted_at'])) : 'Unknown'; ?></td>
                                        <td>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <button type="submit" name="restore_project" class="btn-restore" onclick="return confirmRestore('project')">
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
                            <i class="fas fa-folder-open"></i>
                            <h3>No Deleted Projects</h3>
                            <p>You don't have any deleted projects to restore.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function showTab(tabName) {
            // Update URL without reloading
            history.replaceState(null, null, '?tab=' + tabName);
            
            // Update active tab button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Show active tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Initialize active tab from URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'profile';
            
            // Set active tab based on URL
            const tabBtn = document.querySelector(`.tab-btn[onclick="showTab('${tab}')"]`);
            if (tabBtn) {
                tabBtn.click();
            }
        });
        
        // Password toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = event.target.closest('button');
            
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                button.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }
        
        // Validation functions
        function validateProfile() {
            const username = document.getElementById('username').value;
            const numbers = /[0-9]/;
            
            if (numbers.test(username)) {
                alert('Username should not contain numbers.');
                return false;
            }
            return true;
        }
        
        function validatePassword() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 6) {
                alert('New password must be at least 6 characters long.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match.');
                return false;
            }
            
            return true;
        }
        
        // Delete confirmation
        function confirmDelete() {
            const confirmText = document.getElementById('confirm_text').value;
            
            if (confirmText !== 'DELETE') {
                alert('Please type DELETE to confirm account deletion.');
                return false;
            }
            
            return confirm('Are you sure you want to deactivate your account?\n\nYour account will be deactivated immediately. You can contact support within 30 days to restore it.');
        }
        
        // Restore confirmation
        function confirmRestore(type) {
            return confirm(`Are you sure you want to restore this project?\n\nThe project and all its associated tasks will be restored.`);
        }
        
        // Real-time username validation
        document.getElementById('username')?.addEventListener('input', function() {
            const numbers = /[0-9]/;
            if (numbers.test(this.value)) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });
        
        // Form enhancement
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement?.classList?.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement?.classList?.remove('focused');
            });
        });
    </script>
</body>
</html>