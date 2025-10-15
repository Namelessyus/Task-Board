<?php
session_start();
include('connect.php');

// Check if user is logged in (any user can create projects)
if (!isset($_SESSION['userid'])) {
    header("Location: login.html");
    exit();
}

$error = "";
$success = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    $user_id = $_SESSION['userid'];
    
    if (empty($title)) {
        $error = "Project name is required!";
    } else {
        // Generate unique join code
        $join_code = generateJoinCode($conn);
        
        // Insert into projects table
        $sql = "INSERT INTO projects (title, description, supervisor_id, due_date, priority, join_code) 
                VALUES ('$title', '$description', '$user_id', '$due_date', '$priority', '$join_code')";
        
        if (mysqli_query($conn, $sql)) {
            $project_id = mysqli_insert_id($conn);
            
            // Add creator as project member
            $member_sql = "INSERT INTO project_members (project_id, user_id, team_role) VALUES ('$project_id', '$user_id', 'general')";
            mysqli_query($conn, $member_sql);
            
            $success = "Project created successfully!<br>Share this join code: <strong>$join_code</strong>";
            $_POST = array();
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Project - Task Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }

        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        input:focus, textarea:focus, select:focus {
            border-color: #667eea;
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            transition: background 0.3s;
            font-weight: 600;
        }

        button:hover {
            background: #5a6fd8;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            width: 100%;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            text-align: center;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }

        .footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-top: auto;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between; 
            align-items: flex-start;
        }

        .footer-section h3 {
            margin-bottom: 16px;
            font-size: 18px;
        }

        .footer-section p, .footer-section a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            line-height: 1.6;
        }

        .footer-section a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 16px;
            margin-top: 16px;
        }

        .social-link {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .social-link:hover {
            background: rgba(255,255,255,0.3);
        }

        .footer-bottom {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.6);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <a href="dashboard.php"><i class="fas fa-tasks"></i> Task Board</a>
        </div>
    </header>

     <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h2>Create New Project</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="title">Project Name:</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                           placeholder="Enter project name">
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" 
                              placeholder="Describe your project..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="due_date">Due Date:</label>
                    <input type="date" id="due_date" name="due_date"
                           value="<?php echo isset($_POST['due_date']) ? $_POST['due_date'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority:</label>
                    <select id="priority" name="priority">
                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                    </select>
                </div>
                
                <button type="submit">Create Project</button>
            </form>
            
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>

 <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <div class="logo">
                    <a href="index.html"><i class="fas fa-tasks"></i> Task Board</a>
                </div>
                <p>A modern solution for project management and <br>
                    task tracking designed for academic teams and beyond.</p>
            </div>

            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><i class="fa fa-envelope" style="font-size:16px"></i> contact@taskboard.com</p>
                <p><i class="fa fa-phone" style="font-size:16px"></i> +977 1234567891</p>
                <div class="social-links">
                    <a href="#" class="social-link">
                        <i class="fa fa-twitter" style="font-size:16px"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fa fa-instagram" style="font-size:16px"></i>
                    </a>
                    <a href="https://www.facebook.com/" class="social-link">
                        <i class="fa fa-facebook-square" style="font-size:16px"></i>
                    </a>
                    <a href="https://www.linkedin.com/" class="social-link">
                        <i class="fa fa-linkedin-square" style="font-size:16px"></i>
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