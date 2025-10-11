<?php
session_start();
include('connect.php');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

// Handle role selection
if (isset($_POST['set_role'])) {
    $role = $_POST['role'];
    $userid = $_SESSION['userid'];

    // Update role in database
    $sql = "UPDATE users SET role='$role' WHERE id='$userid'";
    if ($conn->query($sql) === TRUE) {
        $_SESSION['role'] = $role;
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Error updating role: " . $conn->error;
    }
}

// Prevent re-choosing role if already set
if (isset($_SESSION['role']) && !is_null($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Role - Task Board</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: sans-serif;
            background: linear-gradient(to bottom, #e8e4f3, #f5f3ff);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .form-container {
            background: white;
            padding: 40px 50px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 400px;
            text-align: center;
        }

        .form-container h2 {
            color: #6366f1;
            margin-bottom: 20px;
        }

        .form-container select {
            width: 100%;
            padding: 12px 15px;
            margin: 15px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        .form-container button {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: none;
            border-radius: 8px;
            background: #6366f1;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .form-container button:hover {
            background: #4f46e5;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Choose Your Role</h2>
        <?php if(isset($error)) { echo "<div class='error'>$error</div>"; } ?>
        <form method="POST" action="">
            <select name="role" required>
                <option value="" disabled selected>Select your role</option>
                <option value="supervisor">Supervisor</option>
                <option value="participant">Participant</option>
            </select>
            <button type="submit" name="set_role">Continue</button>
        </form>
    </div>
</body>
</html>
