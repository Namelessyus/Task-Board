<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $join_code = mysqli_real_escape_string($conn, $_POST['join_code']);
    $user_id = $_SESSION['userid'];
    
    if (empty($join_code)) {
        $error = "Please enter a join code!";
    } else {
        // Find project by join code
        $sql = "SELECT * FROM projects WHERE join_code = '$join_code'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            $project = mysqli_fetch_assoc($result);
            $project_id = $project['id'];
            
            // Check if user is already a member
            $check_sql = "SELECT * FROM project_members WHERE project_id = '$project_id' AND user_id = '$user_id'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "You are already a member of this project!";
            } else {
                // Add user as project member
                $insert_sql = "INSERT INTO project_members (project_id, user_id, role) VALUES ('$project_id', '$user_id', 'general')";
                
                if (mysqli_query($conn, $insert_sql)) {
                    $success = "Successfully joined project: <strong>" . htmlspecialchars($project['title']) . "</strong>";
                    $_POST = array();
                } else {
                    $error = "Error joining project: " . mysqli_error($conn);
                }
            }
        } else {
            $error = "Invalid join code. Please check and try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Project - Task Board</title>
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
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #764BA2;
            color: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        input:focus {
            border-color: #764BA2;;
            outline: none;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #764BA2;
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
            background: #5d3a7f;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #764BA2;;
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

        .join-code-example {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            border: 1px dashed #dee2e6;
        }

        .join-code-example h4 {
            color: #495057;
            margin-bottom: 10px;
        }

        .code {
            font-family: monospace;
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
            color: #495057;
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
            <h2>Join a Project</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="join_code">Enter Join Code:</label>
                    <input type="text" id="join_code" name="join_code" required 
                           value="<?php echo isset($_POST['join_code']) ? htmlspecialchars($_POST['join_code']) : ''; ?>"
                           placeholder="Enter 6-character join code" maxlength="6" style="text-transform: uppercase;">
                    <small style="color: #666; font-size: 14px;">Enter the 6-character code shared by the project creator</small>
                </div>
                
                <button type="submit">Join Project</button>
            </form>

            <div class="join-code-example">
                <h4>What is a join code?</h4>
                <p>A join code is a 6-character code (like <span class="code">ABC123</span>) that the project creator shares with team members.</p>
                <p>Ask your project lead for the join code to join their project.</p>
            </div>
            
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
