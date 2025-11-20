<?php
session_start();
include('connect.php');

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Check if user exists
        $sql = "SELECT * FROM users WHERE email='$email'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);

            // Verify password
            if (password_verify($password, $row['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['username'] = $row['username'];
                $_SESSION['userid'] = $row['id'];
                $_SESSION['email'] = $row['email'];
                
                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "User not found. Please check your email or sign up.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Task Board</title>
    <style>
        body {
            font-family: sans-serif;
            background: linear-gradient(to bottom, #e8e4f3, #f5f3ff);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .form-container {
            background: white;
            padding: 40px 50px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 400px;
        }

        .form-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #6366f1;
        }

        .form-container input {
            width: 100%;
            padding: 12px 15px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
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

        .form-footer {
            text-align: center;
            margin-top: 15px;
        }

        .form-footer a {
            color: #6366f1;
            text-decoration: none;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #ffebee;
            border-radius: 8px;
            border: 1px solid #ffcdd2;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Login</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        
        <div class="form-footer">
            Don't have an account? <a href="signup.html">Sign Up</a>
        </div>
    </div>
</body>
</html>