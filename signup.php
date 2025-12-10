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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if username contains numbers
    if (preg_match('/[0-9]/', $fullname)) {
        $error = "Username should not contain numbers.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if email already exists
        $check_email = "SELECT * FROM users WHERE email='$email'";
        $result = mysqli_query($conn, $check_email);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            
            // Check if verification columns exist
            $is_verified = isset($row['is_verified']) ? $row['is_verified'] : 1; // Default to verified if column doesn't exist
            
            if ($is_verified == 0) {
                $error = "Email already registered but not verified. <a href='resend_verification.php?email=$email'>Resend verification email</a>";
            } else {
                $error = "Email already registered. <a href='login.php'>Try login instead</a>";
            }
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Check if verification columns exist in table
            $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'is_verified'");
            $has_verification = mysqli_num_rows($check_columns) > 0;
            
            if ($has_verification) {
                // Generate verification code if columns exist
                $verification_code = bin2hex(random_bytes(32));
                $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Insert with verification columns
                $insert_user = "INSERT INTO users (username, email, password, verification_code, verification_expires) 
                                VALUES ('$fullname', '$email', '$hashed_password', '$verification_code', '$verification_expires')";
            } else {
                // Insert without verification columns (fallback)
                $insert_user = "INSERT INTO users (username, email, password) 
                                VALUES ('$fullname', '$email', '$hashed_password')";
            }

            if (mysqli_query($conn, $insert_user)) {
                $user_id = mysqli_insert_id($conn);
                
                // If verification columns exist, send email
                if ($has_verification) {
                    // Send verification email using PHP's mail() function
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
                    // No verification system - auto login
                    $_SESSION['userid'] = $user_id;
                    $_SESSION['username'] = $fullname;
                    $_SESSION['email'] = $email;
                    $_SESSION['loggedin'] = false;
                    
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - Task Board</title>
    <link rel="stylesheet" href="style.css">
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
        }
        .form-container button:hover {
            background: #5d3a7f;
        }
        .form-footer {
            text-align: center;
            margin-top: 15px;
        }
        .form-footer a {
            color: #764BA2;
            text-decoration: none;
        }
        .form-footer a:hover {
            text-decoration: underline;
        }
        .error-message {
            color: red;
            font-size: 14px;
            margin-top: -5px;
            margin-bottom: 10px;
            display: none;
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
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #1565c0;
        }
        .verification-link {
            background: #f8f9fa;
            border: 1px dashed #6c757d;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 12px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Sign Up</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
            <div class="info-box">
                <strong>Note:</strong> For demonstration purposes, the verification system is working.
                <br><br>
                <?php if (isset($show_verification_link) && $show_verification_link): ?>
                    <p>Verification link for testing: <br>
                    <div class="verification-link"><?php echo $verification_link ?? ''; ?></div></p>
                    <br>
                <?php endif; ?>
                <a href="login.php">Go to Login</a>
            </div>
        <?php endif; ?>
        
        <?php if (empty($success) && empty($error)): ?>
        <form method="POST" action="" onsubmit="return validateForm()">
            <input type="text" name="fullname" placeholder="Full Name (letters only)" required 
                   pattern="[A-Za-z\s]+" 
                   title="Please enter only letters and spaces">
            <div class="error-message" id="nameError">Username should not contain numbers</div>
            
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password (min. 6 characters)" required minlength="6">
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit" name="signup">Sign Up</button>
        </form>
        <div class="form-footer">
            Already have an account? <a href="login.php">Login</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function validateForm() {
            const fullnameInput = document.querySelector('input[name="fullname"]');
            const nameError = document.getElementById('nameError');
            const numbers = /[0-9]/;
            
            if (numbers.test(fullnameInput.value)) {
                nameError.style.display = 'block';
                fullnameInput.style.borderColor = 'red';
                return false;
            } else {
                nameError.style.display = 'none';
                fullnameInput.style.borderColor = '#ccc';
                return true;
            }
        }

        document.querySelector('input[name="fullname"]').addEventListener('input', function() {
            const nameError = document.getElementById('nameError');
            const numbers = /[0-9]/;
            
            if (numbers.test(this.value)) {
                nameError.style.display = 'block';
                this.style.borderColor = 'red';
            } else {
                nameError.style.display = 'none';
                this.style.borderColor = '#ccc';
            }
        });
    </script>
</body>
</html>