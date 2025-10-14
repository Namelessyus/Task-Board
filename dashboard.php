<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header("Location: login.html");
    exit();
}

// If user doesn't have a role yet, redirect to choose role
if (!isset($_SESSION['role']) || empty($_SESSION['role'])) {
    header("Location: choose_role.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
?>


<!DOCTYPE html>
<html>
  <head>
        <link rel="stylesheet" href="Dashboard.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <title>Task Board</title>


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

    

             <!-- Content Area -->
        <main class="content">
            <div class="content-header">
                <div class="search-bar">
                    <span class="search-icon">
                        <i class="fas fa-search search-icon"></i>
                    </span>
                    <input type="text" placeholder="Search tasks, projects, or team members..." onkeyup="searchTasks(this.value)">
                </div>
                <div class="action-buttons">
                    <?php if($role == "supervisor") { ?>
                        <a href="create_project.php" class="btn btn-primary">Create Project</a>
                    <?php } ?>
                    <button class="btn btn-warning" onclick="addTask()">Add Task</button>
                </div>
            </div>

            <h1 class="project-title">Welcome to Your Dashboard, <?php echo htmlspecialchars($username); ?>!</h1>
            <p style="color: #666; margin-bottom: 20px;">Role: <strong><?php echo ucfirst($role); ?></strong></p>
    
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
                <h3>Contact Us</h3> <p><i class="fa fa-envelope" style="font-size:16px"></i> contact@taskboard.com</p>
    <p><i class="fa fa-phone" style="font-size:16px"></i> +977 1234567891</p>
    <div class="social-links">
        <a href="https://www.x.com/">
            <i class="fa fa-twitter" style="font-size:20px"></i>
        </a>
        <a href="https://instagram.com/">
            <i class="fa fa-instagram" style="font-size:20px"></i>
        </a>
        <a href="https://www.facebook.com/">
            <i class="fa fa-facebook-square" style="font-size:20px"></i>
        </a>
        <a href="https://www.linkedin.com/">
            <i class="fa fa-linkedin-square" style="font-size:20px"></i>
        </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2025 Task Board. All rights reserved.</p>
        </div>
    </footer>

    
  </body>
</html>