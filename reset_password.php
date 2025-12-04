<?php
session_start();
include('connect.php');

$success = "";
$error = "";
$valid_token = false;

// Check if token is provided
if (isset($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    
    // Check if token is valid and not expired
    $check_sql = "SELECT * FROM users WHERE reset_token = '$token' AND reset_expires > NOW()";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $valid_token = true;
        $user = mysqli_fetch_assoc($check_result);
        
        // Handle password reset
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($new_password) || empty($confirm_password)) {
                $error = "Please fill in all fields.";
            } elseif ($new_password != $confirm_password) {
                $error = "Passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } else {
                // Hash and update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = '$hashed_password', reset_token = NULL, reset_expires = NULL WHERE id = '{$user['id']}'";
                
                if (mysqli_query($conn, $update_sql)) {
                    $success = "Password reset successfully! You can now login with your new password.";
                    $valid_token = false; // Token used
                } else {
                    $error = "Error resetting password: " . mysqli_error($conn);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - Task Board</title>
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

        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .password-toggle input:focus {
            border-color: #667eea;
            outline: none;
        }

        .toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            font-size: 16px;
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

        .error-box {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 5px;
            padding: 30px;
            text-align: center;
            color: #c62828;
        }

        .error-box i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$valid_token && empty($success)): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <h2>Invalid or Expired Link</h2>
                <p>The password reset link is invalid or has expired.</p>
                <p>Please request a new password reset link.</p>
                <a href="forgot_password.php" class="back-link">Request New Reset Link</a>
            </div>
        <?php else: ?>
            <h2><i class="fas fa-key"></i> Reset Password</h2>
            <p>Enter your new password below.</p>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="back-link">← Go to Login</a>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" onsubmit="return validatePassword()">
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <div class="password-toggle">
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                            <button type="button" class="toggle-btn" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small style="color: #718096; font-size: 12px;">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <div class="password-toggle">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit">Reset Password</button>
                </form>
                
                <a href="login.php" class="back-link">← Back to Login</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
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
        
        function validatePassword() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 6) {
                alert('Password must be at least 6 characters long.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match.');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>