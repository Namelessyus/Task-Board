<?php
session_start();
include('connect.php');
require_once 'message.php';

// Check if user is already logged in
if (isset($_SESSION['userid']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

// Check if verification system exists
$check_verification = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'is_verified'");
$has_verification = mysqli_num_rows($check_verification) > 0;

if (!$has_verification) {
    $error = "Email verification system is not enabled.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $has_verification) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        // Check if user exists and is not verified
        $sql = "SELECT * FROM users WHERE email = '$email' AND is_verified = 0";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $fullname = $user['username'];
            
            // Check if verification code already exists and is not expired
            $current_time = date('Y-m-d H:i:s');
            if ($user['verification_expires'] && $user['verification_expires'] > $current_time) {
                // Use existing code
                $verification_code = $user['verification_code'];
            } else {
                // Generate new verification code
                $verification_code = bin2hex(random_bytes(32));
                $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Update verification code in database
                $update_sql = "UPDATE users SET verification_code = '$verification_code', verification_expires = '$verification_expires' 
                               WHERE id = '{$user['id']}'";
                mysqli_query($conn, $update_sql);
            }
            
            // Send verification email
            $verification_link = "http://localhost/Task-Board/verify_email.php?code=$verification_code";
            
             // Email content
                        $message = "
                        <html>
                        <head>
                            <title>Email Verification</title>
                            <style>
                                body { font-family: Arial, sans-serif; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .button { background: #764BA2; color: white; padding: 12px 24px; 
                                         text-decoration: none; border-radius: 5px; display: inline-block; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <h2>Welcome to Task Board!</h2>
                                <p>Hello $fullname,</p>
                                <p>Thank you for registering. Please verify your email by clicking the button below:</p>
                                <p style='text-align: center; margin: 30px 0;'>
                                    <a href='$verification_link' class='button'>Verify Email</a>
                                </p>
                                <p>Or copy this link: <br><code>$verification_link</code></p>
                                <p><strong>Note:</strong> This link will expire in 24 hours.</p>
                                <p>If you didn't create an account, please ignore this email.</p>
                                <br>
                                <p>Best regards,<br><strong>Task Board Team</strong></p>
                            </div>
                        </body>
                        </html>
                        ";
                    
                    $mail->addAddress($email);
                    $mail->Subject = 'Verification Email From Task Board';
                    

                    $mail->Body = $message;
                    
                    // Send email
                    if (($mail -> send())) {
                        $success = "Registration successful! Please check your email to verify your account.";
                        $show_verification_link = true;
                    } else {
                        // Email failed, but user is created
                        $error = "Registration successful but verification email failed to send. Verification link: <a href='$verification_link'>Click here to verify</a>";
                        $show_verification_link = true;
                    }
        } else {
            $error = "No unverified account found with this email, or the email is already verified.";
        }
    }
}

// If email is provided in URL
if (isset($_GET['email'])) {
    $email_value = $_GET['email'];
} else {
    $email_value = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - Task Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            color: #764BA2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            background: #764BA2;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-container button:hover {
            background: #5d3a7f;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .form-footer a {
            color: #764BA2;
            text-decoration: none;
            margin: 0 10px;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
            text-align: center;
            font-size: 14px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            text-align: center;
            font-size: 14px;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1565c0;
            text-align: center;
        }
        
        .verification-link {
            background: #f8f9fa;
            border: 1px dashed #6c757d;
            border-radius: 5px;
            padding: 10px;
            margin: 15px 0;
            font-size: 12px;
            word-break: break-all;
            text-align: center;
        }
        
        .disabled-form {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2><i class="fas fa-envelope"></i> Resend Verification</h2>
        
        <?php if (!$has_verification): ?>
            <div class="error"><?php echo $error; ?></div>
            <div class="info-box">
                <p>Email verification is not enabled in the system.</p>
                <p>You can login directly without verification.</p>
            </div>
        <?php else: ?>
        
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
                <?php if (isset($show_link) && $show_link && isset($verification_link)): ?>
                    <div class="info-box">
                        <p>For testing purposes, here is your verification link:</p>
                        <div class="verification-link"><?php echo $verification_link; ?></div>
                        <p><small>Click the link above to verify your email.</small></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (empty($success)): ?>
                <div class="info-box">
                    <p>Enter your email to receive a new verification link.</p>
                    <p><small>Note: Links expire after 24 hours.</small></p>
                </div>
                
                <form method="POST" action="">
                    <input type="email" name="email" placeholder="Enter your email" required 
                           value="<?php echo htmlspecialchars($email_value); ?>">
                    <button type="submit">
                        <i class="fas fa-paper-plane"></i> Send Verification Email
                    </button>
                </form>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <div class="form-footer">
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="signup.php"><i class="fas fa-user-plus"></i> Sign Up</a>
            <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password</a>
        </div>
    </div>
</body>
</html>