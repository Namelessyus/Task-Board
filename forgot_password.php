<?php
session_start();
include('connect.php');

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        // Check if email exists
        $check_sql = "SELECT * FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $update_sql = "UPDATE users SET reset_token = '$token', reset_expires = '$expires' WHERE email = '$email'";
            
            if (mysqli_query($conn, $update_sql)) {
                // In a real application, you would send an email here
                // For this demo, we'll just show the reset link
                $reset_link = "needs updates";
                $success = "Password reset link generated!<br<strong><a href='$reset_link'>$reset_link</a></strong><br>";
            } else {
                $error = "Error generating reset link: " . mysqli_error($conn);
            }
        } else {
            $error = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - Task Board</title>
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
            justify-content: center;
            align-items: center;
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

        p {
            color: #666;
            margin-bottom: 30px;
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
            border-color: #667eea;
            outline: none;
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

        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1565c0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-key"></i> Forgot Password</h2>
        <p>Enter your email address and link will be sent to reset your password.</p>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required 
                       placeholder="Enter your registered email">
            </div>
            
            <button type="submit">Send Reset Link</button>
        </form>
        
        <div class="info-box">
            <strong>Note:</strong> Under Maintainance hehe
        </div>
        
        <a href="login.php" class="back-link">← Back to Login</a>
    </div>
</body>
</html>