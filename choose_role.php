<?php
session_start();
include('connect.php');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.html");
    exit();
}

// Prevent re-choosing role if already set
if (isset($_SESSION['role']) && !empty($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle role selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_role'])) {
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $userid = $_SESSION['userid'];

    // Update role in database
    $sql = "UPDATE users SET role='$role' WHERE id='$userid'";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['role'] = $role;
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Error updating role: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Role - Task Board</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            width: 400px;
            text-align: center;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        select {
            width: 100%;
            padding: 12px;
            margin: 15px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
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
        }
        button:hover {
            background: #5a6fd8;
        }
        .error {
            color: red;
            background: #ffebee;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .welcome {
            color: #666;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Choose Your Role</h2>
        
        <div class="welcome">
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
        </div>
        
        <?php if(isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <select name="role" required>
                <option value="" disabled selected>Select your role</option>
                <option value="supervisor">Supervisor - Create projects and assign tasks</option>
                <option value="participant">Participant - Work on tasks and update progress</option>
            </select>
            <button type="submit" name="set_role">Continue to Dashboard</button>
        </form>
        
        <p style="margin-top: 15px; color: #666; font-size: 14px;">
            Your role determines what you can do in the system.
        </p>
    </div>
</body>
</html>